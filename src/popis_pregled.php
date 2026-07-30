<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';
require_once 'helpers.php';

$id = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : null;
if (empty($id)) {
    header("Location: popisi_index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM popisi_osnovnih_sredstava WHERE id = :id");
$stmt->execute([':id' => $id]);
$popis = $stmt->fetch();

if (!$popis) {
    header("Location: popisi_index.php");
    exit;
}

$poruka = '';

// Akcije promene statusa (Pokreni / Završi / Otkaži popis) - namerno kao
// eksplicitna dugmad, a ne slobodan izbor iz padajućeg menija, da se ne bi
// "preskočilo" neko stanje u životnom ciklusu popisa.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akcija'])) {
    $akcija = $_POST['akcija'];
    try {
        if ($akcija === 'pokreni' && $popis['status'] === 'U_PRIPREMI') {
            $pdo->prepare("UPDATE popisi_osnovnih_sredstava SET status = 'U_TOKU' WHERE id = :id")
                ->execute([':id' => $id]);
        } elseif ($akcija === 'zavrsi' && $popis['status'] === 'U_TOKU') {
            $pdo->prepare("UPDATE popisi_osnovnih_sredstava SET status = 'ZAVRSEN', datum_do = COALESCE(datum_do, CURDATE()) WHERE id = :id")
                ->execute([':id' => $id]);
        } elseif ($akcija === 'otkazi' && in_array($popis['status'], ['U_PRIPREMI', 'U_TOKU'], true)) {
            $pdo->prepare("UPDATE popisi_osnovnih_sredstava SET status = 'OTKAZAN' WHERE id = :id")
                ->execute([':id' => $id]);
        }
        header("Location: popis_pregled.php?id=" . $id);
        exit;
    } catch (\PDOException $e) {
        $poruka = "Greška pri promeni statusa: " . $e->getMessage();
    }
}

// Lista sredstava koja treba popisati - svi statusi koji NISU završni
// (rashodovano/prodato/otpisano sredstvo se po pravilu ne popisuje ponovo) -
// zajedno sa rezultatom popisa za OVAJ popis, ako već postoji.
$stmt = $pdo->prepare(
    "SELECT
        os.id AS sredstvo_id, os.inventarski_broj, os.naziv AS naziv_sredstva,
        k.naziv AS naziv_klase,
        l.naziv AS registrovana_lokacija,
        sp.id AS stavka_id, sp.popisano_stanje, sp.napomena AS stavka_napomena,
        pl.naziv AS popisana_lokacija
     FROM osnovna_sredstva os
     JOIN klase_osnovnih_sredstava k ON k.id = os.klasa_id
     JOIN statusi_sredstva s ON s.id = os.status_id
     LEFT JOIN lokacije l ON l.id = os.lokacija_id
     LEFT JOIN stavke_popisa sp ON sp.sredstvo_id = os.id AND sp.popis_id = :popis_id
     LEFT JOIN lokacije pl ON pl.id = sp.popisana_lokacija_id
     WHERE s.da_li_je_zavrsni_status = 0
     ORDER BY os.naziv"
);
$stmt->execute([':popis_id' => $id]);
$stavke = $stmt->fetchAll();

// Sažetak napretka
$brojUkupno = count($stavke);
$brojPronadjeno = 0;
$brojNijePronadjeno = 0;
$brojVisak = 0;
$brojNepopisano = 0;
foreach ($stavke as $s) {
    if ($s['popisano_stanje'] === 'PRONADJENO') {
        $brojPronadjeno++;
    } elseif ($s['popisano_stanje'] === 'NIJE_PRONADJENO') {
        $brojNijePronadjeno++;
    } elseif ($s['popisano_stanje'] === 'VISAK') {
        $brojVisak++;
    } else {
        $brojNepopisano++;
    }
}

[$nazivStatusa, $klasaOznake] = oznakaStatusaPopisa($popis['status']);

