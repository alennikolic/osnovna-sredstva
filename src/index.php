<?php
require_once 'db.php';

// Dohvatanje podataka iz pomoćnog view-a definisanog u SQL šemi
$stmt = $pdo->query("SELECT * FROM pregled_osnovnih_sredstava ORDER BY id DESC");
$sredstva = $stmt->fetchAll();

$naslovStranice = 'Osnovna Sredstva - Pregled';
require_once 'header.php';
?>

    <h1>Evidencija osnovnih sredstava</h1>
    <a href="os_form.php" class="btn-add">+ Novo Osnovno Sredstvo</a>

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
                <th>Akcije</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($sredstva)): ?>
                <tr><td colspan="10" style="text-align:center;">Nema unetih osnovnih sredstava.</td></tr>
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
                        <td class="akcije">
                            <a href="os_pregled.php?id=<?= $row['id'] ?>">Pregled</a>
                            <a href="os_form.php?id=<?= $row['id'] ?>">Izmeni</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php require_once 'footer.php'; ?>
