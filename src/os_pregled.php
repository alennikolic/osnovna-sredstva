<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$id = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : null;
if (empty($id)) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare(
    "SELECT
        os.*,
        k.naziv AS naziv_klase,
        s.naziv AS naziv_statusa,
        l.naziv AS naziv_lokacije,
        mt.naziv AS naziv_mesta_troska,
        CASE WHEN z.id IS NOT NULL THEN CONCAT(z.ime, ' ', z.prezime) ELSE NULL END AS naziv_zaposlenog
     FROM osnovna_sredstva os
     JOIN klase_osnovnih_sredstava k ON k.id = os.klasa_id
     JOIN statusi_sredstva s ON s.id = os.status_id
     LEFT JOIN lokacije l ON l.id = os.lokacija_id
     LEFT JOIN mesta_troska mt ON mt.id = os.mesto_troska_id
     LEFT JOIN zaposleni z ON z.id = os.zaposleni_id
     WHERE os.id = :id"
);
$stmt->execute([':id' => $id]);
$sredstvo = $stmt->fetch();

if (!$sredstvo) {
    header("Location: index.php");
    exit;
}

// Zaduženo lice - prioritet ima formalni zaposleni iz evidencije,
// u suprotnom slobodan tekst iz odgovorno_lice.
$zaduzenoLice = $sredstvo['naziv_zaposlenog'] ?? $sredstvo['odgovorno_lice'] ?? null;

$naslovStranice = 'Pregled: ' . $sredstvo['naziv'];
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2><?= htmlspecialchars($sredstvo['naziv']) ?></h2>
    <p class="napomena-polje" style="margin-top:-10px; margin-bottom: 20px;">
        Inventarski broj: <strong><?= htmlspecialchars($sredstvo['inventarski_broj']) ?></strong>
    </p>

    <div class="detalj-sekcija">Osnovni podaci</div>
    <div class="detalj-red">
        <span class="detalj-labela">Klasa</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($sredstvo['naziv_klase']) ?></span>
    </div>
    <div class="detalj-red">
        <span class="detalj-labela">Status</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($sredstvo['naziv_statusa']) ?></span>
    </div>
    <?php if (!empty($sredstvo['opis'])): ?>
    <div class="detalj-red">
        <span class="detalj-labela">Opis</span>
        <span class="detalj-vrednost"><?= nl2br(htmlspecialchars($sredstvo['opis'])) ?></span>
    </div>
    <?php endif; ?>

    <div class="detalj-sekcija">Lokacija i zaduženje</div>
    <div class="detalj-red">
        <span class="detalj-labela">Lokacija</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($sredstvo['naziv_lokacije'] ?? '—') ?></span>
    </div>
    <div class="detalj-red">
        <span class="detalj-labela">Mesto troška</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($sredstvo['naziv_mesta_troska'] ?? '—') ?></span>
    </div>
    <div class="detalj-red">
        <span class="detalj-labela">Zaduženo lice</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($zaduzenoLice ?? '—') ?></span>
    </div>

    <div class="detalj-sekcija">Finansijski podaci</div>
    <div class="detalj-red">
        <span class="detalj-labela">Nabavna vrednost</span>
        <span class="detalj-vrednost"><?= number_format($sredstvo['nabavna_vrednost'], 2, ',', '.') ?> RSD</span>
    </div>
    <div class="detalj-red">
        <span class="detalj-labela">Osnovica za amortizaciju</span>
        <span class="detalj-vrednost"><?= number_format($sredstvo['osnovica_za_amortizaciju'], 2, ',', '.') ?> RSD</span>
    </div>
    <div class="detalj-red">
        <span class="detalj-labela">Akumulirana amortizacija</span>
        <span class="detalj-vrednost"><?= number_format($sredstvo['akumulirana_amortizacija'], 2, ',', '.') ?> RSD</span>
    </div>
    <div class="detalj-red">
        <span class="detalj-labela">Sadašnja knjigovodstvena vrednost</span>
        <span class="detalj-vrednost">
            <?= $sredstvo['sadasnja_knjigovodstvena_vrednost'] !== null
                ? number_format($sredstvo['sadasnja_knjigovodstvena_vrednost'], 2, ',', '.') . ' RSD'
                : '—' ?>
        </span>
    </div>
    <div class="detalj-red">
        <span class="detalj-labela">Amortizuje se</span>
        <span class="detalj-vrednost"><?= $sredstvo['da_li_se_amortizuje'] ? 'Da' : 'Ne' ?></span>
    </div>

    <div class="detalj-sekcija">Datumi</div>
    <div class="detalj-red">
        <span class="detalj-labela">Datum nabavke</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($sredstvo['datum_nabavke']) ?></span>
    </div>
    <div class="detalj-red">
        <span class="detalj-labela">Datum stavljanja u upotrebu</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($sredstvo['datum_stavljanja_u_upotrebu'] ?? '—') ?></span>
    </div>

    <?php if (!empty($sredstvo['proizvodjac']) || !empty($sredstvo['model']) || !empty($sredstvo['serijski_broj'])): ?>
    <div class="detalj-sekcija">Identifikacija</div>
        <?php if (!empty($sredstvo['proizvodjac'])): ?>
        <div class="detalj-red">
            <span class="detalj-labela">Proizvođač</span>
            <span class="detalj-vrednost"><?= htmlspecialchars($sredstvo['proizvodjac']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($sredstvo['model'])): ?>
        <div class="detalj-red">
            <span class="detalj-labela">Model</span>
            <span class="detalj-vrednost"><?= htmlspecialchars($sredstvo['model']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($sredstvo['serijski_broj'])): ?>
        <div class="detalj-red">
            <span class="detalj-labela">Serijski broj</span>
            <span class="detalj-vrednost"><?= htmlspecialchars($sredstvo['serijski_broj']) ?></span>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($sredstvo['napomena'])): ?>
    <div class="detalj-sekcija">Napomena</div>
    <div class="detalj-red">
        <span class="detalj-vrednost"><?= nl2br(htmlspecialchars($sredstvo['napomena'])) ?></span>
    </div>
    <?php endif; ?>

    <div style="margin-top: 25px;">
        <a href="os_form.php?id=<?= $sredstvo['id'] ?>" class="btn">Izmeni</a>
        <a href="premestaj_form.php?sredstvo_id=<?= $sredstvo['id'] ?>" class="btn">Premesti</a>
        <a href="kretanje_istorija.php?pojam=<?= urlencode($sredstvo['inventarski_broj']) ?>" class="btn">Istorija kretanja</a>
        <a href="index.php" class="btn-cancel">Nazad na pregled</a>
    </div>
</div>

<?php require_once 'footer.php'; ?>
