<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';
require_once 'helpers.php';

$poruka = '';

// Kod GET zahteva ID dolazi iz query stringa (klik na "Izmeni" iz liste),
// kod POST zahteva iz skrivenog polja forme. Namerno se ne oslanjamo na to
// da li browser čuva query string kada je action="" (ponašanje se razlikuje),
// nego eksplicitno gledamo pravi izvor u zavisnosti od metode zahteva.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
} else {
    $id = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : null;
}
$izmena = !empty($id);

// Podrazumevane (prazne) vrednosti forme
$podaci = [
    'sifra' => '',
    'naziv' => '',
    'opis' => '',
    'nadredjena_klasa_id' => '',
    'tip_sredstva' => 'MATERIJALNO',
    'amortizaciona_grupa_id' => '',
    'metoda_amortizacije_id' => '',
    'konto_nabavne_vrednosti' => '',
    'konto_ispravke_vrednosti' => '',
    'konto_troska_amortizacije' => '',
    'ukljucuje_se_u_popis' => 1,
    'aktivna' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $podaci['sifra'] = trim($_POST['sifra'] ?? '');
    $podaci['naziv'] = trim($_POST['naziv'] ?? '');
    $podaci['opis'] = trim($_POST['opis'] ?? '');
    $podaci['nadredjena_klasa_id'] = $_POST['nadredjena_klasa_id'] ?? '';
    $podaci['tip_sredstva'] = $_POST['tip_sredstva'] ?? 'MATERIJALNO';
    $podaci['amortizaciona_grupa_id'] = $_POST['amortizaciona_grupa_id'] ?? '';
    $podaci['metoda_amortizacije_id'] = $_POST['metoda_amortizacije_id'] ?? '';
    $podaci['konto_nabavne_vrednosti'] = trim($_POST['konto_nabavne_vrednosti'] ?? '');
    $podaci['konto_ispravke_vrednosti'] = trim($_POST['konto_ispravke_vrednosti'] ?? '');
    $podaci['konto_troska_amortizacije'] = trim($_POST['konto_troska_amortizacije'] ?? '');
    $podaci['ukljucuje_se_u_popis'] = isset($_POST['ukljucuje_se_u_popis']) ? 1 : 0;
    $podaci['aktivna'] = isset($_POST['aktivna']) ? 1 : 0;

    if ($podaci['sifra'] === '' || $podaci['naziv'] === '') {
        $poruka = "Šifra i naziv klase su obavezni!";
    } else {
        try {
            $parametri = [
                ':sifra'       => $podaci['sifra'],
                ':naziv'       => $podaci['naziv'],
                ':opis'        => $podaci['opis'] !== '' ? $podaci['opis'] : null,
                ':nadredjena'  => $podaci['nadredjena_klasa_id'] !== '' ? (int)$podaci['nadredjena_klasa_id'] : null,
                ':tip'         => $podaci['tip_sredstva'],
                ':amortGrupa'  => $podaci['amortizaciona_grupa_id'] !== '' ? (int)$podaci['amortizaciona_grupa_id'] : null,
                ':metodaAmort' => $podaci['metoda_amortizacije_id'] !== '' ? (int)$podaci['metoda_amortizacije_id'] : null,
                ':konto1'      => $podaci['konto_nabavne_vrednosti'] !== '' ? $podaci['konto_nabavne_vrednosti'] : null,
                ':konto2'      => $podaci['konto_ispravke_vrednosti'] !== '' ? $podaci['konto_ispravke_vrednosti'] : null,
                ':konto3'      => $podaci['konto_troska_amortizacije'] !== '' ? $podaci['konto_troska_amortizacije'] : null,
                ':popis'       => $podaci['ukljucuje_se_u_popis'],
                ':aktivna'     => $podaci['aktivna'],
            ];

            if ($izmena) {
                // Zaštita: klasa ne sme biti nadređena sama sebi ili nekoj
                // od svojih (pod)klasa - to bi napravilo kružnu referencu.
                if ($parametri[':nadredjena'] !== null) {
                    $sveKlaseOsnovno = $pdo->query("SELECT id, nadredjena_klasa_id FROM klase_osnovnih_sredstava")->fetchAll();
                    $potomci = pronadjiPotomkeKlase($sveKlaseOsnovno, $id);
                    if ($parametri[':nadredjena'] === $id || in_array($parametri[':nadredjena'], $potomci, true)) {
                        throw new \RuntimeException("Klasa ne može biti nadređena sama sebi ili svojoj podklasi.");
                    }
                }

                $sql = "UPDATE klase_osnovnih_sredstava SET
                            sifra = :sifra,
                            naziv = :naziv,
                            opis = :opis,
                            nadredjena_klasa_id = :nadredjena,
                            tip_sredstva = :tip,
                            amortizaciona_grupa_id = :amortGrupa,
                            metoda_amortizacije_id = :metodaAmort,
                            konto_nabavne_vrednosti = :konto1,
                            konto_ispravke_vrednosti = :konto2,
                            konto_troska_amortizacije = :konto3,
                            ukljucuje_se_u_popis = :popis,
                            aktivna = :aktivna
                        WHERE id = :id";
                $parametri[':id'] = $id;
            } else {
                $sql = "INSERT INTO klase_osnovnih_sredstava
                            (sifra, naziv, opis, nadredjena_klasa_id, tip_sredstva, amortizaciona_grupa_id,
                             metoda_amortizacije_id, konto_nabavne_vrednosti, konto_ispravke_vrednosti,
                             konto_troska_amortizacije, ukljucuje_se_u_popis, aktivna)
                        VALUES
                            (:sifra, :naziv, :opis, :nadredjena, :tip, :amortGrupa,
                             :metodaAmort, :konto1, :konto2, :konto3, :popis, :aktivna)";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($parametri);

            header("Location: klase_index.php");
            exit;
        } catch (\RuntimeException $e) {
            $poruka = $e->getMessage();
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $poruka = "Klasa sa šifrom \"{$podaci['sifra']}\" već postoji. Izaberite drugu šifru.";
            } else {
                $poruka = "Greška pri upisu u bazu: " . $e->getMessage();
            }
        }
    }
} elseif ($izmena) {
    // Obični GET zahtev za izmenu (klik na "Izmeni") - učitaj postojeće podatke
    $stmt = $pdo->prepare("SELECT * FROM klase_osnovnih_sredstava WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $postojeca = $stmt->fetch();

    if (!$postojeca) {
        header("Location: klase_index.php");
        exit;
    }

    $podaci['sifra'] = $postojeca['sifra'];
    $podaci['naziv'] = $postojeca['naziv'];
    $podaci['opis'] = $postojeca['opis'] ?? '';
    $podaci['nadredjena_klasa_id'] = $postojeca['nadredjena_klasa_id'] ?? '';
    $podaci['tip_sredstva'] = $postojeca['tip_sredstva'];
    $podaci['amortizaciona_grupa_id'] = $postojeca['amortizaciona_grupa_id'] ?? '';
    $podaci['metoda_amortizacije_id'] = $postojeca['metoda_amortizacije_id'] ?? '';
    $podaci['konto_nabavne_vrednosti'] = $postojeca['konto_nabavne_vrednosti'] ?? '';
    $podaci['konto_ispravke_vrednosti'] = $postojeca['konto_ispravke_vrednosti'] ?? '';
    $podaci['konto_troska_amortizacije'] = $postojeca['konto_troska_amortizacije'] ?? '';
    $podaci['ukljucuje_se_u_popis'] = (int)$postojeca['ukljucuje_se_u_popis'];
    $podaci['aktivna'] = (int)$postojeca['aktivna'];
}

// Učitavanje šifarnika za padajuće menije
$sveKlaseZaSelect = ucitajKlaseHijerarhijski($pdo);
$amortGrupe = $pdo->query("SELECT id, sifra, naziv FROM amortizacione_grupe WHERE aktivna = 1 ORDER BY sifra")->fetchAll();
$metodeAmort = $pdo->query("SELECT id, naziv FROM metode_amortizacije WHERE aktivna = 1 ORDER BY naziv")->fetchAll();

// Za izmenu: izračunaj potomke da bismo ih isključili iz liste mogućih roditelja
// (klasa ne sme postati roditelj samoj sebi ili svojoj podklasi)
$iskljuceniIzListe = [];
if ($izmena) {
    $iskljuceniIzListe[] = $id;
    $sveKlaseOsnovno = $pdo->query("SELECT id, nadredjena_klasa_id FROM klase_osnovnih_sredstava")->fetchAll();
    $iskljuceniIzListe = array_merge($iskljuceniIzListe, pronadjiPotomkeKlase($sveKlaseOsnovno, $id));
}

$tipoviSredstva = [
    'MATERIJALNO' => 'Materijalno',
    'NEMATERIJALNO' => 'Nematerijalno',
    'INVESTICIONA_NEKRETNINA' => 'Investiciona nekretnina',
];

$naslovStranice = ($izmena ? 'Izmena klase' : 'Nova klasa') . ' osnovnog sredstva';
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2><?= $izmena ? 'Izmena klase: ' . htmlspecialchars($podaci['naziv']) : 'Nova klasa osnovnog sredstva' ?></h2>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php if ($izmena): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>

        <div class="red-2">
            <div class="form-group">
                <label>Šifra klase *</label>
                <input type="text" name="sifra" maxlength="20" required value="<?= htmlspecialchars($podaci['sifra']) ?>">
            </div>
            <div class="form-group">
                <label>Tip sredstva *</label>
                <select name="tip_sredstva" required>
                    <?php foreach ($tipoviSredstva as $vrednost => $labela): ?>
                        <option value="<?= $vrednost ?>" <?= $podaci['tip_sredstva'] === $vrednost ? 'selected' : '' ?>><?= $labela ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Naziv klase *</label>
            <input type="text" name="naziv" maxlength="150" required value="<?= htmlspecialchars($podaci['naziv']) ?>">
        </div>

        <div class="form-group">
            <label>Opis</label>
            <textarea name="opis"><?= htmlspecialchars($podaci['opis']) ?></textarea>
        </div>

        <div class="form-group">
            <label>Nadređena klasa <span class="napomena-polje">(ostavite prazno za korensku klasu)</span></label>
            <select name="nadredjena_klasa_id">
                <option value="">-- Nema (korenska klasa) --</option>
                <?php foreach ($sveKlaseZaSelect as $k): ?>
                    <?php if (in_array((int)$k['id'], $iskljuceniIzListe, true)) continue; ?>
                    <option value="<?= $k['id'] ?>" <?= (string)$podaci['nadredjena_klasa_id'] === (string)$k['id'] ? 'selected' : '' ?>>
                        <?= str_repeat('— ', $k['nivo']) . htmlspecialchars($k['sifra'] . ' - ' . $k['naziv']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="red-2">
            <div class="form-group">
                <label>Amortizaciona grupa</label>
                <select name="amortizaciona_grupa_id">
                    <option value="">-- Nije definisano --</option>
                    <?php foreach ($amortGrupe as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= (string)$podaci['amortizaciona_grupa_id'] === (string)$ag['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ag['sifra'] . ' - ' . $ag['naziv']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Metoda amortizacije</label>
                <select name="metoda_amortizacije_id">
                    <option value="">-- Nije definisano --</option>
                    <?php foreach ($metodeAmort as $ma): ?>
                        <option value="<?= $ma['id'] ?>" <?= (string)$podaci['metoda_amortizacije_id'] === (string)$ma['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ma['naziv']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="naslov-podsekcije">Konta glavne knjige <span class="napomena-polje">(opciono, koriste se pri knjiženju)</span></div>
        <div class="red-2">
            <div class="form-group">
                <label class="napomena-polje">Konto nabavne vrednosti</label>
                <input type="text" name="konto_nabavne_vrednosti" maxlength="20" value="<?= htmlspecialchars($podaci['konto_nabavne_vrednosti']) ?>">
            </div>
            <div class="form-group">
                <label class="napomena-polje">Konto ispravke vrednosti</label>
                <input type="text" name="konto_ispravke_vrednosti" maxlength="20" value="<?= htmlspecialchars($podaci['konto_ispravke_vrednosti']) ?>">
            </div>
            <div class="form-group">
                <label class="napomena-polje">Konto troška amortizacije</label>
                <input type="text" name="konto_troska_amortizacije" maxlength="20" value="<?= htmlspecialchars($podaci['konto_troska_amortizacije']) ?>">
            </div>
        </div>

        <div class="form-group checkbox-group">
            <input type="checkbox" name="ukljucuje_se_u_popis" id="ukljucuje_se_u_popis" <?= $podaci['ukljucuje_se_u_popis'] ? 'checked' : '' ?>>
            <label for="ukljucuje_se_u_popis">Sredstva ove klase uključuju se u fizički popis <span class="napomena-polje">(isključi npr. za nematerijalna ulaganja)</span></label>
        </div>

        <div class="form-group checkbox-group">
            <input type="checkbox" name="aktivna" id="aktivna" <?= $podaci['aktivna'] ? 'checked' : '' ?>>
            <label for="aktivna">Klasa je aktivna</label>
        </div>

        <button type="submit" class="btn">Sačuvaj</button>
        <a href="klase_index.php" class="btn-cancel">Otkaži</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
