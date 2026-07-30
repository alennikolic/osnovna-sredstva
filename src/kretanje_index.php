<?php
/**
 * kretanje_index.php
 * -------------------
 * Čvorna (hub) stranica menija "Evidencija kretanja" - objedinjuje na jednom
 * mestu sve akcije vezane za kretanje osnovnih sredstava: premeštaj (promena
 * lokacije/mesta troška/zaduženog lica), zaduženje i razduženje putem
 * reversa, i (u budućnosti) objedinjenu istoriju svih transakcija.
 *
 * Postojeći header.php nema padajuće podmenije, pa je ovo najjednostavniji
 * način da se više modula grupiše pod jednu stavku glavnog menija bez
 * menjanja navigacione komponente.
 */

require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$naslovStranice = 'Evidencija kretanja';
require_once 'header.php';
?>

<h1>Evidencija kretanja osnovnih sredstava</h1>
<p class="napomena-polje" style="margin-bottom: 20px;">
    Sve akcije vezane za premeštaj, zaduženje i razduženje osnovnih sredstava, kao i pregled istorije kretanja.
</p>

<div style="display:flex; flex-wrap:wrap; gap:16px;">

    <div class="form-container" style="flex:1; min-width:260px; margin:0;">
        <h3 style="margin-top:0;">Premeštaj</h3>
        <p class="napomena-polje">Promena lokacije, mesta troška ili zaduženog lica za osnovno sredstvo.</p>
        <a href="premestaj_form.php" class="btn">+ Novi premeštaj</a>
        <a href="premestaji_index.php" class="btn-cancel" style="margin-left:8px;">Istorija premeštaja</a>
    </div>

    <div class="form-container" style="flex:1; min-width:260px; margin:0;">
        <h3 style="margin-top:0;">Zaduženje (revers)</h3>
        <p class="napomena-polje">Formalno zaduživanje osnovnog sredstva zaposlenom putem reversa.</p>
        <a href="revers_form.php" class="btn">+ Novi revers</a>
        <a href="reversi_index.php" class="btn-cancel" style="margin-left:8px;">Pregled reversa</a>
    </div>

    <div class="form-container" style="flex:1; min-width:260px; margin:0;">
        <h3 style="margin-top:0;">Razduženje reversa</h3>
        <p class="napomena-polje">Vraćanje ranije zaduženih sredstava - otvorite revers i označite koje stavke se vraćaju.</p>
        <a href="reversi_index.php" class="btn">Otvori reverse</a>
    </div>

    <div class="form-container" style="flex:1; min-width:260px; margin:0; opacity:0.6;">
        <h3 style="margin-top:0;">Istorija kretanja</h3>
        <p class="napomena-polje">
            Objedinjen hronološki pregled svih transakcija (premeštaji, zaduženja, razduženja...).
            <strong>Uskoro</strong>.
        </p>
    </div>

</div>

<?php require_once 'footer.php'; ?>
