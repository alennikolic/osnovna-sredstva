<?php
/**
 * premestaji_pregled.php
 * -----------------------
 * Pregled jednog dokumenta premeštaja. Dok je status U_PRIPREMI, dostupne su
 * akcije "Izdaj premeštaj" (stvarna promena lokacije/mesta troška) i "Poništi
 * nacrt". Nakon izdavanja, dokument je nepromenljiv i može se odštampati.
 * Nema statusa "vraćeno" - premeštaj je jednosmerna promena.
 */

require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';
require_once 'helpers.php';

$id = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : null;
if (empty($id)) {
    header("Location: premestaji_index.php");
    exit;
}

$stmt = $pdo->prepare(
    "SELECT d.*, nl.naziv AS nova_lokacija, nmt.naziv AS novo_mesto_troska, k.korisnicko_ime
     FROM dokumenti_premestaja d
     LEFT JOIN lokacije nl ON nl.id = d.nova_lokacija_id
     LEFT JOIN mesta_troska nmt ON nmt.id = d.novo_mesto_troska_id
     LEFT JOIN korisnici k ON k.id = d.korisnik_id
     WHERE d.id = :id"
);
$stmt->execute([':id' => $id]);
$dokument = $stmt->fetch();

if (!$dokument) {
    header("Location: premestaji_index.php");
    exit;
}

