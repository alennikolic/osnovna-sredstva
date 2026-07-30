<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';
require_once 'helpers.php';

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
    'nadredjeno_mesto_troska_id' => '',
    'napomena' => '',
    'aktivno' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $podaci['sifra'] = trim($_POST['sifra'] ?? '');
    $podaci['naziv'] = trim($_POST['naziv'] ?? '');
    $podaci['nadredjeno_mesto_troska_id'] = $_POST['nadredjeno_mesto_troska_id'] ?? '';
    $podaci['napomena'] = trim($_POST['napomena'] ?? '');
    $podaci['aktivno'] = isset($_POST['aktivno']) ? 1 : 0;

    if ($podaci['sifra'] === '' || $podaci['naziv'] === '') {
        $poruka = "Šifra i naziv mesta troška su obavezni!";
    } else {
        try {
            $parametri = [
                ':sifra'      => $podaci['sifra'],
                ':naziv'      => $podaci['naziv'],
                ':nadredjeno' => $podaci['nadredjeno_mesto_troska_id'] !== '' ? (int)$podaci['nadredjeno_mesto_troska_id'] : null,
                ':napomena'   => $podaci['napomena'] !== '' ? $podaci['napomena'] : null,
                ':aktivno'    => $podaci['aktivno'],
            ];

            if ($izmena) {
                // Zaštita: mesto troška ne sme biti nadređeno samo sebi ili svom podređenom
                if ($parametri[':nadredjeno'] !== null) {
                    $sveMestaOsnovno = $pdo->query("SELECT id, nadredjeno_mesto_troska_id FROM mesta_troska")->fetchAll();
                    $potomci = pronadjiPotomkeMesta($sveMestaOsnovno, $id);
                    if ($parametri[':nadredjeno'] === $id || in_array($parametri[':nadredjeno'], $potomci, true)) {
                        throw new \RuntimeException("Mesto troška ne može biti nadređeno samo sebi ili svom podređenom mestu troška.");
                    }
                }

                $sql = "UPDATE mesta_troska SET
                            sifra = :sifra,
                            naziv = :naziv,
                            nadredjeno_mesto_troska_id = :nadredjeno,
                            napomena = :napomena,
                            aktivno = :aktivno
                        WHERE id = :id";
                $parametri[':id'] = $id;
            } else {
                $sql = "INSERT INTO mesta_troska
                            (sifra, naziv, nadredjeno_mesto_troska_id, napomena, aktivno)
                        VALUES
                            (:sifra, :naziv, :nadredjeno, :napomena, :aktivno)";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($parametri);

            header("Location: mesta_troska_index.php");
            exit;
        } catch (\RuntimeException $e) {
            $poruka = $e->getMessage();
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $poruka = "Mesto troška sa šifrom \"{$podaci['sifra']}\" već postoji. Izaberite drugu šifru.";
            } else {
                $poruka = "Greška pri upisu u bazu: " . $e->getMessage();
            }
        }
    }
} elseif ($izmena) {
    $stmt = $pdo->prepare("SELECT * FROM mesta_troska WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $postojece = $stmt->fetch();

    if (!$postojece) {
        header("Location: mesta_troska_index.php");
        exit;
    }

    $podaci['sifra'] = $postojece['sifra'];
    $podaci['naziv'] = $postojece['naziv'];
    $podaci['nadredjeno_mesto_troska_id'] = $postojece['nadredjeno_mesto_troska_id'] ?? '';
    $podaci['napomena'] = $postojece['napomena'] ?? '';
    $podaci['aktivno'] = (int)$postojece['aktivno'];
}

// Učitavanje mesta troška za padajući meni (isključujemo sebe i potomke kod izmene)
$sveMestaZaSelect = ucitajMestaTroskaHijerarhijski($pdo);

$iskljuceniIzListe = [];
if ($izmena) {
    $iskljuceniIzListe[] = $id;
    $sveMestaOsnovno = $pdo->query("SELECT id, nadredjeno_mesto_troska_id FROM mesta_troska")->fetchAll();
    $iskljuceniIzListe = array_merge($iskljuceniIzListe, pronadjiPotomkeMesta($sveMestaOsnovno, $id));
}

$naslovStranice = $izmena ? 'Izmena mesta troška' : 'Novo mesto troška';
require_once 'header.php';
?>

<div class="form-container">
    <h2><?= $izmena ? 'Izmena mesta troška: ' . htmlspecialchars($podaci['naziv']) : 'Novo mesto troška' ?></h2>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php if ($izmena): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>

        <div class="form-group">
            <label>Šifra *</label>
            <input type="text" name="sifra" maxlength="20" required value="<?= htmlspecialchars($podaci['sifra']) ?>">
        </div>

        <div class="form-group">
            <label>Naziv *</label>
            <input type="text" name="naziv" maxlength="150" required value="<?= htmlspecialchars($podaci['naziv']) ?>">
        </div>

        <div class="form-group">
            <label>Nadređeno mesto troška <span class="napomena-polje">(ostavite prazno za najvišu organizacionu jedinicu)</span></label>
            <select name="nadredjeno_mesto_troska_id">
                <option value="">-- Nema (korensko mesto troška) --</option>
                <?php foreach ($sveMestaZaSelect as $m): ?>
                    <?php if (in_array((int)$m['id'], $iskljuceniIzListe, true)) continue; ?>
                    <option value="<?= $m['id'] ?>" <?= (string)$podaci['nadredjeno_mesto_troska_id'] === (string)$m['id'] ? 'selected' : '' ?>>
                        <?= str_repeat('— ', $m['nivo']) . htmlspecialchars($m['sifra'] . ' - ' . $m['naziv']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Napomena</label>
            <textarea name="napomena"><?= htmlspecialchars($podaci['napomena']) ?></textarea>
        </div>

        <div class="form-group checkbox-group">
            <input type="checkbox" name="aktivno" id="aktivno" <?= $podaci['aktivno'] ? 'checked' : '' ?>>
            <label for="aktivno">Mesto troška je aktivno</label>
        </div>

        <button type="submit" class="btn">Sačuvaj</button>
        <a href="mesta_troska_index.php" class="btn-cancel">Otkaži</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
