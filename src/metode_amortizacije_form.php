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
    'tip_obracuna' => 'LINEARNA',
    'opis' => '',
    'aktivna' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $podaci['sifra'] = trim($_POST['sifra'] ?? '');
    $podaci['naziv'] = trim($_POST['naziv'] ?? '');
    $podaci['tip_obracuna'] = $_POST['tip_obracuna'] ?? 'LINEARNA';
    $podaci['opis'] = trim($_POST['opis'] ?? '');
    $podaci['aktivna'] = isset($_POST['aktivna']) ? 1 : 0;

    if (!empty($podaci['sifra']) && !empty($podaci['naziv'])) {
        try {
            $parametri = [
                ':sifra'   => $podaci['sifra'],
                ':naziv'   => $podaci['naziv'],
                ':tip'     => $podaci['tip_obracuna'],
                ':opis'    => $podaci['opis'] !== '' ? $podaci['opis'] : null,
                ':aktivna' => $podaci['aktivna'],
            ];

            if ($izmena) {
                $sql = "UPDATE metode_amortizacije SET
                            sifra = :sifra, naziv = :naziv, tip_obracuna = :tip,
                            opis = :opis, aktivna = :aktivna
                        WHERE id = :id";
                $parametri[':id'] = $id;
            } else {
                $sql = "INSERT INTO metode_amortizacije (sifra, naziv, tip_obracuna, opis, aktivna)
                        VALUES (:sifra, :naziv, :tip, :opis, :aktivna)";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($parametri);

            header("Location: metode_amortizacije_index.php");
            exit;
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $poruka = "Metoda sa šifrom \"{$podaci['sifra']}\" već postoji.";
            } else {
                $poruka = "Greška pri upisu u bazu: " . $e->getMessage();
            }
        }
    } else {
        $poruka = "Šifra i naziv su obavezni!";
    }
} elseif ($izmena) {
    $stmt = $pdo->prepare("SELECT * FROM metode_amortizacije WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $postojeca = $stmt->fetch();

    if (!$postojeca) {
        header("Location: metode_amortizacije_index.php");
        exit;
    }

    $podaci['sifra'] = $postojeca['sifra'];
    $podaci['naziv'] = $postojeca['naziv'];
    $podaci['tip_obracuna'] = $postojeca['tip_obracuna'];
    $podaci['opis'] = $postojeca['opis'] ?? '';
    $podaci['aktivna'] = (int)$postojeca['aktivna'];
}

$tipoviObracuna = [
    'LINEARNA'          => 'Linearna',
    'DEGRESIVNA_DUPLA'  => 'Degresivna (dupla)',
    'SUMA_GODINA'       => 'Suma godina',
    'FUNKCIONALNA'      => 'Funkcionalna',
    'BEZ_AMORTIZACIJE'  => 'Bez amortizacije',
];

$naslovStranice = $izmena ? 'Izmena metode amortizacije' : 'Nova metoda amortizacije';
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2><?= $izmena ? 'Izmena metode: ' . htmlspecialchars($podaci['naziv']) : 'Nova metoda amortizacije' ?></h2>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php if ($izmena): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>

        <div class="red-2">
            <div class="form-group">
                <label>Šifra *</label>
                <input type="text" name="sifra" maxlength="30" required value="<?= htmlspecialchars($podaci['sifra']) ?>">
            </div>
            <div class="form-group">
                <label>Naziv *</label>
                <input type="text" name="naziv" maxlength="150" required value="<?= htmlspecialchars($podaci['naziv']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Tip obračuna * <span class="napomena-polje">(algoritam koji obračun koristi)</span></label>
            <select name="tip_obracuna" required>
                <?php foreach ($tipoviObracuna as $sifra => $naziv): ?>
                    <option value="<?= $sifra ?>" <?= $podaci['tip_obracuna'] === $sifra ? 'selected' : '' ?>><?= htmlspecialchars($naziv) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="napomena-polje" style="margin-top:6px;">
                Obračun trenutno stvarno računa samo za tip "Linearna" - ostali tipovi se evidentiraju, ali se sredstva sa njima preskaču pri obračunu (planirano za budući razvoj).
            </p>
        </div>

        <div class="form-group">
            <label>Opis</label>
            <textarea name="opis"><?= htmlspecialchars($podaci['opis']) ?></textarea>
        </div>

        <div class="form-group checkbox-group">
            <input type="checkbox" name="aktivna" id="aktivna" <?= $podaci['aktivna'] ? 'checked' : '' ?>>
            <label for="aktivna">Metoda je aktivna</label>
        </div>

        <button type="submit" class="btn">Sačuvaj</button>
        <a href="metode_amortizacije_index.php" class="btn-cancel">Otkaži</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
