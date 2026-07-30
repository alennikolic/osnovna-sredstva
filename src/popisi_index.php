<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';
require_once 'helpers.php';

$stmt = $pdo->query(
    "SELECT p.*,
        (SELECT COUNT(*) FROM stavke_popisa sp WHERE sp.popis_id = p.id) AS broj_stavki,
        (SELECT COUNT(*) FROM stavke_popisa sp WHERE sp.popis_id = p.id AND sp.popisano_stanje = 'PRONADJENO') AS broj_pronadjenih
     FROM popisi_osnovnih_sredstava p
     ORDER BY p.datum_od DESC, p.id DESC"
);
$popisi = $stmt->fetchAll();

$naslovStranice = 'Popisi osnovnih sredstava';
require_once 'header.php';
?>

    <h1>Popisi osnovnih sredstava</h1>
    <a href="popisi_form.php" class="btn-add">+ Novi popis</a>

    <table>
        <thead>
            <tr>
                <th>Naziv</th>
                <th>Period</th>
                <th>Status</th>
                <th>Napredak</th>
                <th>Predsednik komisije</th>
                <th>Akcije</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($popisi)): ?>
                <tr><td colspan="6" style="text-align:center;">Nema unetih popisa.</td></tr>
            <?php else: ?>
                <?php foreach ($popisi as $p): ?>
                    <?php [$nazivStatusa, $klasaOznake] = oznakaStatusaPopisa($p['status']); ?>
                    <tr>
                        <td><?= htmlspecialchars($p['naziv']) ?></td>
                        <td>
                            <?= htmlspecialchars($p['datum_od']) ?><?= $p['datum_do'] ? ' – ' . htmlspecialchars($p['datum_do']) : '' ?>
                        </td>
                        <td><span class="oznaka <?= $klasaOznake ?>"><?= $nazivStatusa ?></span></td>
                        <td><?= (int)$p['broj_pronadjenih'] ?> / <?= (int)$p['broj_stavki'] ?></td>
                        <td><?= htmlspecialchars($p['predsednik_komisije'] ?? '—') ?></td>
                        <td class="akcije">
                            <a href="popis_pregled.php?id=<?= $p['id'] ?>">Pregled</a>
                            <a href="popisi_form.php?id=<?= $p['id'] ?>">Izmeni</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php require_once 'footer.php'; ?>
