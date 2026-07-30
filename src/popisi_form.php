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
    'naziv' => '',
    'datum_od' => date('Y-m-d'),
    'datum_do' => '',
    'predsednik_komisije' => '',
    'clanovi_komisije' => '',
    'napomena' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $podaci['naziv'] = trim($_POST['naziv'] ?? '');
    $podaci['datum_od'] = trim($_POST['datum_od'] ?? '');
    $podaci['datum_do'] = trim($_POST['datum_do'] ?? '');
    $podaci['predsednik_komisije'] = trim($_POST['predsednik_komisije'] ?? '');
    $podaci['clanovi_komisije'] = trim($_POST['clanovi_komisije'] ?? '');
    $podaci['napomena'] = trim($_POST['napomena'] ?? '');

    if ($podaci['naziv'] === '' || $podaci['datum_od'] === '') {
        $poruka = "Naziv i datum početka su obavezni!";
    } else {
        try {
            $parametri = [
                ':naziv'      => $podaci['naziv'],
                ':datum_od'   => $podaci['datum_od'],
                ':datum_do'   => $podaci['datum_do'] !== '' ? $podaci['datum_do'] : null,
                ':predsednik' => $podaci['predsednik_komisije'] !== '' ? $podaci['predsednik_komisije'] : null,
                ':clanovi'    => $podaci['clanovi_komisije'] !== '' ? $podaci['clanovi_komisije'] : null,
                ':napomena'   => $podaci['napomena'] !== '' ? $podaci['napomena'] : null,
            ];

            if ($izmena) {
                // NAMERNO se ovde ne dira status - to ide isključivo preko dugmadi
                // (Pokreni / Završi / Otkaži) na popis_pregled.php, da se ne bi
                // slučajno "preskočilo" stanje u životnom ciklusu popisa.
                $sql = "UPDATE popisi_osnovnih_sredstava SET
                            naziv = :naziv,
                            datum_od = :datum_od,
                            datum_do = :datum_do,
                            predsednik_komisije = :predsednik,
                            clanovi_komisije = :clanovi,
                            napomena = :napomena
                        WHERE id = :id";
                $parametri[':id'] = $id;
            } else {
                $sql = "INSERT INTO popisi_osnovnih_sredstava
                            (naziv, datum_od, datum_do, predsednik_komisije, clanovi_komisije, napomena, status)
                        VALUES
                            (:naziv, :datum_od, :datum_do, :predsednik, :clanovi, :napomena, 'U_PRIPREMI')";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($parametri);

            $ciljniId = $izmena ? $id : (int)$pdo->lastInsertId();
            header("Location: popis_pregled.php?id=" . $ciljniId);
            exit;
        } catch (\PDOException $e) {
            $poruka = "Greška pri upisu u bazu: " . $e->getMessage();
        }
    }
} elseif ($izmena) {
    $stmt = $pdo->prepare("SELECT * FROM popisi_osnovnih_sredstava WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $postojeci = $stmt->fetch();

    if (!$postojeci) {
        header("Location: popisi_index.php");
        exit;
    }

    $podaci['naziv'] = $postojeci['naziv'];
    $podaci['datum_od'] = $postojeci['datum_od'];
    $podaci['datum_do'] = $postojeci['datum_do'] ?? '';
    $podaci['predsednik_komisije'] = $postojeci['predsednik_komisije'] ?? '';
    $podaci['clanovi_komisije'] = $postojeci['clanovi_komisije'] ?? '';
    $podaci['napomena'] = $postojeci['napomena'] ?? '';
}

$naslovStranice = $izmena ? 'Izmena popisa' : 'Novi popis';
require_once 'header.php';
?>

<div class="form-container">
    <h2><?= $izmena ? 'Izmena popisa: ' . htmlspecialchars($podaci['naziv']) : 'Novi popis osnovnih sredstava' ?></h2>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php if ($izmena): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>

        <div class="form-group">
            <label>Naziv popisa *</label>
            <input type="text" name="naziv" maxlength="150" required value="<?= htmlspecialchars($podaci['naziv']) ?>" placeholder="npr. Godišnji popis 2026">
        </div>

        <div class="red-2">
            <div class="form-group">
                <label>Datum početka *</label>
                <input type="date" name="datum_od" required value="<?= htmlspecialchars($podaci['datum_od']) ?>">
            </div>
            <div class="form-group">
                <label>Datum završetka <span class="napomena-polje">(popunjava se automatski pri završetku)</span></label>
                <input type="date" name="datum_do" value="<?= htmlspecialchars($podaci['datum_do']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Predsednik komisije</label>
            <input type="text" name="predsednik_komisije" maxlength="150" value="<?= htmlspecialchars($podaci['predsednik_komisije']) ?>">
        </div>

        <div class="form-group">
            <label>Članovi komisije</label>
            <textarea name="clanovi_komisije" placeholder="Slobodan tekst - po jedan član u redu"><?= htmlspecialchars($podaci['clanovi_komisije']) ?></textarea>
        </div>

        <div class="form-group">
            <label>Napomena</label>
            <textarea name="napomena"><?= htmlspecialchars($podaci['napomena']) ?></textarea>
        </div>

        <button type="submit" class="btn">Sačuvaj</button>
        <a href="popisi_index.php" class="btn-cancel">Otkaži</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
