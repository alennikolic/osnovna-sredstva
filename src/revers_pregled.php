<?php
/**
 * revers_pregled.php
 * -------------------
 * Pregled jednog reversa. Dok je status U_PRIPREMI, dostupne su akcije
 * "Izdaj revers" (stvarno zaduženje), "Poništi nacrt", i uređivanje stavki
 * (dodavanje/uklanjanje sredstava sa nacrta pre izdavanja). Nakon izdavanja,
 * revers je nepromenljiv dokument - samo se prikazuje i može odštampati.
 *
 * Izdavanje AUTOMATSKI razdužuje svako sredstvo sa OVOG reversa koje je u
 * tom trenutku zaduženo na nekom DRUGOM već izdatom reversu - nema više
 * ručnog/parcijalnog vraćanja.
 */

require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';
require_once 'helpers.php';

$id = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : null;
if (empty($id)) {
    header("Location: reversi_index.php");
    exit;
}

$stmt = $pdo->prepare(
    "SELECT r.*, CONCAT(z.ime, ' ', z.prezime) AS ime_zaposlenog, z.radno_mesto,
            k.korisnicko_ime AS izdao_korisnicko_ime
     FROM reversi r
     JOIN zaposleni z ON z.id = r.zaposleni_id
     LEFT JOIN korisnici k ON k.id = r.korisnik_id
     WHERE r.id = :id"
);
$stmt->execute([':id' => $id]);
$revers = $stmt->fetch();

if (!$revers) {
    header("Location: reversi_index.php");
    exit;
}

