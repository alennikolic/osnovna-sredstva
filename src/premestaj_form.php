<?php
/**
 * premestaj_form.php
 * -------------------
 * Evidentiranje premeštaja osnovnog sredstva - promena lokacije, mesta
 * troška i/ili zaduženog lica. Svaki premeštaj se upisuje u:
 *   - transakcije_sredstva (centralni dnevnik, vrsta = PREMESTAJ)
 *   - premestaji_sredstva (detalji: staro/novo stanje)
 * i odmah ažurira "trenutno stanje" u osnovna_sredstva, isto kao što
 * revers_form.php ažurira zaposleni_id pri izdavanju reversa.
 */

require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$poruka = '';

// Sredstvo može doći unapred zadato kroz query string (klik na "Premesti"
// sa stranice pregleda sredstva) - u tom slučaju je padajući meni zaključan.
$sredstvoIdIzUrl = isset($_GET['sredstvo_id']) && $_GET['sredstvo_id'] !== '' ? (int)$_GET['sredstvo_id'] : null;

$podaci = [
    'sredstvo_id' => $sredstvoIdIzUrl ?? '',
    'datum_premestaja' => date('Y-m-d'),
    'nova_lokacija_id' => '',
    'novo_mesto_troska_id' => '',
    'novi_zaposleni_id' => '',
    'novo_odgovorno_lice' => '',
    'napomena' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $podaci['sredstvo_id'] = $_POST['sredstvo_id'] ?? '';
    $podaci['datum_premestaja'] = trim($_POST['datum_premestaja'] ?? '');
    $podaci['nova_lokacija_id'] = $_POST['nova_lokacija_id'] ?? '';
    $podaci['novo_mesto_troska_id'] = $_POST['novo_mesto_troska_id'] ?? '';
    $podaci['novi_zaposleni_id'] = $_POST['novi_zaposleni_id'] ?? '';
    $podaci['novo_odgovorno_lice'] = trim($_POST['novo_odgovorno_lice'] ?? '');
    $podaci['napomena'] = trim($_POST['napomena'] ?? '');

    if ($podaci['sredstvo_id'] === '' || $podaci['datum_premestaja'] === '') {
        $poruka = "Sredstvo i datum premeštaja su obavezni!";
    } else {
        // Učitaj trenutno (staro) stanje sredstva pre premeštaja
        $stmt = $pdo->prepare(
            "SELECT id, lokacija_id, mesto_troska_id, zaposleni_id, odgovorno_lice
             FROM osnovna_sredstva WHERE id = :id"
        );
        $stmt->execute([':id' => (int)$podaci['sredstvo_id']]);
        $staroStanje = $stmt->fetch();

        if (!$staroStanje) {
            $poruka = "Izabrano sredstvo ne postoji.";
        } else {
            $novaLokacija = $podaci['nova_lokacija_id'] !== '' ? (int)$podaci['nova_lokacija_id'] : null;
            $novoMestoTroska = $podaci['novo_mesto_troska_id'] !== '' ? (int)$podaci['novo_mesto_troska_id'] : null;
            $noviZaposleni = $podaci['novi_zaposleni_id'] !== '' ? (int)$podaci['novi_zaposleni_id'] : null;
            $novoOdgovornoLice = $podaci['novo_odgovorno_lice'] !== '' ? $podaci['novo_odgovorno_lice'] : null;

            $staraLokacija = $staroStanje['lokacija_id'] !== null ? (int)$staroStanje['lokacija_id'] : null;
            $staroMestoTroska = $staroStanje['mesto_troska_id'] !== null ? (int)$staroStanje['mesto_troska_id'] : null;
            $stariZaposleni = $staroStanje['zaposleni_id'] !== null ? (int)$staroStanje['zaposleni_id'] : null;
            $staroOdgovornoLice = $staroStanje['odgovorno_lice'];

            $imaPromene = ($novaLokacija !== $staraLokacija)
                || ($novoMestoTroska !== $staroMestoTroska)
                || ($noviZaposleni !== $stariZaposleni)
                || ($novoOdgovornoLice !== $staroOdgovornoLice);

            if (!$imaPromene) {
                $poruka = "Nema promene u odnosu na trenutno stanje sredstva - izmenite bar jedno od polja (lokaciju, mesto troška ili zaduženo lice) u odnosu na trenutne vrednosti.";
            } else {
                try {
                    $pdo->beginTransaction();

                    $vrstaTransakcije = $pdo->query(
                        "SELECT id FROM vrste_transakcija WHERE sifra = 'PREMESTAJ'"
                    )->fetch();

                    if (!$vrstaTransakcije) {
                        throw new \RuntimeException('Vrsta transakcije PREMESTAJ ne postoji u bazi - proverite da li je init.sql ispravno učitan.');
                    }

                    $trenutni = trenutniKorisnik();

                    $stmt = $pdo->prepare(
                        "INSERT INTO transakcije_sredstva
                            (sredstvo_id, vrsta_transakcije_id, datum_transakcije, opis, korisnik_id, napomena)
                         VALUES
                            (:sredstvo, :vrsta, :datum, :opis, :korisnik, :napomena)"
                    );
                    $stmt->execute([
                        ':sredstvo' => (int)$podaci['sredstvo_id'],
                        ':vrsta'    => $vrstaTransakcije['id'],
                        ':datum'    => $podaci['datum_premestaja'],
                        ':opis'     => 'Premeštaj osnovnog sredstva',
                        ':korisnik' => $trenutni['id'] ?? null,
                        ':napomena' => $podaci['napomena'] !== '' ? $podaci['napomena'] : null,
                    ]);
                    $transakcijaId = (int)$pdo->lastInsertId();

                    $stmt = $pdo->prepare(
                        "INSERT INTO premestaji_sredstva
                            (transakcija_id, sredstvo_id, datum_premestaja,
                             stara_lokacija_id, nova_lokacija_id,
                             staro_mesto_troska_id, novo_mesto_troska_id,
                             staro_odgovorno_lice, stari_zaposleni_id,
                             novo_odgovorno_lice, novi_zaposleni_id, napomena)
                         VALUES
                            (:transakcija, :sredstvo, :datum,
                             :stara_lok, :nova_lok,
                             :staro_mt, :novo_mt,
                             :staro_lice, :stari_zap,
                             :novo_lice, :novi_zap, :napomena)"
                    );
                    $stmt->execute([
                        ':transakcija' => $transakcijaId,
                        ':sredstvo'    => (int)$podaci['sredstvo_id'],
                        ':datum'       => $podaci['datum_premestaja'],
                        ':stara_lok'   => $staraLokacija,
                        ':nova_lok'    => $novaLokacija,
                        ':staro_mt'    => $staroMestoTroska,
                        ':novo_mt'     => $novoMestoTroska,
                        ':staro_lice'  => $staroOdgovornoLice,
                        ':stari_zap'   => $stariZaposleni,
                        ':novo_lice'   => $novoOdgovornoLice,
                        ':novi_zap'    => $noviZaposleni,
                        ':napomena'    => $podaci['napomena'] !== '' ? $podaci['napomena'] : null,
                    ]);

                    // Ažuriraj trenutno stanje sredstva na nove vrednosti - isti princip
                    // kao kod izdavanja reversa (revers_form.php ažurira zaposleni_id).
                    $stmt = $pdo->prepare(
                        "UPDATE osnovna_sredstva
                         SET lokacija_id = :lokacija,
                             mesto_troska_id = :mesto_troska,
                             zaposleni_id = :zaposleni,
                             odgovorno_lice = :odgovorno_lice
                         WHERE id = :id"
                    );
                    $stmt->execute([
                        ':lokacija'       => $novaLokacija,
                        ':mesto_troska'   => $novoMestoTroska,
                        ':zaposleni'      => $noviZaposleni,
                        ':odgovorno_lice' => $novoOdgovornoLice,
                        ':id'             => (int)$podaci['sredstvo_id'],
                    ]);

                    $pdo->commit();

                    header("Location: os_pregled.php?id=" . (int)$podaci['sredstvo_id']);
                    exit;
                } catch (\PDOException $e) {
                    $pdo->rollBack();
                    $poruka = "Greška pri upisu u bazu: " . $e->getMessage();
                } catch (\RuntimeException $e) {
                    $pdo->rollBack();
                    $poruka = $e->getMessage();
                }
            }
        }
    }
}

