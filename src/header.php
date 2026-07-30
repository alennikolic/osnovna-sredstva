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
 */

$naslovStranice = $naslovStranice ?? 'Osnovna sredstva';
$trenutnaStranica = basename($_SERVER['SCRIPT_NAME']);

// 'sekcija' je lista fajlova koji pripadaju istoj celini - npr. i pregled i
// forma za unos osnovnih sredstava treba da drže istu stavku menija aktivnom.
$stavkeMenija = [
    ['naziv' => 'Osnovna sredstva', 'link' => 'index.php', 'sekcija' => ['index.php', 'os_form.php', 'os_pregled.php']],
    ['naziv' => 'Klase osnovnih sredstava', 'link' => 'klase_index.php', 'sekcija' => ['klase_index.php', 'klase_form.php']],
    ['naziv' => 'Zaposleni', 'link' => 'zaposleni_index.php', 'sekcija' => ['zaposleni_index.php', 'zaposleni_form.php']],
    ['naziv' => 'Lokacije', 'link' => 'lokacije_index.php', 'sekcija' => ['lokacije_index.php', 'lokacije_form.php']],
    ['naziv' => 'Mesta troška', 'link' => 'mesta_troska_index.php', 'sekcija' => ['mesta_troska_index.php', 'mesta_troska_form.php']],
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
        <?php foreach ($stavkeMenija as $stavka): ?>
            <a href="<?= htmlspecialchars($stavka['link']) ?>"
               class="<?= in_array($trenutnaStranica, $stavka['sekcija'], true) ? 'aktivan' : '' ?>">
                <?= htmlspecialchars($stavka['naziv']) ?>
            </a>
        <?php endforeach; ?>
    </div>
