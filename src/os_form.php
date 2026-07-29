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
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Novo Osnovno Sredstvo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f6f9; }
        .form-container { background: #fff; padding: 20px; max-width: 500px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="number"], input[type="date"], select { width: 100%; padding: 8px; box-sizing: border-box; }
        .btn { padding: 10px 15px; background: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px; }
        .btn-cancel { background: #6c757d; text-decoration: none; padding: 10px 15px; color: white; border-radius: 4px; display: inline-block; }
        .error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>

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

</body>
</html>