// Sredstva dostupna za premeštaj - isključujemo sredstva u završnom statusu
// (rashodovano/prodato/otpisano sredstvo se više ne premešta), isto pravilo
// kao kod popisa i reversa. Odmah učitavamo i trenutno stanje svakog sredstva
// da bismo na klijentu mogli automatski popuniti polja bez dodatnog upita.
$sveSredstva = $pdo->query(
    "SELECT os.id, os.inventarski_broj, os.naziv, k.naziv AS naziv_klase,
            os.lokacija_id, os.mesto_troska_id, os.zaposleni_id, os.odgovorno_lice
     FROM osnovna_sredstva os
     JOIN klase_osnovnih_sredstava k ON k.id = os.klasa_id
     JOIN statusi_sredstva s ON s.id = os.status_id
     WHERE s.da_li_je_zavrsni_status = 0
     ORDER BY os.naziv"
)->fetchAll();

$stanjaSredstavaZaJs = [];
foreach ($sveSredstva as $s) {
    $stanjaSredstavaZaJs[$s['id']] = [
        'lokacija_id'     => $s['lokacija_id'],
        'mesto_troska_id' => $s['mesto_troska_id'],
        'zaposleni_id'    => $s['zaposleni_id'],
        'odgovorno_lice'  => $s['odgovorno_lice'],
    ];
}

