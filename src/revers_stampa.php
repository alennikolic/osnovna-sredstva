<?php
/**
 * revers_stampa.php
 * ------------------
 * Štampani dokument reversa. Radi za status IZDAT (potvrda zaduženja - potpis
 * "Predao/Primio") i za status VRACEN (potvrda da je SVE vraćeno - potpis
 * "Vratio/Primio nazad"), pa se isti dokument može ponovo odštampati kao
 * dokaz vraćanja kada su sve stavke označene kao vraćene.
 */

require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$id = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : null;
if (empty($id)) {
    header("Location: reversi_index.php");
    exit;
}

$stmt = $pdo->prepare(
    "SELECT r.*, CONCAT(z.ime, ' ', z.prezime) AS ime_zaposlenog, z.radno_mesto
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
    "SELECT os.inventarski_broj, os.naziv, os.serijski_broj,
            sr.vraceno, sr.datum_vracanja
     FROM stavke_reversa sr
     JOIN osnovna_sredstva os ON os.id = sr.sredstvo_id
     WHERE sr.revers_id = :id
     ORDER BY os.naziv"
);
$stmt->execute([':id' => $id]);
$stavke = $stmt->fetchAll();

$nazivStatusaZaStampu = [
    'U_PRIPREMI' => 'U pripremi',
    'IZDAT'      => 'Izdat',
    'VRACEN'     => 'Vraćen',
    'PONISTEN'   => 'Poništen',
][$revers['status']] ?? $revers['status'];

// Kad je sve vraćeno, dokument se štampa kao POTVRDA VRAĆANJA (drugačiji
// natpis na potpisima) - inače kao potvrda zaduženja.
$jeVracen = $revers['status'] === 'VRACEN';
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
        .broj { text-align: center; font-size: 14px; margin-bottom: 10px; color: #555; }
        .napomena-vracanja { text-align: center; font-size: 13px; margin-bottom: 25px; font-style: italic; }
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
        <a href="revers_pregled.php?id=<?= $revers['id'] ?>">Nazad</a>
    </div>

    <h1><?= $jeVracen ? 'POTVRDA O VRAĆANJU SREDSTAVA' : 'REVERS O ZADUŽENJU SREDSTAVA' ?></h1>
    <div class="broj">Broj: <?= htmlspecialchars($revers['broj_reversa']) ?> — Status: <?= htmlspecialchars($nazivStatusaZaStampu) ?></div>
    <?php if ($jeVracen): ?>
        <div class="napomena-vracanja">Ovaj dokument potvrđuje da su sva sredstva navedena u reversu vraćena.</div>
    <?php endif; ?>

    <div class="info-red"><strong>Zaposleni:</strong> <?= htmlspecialchars($revers['ime_zaposlenog']) ?><?= $revers['radno_mesto'] ? ' (' . htmlspecialchars($revers['radno_mesto']) . ')' : '' ?></div>
    <div class="info-red"><strong>Datum izdavanja:</strong> <?= htmlspecialchars($revers['datum_izdavanja']) ?></div>

    <table>
        <thead>
            <tr>
                <th>R.br.</th>
                <th>Naziv sredstva</th>
                <th>Inventarski broj</th>
                <th>Serijski broj</th>
                <th>Status</th>
                <th>Datum vraćanja</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stavke as $i => $s): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($s['naziv']) ?></td>
                    <td><?= htmlspecialchars($s['inventarski_broj']) ?></td>
                    <td><?= htmlspecialchars($s['serijski_broj'] ?? '—') ?></td>
                    <td><?= $s['vraceno'] ? 'Vraćeno' : 'Zaduženo' ?></td>
                    <td><?= htmlspecialchars($s['datum_vracanja'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($revers['napomena'])): ?>
        <p><strong>Napomena:</strong> <?= nl2br(htmlspecialchars($revers['napomena'])) ?></p>
    <?php endif; ?>

    <div class="potpisi">
        <div class="potpis-blok">
            <div class="linija-potpisa"><?= $jeVracen ? 'Vratio' : 'Predao' ?></div>
        </div>
        <div class="potpis-blok">
            <div class="linija-potpisa"><?= $jeVracen ? 'Primio nazad' : 'Primio' ?></div>
        </div>
    </div>

</body>
</html>