$poruka = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akcija'])) {

    if ($_POST['akcija'] === 'ponisti' && $revers['status'] === 'U_PRIPREMI') {
        try {
            $pdo->prepare("UPDATE reversi SET status = 'PONISTEN' WHERE id = :id")->execute([':id' => $id]);
            header("Location: revers_pregled.php?id=" . $id);
            exit;
        } catch (\PDOException $e) {
            $poruka = "Greška pri poništavanju: " . $e->getMessage();
        }
    }

    if ($_POST['akcija'] === 'dodaj_stavke' && $revers['status'] === 'U_PRIPREMI') {
        $novaSredstva = array_map('intval', $_POST['sredstva'] ?? []);
        if (empty($novaSredstva)) {
            $poruka = "Izaberite bar jedno sredstvo za dodavanje.";
        } else {
            try {
                $stmtProveri = $pdo->prepare(
                    "SELECT COUNT(*) FROM stavke_reversa WHERE revers_id = :revers AND sredstvo_id = :sredstvo"
                );
                $stmtDodaj = $pdo->prepare(
                    "INSERT INTO stavke_reversa (revers_id, sredstvo_id) VALUES (:revers, :sredstvo)"
                );
                foreach ($novaSredstva as $sredstvoId) {
                    $stmtProveri->execute([':revers' => $id, ':sredstvo' => $sredstvoId]);
                    if ((int)$stmtProveri->fetchColumn() > 0) {
                        continue; // već je na ovom reversu - preskoči
                    }
                    $stmtDodaj->execute([':revers' => $id, ':sredstvo' => $sredstvoId]);
                }
                header("Location: revers_pregled.php?id=" . $id);
                exit;
            } catch (\PDOException $e) {
                $poruka = "Greška pri dodavanju stavki: " . $e->getMessage();
            }
        }
    }

    if ($_POST['akcija'] === 'ukloni_stavku' && $revers['status'] === 'U_PRIPREMI') {
        $stavkaId = isset($_POST['stavka_id']) ? (int)$_POST['stavka_id'] : 0;
        $ukupnoStavki = (int)$pdo->query(
            "SELECT COUNT(*) FROM stavke_reversa WHERE revers_id = " . (int)$id
        )->fetchColumn();

        if ($ukupnoStavki <= 1) {
            $poruka = "Revers mora imati bar jednu stavku - dodajte drugu pre uklanjanja poslednje, ili poništite ceo nacrt.";
        } else {
            try {
                $pdo->prepare("DELETE FROM stavke_reversa WHERE id = :id AND revers_id = :revers")
                    ->execute([':id' => $stavkaId, ':revers' => $id]);
                header("Location: revers_pregled.php?id=" . $id);
                exit;
            } catch (\PDOException $e) {
                $poruka = "Greška pri uklanjanju stavke: " . $e->getMessage();
            }
        }
    }

    if ($_POST['akcija'] === 'izdaj' && $revers['status'] === 'U_PRIPREMI') {
        try {
            $pdo->beginTransaction();

            $trenutni = trenutniKorisnik();

            $vrstaZaduzenje = $pdo->query("SELECT id FROM vrste_transakcija WHERE sifra = 'ZADUZENJE'")->fetch();
            $vrstaRazduzenje = $pdo->query("SELECT id FROM vrste_transakcija WHERE sifra = 'RAZDUZENJE'")->fetch();
            if (!$vrstaZaduzenje || !$vrstaRazduzenje) {
                throw new \RuntimeException('Vrste transakcija ZADUZENJE/RAZDUZENJE ne postoje u bazi.');
            }

            $stmtStavkeOvogReversa = $pdo->prepare("SELECT id, sredstvo_id FROM stavke_reversa WHERE revers_id = :id");
            $stmtStavkeOvogReversa->execute([':id' => $id]);
            $stavkeOvogReversa = $stmtStavkeOvogReversa->fetchAll();

            if (empty($stavkeOvogReversa)) {
                throw new \RuntimeException('Revers nema nijednu stavku - ne može se izdati.');
            }

            // Bilo koja DRUGA otvorena stavka (na nekom drugom već IZDATOM
            // reversu) za isto sredstvo - automatski je razdužujemo.
            $stmtDrugaStavka = $pdo->prepare(
                "SELECT sr.id, sr.revers_id
                 FROM stavke_reversa sr
                 JOIN reversi r2 ON r2.id = sr.revers_id
                 WHERE sr.sredstvo_id = :sredstvo AND sr.vraceno = 0
                   AND r2.status = 'IZDAT' AND sr.revers_id != :ovaj_revers"
            );
            $stmtOznaciVraceno = $pdo->prepare(
                "UPDATE stavke_reversa
                 SET vraceno = 1, datum_vracanja = :datum, napomena_vracanja = :napomena, korisnik_vratio_id = :korisnik
                 WHERE id = :id"
            );
            $stmtTransakcija = $pdo->prepare(
                "INSERT INTO transakcije_sredstva
                    (sredstvo_id, vrsta_transakcije_id, datum_transakcije, broj_dokumenta, opis, korisnik_id, napomena)
                 VALUES
                    (:sredstvo, :vrsta, :datum, :broj_dok, :opis, :korisnik, :napomena)"
            );
            $stmtAzurirajZaduzenje = $pdo->prepare(
                "UPDATE osnovna_sredstva SET zaposleni_id = :zaposleni WHERE id = :id"
            );

            foreach ($stavkeOvogReversa as $stavka) {
                // 1) Automatsko razduženje ako sredstvo ima otvorenu stavku negde drugde
                $stmtDrugaStavka->execute([':sredstvo' => $stavka['sredstvo_id'], ':ovaj_revers' => $id]);
                $drugaStavka = $stmtDrugaStavka->fetch();

                if ($drugaStavka) {
                    $stmtOznaciVraceno->execute([
                        ':datum'    => $revers['datum_izdavanja'],
                        ':napomena' => 'Automatski razduženo - sredstvo ponovo zaduženo po reversu ' . $revers['broj_reversa'],
                        ':korisnik' => $trenutni['id'] ?? null,
                        ':id'       => $drugaStavka['id'],
                    ]);

                    $stmtTransakcija->execute([
                        ':sredstvo' => $stavka['sredstvo_id'],
                        ':vrsta'    => $vrstaRazduzenje['id'],
                        ':datum'    => $revers['datum_izdavanja'],
                        ':broj_dok' => $revers['broj_reversa'],
                        ':opis'     => 'Automatsko razduženje - sredstvo ponovo zaduženo po reversu ' . $revers['broj_reversa'],
                        ':korisnik' => $trenutni['id'] ?? null,
                        ':napomena' => null,
                    ]);

                    $ukupno = (int)$pdo->query(
                        "SELECT COUNT(*) FROM stavke_reversa WHERE revers_id = " . (int)$drugaStavka['revers_id']
                    )->fetchColumn();
                    $vraceno = (int)$pdo->query(
                        "SELECT COUNT(*) FROM stavke_reversa WHERE revers_id = " . (int)$drugaStavka['revers_id'] . " AND vraceno = 1"
                    )->fetchColumn();
                    if ($vraceno >= $ukupno) {
                        $pdo->prepare("UPDATE reversi SET status = 'VRACEN' WHERE id = :id")
                            ->execute([':id' => $drugaStavka['revers_id']]);
                    }
                }

                // 2) Zaduži OVIM reversom
                $stmtTransakcija->execute([
                    ':sredstvo' => $stavka['sredstvo_id'],
                    ':vrsta'    => $vrstaZaduzenje['id'],
                    ':datum'    => $revers['datum_izdavanja'],
                    ':broj_dok' => $revers['broj_reversa'],
                    ':opis'     => 'Zaduženje po reversu ' . $revers['broj_reversa'],
                    ':korisnik' => $trenutni['id'] ?? null,
                    ':napomena' => $revers['napomena'],
                ]);

                $stmtAzurirajZaduzenje->execute([
                    ':zaposleni' => $revers['zaposleni_id'],
                    ':id'        => $stavka['sredstvo_id'],
                ]);
            }

            $pdo->prepare("UPDATE reversi SET status = 'IZDAT' WHERE id = :id")->execute([':id' => $id]);

            $pdo->commit();
            header("Location: revers_pregled.php?id=" . $id);
            exit;
        } catch (\PDOException $e) {
            $pdo->rollBack();
            $poruka = "Greška pri izdavanju: " . $e->getMessage();
        } catch (\RuntimeException $e) {
            $pdo->rollBack();
            $poruka = $e->getMessage();
        }
    }
}

