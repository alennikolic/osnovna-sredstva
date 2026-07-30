<?php
/**
 * premestaji_pregled.php
 * -----------------------
 * Pregled jednog dokumenta premeštaja - zaglavlje (broj, datum, ko je
 * izvršio) i sve stavke (sredstva) koja su njime premeštena, sa dugmetom
 * za štampu (premestaj_stampa.php). Isti obrazac kao revers_pregled.php.
 */

require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

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

$stmt = $pdo->prepare(
    "SELECT
        p.id, p.sredstvo_id, os.inventarski_broj, os.naziv, k.naziv AS naziv_klase,
        sl.naziv AS stara_lokacija, nl.naziv AS nova_lokacija_stavka,
        smt.naziv AS staro_mesto_troska, nmt.naziv AS novo_mesto_troska_stavka
     FROM premestaji_sredstva p
     JOIN osnovna_sredstva os ON os.id = p.sredstvo_id
     JOIN klase_osnovnih_sredstava k ON k.id = os.klasa_id
     LEFT JOIN lokacije sl ON sl.id = p.stara_lokacija_id
     LEFT JOIN lokacije nl ON nl.id = p.nova_lokacija_id
     LEFT JOIN mesta_troska smt ON smt.id = p.staro_mesto_troska_id
     LEFT JOIN mesta_troska nmt ON nmt.id = p.novo_mesto_troska_id
     WHERE p.dokument_premestaja_id = :id
     ORDER BY os.naziv"
);
$stmt->execute([':id' => $id]);
$stavke = $stmt->fetchAll();

$naslovStranice = 'Premeštaj ' . $dokument['broj_dokumenta'];
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2>Premeštaj <?= htmlspecialchars($dokument['broj_dokumenta']) ?></h2>

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
        <span class="detalj-labela">Izvršio</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($dokument['korisnicko_ime'] ?? '—') ?></span>
    </div>
    <?php if (!empty($dokument['napomena'])): ?>
    <div class="detalj-red">
        <span class="detalj-labela">Napomena</span>
        <span class="detalj-vrednost"><?= nl2br(htmlspecialchars($dokument['napomena'])) ?></span>
    </div>
    <?php endif; ?>

    <div style="margin-top: 20px;">
        <a href="premestaj_stampa.php?id=<?= $dokument['id'] ?>" class="btn" target="_blank">Odštampaj premeštaj</a>
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
                    <td><?= htmlspecialchars($s['stara_lokacija'] ?? '—') ?> → <?= htmlspecialchars($s['nova_lokacija_stavka'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($s['staro_mesto_troska'] ?? '—') ?> → <?= htmlspecialchars($s['novo_mesto_troska_stavka'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div style="margin-top: 20px;">
    <a href="premestaji_index.php" class="btn-cancel">Nazad na listu premeštaja</a>
</div>

<?php require_once 'footer.php'; ?>
