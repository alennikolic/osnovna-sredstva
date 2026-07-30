<?php
/**
 * zaduzenja_pregled.php
 * ----------------------
 * Pregled trenutno zaduženih sredstava, grupisano po zaposlenom - čita se
 * direktno iz osnovna_sredstva.zaposleni_id (trenutno stanje), ne iz istorije
 * reversa. Koristan za brzu proveru "ko šta trenutno drži" pre popisa ili
 * pri odlasku zaposlenog.
 */

require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$samoZadUzeni = isset($_GET['samo_zaduzeni']);

$stmt = $pdo->query(
    "SELECT
        z.id AS zaposleni_id, z.ime, z.prezime, z.radno_mesto,
        os.id AS sredstvo_id, os.inventarski_broj, os.naziv AS naziv_sredstva, k.naziv AS naziv_klase
     FROM zaposleni z
     LEFT JOIN osnovna_sredstva os ON os.zaposleni_id = z.id
     LEFT JOIN klase_osnovnih_sredstava k ON k.id = os.klasa_id
     WHERE z.aktivan = 1
     ORDER BY z.prezime, z.ime, os.naziv"
);
$redovi = $stmt->fetchAll();

// Grupisanje redova (jedan red po sredstvu, ili jedan red bez sredstva ako
// zaposleni ništa ne drži) u niz po zaposlenom.
$poZaposlenom = [];
foreach ($redovi as $r) {
    $zid = $r['zaposleni_id'];
    if (!isset($poZaposlenom[$zid])) {
        $poZaposlenom[$zid] = [
            'ime' => $r['ime'],
            'prezime' => $r['prezime'],
            'radno_mesto' => $r['radno_mesto'],
            'sredstva' => [],
        ];
    }
    if ($r['sredstvo_id']) {
        $poZaposlenom[$zid]['sredstva'][] = [
            'sredstvo_id' => $r['sredstvo_id'],
            'inventarski_broj' => $r['inventarski_broj'],
            'naziv' => $r['naziv_sredstva'],
            'naziv_klase' => $r['naziv_klase'],
        ];
    }
}

$naslovStranice = 'Zaduženja po zaposlenom';
require_once 'header.php';
?>

<h1>Zaduženja po zaposlenom</h1>
<p class="napomena-polje" style="margin-bottom: 15px;">
    Trenutno zaduženje po zaposlenom (na osnovu poslednjeg izdatog reversa za svako sredstvo).
</p>

<div style="margin-bottom: 20px;">
    <?php if ($samoZadUzeni): ?>
        <a href="zaduzenja_pregled.php" class="btn-cancel">Prikaži sve zaposlene</a>
    <?php else: ?>
        <a href="zaduzenja_pregled.php?samo_zaduzeni=1" class="btn-cancel">Prikaži samo zadužene</a>
    <?php endif; ?>
</div>

<?php foreach ($poZaposlenom as $z): ?>
    <?php if ($samoZadUzeni && empty($z['sredstva'])): ?>
        <?php continue; ?>
    <?php endif; ?>
    <div class="form-container forma-siroka" style="margin-bottom: 15px;">
        <h3 style="margin-top:0;">
            <?= htmlspecialchars($z['prezime'] . ' ' . $z['ime']) ?>
            <?php if ($z['radno_mesto']): ?>
                <span class="napomena-polje">(<?= htmlspecialchars($z['radno_mesto']) ?>)</span>
            <?php endif; ?>
            <span class="oznaka <?= empty($z['sredstva']) ? 'oznaka-neaktivna' : 'oznaka-aktivna' ?>">
                <?= count($z['sredstva']) ?> zaduženo
            </span>
        </h3>
        <?php if (empty($z['sredstva'])): ?>
            <p class="napomena-polje">Nema trenutno zaduženih sredstava.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Inventarski broj</th>
                        <th>Naziv</th>
                        <th>Klasa</th>
                        <th>Akcije</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($z['sredstva'] as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['inventarski_broj']) ?></td>
                            <td><?= htmlspecialchars($s['naziv']) ?></td>
                            <td><?= htmlspecialchars($s['naziv_klase']) ?></td>
                            <td class="akcije"><a href="os_pregled.php?id=<?= $s['sredstvo_id'] ?>">Pregled</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php require_once 'footer.php'; ?>
