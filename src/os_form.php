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

// Podrazumevane vrednosti forme
$podaci = [
    'inventarski_broj' => '',
    'naziv' => '',
    'opis' => '',
    'klasa_id' => '',
    'status_id' => '',
    'nabavna_vrednost' => '0.00',
    'datum_nabavke' => date('Y-m-d'),
    'datum_stavljanja_u_upotrebu' => '',
    'lokacija_id' => '',
    'mesto_troska_id' => '',
    'odgovorno_lice' => '',
    'zaposleni_id' => '',
    'proizvodjac' => '',
    'model' => '',
    'serijski_broj' => '',
    'napomena' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $podaci['inventarski_broj'] = trim($_POST['inventarski_broj'] ?? '');
    $podaci['naziv'] = trim($_POST['naziv'] ?? '');
    $podaci['opis'] = trim($_POST['opis'] ?? '');
    $podaci['klasa_id'] = $_POST['klasa_id'] ?? '';
    $podaci['status_id'] = $_POST['status_id'] ?? '';
    $podaci['nabavna_vrednost'] = $_POST['nabavna_vrednost'] ?? '0';
    $podaci['datum_nabavke'] = trim($_POST['datum_nabavke'] ?? '');
    $podaci['datum_stavljanja_u_upotrebu'] = trim($_POST['datum_stavljanja_u_upotrebu'] ?? '');
    $podaci['lokacija_id'] = $_POST['lokacija_id'] ?? '';
    $podaci['mesto_troska_id'] = $_POST['mesto_troska_id'] ?? '';
    $podaci['odgovorno_lice'] = trim($_POST['odgovorno_lice'] ?? '');
    $podaci['zaposleni_id'] = $_POST['zaposleni_id'] ?? '';
    $podaci['proizvodjac'] = trim($_POST['proizvodjac'] ?? '');
    $podaci['model'] = trim($_POST['model'] ?? '');
    $podaci['serijski_broj'] = trim($_POST['serijski_broj'] ?? '');
    $podaci['napomena'] = trim($_POST['napomena'] ?? '');

    if (!empty($podaci['inventarski_broj']) && !empty($podaci['naziv']) && !empty($podaci['klasa_id']) && !empty($podaci['status_id'])) {
        try {
            $parametri = [
                ':inv'              => $podaci['inventarski_broj'],
                ':naziv'            => $podaci['naziv'],
                ':opis'             => $podaci['opis'] !== '' ? $podaci['opis'] : null,
                ':klasa'            => $podaci['klasa_id'],
                ':status'           => $podaci['status_id'],
                ':nabavna'          => $podaci['nabavna_vrednost'],
                ':datum_nabavke'    => $podaci['datum_nabavke'],
                ':datum_upotreba'   => $podaci['datum_stavljanja_u_upotrebu'] !== '' ? $podaci['datum_stavljanja_u_upotrebu'] : null,
                ':lokacija'         => $podaci['lokacija_id'] !== '' ? (int)$podaci['lokacija_id'] : null,
                ':mesto_troska'     => $podaci['mesto_troska_id'] !== '' ? (int)$podaci['mesto_troska_id'] : null,
                ':lice'             => $podaci['odgovorno_lice'] !== '' ? $podaci['odgovorno_lice'] : null,
                ':zaposleni'        => $podaci['zaposleni_id'] !== '' ? (int)$podaci['zaposleni_id'] : null,
                ':proizvodjac'      => $podaci['proizvodjac'] !== '' ? $podaci['proizvodjac'] : null,
                ':model'            => $podaci['model'] !== '' ? $podaci['model'] : null,
                ':serijski'         => $podaci['serijski_broj'] !== '' ? $podaci['serijski_broj'] : null,
                ':napomena'         => $podaci['napomena'] !== '' ? $podaci['napomena'] : null,
            ];

            if ($izmena) {
                // NAMERNO se ovde NE dira osnovica_za_amortizaciju, akumulirana_amortizacija
                // ni sadasnja_knjigovodstvena_vrednost - to su knjigovodstvene vrednosti koje
                // treba da menja isključivo budući modul za obračun amortizacije/revalorizaciju,
                // ne obična izmena matičnih podataka sredstva.
                $sql = "UPDATE osnovna_sredstva SET
                            inventarski_broj = :inv,
                            naziv = :naziv,
                            opis = :opis,
                            klasa_id = :klasa,
                            status_id = :status,
                            nabavna_vrednost = :nabavna,
                            datum_nabavke = :datum_nabavke,
                            datum_stavljanja_u_upotrebu = :datum_upotreba,
                            lokacija_id = :lokacija,
                            mesto_troska_id = :mesto_troska,
                            odgovorno_lice = :lice,
                            zaposleni_id = :zaposleni,
                            proizvodjac = :proizvodjac,
                            model = :model,
                            serijski_broj = :serijski,
                            napomena = :napomena
                        WHERE id = :id";
                $parametri[':id'] = $id;
            } else {
                // Za novo sredstvo: nabavna_vrednost = osnovica_za_amortizaciju =
                // sadasnja_knjigovodstvena_vrednost (nema još obračunate amortizacije).
                $parametri[':osnovica'] = $podaci['nabavna_vrednost'];
                $parametri[':knjig_vrednost'] = $podaci['nabavna_vrednost'];

                $sql = "INSERT INTO osnovna_sredstva
                            (inventarski_broj, naziv, opis, klasa_id, status_id, nabavna_vrednost,
                             osnovica_za_amortizaciju, sadasnja_knjigovodstvena_vrednost,
                             datum_nabavke, datum_stavljanja_u_upotrebu, lokacija_id, mesto_troska_id,
                             odgovorno_lice, zaposleni_id, proizvodjac, model, serijski_broj, napomena)
                        VALUES
                            (:inv, :naziv, :opis, :klasa, :status, :nabavna,
                             :osnovica, :knjig_vrednost,
                             :datum_nabavke, :datum_upotreba, :lokacija, :mesto_troska,
                             :lice, :zaposleni, :proizvodjac, :model, :serijski, :napomena)";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($parametri);

            header("Location: index.php");
            exit;
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                $poruka = "Sredstvo sa inventarskim brojem \"{$podaci['inventarski_broj']}\" već postoji.";
            } else {
                $poruka = "Greška pri upisu u bazu: " . $e->getMessage();
            }
        }
    } else {
        $poruka = "Molimo popunite sva obavezna polja!";
    }
} elseif ($izmena) {
    // Obični GET zahtev za izmenu (klik na "Izmeni") - učitaj postojeće podatke
    $stmt = $pdo->prepare("SELECT * FROM osnovna_sredstva WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $postojece = $stmt->fetch();

    if (!$postojece) {
        header("Location: index.php");
        exit;
    }

    $podaci['inventarski_broj'] = $postojece['inventarski_broj'];
    $podaci['naziv'] = $postojece['naziv'];
    $podaci['opis'] = $postojece['opis'] ?? '';
    $podaci['klasa_id'] = $postojece['klasa_id'];
    $podaci['status_id'] = $postojece['status_id'];
    $podaci['nabavna_vrednost'] = $postojece['nabavna_vrednost'];
    $podaci['datum_nabavke'] = $postojece['datum_nabavke'];
    $podaci['datum_stavljanja_u_upotrebu'] = $postojece['datum_stavljanja_u_upotrebu'] ?? '';
    $podaci['lokacija_id'] = $postojece['lokacija_id'] ?? '';
    $podaci['mesto_troska_id'] = $postojece['mesto_troska_id'] ?? '';
    $podaci['odgovorno_lice'] = $postojece['odgovorno_lice'] ?? '';
    $podaci['zaposleni_id'] = $postojece['zaposleni_id'] ?? '';
    $podaci['proizvodjac'] = $postojece['proizvodjac'] ?? '';
    $podaci['model'] = $postojece['model'] ?? '';
    $podaci['serijski_broj'] = $postojece['serijski_broj'] ?? '';
    $podaci['napomena'] = $postojece['napomena'] ?? '';
}

// Učitavanje šifarnika za padajuće menije
$klase = $pdo->query("SELECT id, naziv FROM klase_osnovnih_sredstava WHERE aktivna = 1 ORDER BY naziv")->fetchAll();
$statusi = $pdo->query("SELECT id, naziv FROM statusi_sredstva ORDER BY redosled_prikaza")->fetchAll();
$lokacije = $pdo->query("SELECT id, naziv FROM lokacije WHERE aktivna = 1 ORDER BY naziv")->fetchAll();
$mestaTroska = $pdo->query("SELECT id, naziv FROM mesta_troska WHERE aktivno = 1 ORDER BY naziv")->fetchAll();
$zaposleniLista = $pdo->query("SELECT id, ime, prezime FROM zaposleni WHERE aktivan = 1 ORDER BY prezime, ime")->fetchAll();

$naslovStranice = $izmena ? 'Izmena osnovnog sredstva' : 'Novo Osnovno Sredstvo';
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2><?= $izmena ? 'Izmena sredstva: ' . htmlspecialchars($podaci['naziv']) : 'Unos novog osnovnog sredstva' ?></h2>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php if ($izmena): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>

        <div class="red-2">
            <div class="form-group">
                <label>Inventarski broj *</label>
                <input type="text" name="inventarski_broj" required value="<?= htmlspecialchars($podaci['inventarski_broj']) ?>">
            </div>
            <div class="form-group">
                <label>Naziv sredstva *</label>
                <input type="text" name="naziv" required value="<?= htmlspecialchars($podaci['naziv']) ?>">
            </div>
        </div>

        <div class="red-2">
            <div class="form-group">
                <label>Klasa sredstva *</label>
                <select name="klasa_id" required>
                    <option value="">-- Izaberite klasu --</option>
                    <?php foreach ($klase as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= (string)$podaci['klasa_id'] === (string)$k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['naziv']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Status *</label>
                <select name="status_id" required>
                    <?php foreach ($statusi as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= (string)$podaci['status_id'] === (string)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['naziv']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Opis</label>
            <textarea name="opis"><?= htmlspecialchars($podaci['opis']) ?></textarea>
        </div>

        <div class="red-2">
            <div class="form-group">
                <label>Nabavna vrednost (RSD) *</label>
                <input type="number" step="0.01" name="nabavna_vrednost" required value="<?= htmlspecialchars($podaci['nabavna_vrednost']) ?>">
            </div>
            <div class="form-group">
                <label>Datum nabavke *</label>
                <input type="date" name="datum_nabavke" required value="<?= htmlspecialchars($podaci['datum_nabavke']) ?>">
            </div>
        </div>

        <div class="red-2">
            <div class="form-group">
                <label>Proizvođač</label>
                <input type="text" name="proizvodjac" maxlength="150" value="<?= htmlspecialchars($podaci['proizvodjac']) ?>">
            </div>
            <div class="form-group">
                <label>Model</label>
                <input type="text" name="model" maxlength="150" value="<?= htmlspecialchars($podaci['model']) ?>">
            </div>
        </div>

        <div class="red-2">
            <div class="form-group">
                <label>Serijski broj</label>
                <input type="text" name="serijski_broj" maxlength="100" value="<?= htmlspecialchars($podaci['serijski_broj']) ?>">
            </div>
            <div class="form-group">
                <label>Datum stavljanja u upotrebu</label>
                <input type="date" name="datum_stavljanja_u_upotrebu" value="<?= htmlspecialchars($podaci['datum_stavljanja_u_upotrebu']) ?>">
            </div>
        </div>

        <div class="red-2">
            <div class="form-group">
                <label>Lokacija</label>
                <select name="lokacija_id">
                    <option value="">-- Nije dodeljeno --</option>
                    <?php foreach ($lokacije as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= (string)$podaci['lokacija_id'] === (string)$l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['naziv']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Mesto troška</label>
                <select name="mesto_troska_id">
                    <option value="">-- Nije dodeljeno --</option>
                    <?php foreach ($mestaTroska as $mt): ?>
                        <option value="<?= $mt['id'] ?>" <?= (string)$podaci['mesto_troska_id'] === (string)$mt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($mt['naziv']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="naslov-podsekcije">Zaduženje <span class="napomena-polje">(opciono - izaberite zaposlenog ILI upišite ime, ne mora oboje)</span></div>
        <div class="red-2">
            <div class="form-group">
                <label>Zaduženi zaposleni</label>
                <select name="zaposleni_id">
                    <option value="">-- Nije zaduženo --</option>
                    <?php foreach ($zaposleniLista as $z): ?>
                        <option value="<?= $z['id'] ?>" <?= (string)$podaci['zaposleni_id'] === (string)$z['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($z['prezime'] . ' ' . $z['ime']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Odgovorno lice <span class="napomena-polje">(slobodan tekst - za lica van evidencije zaposlenih)</span></label>
                <input type="text" name="odgovorno_lice" value="<?= htmlspecialchars($podaci['odgovorno_lice']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Napomena</label>
            <textarea name="napomena"><?= htmlspecialchars($podaci['napomena']) ?></textarea>
        </div>

        <button type="submit" class="btn">Sačuvaj</button>
        <a href="index.php" class="btn-cancel">Otkaži</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
