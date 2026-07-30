<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$popisId = isset($_GET['popis_id']) ? (int)$_GET['popis_id'] : (isset($_POST['popis_id']) ? (int)$_POST['popis_id'] : null);
$sredstvoId = isset($_GET['sredstvo_id']) ? (int)$_GET['sredstvo_id'] : (isset($_POST['sredstvo_id']) ? (int)$_POST['sredstvo_id'] : null);

if (empty($popisId) || empty($sredstvoId)) {
    header("Location: popisi_index.php");
    exit;
}

// Popis mora postojati i mora biti U_TOKU da bi se stavke mogle evidentirati/menjati
$stmt = $pdo->prepare("SELECT * FROM popisi_osnovnih_sredstava WHERE id = :id");
$stmt->execute([':id' => $popisId]);
$popis = $stmt->fetch();

if (!$popis) {
    header("Location: popisi_index.php");
    exit;
}
if ($popis['status'] !== 'U_TOKU') {
    header("Location: popis_pregled.php?id=" . $popisId);
    exit;
}

// Sredstvo mora postojati
$stmt = $pdo->prepare("SELECT id, inventarski_broj, naziv FROM osnovna_sredstva WHERE id = :id");
$stmt->execute([':id' => $sredstvoId]);
$sredstvo = $stmt->fetch();

if (!$sredstvo) {
    header("Location: popis_pregled.php?id=" . $popisId);
    exit;
}

$poruka = '';

$podaci = [
    'popisano_stanje' => 'PRONADJENO',
    'popisana_lokacija_id' => '',
    'napomena' => '',
    'datum_popisa_stavke' => date('Y-m-d'),
];

// Učitaj postojeću stavku (ako je sredstvo već popisano u okviru ovog popisa)
$stmt = $pdo->prepare("SELECT * FROM stavke_popisa WHERE popis_id = :pid AND sredstvo_id = :sid");
$stmt->execute([':pid' => $popisId, ':sid' => $sredstvoId]);
$postojeca = $stmt->fetch();

if ($postojeca) {
    $podaci['popisano_stanje'] = $postojeca['popisano_stanje'];
    $podaci['popisana_lokacija_id'] = $postojeca['popisana_lokacija_id'] ?? '';
    $podaci['napomena'] = $postojeca['napomena'] ?? '';
    $podaci['datum_popisa_stavke'] = $postojeca['datum_popisa_stavke'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $podaci['popisano_stanje'] = $_POST['popisano_stanje'] ?? 'PRONADJENO';
    $podaci['popisana_lokacija_id'] = $_POST['popisana_lokacija_id'] ?? '';
    $podaci['napomena'] = trim($_POST['napomena'] ?? '');
    $podaci['datum_popisa_stavke'] = trim($_POST['datum_popisa_stavke'] ?? date('Y-m-d'));

    $dozvoljenaStanja = ['PRONADJENO', 'NIJE_PRONADJENO', 'VISAK'];
    if (!in_array($podaci['popisano_stanje'], $dozvoljenaStanja, true)) {
        $poruka = "Nevažeći rezultat popisa.";
    } elseif ($podaci['datum_popisa_stavke'] === '') {
        $poruka = "Datum je obavezan.";
    } else {
        try {
            if ($postojeca) {
                $sql = "UPDATE stavke_popisa SET
                            popisano_stanje = :stanje,
                            popisana_lokacija_id = :lokacija,
                            napomena = :napomena,
                            datum_popisa_stavke = :datum
                        WHERE id = :id";
                $parametri = [
                    ':stanje'   => $podaci['popisano_stanje'],
                    ':lokacija' => $podaci['popisana_lokacija_id'] !== '' ? (int)$podaci['popisana_lokacija_id'] : null,
                    ':napomena' => $podaci['napomena'] !== '' ? $podaci['napomena'] : null,
                    ':datum'    => $podaci['datum_popisa_stavke'],
                    ':id'       => $postojeca['id'],
                ];
            } else {
                $sql = "INSERT INTO stavke_popisa
                            (popis_id, sredstvo_id, popisano_stanje, popisana_lokacija_id, napomena, datum_popisa_stavke)
                        VALUES
                            (:popis, :sredstvo, :stanje, :lokacija, :napomena, :datum)";
                $parametri = [
                    ':popis'    => $popisId,
                    ':sredstvo' => $sredstvoId,
                    ':stanje'   => $podaci['popisano_stanje'],
                    ':lokacija' => $podaci['popisana_lokacija_id'] !== '' ? (int)$podaci['popisana_lokacija_id'] : null,
                    ':napomena' => $podaci['napomena'] !== '' ? $podaci['napomena'] : null,
                    ':datum'    => $podaci['datum_popisa_stavke'],
                ];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($parametri);

            header("Location: popis_pregled.php?id=" . $popisId);
            exit;
        } catch (\PDOException $e) {
            $poruka = "Greška pri upisu u bazu: " . $e->getMessage();
        }
    }
}

$lokacije = $pdo->query("SELECT id, naziv FROM lokacije WHERE aktivna = 1 ORDER BY naziv")->fetchAll();

$naslovStranice = 'Popis sredstva';
require_once 'header.php';
?>

<div class="form-container">
    <h2>Popis sredstva</h2>
    <p class="napomena-polje" style="margin-top:-10px; margin-bottom: 20px;">
        <?= htmlspecialchars($sredstvo['naziv']) ?> (<?= htmlspecialchars($sredstvo['inventarski_broj']) ?>)<br>
        Popis: <?= htmlspecialchars($popis['naziv']) ?>
    </p>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="popis_id" value="<?= $popisId ?>">
        <input type="hidden" name="sredstvo_id" value="<?= $sredstvoId ?>">

        <div class="form-group">
            <label>Rezultat popisa *</label>
            <select name="popisano_stanje" required>
                <option value="PRONADJENO" <?= $podaci['popisano_stanje'] === 'PRONADJENO' ? 'selected' : '' ?>>Pronađeno</option>
                <option value="NIJE_PRONADJENO" <?= $podaci['popisano_stanje'] === 'NIJE_PRONADJENO' ? 'selected' : '' ?>>Nije pronađeno</option>
                <option value="VISAK" <?= $podaci['popisano_stanje'] === 'VISAK' ? 'selected' : '' ?>>Višak (zatečeno neočekivano)</option>
            </select>
        </div>

        <div class="form-group">
            <label>Lokacija gde je sredstvo fizički zatečeno <span class="napomena-polje">(opciono, ako se razlikuje od registrovane)</span></label>
            <select name="popisana_lokacija_id">
                <option value="">-- Nije naznačeno --</option>
                <?php foreach ($lokacije as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= (string)$podaci['popisana_lokacija_id'] === (string)$l['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($l['naziv']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Datum popisa *</label>
            <input type="date" name="datum_popisa_stavke" required value="<?= htmlspecialchars($podaci['datum_popisa_stavke']) ?>">
        </div>

        <div class="form-group">
            <label>Napomena</label>
            <textarea name="napomena"><?= htmlspecialchars($podaci['napomena']) ?></textarea>
        </div>

        <button type="submit" class="btn">Sačuvaj</button>
        <a href="popis_pregled.php?id=<?= $popisId ?>" class="btn-cancel">Otkaži</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
