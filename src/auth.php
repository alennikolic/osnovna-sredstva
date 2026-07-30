<?php
/**
 * auth.php
 * --------
 * Pokreće sesiju i pruža funkcije za proveru prijave. Uključuje se na vrhu
 * SVAKE stranice (pre bilo kakvog HTML izlaza) - zaštićene stranice zatim
 * pozivaju zahtevajPrijavu() odmah posle require_once-a.
 *
 * Posle uspešne prijave (u login.php), $_SESSION['korisnik'] sadrži:
 *   id, korisnicko_ime, ime_prezime, rola_sifra, rola_naziv
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Preusmerava na login.php ako korisnik nije prijavljen, čuvajući trenutnu
 * putanju (sa query stringom) da se posle uspešne prijave vrati tačno tu
 * gde je krenuo.
 */
function zahtevajPrijavu(): void
{
    if (empty($_SESSION['korisnik'])) {
        $povratak = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: login.php?povratak=" . $povratak);
        exit;
    }
}

/**
 * Vraća niz sa podacima o trenutno prijavljenom korisniku
 * (id, korisnicko_ime, ime_prezime, rola_sifra, rola_naziv), ili null
 * ako niko nije prijavljen.
 */
function trenutniKorisnik(): ?array
{
    return $_SESSION['korisnik'] ?? null;
}
