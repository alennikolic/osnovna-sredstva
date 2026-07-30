<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';
require_once 'helpers.php';

$poruka = '';

// Kod GET zahteva ID dolazi iz query stringa, kod POST iz skrivenog polja
// forme - isti obrazac kao kod klase_form.php.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
} else {
    $id = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : null;
}
$izmena = !empty($id);

$podaci = [
    'sifra' => '',
    'naziv' => '',
    'adresa' => '',
    'grad' => '',
    'nadredjena_lokacija_id' => '',
    'napomena' => '',
    'aktivna' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $podaci['sifra'] = trim($_POST['sifra'] ?? '');
    $podaci['naziv'] = trim($_POST['naziv'] ?? '');
    $podaci['adresa'] = trim($_POST['adresa'] ?? '');
    $podaci['grad'] = trim($_POST['grad'] ?? '');
    $podaci['nadredjena_lokacija_id'] = $_POST['nadredjena_lokacija_id'] ?? '';
    $podaci['napomena'] = trim($_POST['napomena'] ?? '');
    $podaci['aktivna'] = isset($_POST['aktivna']) ? 1 : 0;

    if ($podaci['sifra'] === '' || $podaci['naziv'] === '') {
        $poruka = "Šifra i naziv lokacije su obavezni!";
    } else {
        try {
            $parametri = [
                ':sifra'      => $podaci['sifra'],
                ':naziv'      => $podaci['naziv'],
                ':adresa'     => $podaci['adresa'] !== '' ? $podaci['adresa'] : null,
                ':grad'       => $podaci['grad'] !== '' ? $podaci['grad'] : null,
                ':nadredjena' => $podaci['nadredjena_lokacija_id'] !== '' ? (int)$podaci['nadredjena_lokacija_id'] : null,
                ':napomena'   => $podaci['napomena'] !== '' ? $podaci['napomena'] : null,
                ':aktivna'    => $podaci['aktivna'],
            ];

            if ($izmena) {
                // Zaštita: lokacija ne sme biti nadređena sama sebi ili svojoj podlokaciji
                if ($parametri[':nadredjena'] !== null) {
                    $sveLokacijeOsnovno = $pdo->query("SELECT id, nadredjena_lokacija_id FROM lokacije")->fetchAll();
                    $potomci = pronadjiPotomkeLokacije($sveLokacijeOsnovno, $id);
                    if ($parametri[':nadredjena'] === $id || in_array($parametri[':nadredjena'], $potomci, true)) {
                        throw new \RuntimeException("Lokacija ne može biti nadređena sama sebi ili svojoj podlokaciji.");
                    }
                }

                $sql = "UPDATE lokacije SET
                            sifra = :sifra,
                            naziv = :naziv,
                            adresa = :adresa,
                            grad = :grad,
                            nadredjena_lokacija_id = :nadredjena,
                            napomena = :napomena,
                            aktivna = :aktivna
                        WHERE id = :id";
                $parametri[':id'] = $id;
            } else {
                $sql = "INSERT INTO lokacije
                            (sifra, naziv, adresa, grad, nadredjena_lokacija_id, napomena, aktivna)
                        VALUES
                            (:sifra, :naziv, :adresa, :grad, :nadredjena, :napomena, :aktivna)";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($parametri);

            header("Location: lokacije_index.php");
            exit;
        } catch (\RuntimeException $e) {
            $poruka = $e->getMessage();
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $poruka = "Lokacija sa šifrom \"{$podaci['sifra']}\" već postoji. Izaberite drugu šifru.";
            } else {
                $poruka = "Greška pri upisu u bazu: " . $e->getMessage();
            }
        }
    }
} elseif ($izmena) {
    $stmt = $pdo->prepare("SELECT * FROM lokacije WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $postojeca = $stmt->fetch();

    if (!$postojeca) {
        header("Location: lokacije_index.php");
        exit;
    }

    $podaci['sifra'] = $postojeca['sifra'];
    $podaci['naziv'] = $postojeca['naziv'];
    $podaci['adresa'] = $postojeca['adresa'] ?? '';
    $podaci['grad'] = $postojeca['grad'] ?? '';
    $podaci['nadredjena_lokacija_id'] = $postojeca['nadredjena_lokacija_id'] ?? '';
    $podaci['napomena'] = $postojeca['napomena'] ?? '';
    $podaci['aktivna'] = (int)$postojeca['aktivna'];
}

// Učitavanje lokacija za padajući meni (isključujemo sebe i svoje potomke kod izmene)
$sveLokacijeZaSelect = ucitajLokacijeHijerarhijski($pdo);

$iskljuceniIzListe = [];
if ($izmena) {
    $iskljuceniIzListe[] = $id;
    $sveLokacijeOsnovno = $pdo->query("SELECT id, nadredjena_lokacija_id FROM lokacije")->fetchAll();
    $iskljuceniIzListe = array_merge($iskljuceniIzListe, pronadjiPotomkeLokacije($sveLokacijeOsnovno, $id));
}

$naslovStranice = $izmena ? 'Izmena lokacije' : 'Nova lokacija';
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2><?= $izmena ? 'Izmena lokacije: ' . htmlspecialchars($podaci['naziv']) : 'Nova lokacija' ?></h2>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php if ($izmena): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>

        <div class="red-2">
            <div class="form-group">
                <label>Šifra lokacije *</label>
                <input type="text" name="sifra" maxlength="20" required value="<?= htmlspecialchars($podaci['sifra']) ?>">
            </div>
            <div class="form-group">
                <label>Naziv lokacije *</label>
                <input type="text" name="naziv" maxlength="150" required value="<?= htmlspecialchars($podaci['naziv']) ?>">
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

        <div class="form-group">
            <label>Nadređena lokacija <span class="napomena-polje">(npr. sprat/prostorija - ostavite prazno za objekat/zgradu)</span></label>
            <select name="nadredjena_lokacija_id">
                <option value="">-- Nema (korenska lokacija) --</option>
                <?php foreach ($sveLokacijeZaSelect as $l): ?>
                    <?php if (in_array((int)$l['id'], $iskljuceniIzListe, true)) continue; ?>
                    <option value="<?= $l['id'] ?>" <?= (string)$podaci['nadredjena_lokacija_id'] === (string)$l['id'] ? 'selected' : '' ?>>
                        <?= str_repeat('— ', $l['nivo']) . htmlspecialchars($l['sifra'] . ' - ' . $l['naziv']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Napomena</label>
            <textarea name="napomena"><?= htmlspecialchars($podaci['napomena']) ?></textarea>
        </div>

        <div class="form-group checkbox-group">
            <input type="checkbox" name="aktivna" id="aktivna" <?= $podaci['aktivna'] ? 'checked' : '' ?>>
            <label for="aktivna">Lokacija je aktivna</label>
        </div>

        <button type="submit" class="btn">Sačuvaj</button>
        <a href="lokacije_index.php" class="btn-cancel">Otkaži</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
