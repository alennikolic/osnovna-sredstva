<?php
/**
 * obracun_pregled.php
 * --------------------
 * Pregled jednog obračuna amortizacije. Status pipeline:
 *   U_PRIPREMI  -> (Izvrši obračun) -> OBRACUNATO -> (Knjiži) -> KNJIZENO
 *   U_PRIPREMI / OBRACUNATO -> (Storniraj) -> STORNIRANO
 *
 * "Izvrši obračun" je obračunski motor - prolazi kroz sva sredstva koja se
 * amortizuju, računa mesečni iznos LINEARNOM metodom
 * (osnovica_za_amortizaciju / (vek_trajanja_godine * 12)), upisuje stavku,
 * transakciju i ažurira trenutno stanje sredstva. Sredstva sa nepodržanom
 * metodom ili nepotpunom konfiguracijom (nema amortizacionu grupu/vek
 * trajanja) se preskaču - prikazuju se u posebnoj sekciji sa razlogom.
 */

require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$id = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : null;
if (empty($id)) {
    header("Location: obracuni_index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM obracuni_amortizacije WHERE id = :id");
$stmt->execute([':id' => $id]);
$obracun = $stmt->fetch();

if (!$obracun) {
    header("Location: obracuni_index.php");
    exit;
}

$poruka = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akcija'])) {

    // --- IZVRŠI OBRAČUN ------------------------------------------------------
    if ($_POST['akcija'] === 'izvrsi' && $obracun['status'] === 'U_PRIPREMI') {
        try {
            $pdo->beginTransaction();

            $trenutni = trenutniKorisnik();

            $vrstaObracuna = $pdo->query("SELECT id FROM vrste_transakcija WHERE sifra = 'OBRACUN_AMORTIZACIJE'")->fetch();
            if (!$vrstaObracuna) {
                throw new \RuntimeException('Vrsta transakcije OBRACUN_AMORTIZACIJE ne postoji u bazi.');
            }

            // Kandidati: sredstva koja se amortizuju, u statusu koji dozvoljava
            // amortizaciju, stavljena u upotrebu do kraja perioda.
            $stmtKandidati = $pdo->prepare(
                "SELECT os.id, os.osnovica_za_amortizaciju, os.akumulirana_amortizacija,
                        os.sadasnja_knjigovodstvena_vrednost, os.mesto_troska_id,
                        COALESCE(os.amortizaciona_grupa_id, k.amortizaciona_grupa_id) AS amort_grupa_id,
                        COALESCE(os.metoda_amortizacije_id, k.metoda_amortizacije_id) AS metoda_id,
                        k.konto_troska_amortizacije, k.konto_ispravke_vrednosti
                 FROM osnovna_sredstva os
                 JOIN klase_osnovnih_sredstava k ON k.id = os.klasa_id
                 JOIN statusi_sredstva s ON s.id = os.status_id
                 WHERE os.da_li_se_amortizuje = 1
                   AND s.da_li_se_amortizuje_u_ovom_statusu = 1
                   AND s.da_li_je_zavrsni_status = 0
                   AND os.datum_stavljanja_u_upotrebu IS NOT NULL
                   AND os.datum_stavljanja_u_upotrebu <= :period_do"
            );
            $stmtKandidati->execute([':period_do' => $obracun['period_do']]);
            $kandidati = $stmtKandidati->fetchAll();

            $stmtGrupa = $pdo->prepare("SELECT vek_trajanja_godine FROM amortizacione_grupe WHERE id = :id");
            $stmtMetoda = $pdo->prepare("SELECT tip_obracuna FROM metode_amortizacije WHERE id = :id");

            $stmtStavka = $pdo->prepare(
                "INSERT INTO stavke_obracuna_amortizacije
                    (obracun_id, sredstvo_id, knjigovodstvena_vrednost_pocetna, iznos_amortizacije,
                     akumulirana_amortizacija_posle, knjigovodstvena_vrednost_krajnja,
                     konto_troska, konto_ispravke_vrednosti, mesto_troska_id)
                 VALUES
                    (:obracun, :sredstvo, :vr_pocetna, :iznos, :akum_posle, :vr_krajnja, :konto_t, :konto_i, :mesto)"
            );
            $stmtTransakcija = $pdo->prepare(
                "INSERT INTO transakcije_sredstva
                    (sredstvo_id, vrsta_transakcije_id, datum_transakcije, broj_dokumenta, opis, iznos,
                     knjigovodstvena_vrednost_pre, knjigovodstvena_vrednost_posle, korisnik_id)
                 VALUES
                    (:sredstvo, :vrsta, :datum, :broj_dok, :opis, :iznos, :vr_pre, :vr_posle, :korisnik)"
            );
            $stmtAzurirajSredstvo = $pdo->prepare(
                "UPDATE osnovna_sredstva
                 SET akumulirana_amortizacija = :akum, sadasnja_knjigovodstvena_vrednost = :vr,
                     datum_poslednjeg_obracuna_amortizacije = :datum
                 WHERE id = :id"
            );

            $ukupanIznos = 0.0;
            $brojObracunatih = 0;

            foreach ($kandidati as $sr) {
                // Nema podešenu amortizacionu grupu ili metodu - preskoči
                if (!$sr['amort_grupa_id'] || !$sr['metoda_id']) {
                    continue;
                }
                $stmtMetoda->execute([':id' => $sr['metoda_id']]);
                $metoda = $stmtMetoda->fetch();
                // Trenutno se stvarno računa samo linearna metoda
                if (!$metoda || $metoda['tip_obracuna'] !== 'LINEARNA') {
                    continue;
                }
                $stmtGrupa->execute([':id' => $sr['amort_grupa_id']]);
                $grupa = $stmtGrupa->fetch();
                // Amortizaciona grupa nema podešen vek trajanja - ne može da se izračuna
                if (!$grupa || $grupa['vek_trajanja_godine'] === null || (float)$grupa['vek_trajanja_godine'] <= 0) {
                    continue;
                }

                $knjigVrPocetna = $sr['sadasnja_knjigovodstvena_vrednost'] !== null
                    ? (float)$sr['sadasnja_knjigovodstvena_vrednost']
                    : (float)$sr['osnovica_za_amortizaciju'];

                // Već u potpunosti otpisano - ništa za obračunati
                if ($knjigVrPocetna <= 0) {
                    continue;
                }

                $mesecnaStopa = (float)$sr['osnovica_za_amortizaciju'] / ((float)$grupa['vek_trajanja_godine'] * 12);
                // Ograničeno na preostalu knjigovodstvenu vrednost - ne ide ispod 0
                $iznos = round(min($mesecnaStopa, $knjigVrPocetna), 2);
                if ($iznos <= 0) {
                    continue;
                }

                $akumPosle = round((float)$sr['akumulirana_amortizacija'] + $iznos, 2);
                $vrKrajnja = round($knjigVrPocetna - $iznos, 2);

                $stmtStavka->execute([
                    ':obracun'    => $id,
                    ':sredstvo'   => $sr['id'],
                    ':vr_pocetna' => $knjigVrPocetna,
                    ':iznos'      => $iznos,
                    ':akum_posle' => $akumPosle,
                    ':vr_krajnja' => $vrKrajnja,
                    ':konto_t'    => $sr['konto_troska_amortizacije'],
                    ':konto_i'    => $sr['konto_ispravke_vrednosti'],
                    ':mesto'      => $sr['mesto_troska_id'],
                ]);

                $stmtTransakcija->execute([
                    ':sredstvo' => $sr['id'],
                    ':vrsta'    => $vrstaObracuna['id'],
                    ':datum'    => $obracun['period_do'],
                    ':broj_dok' => $obracun['naziv'],
                    ':opis'     => 'Obračun amortizacije - ' . $obracun['naziv'],
                    ':iznos'    => $iznos,
                    ':vr_pre'   => $knjigVrPocetna,
                    ':vr_posle' => $vrKrajnja,
                    ':korisnik' => $trenutni['id'] ?? null,
                ]);

                $stmtAzurirajSredstvo->execute([
                    ':akum'  => $akumPosle,
                    ':vr'    => $vrKrajnja,
                    ':datum' => $obracun['period_do'],
                    ':id'    => $sr['id'],
                ]);

                $ukupanIznos += $iznos;
                $brojObracunatih++;
            }

            $pdo->prepare(
                "UPDATE obracuni_amortizacije
                 SET status = 'OBRACUNATO', datum_obracuna = NOW(), ukupan_iznos_amortizacije = :iznos
                 WHERE id = :id"
            )->execute([':iznos' => round($ukupanIznos, 2), ':id' => $id]);

            $pdo->commit();
            header("Location: obracun_pregled.php?id=" . $id);
            exit;
        } catch (\PDOException $e) {
            $pdo->rollBack();
            $poruka = "Greška pri obračunu: " . $e->getMessage();
        } catch (\RuntimeException $e) {
            $pdo->rollBack();
            $poruka = $e->getMessage();
        }
    }

    // --- KNJIŽI OBRAČUN -------------------------------------------------------
    if ($_POST['akcija'] === 'knjizi' && $obracun['status'] === 'OBRACUNATO') {
        try {
            $pdo->prepare("UPDATE obracuni_amortizacije SET status = 'KNJIZENO', datum_knjizenja = NOW() WHERE id = :id")
                ->execute([':id' => $id]);
            header("Location: obracun_pregled.php?id=" . $id);
            exit;
        } catch (\PDOException $e) {
            $poruka = "Greška pri knjiženju: " . $e->getMessage();
        }
    }

    // --- STORNIRAJ OBRAČUN -----------------------------------------------------
    if ($_POST['akcija'] === 'storniraj' && in_array($obracun['status'], ['U_PRIPREMI', 'OBRACUNATO'], true)) {
        try {
            $pdo->beginTransaction();

            if ($obracun['status'] === 'OBRACUNATO') {
                $trenutni = trenutniKorisnik();
                $vrstaStorno = $pdo->query("SELECT id FROM vrste_transakcija WHERE sifra = 'STORNO'")->fetch();
                if (!$vrstaStorno) {
                    throw new \RuntimeException('Vrsta transakcije STORNO ne postoji u bazi.');
                }

                $stmtStavke = $pdo->prepare("SELECT * FROM stavke_obracuna_amortizacije WHERE obracun_id = :id");
                $stmtStavke->execute([':id' => $id]);
                $stavke = $stmtStavke->fetchAll();

                $stmtVratiSredstvo = $pdo->prepare(
                    "UPDATE osnovna_sredstva
                     SET akumulirana_amortizacija = akumulirana_amortizacija - :iznos,
                         sadasnja_knjigovodstvena_vrednost = :vr_pocetna
                     WHERE id = :id"
                );
                $stmtTransakcijaStorno = $pdo->prepare(
                    "INSERT INTO transakcije_sredstva
                        (sredstvo_id, vrsta_transakcije_id, datum_transakcije, broj_dokumenta, opis, iznos,
                         knjigovodstvena_vrednost_pre, knjigovodstvena_vrednost_posle, korisnik_id, napomena)
                     VALUES
                        (:sredstvo, :vrsta, CURDATE(), :broj_dok, :opis, :iznos, :vr_pre, :vr_posle, :korisnik, :napomena)"
                );

                foreach ($stavke as $st) {
                    $stmtVratiSredstvo->execute([
                        ':iznos'      => $st['iznos_amortizacije'],
                        ':vr_pocetna' => $st['knjigovodstvena_vrednost_pocetna'],
                        ':id'         => $st['sredstvo_id'],
                    ]);
                    $stmtTransakcijaStorno->execute([
                        ':sredstvo' => $st['sredstvo_id'],
                        ':vrsta'    => $vrstaStorno['id'],
                        ':broj_dok' => $obracun['naziv'],
                        ':opis'     => 'Storno obračuna amortizacije - ' . $obracun['naziv'],
                        ':iznos'    => $st['iznos_amortizacije'],
                        ':vr_pre'   => $st['knjigovodstvena_vrednost_krajnja'],
                        ':vr_posle' => $st['knjigovodstvena_vrednost_pocetna'],
                        ':korisnik' => $trenutni['id'] ?? null,
                        ':napomena' => 'Automatski storno pri poništavanju obračuna',
                    ]);
                }
            }

            $pdo->prepare("UPDATE obracuni_amortizacije SET status = 'STORNIRANO' WHERE id = :id")
                ->execute([':id' => $id]);

            $pdo->commit();
            header("Location: obracun_pregled.php?id=" . $id);
            exit;
        } catch (\PDOException $e) {
            $pdo->rollBack();
            $poruka = "Greška pri storniranju: " . $e->getMessage();
        } catch (\RuntimeException $e) {
            $pdo->rollBack();
            $poruka = $e->getMessage();
        }
    }
}