$stmt = $pdo->prepare(
    "SELECT sr.id, sr.sredstvo_id, sr.vraceno, sr.datum_vracanja, sr.napomena_vracanja,
            os.inventarski_broj, os.naziv, k.naziv AS naziv_klase
     FROM stavke_reversa sr
     JOIN osnovna_sredstva os ON os.id = sr.sredstvo_id
     JOIN klase_osnovnih_sredstava k ON k.id = os.klasa_id
     WHERE sr.revers_id = :id
     ORDER BY os.naziv"
);
$stmt->execute([':id' => $id]);
$stavke = $stmt->fetchAll();

// Dostupna sredstva za DODAVANJE na nacrt - sva sredstva u nezavršnom
// statusu, MINUS ona koja su već na ovom reversu. Učitava se samo dok je
// revers U_PRIPREMI (nakon izdavanja se stavke više ne menjaju).
$dostupnaSredstva = [];
if ($revers['status'] === 'U_PRIPREMI') {
    $vecNaReversu = array_column($stavke, 'sredstvo_id');

    $sql = "SELECT os.id, os.inventarski_broj, os.naziv, k.naziv AS naziv_klase,
                   CASE WHEN z.id IS NOT NULL THEN CONCAT(z.ime, ' ', z.prezime) ELSE NULL END AS trenutno_zaduzen
            FROM osnovna_sredstva os
            JOIN klase_osnovnih_sredstava k ON k.id = os.klasa_id
            JOIN statusi_sredstva s ON s.id = os.status_id
            LEFT JOIN zaposleni z ON z.id = os.zaposleni_id
            WHERE s.da_li_je_zavrsni_status = 0";

    $parametri = [];
    if (!empty($vecNaReversu)) {
        $placeholderi = implode(',', array_fill(0, count($vecNaReversu), '?'));
        $sql .= " AND os.id NOT IN ($placeholderi)";
        $parametri = $vecNaReversu;
    }
    $sql .= " ORDER BY os.naziv";

    $stmtDostupna = $pdo->prepare($sql);
    $stmtDostupna->execute($parametri);
    $dostupnaSredstva = $stmtDostupna->fetchAll();
}

[$nazivStatusaReversa, $klasaOznakeReversa] = oznakaStatusaReversa($revers['status']);

