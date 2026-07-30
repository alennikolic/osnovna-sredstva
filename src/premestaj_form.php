<?php
/**
 * premestaj_form.php
 * -------------------
 * Evidentiranje premeštaja jednog ili više osnovnih sredstava - promena
 * lokacije i/ili mesta troška. NAMERNO ne dira zaduženje (zaposleni_id) -
 * to je odvojen proces koji ide isključivo kroz revers (izdavanje/vraćanje),
 * da se ne bi mešala dva puta koja menjaju istu kolonu.
 *
 * Podržava grupni premeštaj (više sredstava u jednom dokumentu/transakciji),
 * isti obrazac kao revers_form.php za više stavki na jednom reversu.
 */

require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$poruka = '';

// Ako se dolazi sa stranice sredstva (dugme "Premesti"), to sredstvo je
// unapred čekirano u listi - korisnik i dalje može da doda još sredstava.
$sredstvoIdIzUrl = isset($_GET['sredstvo_id']) && $_GET['sredstvo_id'] !== '' ? (int)$_GET['sredstvo_id'] : null;

$podaci = [
    'sredstva' => $sredstvoIdIzUrl ? [$sredstvoIdIzUrl] : [],
    'datum_premestaja' => date('Y-m-d'),
    'nova_lokacija_id' => '',
    'novo_mesto_troska_id' => '',
    'napomena' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $podaci['sredstva'] = array_map('intval', $_POST['sredstva'] ?? []);
    $podaci['datum_premestaja'] = trim($_POST['datum_premestaja'] ?? '');
    $podaci['nova_lokacija_id'] = $_POST['nova_lokacija_id'] ?? '';
    $podaci['novo_mesto_troska_id'] = $_POST['novo_mesto_troska_id'] ?? '';
    $podaci['napomena'] = trim($_POST['napomena'] ?? '');

    if (empty($podaci['sredstva'])) {
        $poruka = "Izaberite bar jedno sredstvo za premeštaj.";
    } elseif ($podaci['datum_premestaja'] === '') {
        $poruka = "Datum premeštaja je obavezan.";
    } elseif ($podaci['nova_lokacija_id'] === '' && $podaci['novo_mesto_troska_id'] === '') {
        $poruka = "Izaberite novu lokaciju i/ili novo mesto troška (ostavite polje na 'Ne menjaj' ako se to ne menja).";
    } else {
        // '' znači "ne menjaj ovo polje" - primenjuje se trenutna vrednost
        // svakog sredstva ponaosob (vidi petlju ispod). Ovo omogućava da se
        // u jednom premeštaju promeni SAMO lokacija ili SAMO mesto troška.
        $novaLokacija = $podaci['nova_lokacija_id'] !== '' ? (int)$podaci['nova_lokacija_id'] : null;
        $novoMestoTroska = $podaci['novo_mesto_troska_id'] !== '' ? (int)$podaci['novo_mesto_troska_id'] : null;
        $nemenjajLokaciju = $podaci['nova_lokacija_id'] === '';
        $nemenjajMestoTroska = $podaci['novo_mesto_troska_id'] === '';

        try {
            $pdo->beginTransaction();

            $vrstaTransakcije = $pdo->query(
                "SELECT id FROM vrste_transakcija WHERE sifra = 'PREMESTAJ'"
            )->fetch();
            if (!$vrstaTransakcije) {
                throw new \RuntimeException('Vrsta transakcije PREMESTAJ ne postoji u bazi.');
            }

            $trenutni = trenutniKorisnik();

            $stmtStanje = $pdo->prepare(
                "SELECT lokacija_id, mesto_troska_id, odgovorno_lice, zaposleni_id
                 FROM osnovna_sredstva WHERE id = :id"
            );
            $stmtTransakcija = $pdo->prepare(
                "INSERT INTO transakcije_sredstva
                    (sredstvo_id, vrsta_transakcije_id, datum_transakcije, opis, korisnik_id, napomena)
                 VALUES
                    (:sredstvo, :vrsta, :datum, :opis, :korisnik, :napomena)"
            );
            $stmtPremestaj = $pdo->prepare(
                "INSERT INTO premestaji_sredstva
                    (transakcija_id, sredstvo_id, datum_premestaja,
                     stara_lokacija_id, nova_lokacija_id,
                     staro_mesto_troska_id, novo_mesto_troska_id,
                     staro_odgovorno_lice, stari_zaposleni_id,
                     novo_odgovorno_lice, novi_zaposleni_id, napomena)
                 VALUES
                    (:transakcija, :sredstvo, :datum,
                     :stara_lok, :nova_lok,
                     :staro_mt, :novo_mt,
                     :staro_lice, :stari_zap,
                     :novo_lice, :novi_zap, :napomena)"
            );
            // Premeštaj menja SAMO lokaciju i mesto troška - zaduženje
            // (zaposleni_id, odgovorno_lice) se namerno ne dira.
            $stmtAzuriraj = $pdo->prepare(
                "UPDATE osnovna_sredstva
                 SET lokacija_id = :lokacija, mesto_troska_id = :mesto_troska
                 WHERE id = :id"
            );

            $brojPremestenih = 0;
            $brojPreskocenih = 0;

            foreach ($podaci['sredstva'] as $sredstvoId) {
                $stmtStanje->execute([':id' => $sredstvoId]);
                $staroStanje = $stmtStanje->fetch();
                if (!$staroStanje) {
                    continue; // sredstvo ne postoji - preskoči
                }

                $staraLokacija = $staroStanje['lokacija_id'] !== null ? (int)$staroStanje['lokacija_id'] : null;
                $staroMestoTroska = $staroStanje['mesto_troska_id'] !== null ? (int)$staroStanje['mesto_troska_id'] : null;

                $primeniLokaciju = $nemenjajLokaciju ? $staraLokacija : $novaLokacija;
                $primeniMestoTroska = $nemenjajMestoTroska ? $staroMestoTroska : $novoMestoTroska;

                // Ako se za OVO sredstvo ništa stvarno ne menja, preskoči ga -
                // nema smisla evidentirati "premeštaj" bez promene.
                if ($primeniLokaciju === $staraLokacija && $primeniMestoTroska === $staroMestoTroska) {
                    $brojPreskocenih++;
                    continue;
                }

                $stmtTransakcija->execute([
                    ':sredstvo' => $sredstvoId,
                    ':vrsta'    => $vrstaTransakcije['id'],
                    ':datum'    => $podaci['datum_premestaja'],
                    ':opis'     => 'Premeštaj osnovnog sredstva',
                    ':korisnik' => $trenutni['id'] ?? null,
                    ':napomena' => $podaci['napomena'] !== '' ? $podaci['napomena'] : null,
                ]);
                $transakcijaId = (int)$pdo->lastInsertId();

                $stmtPremestaj->execute([
                    ':transakcija' => $transakcijaId,
                    ':sredstvo'    => $sredstvoId,
                    ':datum'       => $podaci['datum_premestaja'],
                    ':stara_lok'   => $staraLokacija,
                    ':nova_lok'    => $primeniLokaciju,
                    ':staro_mt'    => $staroMestoTroska,
                    ':novo_mt'     => $primeniMestoTroska,
                    ':staro_lice'  => $staroStanje['odgovorno_lice'],
                    ':stari_zap'   => $staroStanje['zaposleni_id'],
                    ':novo_lice'   => $staroStanje['odgovorno_lice'], // nepromenjeno - premeštaj ne dira zaduženje
                    ':novi_zap'    => $staroStanje['zaposleni_id'],   // nepromenjeno - premeštaj ne dira zaduženje
                    ':napomena'    => $podaci['napomena'] !== '' ? $podaci['napomena'] : null,
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
                $poruka = "Nijedno izabrano sredstvo nije premešteno - sva su već na izabranoj lokaciji/mestu troška.";
            } else {
                $pdo->commit();
                header("Location: premestaji_index.php");
                exit;
            }
        } catch (\PDOException $e) {
            $pdo->rollBack();
            $poruka = "Greška pri upisu u bazu: " . $e->getMessage();
        } catch (\RuntimeException $e) {
            $pdo->rollBack();
            $poruka = $e->getMessage();
        }
    }
}

// Sredstva dostupna za premeštaj - isto pravilo kao kod reversa i popisa:
// svi statusi koji NISU završni.
$sveSredstva = $pdo->query(
    "SELECT os.id, os.inventarski_broj, os.naziv, k.naziv AS naziv_klase,
            l.naziv AS trenutna_lokacija, mt.naziv AS trenutno_mesto_troska
     FROM osnovna_sredstva os
     JOIN klase_osnovnih_sredstava k ON k.id = os.klasa_id
     JOIN statusi_sredstva s ON s.id = os.status_id
     LEFT JOIN lokacije l ON l.id = os.lokacija_id
     LEFT JOIN mesta_troska mt ON mt.id = os.mesto_troska_id
     WHERE s.da_li_je_zavrsni_status = 0
     ORDER BY os.naziv"
)->fetchAll();

$lokacije = $pdo->query("SELECT id, naziv FROM lokacije WHERE aktivna = 1 ORDER BY naziv")->fetchAll();
$mestaTroska = $pdo->query("SELECT id, naziv FROM mesta_troska WHERE aktivno = 1 ORDER BY naziv")->fetchAll();

$naslovStranice = 'Premeštaj osnovnih sredstava';
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2>Premeštaj osnovnih sredstava</h2>
    <p class="napomena-polje" style="margin-top:-10px; margin-bottom: 20px;">
        Promena lokacije i/ili mesta troška za jedno ili više sredstava odjednom.
        Za promenu zaduženog lica koristite <a href="revers_form.php">Revers</a>.
    </p>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>Datum premeštaja *</label>
            <input type="date" name="datum_premestaja" required value="<?= htmlspecialchars($podaci['datum_premestaja']) ?>">
        </div>

        <div class="red-2">
            <div class="form-group">
                <label>Nova lokacija</label>
                <select name="nova_lokacija_id">
                    <option value="">-- Ne menjaj --</option>
                    <?php foreach ($lokacije as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= (string)$podaci['nova_lokacija_id'] === (string)$l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['naziv']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Novo mesto troška</label>
                <select name="novo_mesto_troska_id">
                    <option value="">-- Ne menjaj --</option>
                    <?php foreach ($mestaTroska as $mt): ?>
                        <option value="<?= $mt['id'] ?>" <?= (string)$podaci['novo_mesto_troska_id'] === (string)$mt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($mt['naziv']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Napomena</label>
            <textarea name="napomena"><?= htmlspecialchars($podaci['napomena']) ?></textarea>
        </div>

        <div class="form-group">
            <label>Sredstva za premeštaj * <span class="napomena-polje">(izaberite jedno ili više)</span></label>
            <div class="lista-checkboxova">
                <?php if (empty($sveSredstva)): ?>
                    <p class="napomena-polje">Nema dostupnih sredstava.</p>
                <?php endif; ?>
                <?php foreach ($sveSredstva as $sr): ?>
                    <label class="stavka-checkboxa">
                        <input type="checkbox" name="sredstva[]" value="<?= $sr['id'] ?>"
                               <?= in_array($sr['id'], $podaci['sredstva'], true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($sr['inventarski_broj'] . ' - ' . $sr['naziv']) ?>
                        <span class="napomena-polje">(<?= htmlspecialchars($sr['naziv_klase']) ?>)</span>
                        <span class="napomena-polje">— trenutno: <?= htmlspecialchars($sr['trenutna_lokacija'] ?? '—') ?> / <?= htmlspecialchars($sr['trenutno_mesto_troska'] ?? '—') ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" class="btn">Izvrši premeštaj</button>
        <a href="kretanje_index.php" class="btn-cancel">Otkaži</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
