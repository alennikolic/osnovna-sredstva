<?php
require_once 'db.php';

$poruka = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inventarski_broj = trim($_POST['inventarski_broj']);
    $naziv = trim($_POST['naziv']);
    $klasa_id = $_POST['klasa_id'];
    $status_id = $_POST['status_id'];
    $nabavna_vrednost = $_POST['nabavna_vrednost'];
    $datum_nabavke = $_POST['datum_nabavke'];
    $odgovorno_lice = trim($_POST['odgovorno_lice']);

    if (!empty($inventarski_broj) && !empty($naziv) && !empty($klasa_id) && !empty($status_id)) {
        try {
            $sql = "INSERT INTO osnovna_sredstva 
                    (inventarski_broj, naziv, klasa_id, status_id, nabavna_vrednost, osnovica_za_amortizaciju, sadasnja_knjigovodstvena_vrednost, datum_nabavke, odgovorno_lice) 
                    VALUES (:inv, :naziv, :klasa, :status, :vrednost1, :vrednost2, :vrednost3, :datum, :lice)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':inv'       => $inventarski_broj,
                ':naziv'     => $naziv,
                ':klasa'     => $klasa_id,
                ':status'    => $status_id,
                ':vrednost1' => $nabavna_vrednost,
                ':vrednost2' => $nabavna_vrednost,
                ':vrednost3' => $nabavna_vrednost,
                ':datum'     => $datum_nabavke,
                ':lice'      => $odgovorno_lice
            ]);

            header("Location: index.php");
            exit;
        } catch (\PDOException $e) {
            $poruka = "Greška pri upisu u bazu: " . $e->getMessage();
        }
    } else {
        $poruka = "Molimo popunite sva obavezna polja!";
    }
}

// Učitavanje šifarnika iz baze za padajuće menije
$klase = $pdo->query("SELECT id, naziv FROM klase_osnovnih_sredstava WHERE aktivna = 1")->fetchAll();
$statusi = $pdo->query("SELECT id, naziv FROM statusi_sredstva ORDER BY redosled_prikaza")->fetchAll();

$naslovStranice = 'Novo Osnovno Sredstvo';
require_once 'header.php';
?>

<div class="form-container">
    <h2>Unos novog osnovnog sredstva</h2>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>Inventarski broj *</label>
            <input type="text" name="inventarski_broj" required>
        </div>

        <div class="form-group">
            <label>Naziv sredstva *</label>
            <input type="text" name="naziv" required>
        </div>

        <div class="form-group">
            <label>Klasa sredstva *</label>
            <select name="klasa_id" required>
                <option value="">-- Izaberite klasu --</option>
                <?php foreach ($klase as $k): ?>
                    <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['naziv']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Status *</label>
            <select name="status_id" required>
                <?php foreach ($statusi as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['naziv']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Nabavna vrednost (RSD) *</label>
            <input type="number" step="0.01" name="nabavna_vrednost" value="0.00" required>
        </div>

        <div class="form-group">
            <label>Datum nabavke *</label>
            <input type="date" name="datum_nabavke" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="form-group">
            <label>Odgovorno lice</label>
            <input type="text" name="odgovorno_lice">
        </div>

        <button type="submit" class="btn">Sačuvaj</button>
        <a href="index.php" class="btn-cancel">Otkaži</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
