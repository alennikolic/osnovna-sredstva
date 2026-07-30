<?php
/**
 * helpers.php
 * -----------
 * Pomoćne funkcije koje se dele između više modula aplikacije.
 * Trenutno se koriste za rad sa hijerarhijom klasa osnovnih sredstava
 * (klase_osnovnih_sredstava.nadredjena_klasa_id je self-referencing FK).
 */

/**
 * Učitava SVE klase osnovnih sredstava iz baze (zajedno sa nazivom povezane
 * amortizacione grupe i metode amortizacije) i vraća ih kao ravnu listu
 * složenu u hijerarhijski redosled (prvo roditelj, pa odmah ispod njegova
 * deca), sa dodatim ključem 'nivo' koji predstavlja dubinu u hijerarhiji
 * (0 = korenska/najviša klasa).
 *
 * Ima ugrađenu zaštitu od:
 *  - kružnih referenci u podacima (da se ne dogodi beskonačna petlja)
 *  - "osirotelih" klasa čiji roditelj više ne postoji (ipak se prikazuju)
 *
 * @return array Lista asocijativnih nizova (redova) sa dodatim 'nivo'.
 */
function ucitajKlaseHijerarhijski(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT
            k.id, k.sifra, k.naziv, k.opis, k.nadredjena_klasa_id, k.tip_sredstva,
            k.amortizaciona_grupa_id, k.metoda_amortizacije_id,
            k.konto_nabavne_vrednosti, k.konto_ispravke_vrednosti, k.konto_troska_amortizacije,
            k.ukljucuje_se_u_popis, k.aktivna,
            ag.naziv AS naziv_amort_grupe,
            ma.naziv AS naziv_metode_amort
        FROM klase_osnovnih_sredstava k
        LEFT JOIN amortizacione_grupe ag ON ag.id = k.amortizaciona_grupa_id
        LEFT JOIN metode_amortizacije ma ON ma.id = k.metoda_amortizacije_id
        ORDER BY k.naziv"
    );
    $sve = $stmt->fetchAll();

    // Grupisanje po ID-ju roditelja (0 = nema roditelja / korenska klasa)
    $poRoditelju = [];
    foreach ($sve as $red) {
        $roditeljId = $red['nadredjena_klasa_id'] !== null ? (int)$red['nadredjena_klasa_id'] : 0;
        $poRoditelju[$roditeljId][] = $red;
    }

    $rezultat = [];
    $posecene = [];
    $dodajDecu = function (int $roditeljId, int $nivo) use (&$dodajDecu, &$poRoditelju, &$rezultat, &$posecene) {
        if (empty($poRoditelju[$roditeljId])) {
            return;
        }
        foreach ($poRoditelju[$roditeljId] as $red) {
            $id = (int)$red['id'];
            if (isset($posecene[$id])) {
                continue; // zaštita od kružne reference u podacima
            }
            $posecene[$id] = true;
            $red['nivo'] = $nivo;
            $rezultat[] = $red;
            $dodajDecu($id, $nivo + 1);
        }
    };
    $dodajDecu(0, 0);

    // Ako neka klasa ima roditelja koji ne postoji u bazi (osirotela klasa),
    // ipak je prikaži na kraju liste da ne "nestane" iz pregleda.
    if (count($rezultat) < count($sve)) {
        $prikazaniIdovi = array_map(fn($r) => (int)$r['id'], $rezultat);
        foreach ($sve as $red) {
            if (!in_array((int)$red['id'], $prikazaniIdovi, true)) {
                $red['nivo'] = 0;
                $rezultat[] = $red;
            }
        }
    }

    return $rezultat;
}

/**
 * Vraća listu ID-jeva svih potomaka (dece, unučadi, ...) date klase.
 * Koristi se da se pri izmeni klase spreči da ona bude izabrana kao
 * roditelj samoj sebi ili nekom od svojih potomaka (kružna referenca).
 *
 * @param array $sveKlase   Lista redova sa bar kolonama 'id' i 'nadredjena_klasa_id'
 * @param int   $roditeljId ID klase za koju tražimo potomke
 * @return int[] Lista ID-jeva potomaka
 */
function pronadjiPotomkeKlase(array $sveKlase, int $roditeljId): array
{
    $potomci = [];
    foreach ($sveKlase as $klasa) {
        $trenutniRoditelj = $klasa['nadredjena_klasa_id'] !== null ? (int)$klasa['nadredjena_klasa_id'] : 0;
        if ($trenutniRoditelj === $roditeljId) {
            $id = (int)$klasa['id'];
            $potomci[] = $id;
            $potomci = array_merge($potomci, pronadjiPotomkeKlase($sveKlase, $id));
        }
    }
    return $potomci;
}