$poruka = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akcija'])) {

    if ($_POST['akcija'] === 'ponisti' && $dokument['status'] === 'U_PRIPREMI') {
        try {
            $pdo->prepare("UPDATE dokumenti_premestaja SET status = 'PONISTEN' WHERE id = :id")->execute([':id' => $id]);
            header("Location: premestaji_pregled.php?id=" . $id);
            exit;
        } catch (\PDOException $e) {
            $poruka = "Greška pri poništavanju: " . $e->getMessage();
        }
    }

    if ($_POST['akcija'] === 'izdaj' && $dokument['status'] === 'U_PRIPREMI') {
        try {
            $pdo->beginTransaction();

            $vrstaTransakcije = $pdo->query("SELECT id FROM vrste_transakcija WHERE sifra = 'PREMESTAJ'")->fetch();
            if (!$vrstaTransakcije) {
                throw new \RuntimeException('Vrsta transakcije PREMESTAJ ne postoji u bazi.');
            }
            $trenutni = trenutniKorisnik();

            $novaLokacija = $dokument['nova_lokacija_id'] !== null ? (int)$dokument['nova_lokacija_id'] : null;
            $novoMestoTroska = $dokument['novo_mesto_troska_id'] !== null ? (int)$dokument['novo_mesto_troska_id'] : null;
            $nemenjajLokaciju = $dokument['nova_lokacija_id'] === null;
            $nemenjajMestoTroska = $dokument['novo_mesto_troska_id'] === null;

            $stmtStavke = $pdo->prepare("SELECT sredstvo_id FROM stavke_premestaja WHERE dokument_premestaja_id = :id");
            $stmtStavke->execute([':id' => $id]);
            $stavkeIds = $stmtStavke->fetchAll(PDO::FETCH_COLUMN);

            if (empty($stavkeIds)) {
                throw new \RuntimeException('Dokument nema nijednu stavku - ne može se izdati.');
            }

            $stmtStanje = $pdo->prepare(
                "SELECT lokacija_id, mesto_troska_id, odgovorno_lice, zaposleni_id
                 FROM osnovna_sredstva WHERE id = :id"
            );
            $stmtTransakcija = $pdo->prepare(
                "INSERT INTO transakcije_sredstva
                    (sredstvo_id, vrsta_transakcije_id, datum_transakcije, broj_dokumenta, opis, korisnik_id, napomena)
                 VALUES
                    (:sredstvo, :vrsta, :datum, :broj_dok, :opis, :korisnik, :napomena)"
            );
            $stmtPremestaj = $pdo->prepare(
                "INSERT INTO premestaji_sredstva
                    (dokument_premestaja_id, transakcija_id, sredstvo_id, datum_premestaja,
                     stara_lokacija_id, nova_lokacija_id,
                     staro_mesto_troska_id, novo_mesto_troska_id,
                     staro_odgovorno_lice, stari_zaposleni_id,
                     novo_odgovorno_lice, novi_zaposleni_id, napomena)
                 VALUES
                    (:dokument, :transakcija, :sredstvo, :datum,
                     :stara_lok, :nova_lok,
                     :staro_mt, :novo_mt,
                     :staro_lice, :stari_zap,
                     :novo_lice, :novi_zap, :napomena)"
            );
            $stmtAzuriraj = $pdo->prepare(
                "UPDATE osnovna_sredstva SET lokacija_id = :lokacija, mesto_troska_id = :mesto_troska WHERE id = :id"
            );

            $brojPremestenih = 0;

            foreach ($stavkeIds as $sredstvoId) {
                $stmtStanje->execute([':id' => $sredstvoId]);
                $staroStanje = $stmtStanje->fetch();
                if (!$staroStanje) {
                    continue;
                }

                $staraLokacija = $staroStanje['lokacija_id'] !== null ? (int)$staroStanje['lokacija_id'] : null;
                $staroMestoTroska = $staroStanje['mesto_troska_id'] !== null ? (int)$staroStanje['mesto_troska_id'] : null;

                $primeniLokaciju = $nemenjajLokaciju ? $staraLokacija : $novaLokacija;
                $primeniMestoTroska = $nemenjajMestoTroska ? $staroMestoTroska : $novoMestoTroska;

                if ($primeniLokaciju === $staraLokacija && $primeniMestoTroska === $staroMestoTroska) {
                    continue; // bez promene za ovo sredstvo - preskoči
                }

                $stmtTransakcija->execute([
                    ':sredstvo' => $sredstvoId,
                    ':vrsta'    => $vrstaTransakcije['id'],
                    ':datum'    => $dokument['datum_premestaja'],
                    ':broj_dok' => $dokument['broj_dokumenta'],
                    ':opis'     => 'Premeštaj po dokumentu ' . $dokument['broj_dokumenta'],
                    ':korisnik' => $trenutni['id'] ?? null,
                    ':napomena' => $dokument['napomena'],
                ]);
                $transakcijaId = (int)$pdo->lastInsertId();

                $stmtPremestaj->execute([
                    ':dokument'    => $id,
                    ':transakcija' => $transakcijaId,
                    ':sredstvo'    => $sredstvoId,
                    ':datum'       => $dokument['datum_premestaja'],
                    ':stara_lok'   => $staraLokacija,
                    ':nova_lok'    => $primeniLokaciju,
                    ':staro_mt'    => $staroMestoTroska,
                    ':novo_mt'     => $primeniMestoTroska,
                    ':staro_lice'  => $staroStanje['odgovorno_lice'],
                    ':stari_zap'   => $staroStanje['zaposleni_id'],
                    ':novo_lice'   => $staroStanje['odgovorno_lice'],
                    ':novi_zap'    => $staroStanje['zaposleni_id'],
                    ':napomena'    => $dokument['napomena'],
                ]);

                $stmtAzuriraj->execute([
                    ':lokacija'     => $primeniLokaciju,
                    ':mesto_troska' => $primeniMestoTroska,
                    ':id'           => $sredstvoId,
                ]);

                $brojPremestenih++;
            }

            if ($brojPremestenih === 0) {
                $pdo->rollBack();
                $poruka = "Nijedno sredstvo nije premešteno - sva su već na izabranoj lokaciji/mestu troška.";
            } else {
                $pdo->prepare("UPDATE dokumenti_premestaja SET status = 'IZDAT' WHERE id = :id")->execute([':id' => $id]);
                $pdo->commit();
                header("Location: premestaji_pregled.php?id=" . $id);
                exit;
            }
        } catch (\PDOException $e) {
            $pdo->rollBack();
            $poruka = "Greška pri izdavanju: " . $e->getMessage();
        } catch (\RuntimeException $e) {
            $pdo->rollBack();
            $poruka = $e->getMessage();
        }
    }
}

// Spoj planiranih stavki (stavke_premestaja) sa izvršenim promenama
// (premestaji_sredstva, popunjeno tek nakon izdavanja).
$stmt = $pdo->prepare(
    "SELECT
        sp.id AS stavka_id, sp.sredstvo_id,
        os.inventarski_broj, os.naziv, k.naziv AS naziv_klase,
        ps.id AS izvrseno_id,
        sl.naziv AS stara_lokacija, nl.naziv AS nova_lokacija,
        smt.naziv AS staro_mesto_troska, nmt.naziv AS novo_mesto_troska
     FROM stavke_premestaja sp
     JOIN osnovna_sredstva os ON os.id = sp.sredstvo_id
     JOIN klase_osnovnih_sredstava k ON k.id = os.klasa_id
     LEFT JOIN premestaji_sredstva ps
        ON ps.dokument_premestaja_id = sp.dokument_premestaja_id AND ps.sredstvo_id = sp.sredstvo_id
     LEFT JOIN lokacije sl ON sl.id = ps.stara_lokacija_id
     LEFT JOIN lokacije nl ON nl.id = ps.nova_lokacija_id
     LEFT JOIN mesta_troska smt ON smt.id = ps.staro_mesto_troska_id
     LEFT JOIN mesta_troska nmt ON nmt.id = ps.novo_mesto_troska_id
     WHERE sp.dokument_premestaja_id = :id
     ORDER BY os.naziv"
);
$stmt->execute([':id' => $id]);
$stavke = $stmt->fetchAll();