$naslovStranice = 'Popis: ' . $popis['naziv'];
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2><?= htmlspecialchars($popis['naziv']) ?> <span class="oznaka <?= $klasaOznake ?>"><?= $nazivStatusa ?></span></h2>
    <p class="napomena-polje" style="margin-top:-10px; margin-bottom: 20px;">
        Period: <?= htmlspecialchars($popis['datum_od']) ?><?= $popis['datum_do'] ? ' – ' . htmlspecialchars($popis['datum_do']) : ' – u toku' ?>
        <?php if (!empty($popis['predsednik_komisije'])): ?>
            &nbsp;|&nbsp; Predsednik komisije: <?= htmlspecialchars($popis['predsednik_komisije']) ?>
        <?php endif; ?>
    </p>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <div style="margin-bottom: 20px;">
        <a href="popisi_form.php?id=<?= $popis['id'] ?>" class="btn-cancel">Izmeni podatke o popisu</a>
        <?php if ($popis['status'] === 'U_PRIPREMI'): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Pokrenuti popis? Nakon pokretanja moći ćete da evidentirate pronađena sredstva.');">
                <input type="hidden" name="akcija" value="pokreni">
                <button type="submit" class="btn">Pokreni popis</button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Otkazati ovaj popis?');">
                <input type="hidden" name="akcija" value="otkazi">
                <button type="submit" class="btn" style="background:#dc3545;">Otkaži popis</button>
            </form>
        <?php elseif ($popis['status'] === 'U_TOKU'): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Završiti popis? Posle završetka neće biti moguće dalje evidentirati stavke.');">
                <input type="hidden" name="akcija" value="zavrsi">
                <button type="submit" class="btn">Završi popis</button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Otkazati ovaj popis?');">
                <input type="hidden" name="akcija" value="otkazi">
                <button type="submit" class="btn" style="background:#dc3545;">Otkaži popis</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (!empty($popis['clanovi_komisije'])): ?>
        <p class="napomena-polje"><strong>Članovi komisije:</strong> <?= nl2br(htmlspecialchars($popis['clanovi_komisije'])) ?></p>
    <?php endif; ?>

    <div class="naslov-podsekcije">
        Napredak: <?= $brojPronadjeno ?> pronađeno, <?= $brojNijePronadjeno ?> nije pronađeno,
        <?= $brojVisak ?> višak, <?= $brojNepopisano ?> još nije evidentirano (od ukupno <?= $brojUkupno ?>)
    </div>
</div>

<div style="margin-top: 20px;">
    <table>
        <thead>
            <tr>
                <th>Inv. broj</th>
                <th>Naziv</th>
                <th>Klasa</th>
                <th>Registrovana lokacija</th>
                <th>Rezultat popisa</th>
                <th>Popisana lokacija</th>
                <th>Napomena</th>
                <?php if ($popis['status'] === 'U_TOKU'): ?>
                    <th>Akcije</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($stavke)): ?>
                <tr><td colspan="8" style="text-align:center;">Nema sredstava koja treba popisati (sva sredstva su u završnom statusu).</td></tr>
            <?php else: ?>
                <?php foreach ($stavke as $s): ?>
                    <?php [$nazivStanja, $klasaStanja] = oznakaPopisanogStanja($s['popisano_stanje']); ?>
                    <tr>
                        <td><?= htmlspecialchars($s['inventarski_broj']) ?></td>
                        <td><?= htmlspecialchars($s['naziv_sredstva']) ?></td>
                        <td><?= htmlspecialchars($s['naziv_klase']) ?></td>
                        <td><?= htmlspecialchars($s['registrovana_lokacija'] ?? '—') ?></td>
                        <td><span class="oznaka <?= $klasaStanja ?>"><?= $nazivStanja ?></span></td>
                        <td><?= htmlspecialchars($s['popisana_lokacija'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($s['stavka_napomena'] ?? '—') ?></td>
                        <?php if ($popis['status'] === 'U_TOKU'): ?>
                        <td class="akcije">
                            <a href="popis_stavka_form.php?popis_id=<?= $popis['id'] ?>&sredstvo_id=<?= $s['sredstvo_id'] ?>">
                                <?= $s['stavka_id'] ? 'Izmeni' : 'Popiši' ?>
                            </a>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div style="margin-top: 20px;">
    <a href="popisi_index.php" class="btn-cancel">Nazad na listu popisa</a>
</div>

<?php require_once 'footer.php'; ?>