$lokacije = $pdo->query("SELECT id, naziv FROM lokacije WHERE aktivna = 1 ORDER BY naziv")->fetchAll();
$mestaTroska = $pdo->query("SELECT id, naziv FROM mesta_troska WHERE aktivno = 1 ORDER BY naziv")->fetchAll();
$zaposleniLista = $pdo->query("SELECT id, ime, prezime FROM zaposleni WHERE aktivan = 1 ORDER BY prezime, ime")->fetchAll();

// Ako je sredstvo već izabrano (iz URL-a ili posle neuspešnog POST-a), padajući
// meniji se podrazumevano postavljaju na njegovo trenutno stanje - korisnik
// onda samo menja ono što se stvarno menja.
$odabranoSredstvoId = $podaci['sredstvo_id'] !== '' ? (int)$podaci['sredstvo_id'] : null;
if ($odabranoSredstvoId && $_SERVER['REQUEST_METHOD'] !== 'POST' && isset($stanjaSredstavaZaJs[$odabranoSredstvoId])) {
    $trenutnoStanje = $stanjaSredstavaZaJs[$odabranoSredstvoId];
    $podaci['nova_lokacija_id'] = $trenutnoStanje['lokacija_id'] ?? '';
    $podaci['novo_mesto_troska_id'] = $trenutnoStanje['mesto_troska_id'] ?? '';
    $podaci['novi_zaposleni_id'] = $trenutnoStanje['zaposleni_id'] ?? '';
    $podaci['novo_odgovorno_lice'] = $trenutnoStanje['odgovorno_lice'] ?? '';
}

