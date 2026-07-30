<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$poruka = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
} else {
    $id = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : null;
}
$izmena = !empty($id);

$podaci = [
    'sifra' => '',
    'naziv' => '',
    'godisnja_stopa_procenat' => '',
    'vek_trajanja_godine' => '',
    'opis' => '',
    'aktivna' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $podaci['sifra'] = trim($_POST['sifra'] ?? '');
    $podaci['naziv'] = trim($_POST['naziv'] ?? '');
    $podaci['godisnja_stopa_procenat'] = trim($_POST['godisnja_stopa_procenat'] ?? '');
    $podaci['vek_trajanja_godine'] = trim($_POST['vek_trajanja_godine'] ?? '');
    $podaci['opis'] = trim($_POST['opis'] ?? '');
    $podaci['aktivna'] = isset($_POST['aktivna']) ? 1 : 0;

    if (!empty($podaci['sifra']) && !empty($podaci['naziv'])) {
        try {
            $parametri = [
                ':sifra'   => $podaci['sifra'],
                ':naziv'   => $podaci['naziv'],
                ':stopa'   => $podaci['godisnja_stopa_procenat'] !== '' ? $podaci['godisnja_stopa_procenat'] : null,
                ':vek'     => $podaci['vek_trajanja_godine'] !== '' ? $podaci['vek_trajanja_godine'] : null,
                ':opis'    => $podaci['opis'] !== '' ? $podaci['opis'] : null,
                ':aktivna' => $podaci['aktivna'],
            ];

            if ($izmena) {
                $sql = "UPDATE amortizacione_grupe SET
                            sifra = :sifra, naziv = :naziv, godisnja_stopa_procenat = :stopa,
                            vek_trajanja_godine = :vek, opis = :opis, aktivna = :aktivna
                        WHERE id = :id";
                $parametri[':id'] = $id;
            } else {
                $sql = "INSERT INTO amortizacione_grupe
                            (sifra, naziv, godisnja_stopa_procenat, vek_trajanja_godine, opis, aktivna)
                        VALUES
                            (:sifra, :naziv, :stopa, :vek, :opis, :aktivna)";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($parametri);

            header("Location: amortizacione_grupe_index.php");
            exit;
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $poruka = "Amortizaciona grupa sa šifrom \"{$podaci['sifra']}\" već postoji.";
            } else {
                $poruka = "Greška pri upisu u bazu: " . $e->getMessage();
            }
        }
    } else {
        $poruka = "Šifra i naziv su obavezni!";
    }
} elseif ($izmena) {
    $stmt = $pdo->prepare("SELECT * FROM amortizacione_grupe WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $postojeca = $stmt->fetch();

    if (!$postojeca) {
        header("Location: amortizacione_grupe_index.php");
        exit;
    }

    $podaci['sifra'] = $postojeca['sifra'];
    $podaci['naziv'] = $postojeca['naziv'];
    $podaci['godisnja_stopa_procenat'] = $postojeca['godisnja_stopa_procenat'] ?? '';
    $podaci['vek_trajanja_godine'] = $postojeca['vek_trajanja_godine'] ?? '';
    $podaci['opis'] = $postojeca['opis'] ?? '';
    $podaci['aktivna'] = (int)$postojeca['aktivna'];
}

$naslovStranice = $izmena ? 'Izmena amortizacione grupe' : 'Nova amortizaciona grupa';
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2><?= $izmena ? 'Izmena amortizacione grupe: ' . htmlspecialchars($podaci['naziv']) : 'Nova amortizaciona grupa' ?></h2>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php if ($izmena): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>

        <div class="red-2">
            <div class="form-group">
                <label>Šifra * <span class="napomena-polje">(npr. I, II, III, IV, V)</span></label>
                <input type="text" name="sifra" maxlength="10" required value="<?= htmlspecialchars($podaci['sifra']) ?>">
            </div>
            <div class="form-group">
                <label>Naziv *</label>
                <input type="text" name="naziv" maxlength="150" required value="<?= htmlspecialchars($podaci['naziv']) ?>">
            </div>
        </div>

        <div class="red-2">
            <div class="form-group">
                <label>Godišnja stopa amortizacije (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="godisnja_stopa_procenat" value="<?= htmlspecialchars($podaci['godisnja_stopa_procenat']) ?>">
            </div>
            <div class="form-group">
                <label>Vek trajanja (godine)</label>
                <input type="number" step="0.01" min="0" name="vek_trajanja_godine" value="<?= htmlspecialchars($podaci['vek_trajanja_godine']) ?>">
            </div>
        </div>
        <p class="napomena-polje" style="margin-top:-10px; margin-bottom: 15px;">
            Popunite u skladu sa važećim poreskim propisom ili internim pravilnikom - obračun amortizacije koristi vek trajanja za linearnu metodu.
        </p>

        <div class="form-group">
            <label>Opis</label>
            <textarea name="opis"><?= htmlspecialchars($podaci['opis']) ?></textarea>
        </div>

        <div class="form-group checkbox-group">
            <input type="checkbox" name="aktivna" id="aktivna" <?= $podaci['aktivna'] ? 'checked' : '' ?>>
            <label for="aktivna">Grupa je aktivna</label>
        </div>

        <button type="submit" class="btn">Sačuvaj</button>
        <a href="amortizacione_grupe_index.php" class="btn-cancel">Otkaži</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
