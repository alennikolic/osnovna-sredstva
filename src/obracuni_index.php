<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$obracuni = $pdo->query("SELECT * FROM obracuni_amortizacije ORDER BY godina DESC, mesec DESC")->fetchAll();

$mapaStatusa = [
    'U_PRIPREMI' => ['U pripremi', 'oznaka-u-toku'],
    'OBRACUNATO' => ['Obračunato', 'oznaka-aktivna'],
    'KNJIZENO'   => ['Knjiženo', 'oznaka-neaktivna'],
    'STORNIRANO' => ['Stornirano', 'oznaka-otkazana'],
];

$nazivMeseca = [1=>'Januar',2=>'Februar',3=>'Mart',4=>'April',5=>'Maj',6=>'Jun',7=>'Jul',8=>'Avgust',9=>'Septembar',10=>'Oktobar',11=>'Novembar',12=>'Decembar'];

$naslovStranice = 'Obračun amortizacije';
require_once 'header.php';
?>

    <h1>Obračun amortizacije</h1>
    <a href="obracun_form.php" class="btn-add">+ Novi obračun</a>

    <table>
        <thead>
            <tr>
                <th>Period</th>
                <th>Naziv</th>
                <th>Ukupan iznos</th>
                <th>Status</th>
                <th>Akcije</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($obracuni)): ?>
                <tr><td colspan="5" style="text-align:center;">Nema evidentiranih obračuna.</td></tr>
            <?php else: ?>
                <?php foreach ($obracuni as $o): ?>
                    <?php [$nazivStatusa, $klasaOznake] = $mapaStatusa[$o['status']] ?? [$o['status'], 'oznaka-neaktivna']; ?>
                    <tr>
                        <td><?= htmlspecialchars($nazivMeseca[(int)$o['mesec']] . ' ' . $o['godina'] . '.') ?></td>
                        <td><?= htmlspecialchars($o['naziv']) ?></td>
                        <td><?= number_format($o['ukupan_iznos_amortizacije'], 2, ',', '.') ?> RSD</td>
                        <td><span class="oznaka <?= $klasaOznake ?>"><?= $nazivStatusa ?></span></td>
                        <td class="akcije">
                            <a href="obracun_pregled.php?id=<?= $o['id'] ?>">Pregled</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php require_once 'footer.php'; ?>
