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
            k.aktivna,
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