// Stavke obračuna (popunjeno tek nakon "Izvrši obračun")
$stmt = $pdo->prepare(
    "SELECT sa.*, os.inventarski_broj, os.naziv, k.naziv AS naziv_klase
     FROM stavke_obracuna_amortizacije sa
     JOIN osnovna_sredstva os ON os.id = sa.sredstvo_id
     JOIN klase_osnovnih_sredstava k ON k.id = os.klasa_id
     WHERE sa.obracun_id = :id
     ORDER BY os.naziv"
);
$stmt->execute([':id' => $id]);
$stavke = $stmt->fetchAll();

// Preskočena sredstva - live izračunato poređenjem kandidata sa upisanim
// stavkama, prikazano samo posle izvršenog obračuna (OBRACUNATO/KNJIZENO).
$preskocena = [];
if (in_array($obracun['status'], ['OBRACUNATO', 'KNJIZENO'], true)) {
    $stmt = $pdo->prepare(
        "SELECT os.id, os.inventarski_broj, os.naziv,
                COALESCE(os.amortizaciona_grupa_id, k.amortizaciona_grupa_id) AS amort_grupa_id,
                COALESCE(os.metoda_amortizacije_id, k.metoda_amortizacije_id) AS metoda_id,
                ag.vek_trajanja_godine, ma.tip_obracuna,
                os.sadasnja_knjigovodstvena_vrednost, os.osnovica_za_amortizaciju
         FROM osnovna_sredstva os
         JOIN klase_osnovnih_sredstava k ON k.id = os.klasa_id
         JOIN statusi_sredstva s ON s.id = os.status_id
         LEFT JOIN amortizacione_grupe ag ON ag.id = COALESCE(os.amortizaciona_grupa_id, k.amortizaciona_grupa_id)
         LEFT JOIN metode_amortizacije ma ON ma.id = COALESCE(os.metoda_amortizacije_id, k.metoda_amortizacije_id)
         WHERE os.da_li_se_amortizuje = 1
           AND s.da_li_se_amortizuje_u_ovom_statusu = 1
           AND s.da_li_je_zavrsni_status = 0
           AND os.datum_stavljanja_u_upotrebu IS NOT NULL
           AND os.datum_stavljanja_u_upotrebu <= :period_do
           AND os.id NOT IN (SELECT sredstvo_id FROM stavke_obracuna_amortizacije WHERE obracun_id = :obracun_id)
         ORDER BY os.naziv"
    );
    $stmt->execute([':period_do' => $obracun['period_do'], ':obracun_id' => $id]);
    $kandidatiPreskoceni = $stmt->fetchAll();

    foreach ($kandidatiPreskoceni as $p) {
        if (!$p['amort_grupa_id']) {
            $razlog = 'Nema podešenu amortizacionu grupu';
        } elseif (!$p['metoda_id']) {
            $razlog = 'Nema podešenu metodu amortizacije';
        } elseif ($p['tip_obracuna'] !== 'LINEARNA') {
            $razlog = 'Metoda amortizacije nije podržana (' . $p['tip_obracuna'] . ')';
        } elseif ($p['vek_trajanja_godine'] === null) {
            $razlog = 'Amortizaciona grupa nema podešen vek trajanja';
        } else {
            $knjigVr = $p['sadasnja_knjigovodstvena_vrednost'] ?? $p['osnovica_za_amortizaciju'];
            $razlog = $knjigVr <= 0 ? 'Već u potpunosti otpisano' : 'Nije obračunato (proveriti ručno)';
        }
        $preskocena[] = [
            'inventarski_broj' => $p['inventarski_broj'],
            'naziv' => $p['naziv'],
            'razlog' => $razlog,
        ];
    }
}

