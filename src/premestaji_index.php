<?php
/**
 * premestaji_index.php
 * ---------------------
 * Lista svih dokumenata premeštaja (zaglavlja), sa brojem stavki po
 * dokumentu - isti obrazac kao reversi_index.php.
 */

require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$stmt = $pdo->query(
    "SELECT
        d.*,
        nl.naziv AS nova_lokacija, nmt.naziv AS novo_mesto_troska,
        (SELECT COUNT(*) FROM premestaji_sredstva p WHERE p.dokument_premestaja_id = d.id) AS broj_stavki
     FROM dokumenti_premestaja d
     LEFT JOIN lokacije nl ON nl.id = d.nova_lokacija_id
     LEFT JOIN mesta_troska nmt ON nmt.id = d.novo_mesto_troska_id
     ORDER BY d.datum_premestaja DESC, d.id DESC"
);
$dokumenti = $stmt->fetchAll();

$naslovStranice = 'Istorija premeštaja';
require_once 'header.php';
?>

    <h1>Istorija premeštaja</h1>
    <a href="premestaj_form.php" class="btn-add">+ Novi premeštaj</a>
    <a href="kretanje_index.php" class="btn-cancel" style="margin-left:8px;">Nazad na Evidenciju kretanja</a>

    <table>
        <thead>
            <tr>
                <th>Broj dokumenta</th>
                <th>Datum</th>
                <th>Nova lokacija</th>
                <th>Novo mesto troška</th>
                <th>Broj stavki</th>
                <th>Akcije</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dokumenti)): ?>
                <tr><td colspan="6" style="text-align:center;">Nema evidentiranih premeštaja.</td></tr>
            <?php else: ?>
                <?php foreach ($dokumenti as $d): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($d['broj_dokumenta']) ?></strong></td>
                        <td><?= htmlspecialchars($d['datum_premestaja']) ?></td>
                        <td><?= htmlspecialchars($d['nova_lokacija'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($d['novo_mesto_troska'] ?? '—') ?></td>
                        <td><?= (int)$d['broj_stavki'] ?></td>
                        <td class="akcije">
                            <a href="premestaji_pregled.php?id=<?= $d['id'] ?>">Pregled</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php require_once 'footer.php'; ?>
