<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$id = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : null;
if (empty($id)) {
    header("Location: reversi_index.php");
    exit;
}

$stmt = $pdo->prepare(
    "SELECT r.*, z.ime, z.prezime, z.radno_mesto
     FROM reversi r
     JOIN zaposleni z ON z.id = r.zaposleni_id
     WHERE r.id = :id"
);
$stmt->execute([':id' => $id]);
$revers = $stmt->fetch();

if (!$revers) {
    header("Location: reversi_index.php");
    exit;
}

$stmt = $pdo->prepare(
    "SELECT os.inventarski_broj, os.naziv, os.serijski_broj, os.nabavna_vrednost
     FROM stavke_reversa sr
     JOIN osnovna_sredstva os ON os.id = sr.sredstvo_id
     WHERE sr.revers_id = :id
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
    <title>Revers <?= htmlspecialchars($revers['broj_reversa']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #000; }
        h1 { text-align: center; font-size: 20px; margin-bottom: 5px; }
        .broj { text-align: center; font-size: 14px; margin-bottom: 30px; color: #555; }
        .ponisten-traka {
            text-align: center; color: #dc3545; font-weight: bold; font-size: 16px;
            margin-bottom: 20px; border: 2px solid #dc3545; padding: 10px;
        }
        .info-red { margin-bottom: 8px; }
        .info-red strong { display: inline-block; width: 160px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; margin-bottom: 30px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; font-size: 13px; }
        th { background: #eee; }
        .izjava { margin-top: 20px; font-size: 13px; }
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
        <a href="revers_pregled.php?id=<?= $revers['id'] ?>">Nazad</a>
    </div>

    <h1>REVERS O ZADUŽENJU OSNOVNIH SREDSTAVA</h1>
    <div class="broj">Broj: <?= htmlspecialchars($revers['broj_reversa']) ?></div>

    <?php if ($revers['status'] === 'PONISTEN'): ?>
        <div class="ponisten-traka">OVAJ REVERS JE PONIŠTEN</div>
    <?php endif; ?>

    <div class="info-red"><strong>Zaposleni:</strong> <?= htmlspecialchars($revers['ime'] . ' ' . $revers['prezime']) ?></div>
    <?php if (!empty($revers['radno_mesto'])): ?>
        <div class="info-red"><strong>Radno mesto:</strong> <?= htmlspecialchars($revers['radno_mesto']) ?></div>
    <?php endif; ?>
    <div class="info-red"><strong>Datum izdavanja:</strong> <?= htmlspecialchars($revers['datum_izdavanja']) ?></div>

    <table>
        <thead>
            <tr>
                <th>R.br.</th>
                <th>Naziv sredstva</th>
                <th>Inventarski broj</th>
                <th>Serijski broj</th>
                <th>Nabavna vrednost</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stavke as $i => $s): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($s['naziv']) ?></td>
                    <td><?= htmlspecialchars($s['inventarski_broj']) ?></td>
                    <td><?= htmlspecialchars($s['serijski_broj'] ?? '—') ?></td>
                    <td><?= number_format($s['nabavna_vrednost'], 2, ',', '.') ?> RSD</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($revers['napomena'])): ?>
        <p><strong>Napomena:</strong> <?= nl2br(htmlspecialchars($revers['napomena'])) ?></p>
    <?php endif; ?>

    <p class="izjava">
        Potpisom potvrđujem da sam gore navedena sredstva primio/la u ispravnom stanju i
        obavezujem se da ću ih čuvati i vratiti po prestanku potrebe ili radnog odnosa.
    </p>

    <div class="potpisi">
        <div class="potpis-blok">
            <div class="linija-potpisa">Zaposleni</div>
        </div>
        <div class="potpis-blok">
            <div class="linija-potpisa">Odgovorno lice / Izdao</div>
        </div>
    </div>

</body>
</html>
