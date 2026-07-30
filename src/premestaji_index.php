<?php
/**
 * premestaji_index.php
 * ---------------------
 * Hronološka lista svih premeštaja osnovnih sredstava (promena lokacije,
 * mesta troška i/ili zaduženog lica), sa prikazom starog i novog stanja.
 */

require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$stmt = $pdo->query(
    "SELECT
        p.id, p.datum_premestaja, p.napomena,
        os.id AS sredstvo_id, os.inventarski_broj, os.naziv AS naziv_sredstva,
        sl.naziv AS stara_lokacija, nl.naziv AS nova_lokacija,
        smt.naziv AS staro_mesto_troska, nmt.naziv AS novo_mesto_troska,
        CASE WHEN sz.id IS NOT NULL THEN CONCAT(sz.ime, ' ', sz.prezime) ELSE p.staro_odgovorno_lice END AS staro_zaduzeno_lice,
        CASE WHEN nz.id IS NOT NULL THEN CONCAT(nz.ime, ' ', nz.prezime) ELSE p.novo_odgovorno_lice END AS novo_zaduzeno_lice
     FROM premestaji_sredstva p
     JOIN osnovna_sredstva os ON os.id = p.sredstvo_id
     LEFT JOIN lokacije sl ON sl.id = p.stara_lokacija_id
     LEFT JOIN lokacije nl ON nl.id = p.nova_lokacija_id
     LEFT JOIN mesta_troska smt ON smt.id = p.staro_mesto_troska_id
     LEFT JOIN mesta_troska nmt ON nmt.id = p.novo_mesto_troska_id
     LEFT JOIN zaposleni sz ON sz.id = p.stari_zaposleni_id
     LEFT JOIN zaposleni nz ON nz.id = p.novi_zaposleni_id
     ORDER BY p.datum_premestaja DESC, p.id DESC"
);
$premestaji = $stmt->fetchAll();

$naslovStranice = 'Istorija premeštaja';
require_once 'header.php';
?>

    <h1>Istorija premeštaja</h1>
    <a href="premestaj_form.php" class="btn-add">+ Novi premeštaj</a>
    <a href="kretanje_index.php" class="btn-cancel" style="margin-left:8px;">Nazad na Evidenciju kretanja</a>

    <table>
        <thead>
            <tr>
                <th>Datum</th>
                <th>Sredstvo</th>
                <th>Lokacija (staro → novo)</th>
                <th>Mesto troška (staro → novo)</th>
                <th>Zaduženo lice (staro → novo)</th>
                <th>Akcije</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($premestaji)): ?>
                <tr><td colspan="6" style="text-align:center;">Nema evidentiranih premeštaja.</td></tr>
            <?php else: ?>
                <?php foreach ($premestaji as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['datum_premestaja']) ?></td>
                        <td><strong><?= htmlspecialchars($p['inventarski_broj']) ?></strong> — <?= htmlspecialchars($p['naziv_sredstva']) ?></td>
                        <td><?= htmlspecialchars($p['stara_lokacija'] ?? '—') ?> → <?= htmlspecialchars($p['nova_lokacija'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($p['staro_mesto_troska'] ?? '—') ?> → <?= htmlspecialchars($p['novo_mesto_troska'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($p['staro_zaduzeno_lice'] ?? '—') ?> → <?= htmlspecialchars($p['novo_zaduzeno_lice'] ?? '—') ?></td>
                        <td class="akcije">
                            <a href="os_pregled.php?id=<?= $p['sredstvo_id'] ?>">Pregled sredstva</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php require_once 'footer.php'; ?>
