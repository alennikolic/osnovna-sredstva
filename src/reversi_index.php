<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';
require_once 'helpers.php';

$stmt = $pdo->query(
    "SELECT r.*, CONCAT(z.ime, ' ', z.prezime) AS ime_zaposlenog,
        (SELECT COUNT(*) FROM stavke_reversa sr WHERE sr.revers_id = r.id) AS broj_stavki,
        (SELECT COUNT(*) FROM stavke_reversa sr WHERE sr.revers_id = r.id AND sr.vraceno = 1) AS broj_vracenih
     FROM reversi r
     JOIN zaposleni z ON z.id = r.zaposleni_id
     ORDER BY r.datum_izdavanja DESC, r.id DESC"
);
$reversi = $stmt->fetchAll();

$naslovStranice = 'Reversi';
require_once 'header.php';
?>

    <h1>Reversi</h1>
    <a href="revers_form.php" class="btn-add">+ Novi revers</a>

    <table>
        <thead>
            <tr>
                <th>Broj reversa</th>
                <th>Datum izdavanja</th>
                <th>Zaposleni</th>
                <th>Vraćeno / Ukupno</th>
                <th>Status</th>
                <th>Akcije</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reversi)): ?>
                <tr><td colspan="6" style="text-align:center;">Nema izdatih reversa.</td></tr>
            <?php else: ?>
                <?php foreach ($reversi as $r): ?>
                    <?php [$nazivStatusa, $klasaOznake] = oznakaStatusaReversa($r['status']); ?>
                    <tr class="<?= $r['status'] === 'PONISTEN' ? 'neaktivna-vrsta' : '' ?>">
                        <td><strong><?= htmlspecialchars($r['broj_reversa']) ?></strong></td>
                        <td><?= htmlspecialchars($r['datum_izdavanja']) ?></td>
                        <td><?= htmlspecialchars($r['ime_zaposlenog']) ?></td>
                        <td><?= (int)$r['broj_vracenih'] ?> / <?= (int)$r['broj_stavki'] ?></td>
                        <td><span class="oznaka <?= $klasaOznake ?>"><?= $nazivStatusa ?></span></td>
                        <td class="akcije">
                            <a href="revers_pregled.php?id=<?= $r['id'] ?>">Pregled</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php require_once 'footer.php'; ?>
