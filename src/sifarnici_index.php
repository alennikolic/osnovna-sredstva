<?php
/**
 * sifarnici_index.php
 * --------------------
 * Čvorna (hub) stranica menija "Šifarnici" - objedinjuje na jednom mestu sve
 * matične/šifrirane podatke koji se koriste u celoj aplikaciji. Isti obrazac
 * kao kretanje_index.php.
 */

require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$naslovStranice = 'Šifarnici';
require_once 'header.php';
?>

<h1>Šifarnici</h1>
<p class="napomena-polje" style="margin-bottom: 20px;">
    Matični/šifrirani podaci koji se koriste u celoj aplikaciji.
</p>

<div style="display:flex; flex-wrap:wrap; gap:16px;">

    <div class="form-container" style="flex:1; min-width:260px; margin:0;">
        <h3 style="margin-top:0;">Klase osnovnih sredstava</h3>
        <p class="napomena-polje">Hijerarhijska klasifikacija sredstava, sa podešavanjima za amortizaciju i popis.</p>
        <a href="klase_index.php" class="btn">Otvori</a>
    </div>

    <div class="form-container" style="flex:1; min-width:260px; margin:0;">
        <h3 style="margin-top:0;">Lokacije</h3>
        <p class="napomena-polje">Fizičke lokacije na kojima se nalaze osnovna sredstva (objekat, sprat, prostorija...).</p>
        <a href="lokacije_index.php" class="btn">Otvori</a>
    </div>

    <div class="form-container" style="flex:1; min-width:260px; margin:0;">
        <h3 style="margin-top:0;">Mesta troška</h3>
        <p class="napomena-polje">Organizacione jedinice zadužene za osnovna sredstva.</p>
        <a href="mesta_troska_index.php" class="btn">Otvori</a>
    </div>

    <div class="form-container" style="flex:1; min-width:260px; margin:0;">
        <h3 style="margin-top:0;">Dobavljači</h3>
        <p class="napomena-polje">Šifarnik dobavljača od kojih se nabavljaju osnovna sredstva.</p>
        <a href="dobavljaci_index.php" class="btn">Otvori</a>
    </div>

</div>

<?php require_once 'footer.php'; ?>
