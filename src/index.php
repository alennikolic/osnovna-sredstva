<?php
require_once 'db.php';

// Dohvatanje podataka iz pomoćnog view-a definisanog u SQL šemi
$stmt = $pdo->query("SELECT * FROM pregled_osnovnih_sredstava ORDER BY id DESC");
$sredstva = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Osnovna Sredstva - Pregled</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f6f9; }
        h1 { color: #333; }
        .nav-bar { margin-bottom: 20px; }
        .nav-bar a { margin-right: 15px; color: #007bff; text-decoration: none; font-weight: bold; }
        .nav-bar a:hover { text-decoration: underline; }
        .btn { display: inline-block; padding: 10px 15px; background: #28a745; color: #fff; text-decoration: none; border-radius: 4px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #007bff; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
    </style>
</head>
<body>

    <div class="nav-bar">
        <a href="index.php">Osnovna sredstva</a>
        <a href="klase_index.php">Klase osnovnih sredstava</a>
    </div>

    <h1>Evidencija osnovnih sredstava</h1>
    <a href="os_form.php" class="btn">+ Novo Osnovno Sredstvo</a>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Inv. Broj</th>
                <th>Naziv</th>
                <th>Klasa</th>
                <th>Status</th>
                <th>Lokacija</th>
                <th>Mesto Troška</th>
                <th>Nabavna Vrednost</th>
                <th>Datum Nabavke</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($sredstva)): ?>
                <tr><td colspan="9" style="text-align:center;">Nema unetih osnovnih sredstava.</td></tr>
            <?php else: ?>
                <?php foreach ($sredstva as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><strong><?= htmlspecialchars($row['inventarski_broj']) ?></strong></td>
                        <td><?= htmlspecialchars($row['naziv']) ?></td>
                        <td><?= htmlspecialchars($row['klasa']) ?></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                        <td><?= htmlspecialchars($row['lokacija'] ?? 'Nije dodeljeno') ?></td>
                        <td><?= htmlspecialchars($row['mesto_troska'] ?? 'Nije dodeljeno') ?></td>
                        <td><?= number_format($row['nabavna_vrednost'], 2, ',', '.') ?> RSD</td>
                        <td><?= htmlspecialchars($row['datum_nabavke']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