$naslovStranice = 'Revers ' . $revers['broj_reversa'];
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2>
        Revers <?= htmlspecialchars($revers['broj_reversa']) ?>
        <span class="oznaka <?= $klasaOznakeReversa ?>"><?= $nazivStatusaReversa ?></span>
    </h2>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <div class="detalj-red">
        <span class="detalj-labela">Zaposleni</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($revers['ime_zaposlenog']) ?><?= $revers['radno_mesto'] ? ' (' . htmlspecialchars($revers['radno_mesto']) . ')' : '' ?></span>
    </div>
    <div class="detalj-red">
        <span class="detalj-labela">Datum izdavanja</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($revers['datum_izdavanja']) ?></span>
    </div>
    <div class="detalj-red">
        <span class="detalj-labela">Kreirao</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($revers['izdao_korisnicko_ime'] ?? '—') ?></span>
    </div>
    <?php if (!empty($revers['napomena'])): ?>
    <div class="detalj-red">
        <span class="detalj-labela">Napomena</span>
        <span class="detalj-vrednost"><?= nl2br(htmlspecialchars($revers['napomena'])) ?></span>
    </div>
    <?php endif; ?>

    <div style="margin-top: 20px;">
        <?php if ($revers['status'] === 'U_PRIPREMI'): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Izdati ovaj revers? Ovim se stvarno zadužuju sredstva i menja se knjigovodstveno stanje.');">
                <input type="hidden" name="akcija" value="izdaj">
                <button type="submit" class="btn">Izdaj revers</button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Poništiti ovaj nacrt reversa?');">
                <input type="hidden" name="akcija" value="ponisti">
                <button type="submit" class="btn" style="background:#dc3545;">Poništi nacrt</button>
            </form>
        <?php else: ?>
            <a href="revers_stampa.php?id=<?= $revers['id'] ?>" class="btn" target="_blank">Odštampaj revers</a>
        <?php endif; ?>
    </div>
</div>

<div style="margin-top: 20px;">
    <div class="detalj-sekcija" style="margin-top:0;">Stavke reversa</div>
    <table>
        <thead>
            <tr>
                <th>Inventarski broj</th>
                <th>Naziv</th>
                <th>Klasa</th>
                <?php if ($revers['status'] !== 'U_PRIPREMI'): ?>
                    <th>Status</th>
                    <th>Datum vraćanja</th>
                <?php else: ?>
                    <th>Akcije</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stavke as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['inventarski_broj']) ?></td>
                    <td><?= htmlspecialchars($s['naziv']) ?></td>
                    <td><?= htmlspecialchars($s['naziv_klase']) ?></td>
                    <?php if ($revers['status'] !== 'U_PRIPREMI'): ?>
                        <td>
                            <?php if ($s['vraceno']): ?>
                                <span class="oznaka oznaka-neaktivna">Vraćeno</span>
                            <?php else: ?>
                                <span class="oznaka oznaka-aktivna">Zaduženo</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($s['datum_vracanja'] ?? '—') ?></td>
                    <?php else: ?>
                        <td class="akcije">
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Ukloniti ovu stavku sa nacrta reversa?');">
                                <input type="hidden" name="akcija" value="ukloni_stavku">
                                <input type="hidden" name="stavka_id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn" style="background:#dc3545; padding:2px 10px; font-size:12px;">Ukloni</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($revers['status'] === 'U_PRIPREMI'): ?>
<div class="form-container forma-siroka" style="margin-top: 20px;">
    <h3 style="margin-top:0;">Dodaj stavke na nacrt</h3>
    <?php if (empty($dostupnaSredstva)): ?>
        <p class="napomena-polje">Nema dodatnih dostupnih sredstava za dodavanje.</p>
    <?php else: ?>
        <form method="POST" action="">
            <input type="hidden" name="akcija" value="dodaj_stavke">
            <div class="lista-checkboxova">
                <?php foreach ($dostupnaSredstva as $sr): ?>
                    <label class="stavka-checkboxa">
                        <input type="checkbox" name="sredstva[]" value="<?= $sr['id'] ?>">
                        <?= htmlspecialchars($sr['inventarski_broj'] . ' - ' . $sr['naziv']) ?>
                        <span class="napomena-polje">(<?= htmlspecialchars($sr['naziv_klase']) ?>)</span>
                        <?php if ($sr['trenutno_zaduzen']): ?>
                            <span class="napomena-polje">— trenutno zaduženo: <?= htmlspecialchars($sr['trenutno_zaduzen']) ?> (automatski razduženo pri izdavanju)</span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn" style="margin-top: 10px;">Dodaj izabrana sredstva</button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div style="margin-top: 20px;">
    <a href="reversi_index.php" class="btn-cancel">Nazad na listu reversa</a>
</div>

<?php require_once 'footer.php'; ?>
