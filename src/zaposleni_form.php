<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$poruka = '';

// Kod GET zahteva ID dolazi iz query stringa (klik na "Izmeni"), kod POST
// zahteva iz skrivenog polja forme - isti obrazac kao kod klase_form.php.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
} else {
    $id = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : null;
}
$izmena = !empty($id);

// Podrazumevane (prazne) vrednosti forme
$podaci = [
    'sifra' => '',
    'ime' => '',
    'prezime' => '',
    'radno_mesto' => '',
    'mesto_troska_id' => '',
    'lokacija_id' => '',
    'email' => '',
    'telefon' => '',
    'datum_zaposlenja' => '',
    'datum_prestanka' => '',
    'napomena' => '',
    'aktivan' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $podaci['sifra'] = trim($_POST['sifra'] ?? '');
    $podaci['ime'] = trim($_POST['ime'] ?? '');
    $podaci['prezime'] = trim($_POST['prezime'] ?? '');
    $podaci['radno_mesto'] = trim($_POST['radno_mesto'] ?? '');
    $podaci['mesto_troska_id'] = $_POST['mesto_troska_id'] ?? '';
    $podaci['lokacija_id'] = $_POST['lokacija_id'] ?? '';
    $podaci['email'] = trim($_POST['email'] ?? '');
    $podaci['telefon'] = trim($_POST['telefon'] ?? '');
    $podaci['datum_zaposlenja'] = trim($_POST['datum_zaposlenja'] ?? '');
    $podaci['datum_prestanka'] = trim($_POST['datum_prestanka'] ?? '');
    $podaci['napomena'] = trim($_POST['napomena'] ?? '');
    $podaci['aktivan'] = isset($_POST['aktivan']) ? 1 : 0;

    if ($podaci['ime'] === '' || $podaci['prezime'] === '') {
        $poruka = "Ime i prezime su obavezni!";
    } else {
        try {
            $parametri = [
                ':sifra'            => $podaci['sifra'] !== '' ? $podaci['sifra'] : null,
                ':ime'              => $podaci['ime'],
                ':prezime'          => $podaci['prezime'],
                ':radno_mesto'      => $podaci['radno_mesto'] !== '' ? $podaci['radno_mesto'] : null,
                ':mesto_troska'     => $podaci['mesto_troska_id'] !== '' ? (int)$podaci['mesto_troska_id'] : null,
                ':lokacija'         => $podaci['lokacija_id'] !== '' ? (int)$podaci['lokacija_id'] : null,
                ':email'            => $podaci['email'] !== '' ? $podaci['email'] : null,
                ':telefon'          => $podaci['telefon'] !== '' ? $podaci['telefon'] : null,
                ':datum_zaposlenja' => $podaci['datum_zaposlenja'] !== '' ? $podaci['datum_zaposlenja'] : null,
                ':datum_prestanka'  => $podaci['datum_prestanka'] !== '' ? $podaci['datum_prestanka'] : null,
                ':napomena'         => $podaci['napomena'] !== '' ? $podaci['napomena'] : null,
                ':aktivan'          => $podaci['aktivan'],
            ];

            if ($izmena) {
                $sql = "UPDATE zaposleni SET
                            sifra = :sifra,
                            ime = :ime,
                            prezime = :prezime,
                            radno_mesto = :radno_mesto,
                            mesto_troska_id = :mesto_troska,
                            lokacija_id = :lokacija,
                            email = :email,
                            telefon = :telefon,
                            datum_zaposlenja = :datum_zaposlenja,
                            datum_prestanka = :datum_prestanka,
                            napomena = :napomena,
                            aktivan = :aktivan
                        WHERE id = :id";
                $parametri[':id'] = $id;
            } else {
                $sql = "INSERT INTO zaposleni
                            (sifra, ime, prezime, radno_mesto, mesto_troska_id, lokacija_id, email, telefon,
                             datum_zaposlenja, datum_prestanka, napomena, aktivan)
                        VALUES
                            (:sifra, :ime, :prezime, :radno_mesto, :mesto_troska, :lokacija, :email, :telefon,
                             :datum_zaposlenja, :datum_prestanka, :napomena, :aktivan)";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($parametri);

            header("Location: zaposleni_index.php");
            exit;
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $poruka = "Zaposleni sa šifrom \"{$podaci['sifra']}\" već postoji. Izaberite drugu šifru.";
            } else {
                $poruka = "Greška pri upisu u bazu: " . $e->getMessage();
            }
        }
    }
} elseif ($izmena) {
    // Obični GET zahtev za izmenu (klik na "Izmeni") - učitaj postojeće podatke
    $stmt = $pdo->prepare("SELECT * FROM zaposleni WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $postojeci = $stmt->fetch();

    if (!$postojeci) {
        header("Location: zaposleni_index.php");
        exit;
    }

    $podaci['sifra'] = $postojeci['sifra'] ?? '';
    $podaci['ime'] = $postojeci['ime'];
    $podaci['prezime'] = $postojeci['prezime'];
    $podaci['radno_mesto'] = $postojeci['radno_mesto'] ?? '';
    $podaci['mesto_troska_id'] = $postojeci['mesto_troska_id'] ?? '';
    $podaci['lokacija_id'] = $postojeci['lokacija_id'] ?? '';
    $podaci['email'] = $postojeci['email'] ?? '';
    $podaci['telefon'] = $postojeci['telefon'] ?? '';
    $podaci['datum_zaposlenja'] = $postojeci['datum_zaposlenja'] ?? '';
    $podaci['datum_prestanka'] = $postojeci['datum_prestanka'] ?? '';
    $podaci['napomena'] = $postojeci['napomena'] ?? '';
    $podaci['aktivan'] = (int)$postojeci['aktivan'];
}

// Učitavanje šifarnika za padajuće menije.
// NAPOMENA: mesta_troska i lokacije trenutno nemaju svoj CRUD modul (samo
// šema postoji), pa će ove liste za sada biti prazne - polja su opciona,
// tako da to ne sprečava unos zaposlenog.
$mestaTroska = $pdo->query("SELECT id, naziv FROM mesta_troska WHERE aktivno = 1 ORDER BY naziv")->fetchAll();
$lokacije = $pdo->query("SELECT id, naziv FROM lokacije WHERE aktivna = 1 ORDER BY naziv")->fetchAll();

$naslovStranice = $izmena ? 'Izmena zaposlenog' : 'Novi zaposleni';
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2><?= $izmena ? 'Izmena zaposlenog: ' . htmlspecialchars($podaci['ime'] . ' ' . $podaci['prezime']) : 'Novi zaposleni' ?></h2>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php if ($izmena): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>

        <div class="red-2">
            <div class="form-group">
                <label>Ime *</label>
                <input type="text" name="ime" maxlength="100" required value="<?= htmlspecialchars($podaci['ime']) ?>">
            </div>
            <div class="form-group">
                <label>Prezime *</label>
                <input type="text" name="prezime" maxlength="100" required value="<?= htmlspecialchars($podaci['prezime']) ?>">
            </div>
        </div>

        <div class="red-2">
            <div class="form-group">
                <label>Interna šifra <span class="napomena-polje">(opciono)</span></label>
                <input type="text" name="sifra" maxlength="20" value="<?= htmlspecialchars($podaci['sifra']) ?>">
            </div>
            <div class="form-group">
                <label>Radno mesto</label>
                <input type="text" name="radno_mesto" maxlength="150" value="<?= htmlspecialchars($podaci['radno_mesto']) ?>">
            </div>
        </div>

        <div class="red-2">
            <div class="form-group">
                <label>Mesto troška</label>
                <select name="mesto_troska_id">
                    <option value="">-- Nije dodeljeno --</option>
                    <?php foreach ($mestaTroska as $mt): ?>
                        <option value="<?= $mt['id'] ?>" <?= (string)$podaci['mesto_troska_id'] === (string)$mt['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mt['naziv']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Lokacija</label>
                <select name="lokacija_id">
                    <option value="">-- Nije dodeljeno --</option>
                    <?php foreach ($lokacije as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= (string)$podaci['lokacija_id'] === (string)$l['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($l['naziv']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="red-2">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" maxlength="150" value="<?= htmlspecialchars($podaci['email']) ?>">
            </div>
            <div class="form-group">
                <label>Telefon</label>
                <input type="text" name="telefon" maxlength="50" value="<?= htmlspecialchars($podaci['telefon']) ?>">
            </div>
        </div>

        <div class="red-2">
            <div class="form-group">
                <label>Datum zaposlenja</label>
                <input type="date" name="datum_zaposlenja" value="<?= htmlspecialchars($podaci['datum_zaposlenja']) ?>">
            </div>
            <div class="form-group">
                <label>Datum prestanka <span class="napomena-polje">(ostavite prazno ako je i dalje zaposlen)</span></label>
                <input type="date" name="datum_prestanka" value="<?= htmlspecialchars($podaci['datum_prestanka']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Napomena</label>
            <textarea name="napomena"><?= htmlspecialchars($podaci['napomena']) ?></textarea>
        </div>

        <div class="form-group checkbox-group">
            <input type="checkbox" name="aktivan" id="aktivan" <?= $podaci['aktivan'] ? 'checked' : '' ?>>
            <label for="aktivan">Zaposleni je aktivan</label>
        </div>

        <button type="submit" class="btn">Sačuvaj</button>
        <a href="zaposleni_index.php" class="btn-cancel">Otkaži</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
