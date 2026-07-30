<?php
/**
 * obracun_form.php
 * -----------------
 * Kreira NACRT obračuna amortizacije (status U_PRIPREMI) za izabrani
 * mesec/godinu. Stvarni obračun (izmena knjigovodstvenih vrednosti na
 * sredstvima) izvršava se tek na obracun_pregled.php, akcijom "Izvrši
 * obračun" - isti obrazac kao kod reversa i premeštaja.
 */

require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$poruka = '';

$nazivMeseca = ['', 'Januar', 'Februar', 'Mart', 'April', 'Maj', 'Jun', 'Jul', 'Avgust', 'Septembar', 'Oktobar', 'Novembar', 'Decembar'];

$podaci = [
    'godina' => (int)date('Y'),
    'mesec' => (int)date('n'),
    'napomena' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $podaci['godina'] = (int)($_POST['godina'] ?? 0);
    $podaci['mesec'] = (int)($_POST['mesec'] ?? 0);
    $podaci['napomena'] = trim($_POST['napomena'] ?? '');

    if ($podaci['godina'] < 2000 || $podaci['godina'] > 2100 || $podaci['mesec'] < 1 || $podaci['mesec'] > 12) {
        $poruka = "Unesite ispravnu godinu i mesec.";
    } else {
        $periodOd = sprintf('%04d-%02d-01', $podaci['godina'], $podaci['mesec']);
        $periodDo = date('Y-m-t', strtotime($periodOd));
        $naziv = 'Amortizacija - ' . $nazivMeseca[$podaci['mesec']] . ' ' . $podaci['godina'] . '.';

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO obracuni_amortizacije (naziv, godina, mesec, period_od, period_do, status, napomena)
                 VALUES (:naziv, :godina, :mesec, :period_od, :period_do, 'U_PRIPREMI', :napomena)"
            );
            $stmt->execute([
                ':naziv'     => $naziv,
                ':godina'    => $podaci['godina'],
                ':mesec'     => $podaci['mesec'],
                ':period_od' => $periodOd,
                ':period_do' => $periodDo,
                ':napomena'  => $podaci['napomena'] !== '' ? $podaci['napomena'] : null,
            ]);
            $obracunId = (int)$pdo->lastInsertId();

            header("Location: obracun_pregled.php?id=" . $obracunId);
            exit;
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $poruka = "Obračun za " . $nazivMeseca[$podaci['mesec']] . " " . $podaci['godina'] . ". već postoji.";
            } else {
                $poruka = "Greška pri upisu u bazu: " . $e->getMessage();
            }
        }
    }
}

$naslovStranice = 'Novi obračun amortizacije';
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2>Novi obračun amortizacije</h2>
    <p class="napomena-polje" style="margin-top:-10px; margin-bottom: 20px;">
        Obračun se prvo čuva kao nacrt ("U pripremi") - stvarna izmena knjigovodstvenih vrednosti izvršava se tek kada ga pokrenete na sledećem ekranu.
    </p>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="red-2">
            <div class="form-group">
                <label>Godina *</label>
                <input type="number" name="godina" min="2000" max="2100" required value="<?= htmlspecialchars($podaci['godina']) ?>">
            </div>
            <div class="form-group">
                <label>Mesec *</label>
                <select name="mesec" required>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $podaci['mesec'] === $m ? 'selected' : '' ?>><?= $nazivMeseca[$m] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Napomena</label>
            <textarea name="napomena"><?= htmlspecialchars($podaci['napomena']) ?></textarea>
        </div>

        <button type="submit" class="btn">Sačuvaj nacrt obračuna</button>
        <a href="obracuni_index.php" class="btn-cancel">Otkaži</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