/**
 * Prikazni naziv tipa sredstva (ENUM tip_sredstva) na srpskom jeziku.
 */
function nazivTipaSredstva(string $tip): string
{
    $mapa = [
        'MATERIJALNO' => 'Materijalno',
        'NEMATERIJALNO' => 'Nematerijalno',
        'INVESTICIONA_NEKRETNINA' => 'Investiciona nekretnina',
    ];
    return $mapa[$tip] ?? $tip;
}

/**
 * Učitava SVE lokacije iz baze i vraća ih kao ravnu listu složenu u
 * hijerarhijski redosled (isti obrazac kao ucitajKlaseHijerarhijski()).
 * 'nivo' 0 = korenska lokacija (npr. objekat/zgrada), veći nivo = dublje
 * u hijerarhiji (sprat, prostorija...).
 */
function ucitajLokacijeHijerarhijski(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, sifra, naziv, adresa, grad, nadredjena_lokacija_id, napomena, aktivna
         FROM lokacije
         ORDER BY naziv"
    );
    $sve = $stmt->fetchAll();

    $poRoditelju = [];
    foreach ($sve as $red) {
        $roditeljId = $red['nadredjena_lokacija_id'] !== null ? (int)$red['nadredjena_lokacija_id'] : 0;
        $poRoditelju[$roditeljId][] = $red;
    }

    $rezultat = [];
    $posecene = [];
    $dodajDecu = function (int $roditeljId, int $nivo) use (&$dodajDecu, &$poRoditelju, &$rezultat, &$posecene) {
        if (empty($poRoditelju[$roditeljId])) {
            return;
        }
        foreach ($poRoditelju[$roditeljId] as $red) {
            $id = (int)$red['id'];
            if (isset($posecene[$id])) {
                continue;
            }
            $posecene[$id] = true;
            $red['nivo'] = $nivo;
            $rezultat[] = $red;
            $dodajDecu($id, $nivo + 1);
        }
    };
    $dodajDecu(0, 0);

    if (count($rezultat) < count($sve)) {
        $prikazaniIdovi = array_map(fn($r) => (int)$r['id'], $rezultat);
        foreach ($sve as $red) {
            if (!in_array((int)$red['id'], $prikazaniIdovi, true)) {
                $red['nivo'] = 0;
                $rezultat[] = $red;
            }
        }
    }

    return $rezultat;
}

/**
 * Vraća listu ID-jeva svih potomaka date lokacije - zaštita od kružne
 * reference pri biranju nadređene lokacije (isti obrazac kao pronadjiPotomkeKlase()).
 */
function pronadjiPotomkeLokacije(array $sveLokacije, int $roditeljId): array
{
    $potomci = [];
    foreach ($sveLokacije as $lokacija) {
        $trenutniRoditelj = $lokacija['nadredjena_lokacija_id'] !== null ? (int)$lokacija['nadredjena_lokacija_id'] : 0;
        if ($trenutniRoditelj === $roditeljId) {
            $id = (int)$lokacija['id'];
            $potomci[] = $id;
            $potomci = array_merge($potomci, pronadjiPotomkeLokacije($sveLokacije, $id));
        }
    }
    return $potomci;
}

/**
 * Učitava SVA mesta troška iz baze u hijerarhijskom redosledu - isti obrazac
 * kao ucitajLokacijeHijerarhijski() / ucitajKlaseHijerarhijski().
 */
function ucitajMestaTroskaHijerarhijski(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, sifra, naziv, nadredjeno_mesto_troska_id, napomena, aktivno
         FROM mesta_troska
         ORDER BY naziv"
    );
    $sve = $stmt->fetchAll();

    $poRoditelju = [];
    foreach ($sve as $red) {
        $roditeljId = $red['nadredjeno_mesto_troska_id'] !== null ? (int)$red['nadredjeno_mesto_troska_id'] : 0;
        $poRoditelju[$roditeljId][] = $red;
    }

    $rezultat = [];
    $posecene = [];
    $dodajDecu = function (int $roditeljId, int $nivo) use (&$dodajDecu, &$poRoditelju, &$rezultat, &$posecene) {
        if (empty($poRoditelju[$roditeljId])) {
            return;
        }
        foreach ($poRoditelju[$roditeljId] as $red) {
            $id = (int)$red['id'];
            if (isset($posecene[$id])) {
                continue;
            }
            $posecene[$id] = true;
            $red['nivo'] = $nivo;
            $rezultat[] = $red;
            $dodajDecu($id, $nivo + 1);
        }
    };
    $dodajDecu(0, 0);

    if (count($rezultat) < count($sve)) {
        $prikazaniIdovi = array_map(fn($r) => (int)$r['id'], $rezultat);
        foreach ($sve as $red) {
            if (!in_array((int)$red['id'], $prikazaniIdovi, true)) {
                $red['nivo'] = 0;
                $rezultat[] = $red;
            }
        }
    }

    return $rezultat;
}

