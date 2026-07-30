<?php
/**
 * kretanje_index.php
 * -------------------
 * Čvorna (hub) stranica menija "Evidencija kretanja".
 */

require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$naslovStranice = 'Evidencija kretanja';
require_once 'header.php';
?>

<h1>Evidencija kretanja osnovnih sredstava</h1>
<p class="napomena-polje" style="margin-bottom: 20px;">
    Sve akcije vezane za premeštaj i zaduženje osnovnih sredstava, kao i pregled istorije kretanja.
</p>

<div style="display:flex; flex-wrap:wrap; gap:16px;">

    <div class="form-container" style="flex:1; min-width:260px; margin:0;">
        <h3 style="margin-top:0;">Premeštaj</h3>
        <p class="napomena-polje">Promena lokacije i/ili mesta troška za jedno ili više sredstava, sa brojem dokumenta i štampom.</p>
        <a href="premestaj_form.php" class="btn">+ Novi premeštaj</a>
        <a href="premestaji_index.php" class="btn-cancel" style="margin-left:8px;">Istorija premeštaja</a>
    </div>

    <div class="form-container" style="flex:1; min-width:260px; margin:0;">
        <h3 style="margin-top:0;">Zaduženje (revers)</h3>
        <p class="napomena-polje">Zaduživanje sredstva zaposlenom putem reversa. Izdavanje novog reversa automatski razdužuje sredstvo sa eventualnog prethodnog reversa.</p>
        <a href="revers_form.php" class="btn">+ Novi revers</a>
        <a href="reversi_index.php" class="btn-cancel" style="margin-left:8px;">Pregled reversa</a>
    </div>

    <div class="form-container" style="flex:1; min-width:260px; margin:0;">
        <h3 style="margin-top:0;">Zaduženja po zaposlenom</h3>
        <p class="napomena-polje">Pregled ko trenutno šta drži - grupisano po zaposlenom, korisno pre popisa.</p>
        <a href="zaduzenja_pregled.php" class="btn">Otvori pregled</a>
    </div>

    <div class="form-container" style="flex:1; min-width:260px; margin:0;">
        <h3 style="margin-top:0;">Istorija kretanja</h3>
        <p class="napomena-polje">Objedinjen hronološki pregled svih zaduženja, premeštaja i razduženja, sa filterima.</p>
        <a href="kretanje_istorija.php" class="btn">Otvori istoriju</a>
    </div>

</div>

<?php require_once 'footer.php'; ?>
