<?php
/**
 * kretanje_istorija.php
 * ----------------------
 * Objedinjen hronološki pregled svih kretanja osnovnih sredstava, čitan
 * direktno iz centralnog dnevnika transakcije_sredstva (zaduženje, premeštaj,
 * razduženje...). Opcioni filteri: sredstvo (inv. broj/naziv), vrsta
 * transakcije, period.
 *
 * NAPOMENA: zaduženje se u ovaj dnevnik upisuje tek od izmene u
 * revers_form.php koja prati ovu funkcionalnost - reversi izdati pre te
 * izmene neće imati odgovarajući zapis ovde (istorijski podaci se ne
 * naknadno popunjavaju).
 */

require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

// Filteri (svi opcioni)
$filterPojam = trim($_GET['pojam'] ?? '');
$filterVrstaId = $_GET['vrsta_id'] ?? '';
$filterDatumOd = trim($_GET['datum_od'] ?? '');
$filterDatumDo = trim($_GET['datum_do'] ?? '');

$uslovi = [];
$parametri = [];

if ($filterPojam !== '') {
    $uslovi[] = "(os.inventarski_broj LIKE :pojam OR os.naziv LIKE :pojam)";
    $parametri[':pojam'] = '%' . $filterPojam . '%';
}
if ($filterVrstaId !== '') {
    $uslovi[] = "t.vrsta_transakcije_id = :vrsta";
    $parametri[':vrsta'] = (int)$filterVrstaId;
}
if ($filterDatumOd !== '') {
    $uslovi[] = "t.datum_transakcije >= :datum_od";
    $parametri[':datum_od'] = $filterDatumOd;
}
if ($filterDatumDo !== '') {
    $uslovi[] = "t.datum_transakcije <= :datum_do";
    $parametri[':datum_do'] = $filterDatumDo;
}

$whereSql = $uslovi ? ('WHERE ' . implode(' AND ', $uslovi)) : '';

$stmt = $pdo->prepare(
    "SELECT
        t.id, t.datum_transakcije, t.opis, t.broj_dokumenta, t.napomena,
        os.id AS sredstvo_id, os.inventarski_broj, os.naziv AS naziv_sredstva,
        vt.naziv AS naziv_vrste, vt.sifra AS sifra_vrste,
        k.korisnicko_ime
     FROM transakcije_sredstva t
     JOIN osnovna_sredstva os ON os.id = t.sredstvo_id
     JOIN vrste_transakcija vt ON vt.id = t.vrsta_transakcije_id
     LEFT JOIN korisnici k ON k.id = t.korisnik_id
     $whereSql
     ORDER BY t.datum_transakcije DESC, t.id DESC"
);
$stmt->execute($parametri);
$transakcije = $stmt->fetchAll();

$vrsteTransakcija = $pdo->query("SELECT id, naziv FROM vrste_transakcija ORDER BY naziv")->fetchAll();

// Mapiranje šifre vrste na CSS klasu bedža - da se vizuelno razlikuju
// zaduženja/premeštaji/razduženja i sl. na prvi pogled.
$klaseOznakaPoVrsti = [
    'ZADUZENJE'  => 'oznaka-aktivna',
    'PREMESTAJ'  => 'oznaka-u-toku',
    'RAZDUZENJE' => 'oznaka-neaktivna',
];

$naslovStranice = 'Istorija kretanja';
require_once 'header.php';
?>

    <h1>Istorija kretanja osnovnih sredstava</h1>
    <p class="napomena-polje" style="margin-bottom: 20px;">
        Objedinjen hronološki pregled svih evidentiranih zaduženja, premeštaja i razduženja.<br>
        Napomena: reversi izdati pre uvođenja ove evidencije neće imati zapis o zaduženju ovde.
    </p>

    <form method="GET" action="" class="form-container" style="margin-bottom: 20px;">
        <div class="red-2">
            <div class="form-group">
                <label>Pretraga sredstva <span class="napomena-polje">(inv. broj ili naziv)</span></label>
                <input type="text" name="pojam" value="<?= htmlspecialchars($filterPojam) ?>">
            </div>
            <div class="form-group">
                <label>Vrsta kretanja</label>
                <select name="vrsta_id">
                    <option value="">-- Sve vrste --</option>
                    <?php foreach ($vrsteTransakcija as $vt): ?>
                        <option value="<?= $vt['id'] ?>" <?= (string)$filterVrstaId === (string)$vt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($vt['naziv']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="red-2">
            <div class="form-group">
                <label>Datum od</label>
                <input type="date" name="datum_od" value="<?= htmlspecialchars($filterDatumOd) ?>">
            </div>
            <div class="form-group">
                <label>Datum do</label>
                <input type="date" name="datum_do" value="<?= htmlspecialchars($filterDatumDo) ?>">
            </div>
        </div>
        <button type="submit" class="btn">Filtriraj</button>
        <a href="kretanje_istorija.php" class="btn-cancel">Poništi filter</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>Datum</th>
                <th>Vrsta</th>
                <th>Sredstvo</th>
                <th>Dokument</th>
                <th>Opis / napomena</th>
                <th>Uneo</th>
                <th>Akcije</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($transakcije)): ?>
                <tr><td colspan="7" style="text-align:center;">Nema evidentiranih transakcija za zadate filtere.</td></tr>
            <?php else: ?>
                <?php foreach ($transakcije as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['datum_transakcije']) ?></td>
                        <td>
                            <span class="oznaka <?= $klaseOznakaPoVrsti[$t['sifra_vrste']] ?? 'oznaka-neaktivna' ?>">
                                <?= htmlspecialchars($t['naziv_vrste']) ?>
                            </span>
                        </td>
                        <td><strong><?= htmlspecialchars($t['inventarski_broj']) ?></strong> — <?= htmlspecialchars($t['naziv_sredstva']) ?></td>
                        <td><?= htmlspecialchars($t['broj_dokumenta'] ?? '—') ?></td>
                        <td>
                            <?= htmlspecialchars($t['opis'] ?? '') ?>
                            <?php if (!empty($t['napomena'])): ?>
                                <br><span class="napomena-polje"><?= htmlspecialchars($t['napomena']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($t['korisnicko_ime'] ?? '—') ?></td>
                        <td class="akcije">
                            <a href="os_pregled.php?id=<?= $t['sredstvo_id'] ?>">Pregled sredstva</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php require_once 'footer.php'; ?>