$mapaStatusa = [
    'U_PRIPREMI' => ['U pripremi', 'oznaka-u-toku'],
    'OBRACUNATO' => ['Obračunato', 'oznaka-aktivna'],
    'KNJIZENO'   => ['Knjiženo', 'oznaka-neaktivna'],
    'STORNIRANO' => ['Stornirano', 'oznaka-otkazana'],
];
[$nazivStatusa, $klasaOznake] = $mapaStatusa[$obracun['status']] ?? [$obracun['status'], 'oznaka-neaktivna'];

$naslovStranice = $obracun['naziv'];
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2>
        <?= htmlspecialchars($obracun['naziv']) ?>
        <span class="oznaka <?= $klasaOznake ?>"><?= $nazivStatusa ?></span>
    </h2>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <div class="detalj-red">
        <span class="detalj-labela">Period</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($obracun['period_od']) ?> - <?= htmlspecialchars($obracun['period_do']) ?></span>
    </div>
    <div class="detalj-red">
        <span class="detalj-labela">Ukupan iznos amortizacije</span>
        <span class="detalj-vrednost"><?= number_format($obracun['ukupan_iznos_amortizacije'], 2, ',', '.') ?> RSD</span>
    </div>
    <?php if (!empty($obracun['datum_obracuna'])): ?>
    <div class="detalj-red">
        <span class="detalj-labela">Datum obračuna</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($obracun['datum_obracuna']) ?></span>
    </div>
    <?php endif; ?>
    <?php if (!empty($obracun['datum_knjizenja'])): ?>
    <div class="detalj-red">
        <span class="detalj-labela">Datum knjiženja</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($obracun['datum_knjizenja']) ?></span>
    </div>
    <?php endif; ?>
    <?php if (!empty($obracun['napomena'])): ?>
    <div class="detalj-red">
        <span class="detalj-labela">Napomena</span>
        <span class="detalj-vrednost"><?= nl2br(htmlspecialchars($obracun['napomena'])) ?></span>
    </div>
    <?php endif; ?>

    <div style="margin-top: 20px;">
        <?php if ($obracun['status'] === 'U_PRIPREMI'): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Izvršiti obračun amortizacije za ovaj period? Ovim se ažuriraju knjigovodstvene vrednosti svih sredstava koja se amortizuju.');">
                <input type="hidden" name="akcija" value="izvrsi">
                <button type="submit" class="btn">Izvrši obračun</button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Stornirati ovaj nacrt obračuna?');">
                <input type="hidden" name="akcija" value="storniraj">
                <button type="submit" class="btn" style="background:#dc3545;">Storniraj</button>
            </form>
        <?php elseif ($obracun['status'] === 'OBRACUNATO'): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Knjižiti ovaj obračun? Nakon knjiženja obračun se više ne može stornirati.');">
                <input type="hidden" name="akcija" value="knjizi">
                <button type="submit" class="btn">Knjiži obračun</button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Stornirati ovaj obračun? Ovim se vraćaju knjigovodstvene vrednosti svih obuhvaćenih sredstava na stanje pre obračuna.');">
                <input type="hidden" name="akcija" value="storniraj">
                <button type="submit" class="btn" style="background:#dc3545;">Storniraj obračun</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($preskocena)): ?>