$naslovStranice = 'Premeštaj osnovnog sredstva';
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2>Premeštaj osnovnog sredstva</h2>
    <p class="napomena-polje" style="margin-top:-10px; margin-bottom: 20px;">
        Promena lokacije, mesta troška i/ili zaduženog lica. Polja koja ne menjate ostaju na trenutnoj vrednosti sredstva.
    </p>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>Osnovno sredstvo *</label>
            <select name="sredstvo_id" id="sredstvo_id_select" required
                    <?= $sredstvoIdIzUrl ? 'disabled' : '' ?>
                    onchange="ucitajTrenutnoStanje(this.value)">
                <option value="">-- Izaberite sredstvo --</option>
                <?php foreach ($sveSredstva as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (string)$podaci['sredstvo_id'] === (string)$s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['inventarski_broj'] . ' - ' . $s['naziv'] . ' (' . $s['naziv_klase'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($sredstvoIdIzUrl): ?>
                <input type="hidden" name="sredstvo_id" value="<?= $sredstvoIdIzUrl ?>">
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Datum premeštaja *</label>
            <input type="date" name="datum_premestaja" required value="<?= htmlspecialchars($podaci['datum_premestaja']) ?>">
        </div>

        <div class="red-2">
            <div class="form-group">
                <label>Nova lokacija</label>
                <select name="nova_lokacija_id">
                    <option value="">-- Bez lokacije --</option>
                    <?php foreach ($lokacije as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= (string)$podaci['nova_lokacija_id'] === (string)$l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['naziv']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Novo mesto troška</label>
                <select name="novo_mesto_troska_id">
                    <option value="">-- Bez mesta troška --</option>
                    <?php foreach ($mestaTroska as $mt): ?>
                        <option value="<?= $mt['id'] ?>" <?= (string)$podaci['novo_mesto_troska_id'] === (string)$mt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($mt['naziv']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="red-2">
            <div class="form-group">
                <label>Novo zaduženo lice <span class="napomena-polje">(iz evidencije zaposlenih)</span></label>
                <select name="novi_zaposleni_id">
                    <option value="">-- Nije zaduženo --</option>
                    <?php foreach ($zaposleniLista as $z): ?>
                        <option value="<?= $z['id'] ?>" <?= (string)$podaci['novi_zaposleni_id'] === (string)$z['id'] ? 'selected' : '' ?>><?= htmlspecialchars($z['prezime'] . ' ' . $z['ime']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Odgovorno lice <span class="napomena-polje">(slobodan tekst, ako lice nije u evidenciji zaposlenih)</span></label>
                <input type="text" name="novo_odgovorno_lice" value="<?= htmlspecialchars($podaci['novo_odgovorno_lice']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Napomena</label>
            <textarea name="napomena"><?= htmlspecialchars($podaci['napomena']) ?></textarea>
        </div>

        <button type="submit" class="btn">Izvrši premeštaj</button>
        <a href="kretanje_index.php" class="btn-cancel">Otkaži</a>
    </form>
</div>

<script>
// Kada korisnik promeni izabrano sredstvo (samo ako nije unapred zaključano
// kroz URL), automatski popuni padajuće menije trenutnim stanjem tog sredstva
// - korisnik onda samo menja ono što se stvarno menja. Podaci dolaze iz JSON
// mape pripremljene na serveru, bez dodatnog upita ka bazi.
var stanjaSredstava = <?= json_encode($stanjaSredstavaZaJs) ?>;
function ucitajTrenutnoStanje(sredstvoId) {
    if (!stanjaSredstava[sredstvoId]) { return; }
    var stanje = stanjaSredstava[sredstvoId];
    document.querySelector('select[name="nova_lokacija_id"]').value = stanje.lokacija_id || '';
    document.querySelector('select[name="novo_mesto_troska_id"]').value = stanje.mesto_troska_id || '';
    document.querySelector('select[name="novi_zaposleni_id"]').value = stanje.zaposleni_id || '';
    document.querySelector('input[name="novo_odgovorno_lice"]').value = stanje.odgovorno_lice || '';
}
</script>

<?php require_once 'footer.php'; ?>
