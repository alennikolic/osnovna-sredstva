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
    'pib' => '',
    'maticni_broj' => '',
    'adresa' => '',
    'grad' => '',
    'kontakt_osoba' => '',
    'kontakt_telefon' => '',
    'kontakt_email' => '',
    'napomena' => '',
    'aktivan' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $podaci['sifra'] = trim($_POST['sifra'] ?? '');
    $podaci['naziv'] = trim($_POST['naziv'] ?? '');
    $podaci['pib'] = trim($_POST['pib'] ?? '');
    $podaci['maticni_broj'] = trim($_POST['maticni_broj'] ?? '');
    $podaci['adresa'] = trim($_POST['adresa'] ?? '');
    $podaci['grad'] = trim($_POST['grad'] ?? '');
    $podaci['kontakt_osoba'] = trim($_POST['kontakt_osoba'] ?? '');
    $podaci['kontakt_telefon'] = trim($_POST['kontakt_telefon'] ?? '');
    $podaci['kontakt_email'] = trim($_POST['kontakt_email'] ?? '');
    $podaci['napomena'] = trim($_POST['napomena'] ?? '');
    $podaci['aktivan'] = isset($_POST['aktivan']) ? 1 : 0;

    if (!empty($podaci['naziv'])) {
        try {
            $parametri = [
                ':sifra'           => $podaci['sifra'] !== '' ? $podaci['sifra'] : null,
                ':naziv'           => $podaci['naziv'],
                ':pib'             => $podaci['pib'] !== '' ? $podaci['pib'] : null,
                ':maticni_broj'    => $podaci['maticni_broj'] !== '' ? $podaci['maticni_broj'] : null,
                ':adresa'          => $podaci['adresa'] !== '' ? $podaci['adresa'] : null,
                ':grad'            => $podaci['grad'] !== '' ? $podaci['grad'] : null,
                ':kontakt_osoba'   => $podaci['kontakt_osoba'] !== '' ? $podaci['kontakt_osoba'] : null,
                ':kontakt_telefon' => $podaci['kontakt_telefon'] !== '' ? $podaci['kontakt_telefon'] : null,
                ':kontakt_email'   => $podaci['kontakt_email'] !== '' ? $podaci['kontakt_email'] : null,
                ':napomena'        => $podaci['napomena'] !== '' ? $podaci['napomena'] : null,
                ':aktivan'         => $podaci['aktivan'],
            ];

            if ($izmena) {
                $sql = "UPDATE dobavljaci SET
                            sifra = :sifra,
                            naziv = :naziv,
                            pib = :pib,
                            maticni_broj = :maticni_broj,
                            adresa = :adresa,
                            grad = :grad,
                            kontakt_osoba = :kontakt_osoba,
                            kontakt_telefon = :kontakt_telefon,
                            kontakt_email = :kontakt_email,
                            napomena = :napomena,
                            aktivan = :aktivan
                        WHERE id = :id";
                $parametri[':id'] = $id;
            } else {
                $sql = "INSERT INTO dobavljaci
                            (sifra, naziv, pib, maticni_broj, adresa, grad, kontakt_osoba,
                             kontakt_telefon, kontakt_email, napomena, aktivan)
                        VALUES
                            (:sifra, :naziv, :pib, :maticni_broj, :adresa, :grad, :kontakt_osoba,
                             :kontakt_telefon, :kontakt_email, :napomena, :aktivan)";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($parametri);

            header("Location: dobavljaci_index.php");
            exit;
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $poruka = "Dobavljač sa PIB-om \"{$podaci['pib']}\" već postoji.";
            } else {
                $poruka = "Greška pri upisu u bazu: " . $e->getMessage();
            }
        }
    } else {
        $poruka = "Naziv dobavljača je obavezan!";
    }
} elseif ($izmena) {
    $stmt = $pdo->prepare("SELECT * FROM dobavljaci WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $postojeci = $stmt->fetch();

    if (!$postojeci) {
        header("Location: dobavljaci_index.php");
        exit;
    }

    $podaci['sifra'] = $postojeci['sifra'] ?? '';
    $podaci['naziv'] = $postojeci['naziv'];
    $podaci['pib'] = $postojeci['pib'] ?? '';
    $podaci['maticni_broj'] = $postojeci['maticni_broj'] ?? '';
    $podaci['adresa'] = $postojeci['adresa'] ?? '';
    $podaci['grad'] = $postojeci['grad'] ?? '';
    $podaci['kontakt_osoba'] = $postojeci['kontakt_osoba'] ?? '';
    $podaci['kontakt_telefon'] = $postojeci['kontakt_telefon'] ?? '';
    $podaci['kontakt_email'] = $postojeci['kontakt_email'] ?? '';
    $podaci['napomena'] = $postojeci['napomena'] ?? '';
    $podaci['aktivan'] = (int)$postojeci['aktivan'];
}

$naslovStranice = $izmena ? 'Izmena dobavljača' : 'Novi dobavljač';
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2><?= $izmena ? 'Izmena dobavljača: ' . htmlspecialchars($podaci['naziv']) : 'Novi dobavljač' ?></h2>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php if ($izmena): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>

        <div class="red-2">
            <div class="form-group">
                <label>Šifra</label>
                <input type="text" name="sifra" maxlength="20" value="<?= htmlspecialchars($podaci['sifra']) ?>">
            </div>
            <div class="form-group">
                <label>Naziv *</label>
                <input type="text" name="naziv" maxlength="200" required value="<?= htmlspecialchars($podaci['naziv']) ?>">
            </div>
        </div>

        <div class="red-2">
            <div class="form-group">
                <label>PIB</label>
                <input type="text" name="pib" maxlength="20" value="<?= htmlspecialchars($podaci['pib']) ?>">
            </div>
            <div class="form-group">
                <label>Matični broj</label>
                <input type="text" name="maticni_broj" maxlength="20" value="<?= htmlspecialchars($podaci['maticni_broj']) ?>">
            </div>
        </div>

        <div class="red-2">
            <div class="form-group">
                <label>Adresa</label>
                <input type="text" name="adresa" maxlength="255" value="<?= htmlspecialchars($podaci['adresa']) ?>">
            </div>
            <div class="form-group">
                <label>Grad</label>
                <input type="text" name="grad" maxlength="100" value="<?= htmlspecialchars($podaci['grad']) ?>">
            </div>
        </div>

        <div class="naslov-podsekcije">Kontakt</div>
        <div class="red-2">
            <div class="form-group">
                <label>Kontakt osoba</label>
                <input type="text" name="kontakt_osoba" maxlength="150" value="<?= htmlspecialchars($podaci['kontakt_osoba']) ?>">
            </div>
            <div class="form-group">
                <label>Telefon</label>
                <input type="text" name="kontakt_telefon" maxlength="50" value="<?= htmlspecialchars($podaci['kontakt_telefon']) ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="kontakt_email" maxlength="100" value="<?= htmlspecialchars($podaci['kontakt_email']) ?>">
        </div>

        <div class="form-group">
            <label>Napomena</label>
            <textarea name="napomena"><?= htmlspecialchars($podaci['napomena']) ?></textarea>
        </div>

        <div class="form-group checkbox-group">
            <input type="checkbox" name="aktivan" id="aktivan" <?= $podaci['aktivan'] ? 'checked' : '' ?>>
            <label for="aktivan">Dobavljač je aktivan</label>
        </div>

        <button type="submit" class="btn">Sačuvaj</button>
        <a href="dobavljaci_index.php" class="btn-cancel">Otkaži</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