$mapaStatusa = [
    'U_PRIPREMI' => ['U pripremi', 'oznaka-u-toku'],
    'IZDAT'      => ['Izdat', 'oznaka-aktivna'],
    'PONISTEN'   => ['Poništen', 'oznaka-otkazana'],
];
[$nazivStatusa, $klasaOznake] = $mapaStatusa[$dokument['status']] ?? [$dokument['status'], 'oznaka-neaktivna'];

$naslovStranice = 'Premeštaj ' . $dokument['broj_dokumenta'];
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2>
        Premeštaj <?= htmlspecialchars($dokument['broj_dokumenta']) ?>
        <span class="oznaka <?= $klasaOznake ?>"><?= $nazivStatusa ?></span>
    </h2>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <div class="detalj-red">
        <span class="detalj-labela">Datum premeštaja</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($dokument['datum_premestaja']) ?></span>
    </div>
    <div class="detalj-red">
        <span class="detalj-labela">Nova lokacija</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($dokument['nova_lokacija'] ?? '— (nije menjano ovim dokumentom)') ?></span>
    </div>
    <div class="detalj-red">
        <span class="detalj-labela">Novo mesto troška</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($dokument['novo_mesto_troska'] ?? '— (nije menjano ovim dokumentom)') ?></span>
    </div>
    <div class="detalj-red">
        <span class="detalj-labela">Kreirao</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($dokument['korisnicko_ime'] ?? '—') ?></span>
    </div>
    <?php if (!empty($dokument['napomena'])): ?>
    <div class="detalj-red">
        <span class="detalj-labela">Napomena</span>
        <span class="detalj-vrednost"><?= nl2br(htmlspecialchars($dokument['napomena'])) ?></span>
    </div>
    <?php endif; ?>

    <div style="margin-top: 20px;">
        <?php if ($dokument['status'] === 'U_PRIPREMI'): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Izdati ovaj premeštaj? Ovim se stvarno menja lokacija/mesto troška izabranih sredstava.');">
                <input type="hidden" name="akcija" value="izdaj">
                <button type="submit" class="btn">Izdaj premeštaj</button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Poništiti ovaj nacrt premeštaja?');">
                <input type="hidden" name="akcija" value="ponisti">
                <button type="submit" class="btn" style="background:#dc3545;">Poništi nacrt</button>
            </form>
        <?php elseif ($dokument['status'] === 'IZDAT'): ?>
            <a href="premestaj_stampa.php?id=<?= $dokument['id'] ?>" class="btn" target="_blank">Odštampaj premeštaj</a>
        <?php endif; ?>
    </div>
</div>

<div style="margin-top: 20px;">
    <table>
        <thead>
            <tr>
                <th>Inventarski broj</th>
                <th>Naziv</th>
                <th>Klasa</th>
                <th>Lokacija (staro → novo)</th>
                <th>Mesto troška (staro → novo)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stavke as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['inventarski_broj']) ?></td>
                    <td><?= htmlspecialchars($s['naziv']) ?></td>
                    <td><?= htmlspecialchars($s['naziv_klase']) ?></td>
                    <?php if ($s['izvrseno_id']): ?>
                        <td><?= htmlspecialchars($s['stara_lokacija'] ?? '—') ?> → <?= htmlspecialchars($s['nova_lokacija'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($s['staro_mesto_troska'] ?? '—') ?> → <?= htmlspecialchars($s['novo_mesto_troska'] ?? '—') ?></td>
                    <?php elseif ($dokument['status'] === 'IZDAT'): ?>
                        <td colspan="2" class="napomena-polje">Bez promene (već na izabranoj lokaciji/mestu troška)</td>
                    <?php else: ?>
                        <td colspan="2" class="napomena-polje">Čeka izdavanje</td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div style="margin-top: 20px;">
    <a href="premestaji_index.php" class="btn-cancel">Nazad na listu premeštaja</a>
</div>

<?php require_once 'footer.php'; ?>
