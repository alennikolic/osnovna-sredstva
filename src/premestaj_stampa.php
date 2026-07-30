<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$id = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : null;
if (empty($id)) {
    header("Location: premestaji_index.php");
    exit;
}

$stmt = $pdo->prepare(
    "SELECT d.*, nl.naziv AS nova_lokacija, nmt.naziv AS novo_mesto_troska
     FROM dokumenti_premestaja d
     LEFT JOIN lokacije nl ON nl.id = d.nova_lokacija_id
     LEFT JOIN mesta_troska nmt ON nmt.id = d.novo_mesto_troska_id
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
        os.inventarski_broj, os.naziv, os.serijski_broj,
        sl.naziv AS stara_lokacija, nl.naziv AS nova_lokacija,
        smt.naziv AS staro_mesto_troska, nmt.naziv AS novo_mesto_troska
     FROM premestaji_sredstva p
     JOIN osnovna_sredstva os ON os.id = p.sredstvo_id
     LEFT JOIN lokacije sl ON sl.id = p.stara_lokacija_id
     LEFT JOIN lokacije nl ON nl.id = p.nova_lokacija_id
     LEFT JOIN mesta_troska smt ON smt.id = p.staro_mesto_troska_id
     LEFT JOIN mesta_troska nmt ON nmt.id = p.novo_mesto_troska_id
     WHERE p.dokument_premestaja_id = :id
     ORDER BY os.naziv"
);
$stmt->execute([':id' => $id]);
$stavke = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premeštaj <?= htmlspecialchars($dokument['broj_dokumenta']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #000; }
        h1 { text-align: center; font-size: 20px; margin-bottom: 5px; }
        .broj { text-align: center; font-size: 14px; margin-bottom: 30px; color: #555; }
        .info-red { margin-bottom: 8px; }
        .info-red strong { display: inline-block; width: 160px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; margin-bottom: 30px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; font-size: 13px; }
        th { background: #eee; }
        .potpisi { display: flex; justify-content: space-between; margin-top: 60px; }
        .potpis-blok { width: 45%; text-align: center; font-size: 13px; }
        .linija-potpisa { border-top: 1px solid #000; margin-top: 50px; padding-top: 5px; }
        .dugmad-stampe { text-align: center; margin-bottom: 30px; }
        .dugmad-stampe a, .dugmad-stampe button { margin: 0 5px; padding: 8px 14px; }
        @media print {
            .dugmad-stampe { display: none; }
            body { margin: 15mm; }
        }
    </style>
</head>
<body>

    <div class="dugmad-stampe">
        <button onclick="window.print()">Odštampaj</button>
        <a href="premestaji_pregled.php?id=<?= $dokument['id'] ?>">Nazad</a>
    </div>

    <h1>NALOG ZA PREMEŠTAJ OSNOVNIH SREDSTAVA</h1>
    <div class="broj">Broj: <?= htmlspecialchars($dokument['broj_dokumenta']) ?></div>

    <div class="info-red"><strong>Datum premeštaja:</strong> <?= htmlspecialchars($dokument['datum_premestaja']) ?></div>
    <?php if (!empty($dokument['nova_lokacija'])): ?>
        <div class="info-red"><strong>Nova lokacija:</strong> <?= htmlspecialchars($dokument['nova_lokacija']) ?></div>
    <?php endif; ?>
    <?php if (!empty($dokument['novo_mesto_troska'])): ?>
        <div class="info-red"><strong>Novo mesto troška:</strong> <?= htmlspecialchars($dokument['novo_mesto_troska']) ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>R.br.</th>
                <th>Naziv sredstva</th>
                <th>Inventarski broj</th>
                <th>Serijski broj</th>
                <th>Lokacija (staro → novo)</th>
                <th>Mesto troška (staro → novo)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stavke as $i => $s): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($s['naziv']) ?></td>
                    <td><?= htmlspecialchars($s['inventarski_broj']) ?></td>
                    <td><?= htmlspecialchars($s['serijski_broj'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($s['stara_lokacija'] ?? '—') ?> → <?= htmlspecialchars($s['nova_lokacija'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($s['staro_mesto_troska'] ?? '—') ?> → <?= htmlspecialchars($s['novo_mesto_troska'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($dokument['napomena'])): ?>
        <p><strong>Napomena:</strong> <?= nl2br(htmlspecialchars($dokument['napomena'])) ?></p>
    <?php endif; ?>

    <div class="potpisi">
        <div class="potpis-blok">
            <div class="linija-potpisa">Predao</div>
        </div>
        <div class="potpis-blok">
            <div class="linija-potpisa">Primio</div>
        </div>
    </div>

</body>
</html>
