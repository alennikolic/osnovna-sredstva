<?php
/**
 * header.php
 * ----------
 * Zajednički <head> i navigacija za sve stranice aplikacije.
 * Stranica koja ga uključuje može (opciono) PRE require_once postaviti:
 *   $naslovStranice - tekst za <title> (podrazumevano: "Osnovna sredstva")
 *
 * VAŽNO: dodavanje novog modula (npr. zaposleni) znači samo dodavanje
 * jedne stavke u $stavkeMenija ispod - meni se automatski ažurira svuda.
 *
 * Pretpostavlja da je stranica koja ga uključuje VEĆ pozvala zahtevajPrijavu()
 * (iz auth.php), pa je $_SESSION['korisnik'] uvek popunjeno u ovom trenutku -
 * ali auth.php se ipak ponovo uključuje ovde (require_once je idempotentan)
 * kao dodatna zaštita ako neka stranica to zaboravi.
 */

require_once 'auth.php';

$naslovStranice = $naslovStranice ?? 'Osnovna sredstva';
$trenutnaStranica = basename($_SERVER['SCRIPT_NAME']);
$korisnik = trenutniKorisnik();

// 'sekcija' je lista fajlova koji pripadaju istoj celini - npr. i pregled i
// forma za unos osnovnih sredstava treba da drže istu stavku menija aktivnom.
$stavkeMenija = [
    ['naziv' => 'Osnovna sredstva', 'link' => 'index.php', 'sekcija' => ['index.php', 'os_form.php', 'os_pregled.php']],
    ['naziv' => 'Klase osnovnih sredstava', 'link' => 'klase_index.php', 'sekcija' => ['klase_index.php', 'klase_form.php']],
    ['naziv' => 'Zaposleni', 'link' => 'zaposleni_index.php', 'sekcija' => ['zaposleni_index.php', 'zaposleni_form.php']],
    ['naziv' => 'Lokacije', 'link' => 'lokacije_index.php', 'sekcija' => ['lokacije_index.php', 'lokacije_form.php']],
    ['naziv' => 'Mesta troška', 'link' => 'mesta_troska_index.php', 'sekcija' => ['mesta_troska_index.php', 'mesta_troska_form.php']],
    ['naziv' => 'Popis', 'link' => 'popisi_index.php', 'sekcija' => ['popisi_index.php', 'popisi_form.php', 'popis_pregled.php', 'popis_stavka_form.php']],
];
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($naslovStranice) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

    <div class="nav-bar">
        <div class="nav-linkovi">
            <?php foreach ($stavkeMenija as $stavka): ?>
                <a href="<?= htmlspecialchars($stavka['link']) ?>"
                   class="<?= in_array($trenutnaStranica, $stavka['sekcija'], true) ? 'aktivan' : '' ?>">
                    <?= htmlspecialchars($stavka['naziv']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if ($korisnik): ?>
        <div class="nav-korisnik">
            <span><?= htmlspecialchars($korisnik['ime_prezime']) ?> <span class="napomena-polje">(<?= htmlspecialchars($korisnik['rola_naziv']) ?>)</span></span>
            <a href="logout.php">Odjava</a>
        </div>
        <?php endif; ?>
    </div>
