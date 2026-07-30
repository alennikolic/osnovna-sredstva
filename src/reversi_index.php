<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$stmt = $pdo->query(
    "SELECT r.*, CONCAT(z.ime, ' ', z.prezime) AS ime_zaposlenog,
        (SELECT COUNT(*) FROM stavke_reversa sr WHERE sr.revers_id = r.id) AS broj_stavki
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
                <th>Broj stavki</th>
                <th>Status</th>
                <th>Akcije</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reversi)): ?>
                <tr><td colspan="6" style="text-align:center;">Nema izdatih reversa.</td></tr>
            <?php else: ?>
                <?php foreach ($reversi as $r): ?>
                    <tr class="<?= $r['status'] === 'PONISTEN' ? 'neaktivna-vrsta' : '' ?>">
                        <td><strong><?= htmlspecialchars($r['broj_reversa']) ?></strong></td>
                        <td><?= htmlspecialchars($r['datum_izdavanja']) ?></td>
                        <td><?= htmlspecialchars($r['ime_zaposlenog']) ?></td>
                        <td><?= (int)$r['broj_stavki'] ?></td>
                        <td>
                            <?php if ($r['status'] === 'IZDAT'): ?>
                                <span class="oznaka oznaka-aktivna">Izdat</span>
                            <?php else: ?>
                                <span class="oznaka oznaka-otkazana">Poništen</span>
                            <?php endif; ?>
                        </td>
                        <td class="akcije">
                            <a href="revers_pregled.php?id=<?= $r['id'] ?>">Pregled</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php require_once 'footer.php'; ?>