<div class="form-container forma-siroka" style="margin-top: 20px;">
    <h3 style="margin-top:0;">Preskočena sredstva <span class="napomena-polje">(<?= count($preskocena) ?>)</span></h3>
    <table>
        <thead>
            <tr>
                <th>Inventarski broj</th>
                <th>Naziv</th>
                <th>Razlog</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($preskocena as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['inventarski_broj']) ?></td>
                    <td><?= htmlspecialchars($p['naziv']) ?></td>
                    <td class="napomena-polje"><?= htmlspecialchars($p['razlog']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div style="margin-top: 20px;">
    <div class="detalj-sekcija" style="margin-top:0;">Stavke obračuna <?= !empty($stavke) ? '(' . count($stavke) . ')' : '' ?></div>
    <table>
        <thead>
            <tr>
                <th>Inventarski broj</th>
                <th>Naziv</th>
                <th>Klasa</th>
                <th>Knjig. vrednost (početna)</th>
                <th>Iznos amortizacije</th>
                <th>Knjig. vrednost (krajnja)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($stavke)): ?>
                <tr><td colspan="6" style="text-align:center;">Obračun još nije izvršen.</td></tr>
            <?php else: ?>
                <?php foreach ($stavke as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['inventarski_broj']) ?></td>
                        <td><?= htmlspecialchars($s['naziv']) ?></td>
                        <td><?= htmlspecialchars($s['naziv_klase']) ?></td>
                        <td><?= number_format($s['knjigovodstvena_vrednost_pocetna'], 2, ',', '.') ?></td>
                        <td><?= number_format($s['iznos_amortizacije'], 2, ',', '.') ?></td>
                        <td><?= number_format($s['knjigovodstvena_vrednost_krajnja'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div style="margin-top: 20px;">
    <a href="obracuni_index.php" class="btn-cancel">Nazad na listu obračuna</a>
</div>

<?php require_once 'footer.php'; ?>