/**
 * Vraća listu ID-jeva svih potomaka datog mesta troška - zaštita od kružne
 * reference pri biranju nadređenog mesta troška.
 */
function pronadjiPotomkeMesta(array $sveMesta, int $roditeljId): array
{
    $potomci = [];
    foreach ($sveMesta as $mesto) {
        $trenutniRoditelj = $mesto['nadredjeno_mesto_troska_id'] !== null ? (int)$mesto['nadredjeno_mesto_troska_id'] : 0;
        if ($trenutniRoditelj === $roditeljId) {
            $id = (int)$mesto['id'];
            $potomci[] = $id;
            $potomci = array_merge($potomci, pronadjiPotomkeMesta($sveMesta, $id));
        }
    }
    return $potomci;
}

/**
 * Vraća [prikazni_naziv, css_klasa_oznake] za status popisne kampanje
 * (U_PRIPREMI / U_TOKU / ZAVRSEN / OTKAZAN).
 */
function oznakaStatusaPopisa(string $status): array
{
    $mapa = [
        'U_PRIPREMI' => ['U pripremi', 'oznaka-neaktivna'],
        'U_TOKU'     => ['U toku', 'oznaka-u-toku'],
        'ZAVRSEN'    => ['Završen', 'oznaka-aktivna'],
        'OTKAZAN'    => ['Otkazan', 'oznaka-otkazana'],
    ];
    return $mapa[$status] ?? [$status, 'oznaka-neaktivna'];
}

/**
 * Vraća [prikazni_naziv, css_klasa_oznake] za rezultat popisa pojedinačnog
 * sredstva (popisano_stanje). $stanje je null ako sredstvo još nije popisano
 * u okviru date popisne kampanje.
 */
function oznakaPopisanogStanja(?string $stanje): array
{
    $mapa = [
        'PRONADJENO'      => ['Pronađeno', 'oznaka-aktivna'],
        'NIJE_PRONADJENO' => ['Nije pronađeno', 'oznaka-otkazana'],
        'VISAK'           => ['Višak', 'oznaka-u-toku'],
    ];
    return $mapa[$stanje] ?? ['Nije popisano', 'oznaka-neaktivna'];
}

/**
 * Generiše sledeći redni broj reversa u formatu REV-GODINA-NNN (npr.
 * REV-2026-001), sekvencijalno po godini. Ako u tekućoj godini već postoje
 * reversi, nastavlja se na poslednji broj; inače kreće od 001.
 *
 * NAPOMENA: kod istovremenog kreiranja dva reversa u istom trenutku postoji
 * teorijska mala šansa da dva korisnika dobiju isti broj - u tom slučaju
 * UNIQUE KEY na broj_reversa baca grešku, koju forma za unos hvata i traži
 * od korisnika da pokuša ponovo (dovoljno za obim ove aplikacije).
 */
function sledeciBrojReversa(PDO $pdo): string
{
    $godina = date('Y');
    $prefiks = "REV-{$godina}-";

    $stmt = $pdo->prepare(
        "SELECT broj_reversa FROM reversi
         WHERE broj_reversa LIKE :prefiks
         ORDER BY broj_reversa DESC LIMIT 1"
    );
    $stmt->execute([':prefiks' => $prefiks . '%']);
    $poslednji = $stmt->fetchColumn();

    $sledeciBroj = 1;
    if ($poslednji) {
        $sledeciBroj = (int)substr($poslednji, strlen($prefiks)) + 1;
    }

    return $prefiks . str_pad((string)$sledeciBroj, 3, '0', STR_PAD_LEFT);
}

/**
 * Vraća [prikazni_naziv, css_klasa_oznake] za status reversa
 * (IZDAT / DELIMICNO_VRACEN / VRACEN / PONISTEN) - isti obrazac kao
 * oznakaStatusaPopisa().
 */
function oznakaStatusaReversa(string $status): array
{
    $mapa = [
        'IZDAT'            => ['Izdat', 'oznaka-aktivna'],
        'DELIMICNO_VRACEN' => ['Delimično vraćen', 'oznaka-u-toku'],
        'VRACEN'           => ['Vraćen', 'oznaka-neaktivna'],
        'PONISTEN'         => ['Poništen', 'oznaka-otkazana'],
    ];
    return $mapa[$status] ?? [$status, 'oznaka-neaktivna'];
}
