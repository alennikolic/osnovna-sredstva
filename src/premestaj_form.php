<?php
/**
 * premestaj_form.php
 * -------------------
 * Kreira NACRT dokumenta premeštaja (status U_PRIPREMI) - stvarna promena
 * lokacije/mesta troška (upis u osnovna_sredstva, transakcije_sredstva,
 * premestaji_sredstva) dešava se tek kada se dokument IZDA na
 * premestaji_pregled.php.
 *
 * NAMERNO ne dira zaduženje (zaposleni_id) - to ide isključivo kroz revers.
 */

require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';
require_once 'helpers.php';

$poruka = '';

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
        $novaLokacija = $podaci['nova_lokacija_id'] !== '' ? (int)$podaci['nova_lokacija_id'] : null;
        $novoMestoTroska = $podaci['novo_mesto_troska_id'] !== '' ? (int)$podaci['novo_mesto_troska_id'] : null;

        try {
            $pdo->beginTransaction();

            $brojDokumenta = sledeciBrojPremestaja($pdo);
            $trenutni = trenutniKorisnik();

            $stmt = $pdo->prepare(
                "INSERT INTO dokumenti_premestaja
                    (broj_dokumenta, datum_premestaja, nova_lokacija_id, novo_mesto_troska_id, korisnik_id, napomena, status)
                 VALUES
                    (:broj, :datum, :lokacija, :mesto_troska, :korisnik, :napomena, 'U_PRIPREMI')"
            );
            $stmt->execute([
                ':broj'         => $brojDokumenta,
                ':datum'        => $podaci['datum_premestaja'],
                ':lokacija'     => $novaLokacija,
                ':mesto_troska' => $novoMestoTroska,
                ':korisnik'     => $trenutni['id'] ?? null,
                ':napomena'     => $podaci['napomena'] !== '' ? $podaci['napomena'] : null,
            ]);
            $dokumentId = (int)$pdo->lastInsertId();

            $stmtStavka = $pdo->prepare(
                "INSERT INTO stavke_premestaja (dokument_premestaja_id, sredstvo_id) VALUES (:dokument, :sredstvo)"
            );
            foreach ($podaci['sredstva'] as $sredstvoId) {
                $stmtStavka->execute([':dokument' => $dokumentId, ':sredstvo' => $sredstvoId]);
            }

            $pdo->commit();
            header("Location: premestaji_pregled.php?id=" . $dokumentId);
            exit;
        } catch (\PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                $poruka = "Došlo je do konflikta pri generisanju broja dokumenta (verovatno je neko drugi baš u ovom trenutku napravio premeštaj). Pokušajte ponovo.";
            } else {
                $poruka = "Greška pri upisu u bazu: " . $e->getMessage();
            }
        }
    }
}

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
        Premeštaj se prvo čuva kao nacrt ("U pripremi") - promena lokacije/mesta troška se stvarno izvršava tek kada ga izdate na sledećem ekranu.
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

        <button type="submit" class="btn">Sačuvaj nacrt premeštaja</button>
        <a href="kretanje_index.php" class="btn-cancel">Otkaži</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
