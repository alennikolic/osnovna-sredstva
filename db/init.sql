-- =====================================================================================
-- MODUL: OSNOVNA SREDSTVA (Fixed Assets)
-- =====================================================================================
-- Sadržaj:
--   1. Šifarnici i klasifikacije (amortizacione grupe, metode amortizacije, statusi,
--      klase sredstava, lokacije, mesta troška, dobavljači, vrste transakcija,
--      definicije dodatnih atributa)
--   1B. Zaposleni, korisnici i prava pristupa (role i dozvole)
--   2. Matična tabela osnovnih sredstava + fleksibilni atributi po klasi (EAV)
--   3. Sredstva u pripremi (CIP - ulaganja pre aktivacije/kapitalizacije)
--   4. Amortizacija (plan i stvarni periodični obračuni)
--   5. Životni ciklus sredstva - centralni dnevnik transakcija + specijalizovane
--      tabele: premeštaj, revalorizacija/obezvređenje, poboljšanje, rashodovanje,
--      prodaja
--   6. Održavanje i osiguranje
--   7. Popis (inventura)
--   8. Prateća dokumentacija
--   9. Referentni (seed) podaci za šifarnike + primer hijerarhije klasa i atributa
--  10. Pomoćni pregledni view
--
-- NAPOMENA O PORESKIM STOPAMA: Vrednosti u tabeli amortizacione_grupe su ostavljene
-- prazne (NULL) jer se stope amortizacije po poreskim propisima menjaju kroz vreme -
-- popuniti ih u skladu sa važećim propisom/internim pravilnikom kompanije.
--
-- NAPOMENA O LOZINKAMA: Tabela korisnici namerno NIJE seed-ovana (nema fiktivnih
-- naloga u ovoj skripti) - kolona lozinka_hash mora sadržati pravi hash generisan
-- kroz aplikaciju (npr. PHP password_hash()), nikad ručno unet SQL literal.
--
-- Motor: InnoDB | Charset: utf8mb4 (puna podrška za srpski jezik i dijakritike)
-- Kompatibilno sa: MySQL 5.7+/8.0, MariaDB 10.3+
-- =====================================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- Po potrebi otkomentarisati i prilagoditi:
-- CREATE DATABASE IF NOT EXISTS osnovna_sredstva CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE osnovna_sredstva;

-- =====================================================================================
-- BRISANJE POSTOJEĆIH TABELA (obrnutim redosledom zavisnosti) - za čist reimport
-- =====================================================================================
DROP TABLE IF EXISTS `dokumenti_sredstva`;
DROP TABLE IF EXISTS `stavke_reversa`;
DROP TABLE IF EXISTS `reversi`;
DROP TABLE IF EXISTS `stavke_popisa`;
DROP TABLE IF EXISTS `popisi_osnovnih_sredstava`;
DROP TABLE IF EXISTS `osiguranja_sredstva`;
DROP TABLE IF EXISTS `odrzavanje_sredstva`;
DROP TABLE IF EXISTS `prodaje_sredstva`;
DROP TABLE IF EXISTS `rashodovanja_sredstva`;
DROP TABLE IF EXISTS `poboljsanja_sredstva`;
DROP TABLE IF EXISTS `revalorizacije_sredstva`;
DROP TABLE IF EXISTS `premestaji_sredstva`;
DROP TABLE IF EXISTS `transakcije_sredstva`;
DROP TABLE IF EXISTS `plan_amortizacije`;
DROP TABLE IF EXISTS `stavke_obracuna_amortizacije`;
DROP TABLE IF EXISTS `obracuni_amortizacije`;
DROP TABLE IF EXISTS `stavke_ulaganja_u_pripremi`;
DROP TABLE IF EXISTS `vrednosti_atributa_sredstva`;
DROP TABLE IF EXISTS `osnovna_sredstva`;
DROP TABLE IF EXISTS `definicije_atributa`;
DROP TABLE IF EXISTS `vrste_transakcija`;
DROP TABLE IF EXISTS `dobavljaci`;
DROP TABLE IF EXISTS `mesta_troska`;
DROP TABLE IF EXISTS `lokacije`;
DROP TABLE IF EXISTS `klase_osnovnih_sredstava`;
DROP TABLE IF EXISTS `statusi_sredstva`;
DROP TABLE IF EXISTS `metode_amortizacije`;
DROP TABLE IF EXISTS `amortizacione_grupe`;
DROP TABLE IF EXISTS `role_dozvole`;
DROP TABLE IF EXISTS `korisnici`;
DROP TABLE IF EXISTS `dozvole`;
DROP TABLE IF EXISTS `korisnicke_role`;
DROP TABLE IF EXISTS `zaposleni`;


-- =====================================================================================
-- SEKCIJA 1: ŠIFARNICI I KLASIFIKACIJE
-- =====================================================================================

CREATE TABLE `amortizacione_grupe` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sifra` VARCHAR(10) NOT NULL COMMENT 'Šifra grupe, npr. I, II, III, IV, V',
  `naziv` VARCHAR(150) NOT NULL COMMENT 'Naziv grupe prema poreskim/računovodstvenim propisima',
  `godisnja_stopa_procenat` DECIMAL(5,2) NULL COMMENT 'Godišnja stopa amortizacije u % - popuniti prema važećem propisu',
  `vek_trajanja_godine` DECIMAL(5,2) NULL COMMENT 'Standardni koristan vek trajanja u godinama za ovu grupu',
  `opis` TEXT NULL,
  `aktivna` TINYINT(1) NOT NULL DEFAULT 1,
  `datum_kreiranja` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amort_grupa_sifra` (`sifra`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Poreske/računovodstvene amortizacione grupe sredstava';


CREATE TABLE `metode_amortizacije` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sifra` VARCHAR(30) NOT NULL COMMENT 'npr. LINEARNA, DEGRESIVNA_DUPLA, FUNKCIONALNA',
  `naziv` VARCHAR(150) NOT NULL,
  `tip_obracuna` ENUM('LINEARNA','DEGRESIVNA_DUPLA','SUMA_GODINA','FUNKCIONALNA','BEZ_AMORTIZACIJE')
      NOT NULL DEFAULT 'LINEARNA' COMMENT 'Algoritam koji koristi aplikativni sloj prilikom obračuna',
  `opis` TEXT NULL,
  `aktivna` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_metoda_sifra` (`sifra`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Metode obračuna amortizacije';


CREATE TABLE `statusi_sredstva` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sifra` VARCHAR(30) NOT NULL COMMENT 'npr. U_PRIPREMI, U_UPOTREBI, RASHODOVANO',
  `naziv` VARCHAR(150) NOT NULL,
  `opis` TEXT NULL,
  `da_li_se_amortizuje_u_ovom_statusu` TINYINT(1) NOT NULL DEFAULT 1
      COMMENT 'Da li sredstvo u ovom statusu ulazi u periodični obračun amortizacije',
  `da_li_je_zavrsni_status` TINYINT(1) NOT NULL DEFAULT 0
      COMMENT 'Da li je status krajnji u životnom ciklusu (rashodovano/prodato/otpisano)',
  `redosled_prikaza` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_status_sifra` (`sifra`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Statusi u životnom ciklusu osnovnog sredstva';


CREATE TABLE `klase_osnovnih_sredstava` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sifra` VARCHAR(20) NOT NULL COMMENT 'Kratka šifra klase, npr. GRAD, OPR-IT, OPR-VOZ',
  `naziv` VARCHAR(150) NOT NULL,
  `opis` TEXT NULL,
  `nadredjena_klasa_id` INT UNSIGNED NULL COMMENT 'Roditeljska klasa - hijerarhija klasa/podklasa (grupa)',
  `tip_sredstva` ENUM('MATERIJALNO','NEMATERIJALNO','INVESTICIONA_NEKRETNINA')
      NOT NULL DEFAULT 'MATERIJALNO' COMMENT 'Osnovna knjigovodstvena podela sredstava',
  `amortizaciona_grupa_id` INT UNSIGNED NULL COMMENT 'Podrazumevana amortizaciona grupa za sredstva ove klase',
  `metoda_amortizacije_id` INT UNSIGNED NULL COMMENT 'Podrazumevana metoda amortizacije za sredstva ove klase',
  `konto_nabavne_vrednosti` VARCHAR(20) NULL COMMENT 'Konto GK - nabavna (bruto) vrednost',
  `konto_ispravke_vrednosti` VARCHAR(20) NULL COMMENT 'Konto GK - ispravka vrednosti (akumulirana amortizacija)',
  `konto_troska_amortizacije` VARCHAR(20) NULL COMMENT 'Konto GK - trošak amortizacije',
  `ukljucuje_se_u_popis` TINYINT(1) NOT NULL DEFAULT 1
      COMMENT 'Da li se sredstva ove klase uključuju u fizički popis (npr. nematerijalna ulaganja se često isključuju - verifikuju se kroz dokumentaciju, ne fizičkim obilaskom)',
  `aktivna` TINYINT(1) NOT NULL DEFAULT 1,
  `datum_kreiranja` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `datum_izmene` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_klase_sifra` (`sifra`),
  KEY `idx_klase_nadredjena` (`nadredjena_klasa_id`),
  KEY `idx_klase_amort_grupa` (`amortizaciona_grupa_id`),
  KEY `idx_klase_metoda_amort` (`metoda_amortizacije_id`),
  CONSTRAINT `fk_klase_nadredjena` FOREIGN KEY (`nadredjena_klasa_id`)
      REFERENCES `klase_osnovnih_sredstava` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_klase_amort_grupa` FOREIGN KEY (`amortizaciona_grupa_id`)
      REFERENCES `amortizacione_grupe` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_klase_metoda_amort` FOREIGN KEY (`metoda_amortizacije_id`)
      REFERENCES `metode_amortizacije` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Hijerarhijska klasifikacija (šifarnik) osnovnih sredstava - klase i podklase/grupe';


CREATE TABLE `lokacije` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sifra` VARCHAR(20) NOT NULL,
  `naziv` VARCHAR(150) NOT NULL,
  `adresa` VARCHAR(255) NULL,
  `grad` VARCHAR(100) NULL,
  `nadredjena_lokacija_id` INT UNSIGNED NULL COMMENT 'Hijerarhija: objekat -> sprat -> prostorija',
  `napomena` TEXT NULL,
  `aktivna` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lokacija_sifra` (`sifra`),
  KEY `idx_lokacija_nadredjena` (`nadredjena_lokacija_id`),
  CONSTRAINT `fk_lokacija_nadredjena` FOREIGN KEY (`nadredjena_lokacija_id`)
      REFERENCES `lokacije` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Fizičke lokacije na kojima se nalaze osnovna sredstva';


CREATE TABLE `mesta_troska` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sifra` VARCHAR(20) NOT NULL,
  `naziv` VARCHAR(150) NOT NULL,
  `nadredjeno_mesto_troska_id` INT UNSIGNED NULL,
  `napomena` TEXT NULL,
  `aktivno` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mesto_troska_sifra` (`sifra`),
  KEY `idx_mesto_troska_nadredjeno` (`nadredjeno_mesto_troska_id`),
  CONSTRAINT `fk_mesto_troska_nadredjeno` FOREIGN KEY (`nadredjeno_mesto_troska_id`)
      REFERENCES `mesta_troska` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Organizacione jedinice / mesta troška zadužena za osnovna sredstva';


CREATE TABLE `dobavljaci` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sifra` VARCHAR(20) NULL,
  `naziv` VARCHAR(200) NOT NULL,
  `pib` VARCHAR(20) NULL COMMENT 'Poreski identifikacioni broj',
  `maticni_broj` VARCHAR(20) NULL,
  `adresa` VARCHAR(255) NULL,
  `grad` VARCHAR(100) NULL,
  `kontakt_osoba` VARCHAR(150) NULL,
  `kontakt_telefon` VARCHAR(50) NULL,
  `kontakt_email` VARCHAR(100) NULL,
  `napomena` TEXT NULL,
  `aktivan` TINYINT(1) NOT NULL DEFAULT 1,
  `datum_kreiranja` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dobavljac_pib` (`pib`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pojednostavljen šifarnik dobavljača - radi referencijalnog integriteta pri nabavci osnovnih sredstava. U punom ERP-u ovo bi bio deo zasebnog modula Poslovni partneri';


CREATE TABLE `vrste_transakcija` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sifra` VARCHAR(30) NOT NULL COMMENT 'npr. NABAVKA, PREMESTAJ, REVALORIZACIJA, RASHODOVANJE, PRODAJA',
  `naziv` VARCHAR(150) NOT NULL,
  `opis` TEXT NULL,
  `utice_na_knjigovodstvenu_vrednost` TINYINT(1) NOT NULL DEFAULT 1,
  `smer_uticaja` ENUM('POVECANJE','SMANJENJE','NEUTRALNO') NOT NULL DEFAULT 'NEUTRALNO',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vrsta_transakcije_sifra` (`sifra`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Šifarnik svih vrsta događaja/transakcija u životnom ciklusu osnovnog sredstva';


CREATE TABLE `definicije_atributa` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `klasa_id` INT UNSIGNED NULL COMMENT 'NULL = atribut je primenjiv na sve klase sredstava',
  `sifra` VARCHAR(50) NOT NULL COMMENT 'Programska šifra atributa, npr. REG_OZNAKA, KVADRATURA',
  `naziv` VARCHAR(150) NOT NULL COMMENT 'Prikazni naziv atributa',
  `tip_podatka` ENUM('TEKST','CEO_BROJ','DECIMALNI_BROJ','DATUM','DA_NE','LISTA')
      NOT NULL DEFAULT 'TEKST',
  `jedinica_mere` VARCHAR(20) NULL COMMENT 'npr. m2, kg, kW, km',
  `lista_dozvoljenih_vrednosti` TEXT NULL COMMENT 'Za tip LISTA - vrednosti odvojene znakom ";"',
  `obavezno_polje` TINYINT(1) NOT NULL DEFAULT 0,
  `redosled_prikaza` SMALLINT NOT NULL DEFAULT 0,
  `aktivan` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_atribut_klasa_sifra` (`klasa_id`,`sifra`),
  KEY `idx_atribut_klasa` (`klasa_id`),
  CONSTRAINT `fk_atribut_klasa` FOREIGN KEY (`klasa_id`)
      REFERENCES `klase_osnovnih_sredstava` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Definicije dodatnih (custom) atributa po klasi sredstva - EAV model za fleksibilne specifikacije (vozila, nekretnine, IT oprema...)';


-- =====================================================================================
-- SEKCIJA 1B: ZAPOSLENI, KORISNICI I PRAVA PRISTUPA
-- =====================================================================================
-- Namerna podela na dva koncepta:
--   - zaposleni  = poslovni/HR entitet (osoba kojoj se zadužuje sredstvo, član komisije...)
--                  ne mora nužno imati pristup aplikaciji.
--   - korisnici  = nalog za prijavu u aplikaciju (username + lozinka), opciono 1:1
--                  povezan sa zaposlenim (spoljni administrator npr. ne mora biti
--                  "zaposleni" u HR smislu).
-- Prava pristupa su rešena kroz proste role (jedna rola po korisniku) sa listom
-- dozvola po roli - dovoljno fleksibilno bez nepotrebne M:N kompleksnosti korisnik-rola.

CREATE TABLE `zaposleni` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sifra` VARCHAR(20) NULL COMMENT 'Interna šifra/broj zaposlenog (opciono)',
  `ime` VARCHAR(100) NOT NULL,
  `prezime` VARCHAR(100) NOT NULL,
  `radno_mesto` VARCHAR(150) NULL,
  `mesto_troska_id` INT UNSIGNED NULL COMMENT 'Organizaciona jedinica kojoj zaposleni pripada',
  `lokacija_id` INT UNSIGNED NULL COMMENT 'Fizička lokacija rada zaposlenog',
  `email` VARCHAR(150) NULL,
  `telefon` VARCHAR(50) NULL,
  `datum_zaposlenja` DATE NULL,
  `datum_prestanka` DATE NULL COMMENT 'NULL = i dalje zaposlen',
  `aktivan` TINYINT(1) NOT NULL DEFAULT 1,
  `napomena` TEXT NULL,
  `datum_kreiranja` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `datum_izmene` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_zaposleni_sifra` (`sifra`),
  KEY `idx_zaposleni_mesto_troska` (`mesto_troska_id`),
  KEY `idx_zaposleni_lokacija` (`lokacija_id`),
  KEY `idx_zaposleni_prezime_ime` (`prezime`,`ime`),
  CONSTRAINT `fk_zaposleni_mesto_troska` FOREIGN KEY (`mesto_troska_id`)
      REFERENCES `mesta_troska` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_zaposleni_lokacija` FOREIGN KEY (`lokacija_id`)
      REFERENCES `lokacije` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Zaposleni - lica kojima se mogu zaduživati sredstva, biti članovi komisija i sl. (nezavisno od pristupa aplikaciji)';


CREATE TABLE `korisnicke_role` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sifra` VARCHAR(30) NOT NULL COMMENT 'npr. ADMIN, KNJIGOVODJA, MAGACIONER, PREGLED',
  `naziv` VARCHAR(100) NOT NULL,
  `opis` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rola_sifra` (`sifra`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Korisničke role - svaki korisnik ima tačno jednu rolu';


CREATE TABLE `dozvole` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sifra` VARCHAR(50) NOT NULL COMMENT 'npr. SREDSTVA_UNOS, SREDSTVA_IZMENA, KLASE_UPRAVLJANJE',
  `naziv` VARCHAR(150) NOT NULL,
  `modul` VARCHAR(50) NULL COMMENT 'Grupisanje dozvola po modulu - za prikaz u UI za dodelu prava',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dozvola_sifra` (`sifra`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pojedinačne dozvole (akcije) u sistemu';


CREATE TABLE `role_dozvole` (
  `rola_id` INT UNSIGNED NOT NULL,
  `dozvola_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`rola_id`,`dozvola_id`),
  KEY `idx_rd_dozvola` (`dozvola_id`),
  CONSTRAINT `fk_rd_rola` FOREIGN KEY (`rola_id`)
      REFERENCES `korisnicke_role` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rd_dozvola` FOREIGN KEY (`dozvola_id`)
      REFERENCES `dozvole` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Koje dozvole ima koja rola (M:N)';


CREATE TABLE `korisnici` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `korisnicko_ime` VARCHAR(50) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `lozinka_hash` VARCHAR(255) NOT NULL COMMENT 'password_hash() - bcrypt/argon2id, NIKAD plain tekst',
  `zaposleni_id` INT UNSIGNED NULL COMMENT 'Povezani zaposleni (opciono - npr. spoljni admin nema HR zapis)',
  `rola_id` INT UNSIGNED NOT NULL,
  `aktivan` TINYINT(1) NOT NULL DEFAULT 1,
  `mora_promeniti_lozinku` TINYINT(1) NOT NULL DEFAULT 0,
  `poslednja_prijava` DATETIME NULL,
  `broj_neuspesnih_prijava` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `zakljucan_do` DATETIME NULL COMMENT 'Privremeno zaključavanje naloga posle više neuspešnih prijava',
  `datum_kreiranja` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `datum_izmene` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_korisnik_korisnicko_ime` (`korisnicko_ime`),
  UNIQUE KEY `uq_korisnik_email` (`email`),
  UNIQUE KEY `uq_korisnik_zaposleni` (`zaposleni_id`),
  KEY `idx_korisnik_rola` (`rola_id`),
  CONSTRAINT `fk_korisnik_zaposleni` FOREIGN KEY (`zaposleni_id`)
      REFERENCES `zaposleni` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_korisnik_rola` FOREIGN KEY (`rola_id`)
      REFERENCES `korisnicke_role` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Korisnički nalozi za prijavu u aplikaciju - svaki nalog ima tačno jednu rolu';


-- =====================================================================================
-- SEKCIJA 2: MATIČNA TABELA OSNOVNIH SREDSTAVA + FLEKSIBILNI ATRIBUTI
-- =====================================================================================

CREATE TABLE `osnovna_sredstva` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inventarski_broj` VARCHAR(50) NOT NULL COMMENT 'Jedinstveni inventarski broj sredstva',
  `interna_sifra` VARCHAR(50) NULL COMMENT 'Dodatna interna oznaka (npr. iz starog sistema)',
  `naziv` VARCHAR(255) NOT NULL,
  `opis` TEXT NULL,
  `klasa_id` INT UNSIGNED NOT NULL,
  `amortizaciona_grupa_id` INT UNSIGNED NULL,
  `metoda_amortizacije_id` INT UNSIGNED NULL,
  `status_id` INT UNSIGNED NOT NULL,
  `lokacija_id` INT UNSIGNED NULL,
  `mesto_troska_id` INT UNSIGNED NULL,
  `odgovorno_lice` VARCHAR(150) NULL COMMENT 'Slobodan tekst - koristi se samo kad zaduženo lice NIJE u evidenciji zaposlenih (npr. eksterni saradnik)',
  `zaposleni_id` INT UNSIGNED NULL COMMENT 'Formalno zaduženo lice iz evidencije zaposlenih (preporučeni način dodele sredstva)',
  `dobavljac_id` INT UNSIGNED NULL,
  `nadredjeno_sredstvo_id` BIGINT UNSIGNED NULL COMMENT 'Za komponente/sastavne delove složenog sredstva',
  `nacin_sticanja` ENUM('KUPOVINA','SOPSTVENA_IZGRADNJA','FINANSIJSKI_LIZING','OPERATIVNI_LIZING','POKLON','ULOG','OSTALO')
      NOT NULL DEFAULT 'KUPOVINA',
  `serijski_broj` VARCHAR(100) NULL,
  `proizvodjac` VARCHAR(150) NULL,
  `model` VARCHAR(150) NULL,
  `godina_proizvodnje` SMALLINT UNSIGNED NULL,
  `broj_racuna_nabavke` VARCHAR(50) NULL,
  `broj_ugovora` VARCHAR(50) NULL,
  `datum_nabavke` DATE NOT NULL,
  `datum_stavljanja_u_upotrebu` DATE NULL,
  `nabavna_vrednost` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'Originalna nabavna (bruto) vrednost',
  `zavisni_troskovi_nabavke` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'Kapitalizovani zavisni troškovi (prevoz, carina, montaža...)',
  `valuta` CHAR(3) NOT NULL DEFAULT 'RSD',
  `devizni_kurs` DECIMAL(12,6) NOT NULL DEFAULT 1,
  `osnovica_za_amortizaciju` DECIMAL(18,2) NOT NULL DEFAULT 0
      COMMENT 'Tekuća osnovica za obračun amortizacije (menja se nakon revalorizacije/poboljšanja)',
  `rezidualna_vrednost` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'Procenjena preostala (otpisna) vrednost na kraju veka trajanja',
  `vek_trajanja_meseci` INT UNSIGNED NULL COMMENT 'Procenjeni koristan vek trajanja u mesecima',
  `datum_pocetka_amortizacije` DATE NULL,
  `datum_planiranog_zavrsetka_amortizacije` DATE NULL,
  `da_li_se_amortizuje` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Neka sredstva (npr. zemljište) se ne amortizuju',
  `akumulirana_amortizacija` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'Keširan/tekući iznos ukupno obračunate amortizacije',
  `sadasnja_knjigovodstvena_vrednost` DECIMAL(18,2) NULL COMMENT 'Keširana neto knjigovodstvena vrednost',
  `datum_poslednjeg_obracuna_amortizacije` DATE NULL,
  `barkod` VARCHAR(100) NULL,
  `qr_kod` VARCHAR(150) NULL,
  `datum_rashodovanja` DATE NULL,
  `napomena` TEXT NULL,
  `datum_kreiranja` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `datum_izmene` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sredstvo_inventarski_broj` (`inventarski_broj`),
  KEY `idx_sredstvo_klasa` (`klasa_id`),
  KEY `idx_sredstvo_status` (`status_id`),
  KEY `idx_sredstvo_lokacija` (`lokacija_id`),
  KEY `idx_sredstvo_mesto_troska` (`mesto_troska_id`),
  KEY `idx_sredstvo_dobavljac` (`dobavljac_id`),
  KEY `idx_sredstvo_nadredjeno` (`nadredjeno_sredstvo_id`),
  KEY `idx_sredstvo_amort_grupa` (`amortizaciona_grupa_id`),
  KEY `idx_sredstvo_metoda_amort` (`metoda_amortizacije_id`),
  KEY `idx_sredstvo_datum_nabavke` (`datum_nabavke`),
  KEY `idx_sredstvo_zaposleni` (`zaposleni_id`),
  CONSTRAINT `fk_sredstvo_klasa` FOREIGN KEY (`klasa_id`)
      REFERENCES `klase_osnovnih_sredstava` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sredstvo_amort_grupa` FOREIGN KEY (`amortizaciona_grupa_id`)
      REFERENCES `amortizacione_grupe` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sredstvo_metoda_amort` FOREIGN KEY (`metoda_amortizacije_id`)
      REFERENCES `metode_amortizacije` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sredstvo_status` FOREIGN KEY (`status_id`)
      REFERENCES `statusi_sredstva` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sredstvo_lokacija` FOREIGN KEY (`lokacija_id`)
      REFERENCES `lokacije` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sredstvo_mesto_troska` FOREIGN KEY (`mesto_troska_id`)
      REFERENCES `mesta_troska` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sredstvo_dobavljac` FOREIGN KEY (`dobavljac_id`)
      REFERENCES `dobavljaci` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sredstvo_nadredjeno` FOREIGN KEY (`nadredjeno_sredstvo_id`)
      REFERENCES `osnovna_sredstva` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sredstvo_zaposleni` FOREIGN KEY (`zaposleni_id`)
      REFERENCES `zaposleni` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Glavna (matična) tabela osnovnih sredstava';


CREATE TABLE `vrednosti_atributa_sredstva` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sredstvo_id` BIGINT UNSIGNED NOT NULL,
  `definicija_atributa_id` INT UNSIGNED NOT NULL,
  `vrednost_tekst` VARCHAR(500) NULL,
  `vrednost_broj` DECIMAL(18,4) NULL,
  `vrednost_datum` DATE NULL,
  `vrednost_da_ne` TINYINT(1) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vrednost_atributa` (`sredstvo_id`,`definicija_atributa_id`),
  KEY `idx_vrednost_atributa_definicija` (`definicija_atributa_id`),
  CONSTRAINT `fk_vrednost_atributa_sredstvo` FOREIGN KEY (`sredstvo_id`)
      REFERENCES `osnovna_sredstva` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_vrednost_atributa_definicija` FOREIGN KEY (`definicija_atributa_id`)
      REFERENCES `definicije_atributa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Konkretne vrednosti dodatnih atributa po sredstvu (EAV)';


-- =====================================================================================
-- SEKCIJA 3: SREDSTVA U PRIPREMI (CIP - Construction/Capital in Progress)
-- =====================================================================================
-- Sredstvo "u pripremi" je običan red u tabeli osnovna_sredstva sa status_id koji
-- odgovara statusu U_PRIPREMI. Ova tabela beleži pojedinačne troškove koji se
-- postepeno akumuliraju pre nego što se sredstvo aktivira (kapitalizuje).

CREATE TABLE `stavke_ulaganja_u_pripremi` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sredstvo_id` BIGINT UNSIGNED NOT NULL COMMENT 'Sredstvo u statusu U_PRIPREMI na koje se ulaganje odnosi',
  `datum` DATE NOT NULL,
  `opis` VARCHAR(500) NOT NULL,
  `iznos` DECIMAL(18,2) NOT NULL,
  `dobavljac_id` INT UNSIGNED NULL,
  `broj_racuna` VARCHAR(50) NULL,
  `napomena` TEXT NULL,
  `datum_kreiranja` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ulaganje_pripreme_sredstvo` (`sredstvo_id`),
  KEY `idx_ulaganje_pripreme_dobavljac` (`dobavljac_id`),
  CONSTRAINT `fk_ulaganje_pripreme_sredstvo` FOREIGN KEY (`sredstvo_id`)
      REFERENCES `osnovna_sredstva` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ulaganje_pripreme_dobavljac` FOREIGN KEY (`dobavljac_id`)
      REFERENCES `dobavljaci` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Postepeno akumulirani troškovi za sredstva u pripremi, pre aktivacije/kapitalizacije';


-- =====================================================================================
-- SEKCIJA 4: AMORTIZACIJA (plan i stvarni obračuni)
-- =====================================================================================

CREATE TABLE `obracuni_amortizacije` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `naziv` VARCHAR(100) NOT NULL COMMENT 'npr. "Amortizacija 07/2026"',
  `godina` SMALLINT NOT NULL,
  `mesec` TINYINT NOT NULL COMMENT '1-12',
  `period_od` DATE NOT NULL,
  `period_do` DATE NOT NULL,
  `status` ENUM('U_PRIPREMI','OBRACUNATO','KNJIZENO','STORNIRANO') NOT NULL DEFAULT 'U_PRIPREMI',
  `datum_obracuna` DATETIME NULL,
  `datum_knjizenja` DATETIME NULL,
  `ukupan_iznos_amortizacije` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `napomena` TEXT NULL,
  `datum_kreiranja` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_obracun_period` (`godina`,`mesec`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Zaglavlje periodičnog obračuna amortizacije za celu kompaniju (jedan red po mesecu/godini)';


CREATE TABLE `stavke_obracuna_amortizacije` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `obracun_id` INT UNSIGNED NOT NULL,
  `sredstvo_id` BIGINT UNSIGNED NOT NULL,
  `knjigovodstvena_vrednost_pocetna` DECIMAL(18,2) NOT NULL,
  `iznos_amortizacije` DECIMAL(18,2) NOT NULL,
  `akumulirana_amortizacija_posle` DECIMAL(18,2) NOT NULL,
  `knjigovodstvena_vrednost_krajnja` DECIMAL(18,2) NOT NULL,
  `konto_troska` VARCHAR(20) NULL,
  `konto_ispravke_vrednosti` VARCHAR(20) NULL,
  `mesto_troska_id` INT UNSIGNED NULL COMMENT 'Mesto troška u trenutku obračuna (za raspodelu troška)',
  `napomena` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stavka_obracuna` (`obracun_id`,`sredstvo_id`),
  KEY `idx_stavka_obracuna_sredstvo` (`sredstvo_id`),
  KEY `idx_stavka_obracuna_mesto_troska` (`mesto_troska_id`),
  CONSTRAINT `fk_stavka_obracuna_obracun` FOREIGN KEY (`obracun_id`)
      REFERENCES `obracuni_amortizacije` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stavka_obracuna_sredstvo` FOREIGN KEY (`sredstvo_id`)
      REFERENCES `osnovna_sredstva` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_stavka_obracuna_mesto_troska` FOREIGN KEY (`mesto_troska_id`)
      REFERENCES `mesta_troska` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Detaljne stavke obračuna amortizacije - po jedan red za svako sredstvo u okviru obračunskog perioda';


CREATE TABLE `plan_amortizacije` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sredstvo_id` BIGINT UNSIGNED NOT NULL,
  `redni_broj_rate` INT UNSIGNED NOT NULL,
  `period_godina` SMALLINT NOT NULL,
  `period_mesec` TINYINT NOT NULL,
  `planirani_iznos_amortizacije` DECIMAL(18,2) NOT NULL,
  `planirana_knjigovodstvena_vrednost_posle` DECIMAL(18,2) NOT NULL,
  `realizovano` TINYINT(1) NOT NULL DEFAULT 0,
  `obracun_id` INT UNSIGNED NULL COMMENT 'Povezuje planiranu ratu sa stvarnim obračunom kada se izvrši',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_sredstvo_period` (`sredstvo_id`,`period_godina`,`period_mesec`),
  KEY `idx_plan_obracun` (`obracun_id`),
  CONSTRAINT `fk_plan_sredstvo` FOREIGN KEY (`sredstvo_id`)
      REFERENCES `osnovna_sredstva` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_plan_obracun` FOREIGN KEY (`obracun_id`)
      REFERENCES `obracuni_amortizacije` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Projektovani plan amortizacije po sredstvu i periodu - osnova za planiranje i izveštavanje';


-- =====================================================================================
-- SEKCIJA 5: ŽIVOTNI CIKLUS SREDSTVA
-- =====================================================================================

CREATE TABLE `transakcije_sredstva` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sredstvo_id` BIGINT UNSIGNED NOT NULL,
  `vrsta_transakcije_id` INT UNSIGNED NOT NULL,
  `datum_transakcije` DATE NOT NULL,
  `broj_dokumenta` VARCHAR(50) NULL,
  `opis` TEXT NULL,
  `iznos` DECIMAL(18,2) NULL COMMENT 'Generički iznos - značenje zavisi od vrste transakcije',
  `knjigovodstvena_vrednost_pre` DECIMAL(18,2) NULL,
  `knjigovodstvena_vrednost_posle` DECIMAL(18,2) NULL,
  `izvrsilac` VARCHAR(150) NULL COMMENT 'Ime lica koje je evidentiralo transakciju (slobodan tekst - fallback kad korisnik_id nije dostupan)',
  `korisnik_id` INT UNSIGNED NULL COMMENT 'Sistemski korisnik koji je evidentirao transakciju (audit trag)',
  `napomena` TEXT NULL,
  `datum_kreiranja` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_transakcija_sredstvo_datum` (`sredstvo_id`,`datum_transakcije`),
  KEY `idx_transakcija_vrsta` (`vrsta_transakcije_id`),
  KEY `idx_transakcija_korisnik` (`korisnik_id`),
  CONSTRAINT `fk_transakcija_sredstvo` FOREIGN KEY (`sredstvo_id`)
      REFERENCES `osnovna_sredstva` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_transakcija_vrsta` FOREIGN KEY (`vrsta_transakcije_id`)
      REFERENCES `vrste_transakcija` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_transakcija_korisnik` FOREIGN KEY (`korisnik_id`)
      REFERENCES `korisnici` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Centralni dnevnik svih događaja u životnom ciklusu sredstva - jedinstvena istorija promena';


CREATE TABLE `premestaji_sredstva` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transakcija_id` BIGINT UNSIGNED NOT NULL,
  `sredstvo_id` BIGINT UNSIGNED NOT NULL,
  `datum_premestaja` DATE NOT NULL,
  `stara_lokacija_id` INT UNSIGNED NULL,
  `nova_lokacija_id` INT UNSIGNED NULL,
  `staro_mesto_troska_id` INT UNSIGNED NULL,
  `novo_mesto_troska_id` INT UNSIGNED NULL,
  `staro_odgovorno_lice` VARCHAR(150) NULL,
  `stari_zaposleni_id` INT UNSIGNED NULL,
  `novo_odgovorno_lice` VARCHAR(150) NULL,
  `novi_zaposleni_id` INT UNSIGNED NULL,
  `napomena` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_premestaj_sredstvo` (`sredstvo_id`),
  KEY `idx_premestaj_transakcija` (`transakcija_id`),
  KEY `idx_premestaj_nova_lokacija` (`nova_lokacija_id`),
  KEY `idx_premestaj_novi_zaposleni` (`novi_zaposleni_id`),
  CONSTRAINT `fk_premestaj_transakcija` FOREIGN KEY (`transakcija_id`)
      REFERENCES `transakcije_sredstva` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_premestaj_sredstvo` FOREIGN KEY (`sredstvo_id`)
      REFERENCES `osnovna_sredstva` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_premestaj_stara_lokacija` FOREIGN KEY (`stara_lokacija_id`)
      REFERENCES `lokacije` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_premestaj_nova_lokacija` FOREIGN KEY (`nova_lokacija_id`)
      REFERENCES `lokacije` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_premestaj_staro_mesto_troska` FOREIGN KEY (`staro_mesto_troska_id`)
      REFERENCES `mesta_troska` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_premestaj_novo_mesto_troska` FOREIGN KEY (`novo_mesto_troska_id`)
      REFERENCES `mesta_troska` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_premestaj_stari_zaposleni` FOREIGN KEY (`stari_zaposleni_id`)
      REFERENCES `zaposleni` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_premestaj_novi_zaposleni` FOREIGN KEY (`novi_zaposleni_id`)
      REFERENCES `zaposleni` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Istorija premeštaja sredstva između lokacija, mesta troška i zaduženih lica (uključujući promene zaduženja)';


CREATE TABLE `revalorizacije_sredstva` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transakcija_id` BIGINT UNSIGNED NOT NULL,
  `sredstvo_id` BIGINT UNSIGNED NOT NULL,
  `datum_revalorizacije` DATE NOT NULL,
  `tip` ENUM('POVECANJE_VREDNOSTI','SMANJENJE_VREDNOSTI','OBEZVREDJENJE') NOT NULL,
  `vrednost_pre` DECIMAL(18,2) NOT NULL,
  `iznos_promene` DECIMAL(18,2) NOT NULL COMMENT 'Pozitivan za povećanje, negativan za smanjenje/obezvređenje',
  `vrednost_posle` DECIMAL(18,2) NOT NULL,
  `akumulirana_amortizacija_pre` DECIMAL(18,2) NULL,
  `akumulirana_amortizacija_posle` DECIMAL(18,2) NULL,
  `osnov_revalorizacije` VARCHAR(255) NULL COMMENT 'npr. broj izveštaja procenitelja, indeks revalorizacije',
  `broj_izvestaja_procenitelja` VARCHAR(50) NULL,
  `napomena` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_revalorizacija_sredstvo` (`sredstvo_id`),
  KEY `idx_revalorizacija_transakcija` (`transakcija_id`),
  CONSTRAINT `fk_revalorizacija_transakcija` FOREIGN KEY (`transakcija_id`)
      REFERENCES `transakcije_sredstva` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_revalorizacija_sredstvo` FOREIGN KEY (`sredstvo_id`)
      REFERENCES `osnovna_sredstva` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Istorija revalorizacija (procena vrednosti) i obezvređenja (impairment) sredstava';


CREATE TABLE `poboljsanja_sredstva` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transakcija_id` BIGINT UNSIGNED NOT NULL,
  `sredstvo_id` BIGINT UNSIGNED NOT NULL,
  `datum` DATE NOT NULL,
  `opis_poboljsanja` VARCHAR(500) NOT NULL,
  `iznos_ulaganja` DECIMAL(18,2) NOT NULL,
  `dobavljac_id` INT UNSIGNED NULL,
  `broj_racuna` VARCHAR(50) NULL,
  `produzenje_veka_trajanja_meseci` INT UNSIGNED NULL
      COMMENT 'Ako investiciono ulaganje produžava koristan vek trajanja sredstva',
  `napomena` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_poboljsanje_sredstvo` (`sredstvo_id`),
  KEY `idx_poboljsanje_transakcija` (`transakcija_id`),
  KEY `idx_poboljsanje_dobavljac` (`dobavljac_id`),
  CONSTRAINT `fk_poboljsanje_transakcija` FOREIGN KEY (`transakcija_id`)
      REFERENCES `transakcije_sredstva` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_poboljsanje_sredstvo` FOREIGN KEY (`sredstvo_id`)
      REFERENCES `osnovna_sredstva` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_poboljsanje_dobavljac` FOREIGN KEY (`dobavljac_id`)
      REFERENCES `dobavljaci` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Kapitalna ulaganja/poboljšanja koja povećavaju vrednost ili produžavaju vek trajanja sredstva';


CREATE TABLE `rashodovanja_sredstva` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transakcija_id` BIGINT UNSIGNED NOT NULL,
  `sredstvo_id` BIGINT UNSIGNED NOT NULL,
  `datum_rashodovanja` DATE NOT NULL,
  `razlog` ENUM('DOTRAJALOST','KVAR','ZASTARELOST','ELEMENTARNA_NEPOGODA','KRADJA','MANJAK_NA_POPISU','OSTALO')
      NOT NULL,
  `opis_razloga` TEXT NULL,
  `knjigovodstvena_vrednost_na_dan_rashoda` DECIMAL(18,2) NOT NULL,
  `trosak_rashodovanja` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'Trošak demontaže, uklanjanja i sl.',
  `broj_zapisnika_komisije` VARCHAR(50) NULL,
  `clanovi_komisije` TEXT NULL COMMENT 'Tekstualni spisak članova komisije za rashodovanje',
  `napomena` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rashodovanje_sredstvo` (`sredstvo_id`),
  KEY `idx_rashodovanje_transakcija` (`transakcija_id`),
  CONSTRAINT `fk_rashodovanje_transakcija` FOREIGN KEY (`transakcija_id`)
      REFERENCES `transakcije_sredstva` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rashodovanje_sredstvo` FOREIGN KEY (`sredstvo_id`)
      REFERENCES `osnovna_sredstva` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Rashodovanja i otpisi sredstava sa razlogom i zapisnikom komisije';


CREATE TABLE `prodaje_sredstva` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transakcija_id` BIGINT UNSIGNED NOT NULL,
  `sredstvo_id` BIGINT UNSIGNED NOT NULL,
  `datum_prodaje` DATE NOT NULL,
  `kupac_naziv` VARCHAR(200) NOT NULL,
  `kupac_pib` VARCHAR(20) NULL,
  `prodajna_vrednost` DECIMAL(18,2) NOT NULL,
  `knjigovodstvena_vrednost_na_dan_prodaje` DECIMAL(18,2) NOT NULL,
  `dobitak_gubitak` DECIMAL(18,2) NOT NULL
      COMMENT 'Razlika: prodajna_vrednost - knjigovodstvena_vrednost_na_dan_prodaje',
  `broj_racuna_prodaje` VARCHAR(50) NULL,
  `napomena` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_prodaja_sredstvo` (`sredstvo_id`),
  KEY `idx_prodaja_transakcija` (`transakcija_id`),
  CONSTRAINT `fk_prodaja_transakcija` FOREIGN KEY (`transakcija_id`)
      REFERENCES `transakcije_sredstva` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_prodaja_sredstvo` FOREIGN KEY (`sredstvo_id`)
      REFERENCES `osnovna_sredstva` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Prodaje osnovnih sredstava trećim licima sa obračunom dobitka/gubitka';


-- =====================================================================================
-- SEKCIJA 6: ODRŽAVANJE I OSIGURANJE
-- =====================================================================================

CREATE TABLE `odrzavanje_sredstva` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sredstvo_id` BIGINT UNSIGNED NOT NULL,
  `tip_odrzavanja` ENUM('REDOVNO','PREVENTIVNO','KOREKTIVNO','VANREDNO') NOT NULL DEFAULT 'REDOVNO',
  `datum_od` DATE NOT NULL,
  `datum_do` DATE NULL,
  `opis` VARCHAR(500) NULL,
  `izvodjac_radova` VARCHAR(200) NULL COMMENT 'Interni tim ili spoljni serviser',
  `trosak` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `da_li_kapitalizovano` TINYINT(1) NOT NULL DEFAULT 0
      COMMENT 'Da li je trošak kapitalizovan (videti poboljsanja_sredstva) ili knjižen kao trošak perioda',
  `sledece_odrzavanje_datum` DATE NULL,
  `napomena` TEXT NULL,
  `datum_kreiranja` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_odrzavanje_sredstvo` (`sredstvo_id`),
  KEY `idx_odrzavanje_datum_od` (`datum_od`),
  CONSTRAINT `fk_odrzavanje_sredstvo` FOREIGN KEY (`sredstvo_id`)
      REFERENCES `osnovna_sredstva` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Evidencija redovnog i vanrednog održavanja/servisiranja sredstava';


CREATE TABLE `osiguranja_sredstva` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sredstvo_id` BIGINT UNSIGNED NOT NULL,
  `naziv_osiguravajuce_kuce` VARCHAR(200) NOT NULL,
  `broj_polise` VARCHAR(50) NOT NULL,
  `tip_osiguranja` VARCHAR(100) NULL,
  `datum_od` DATE NOT NULL,
  `datum_do` DATE NOT NULL,
  `osigurana_vrednost` DECIMAL(18,2) NOT NULL,
  `premija` DECIMAL(18,2) NULL,
  `napomena` TEXT NULL,
  `datum_kreiranja` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_osiguranje_sredstvo` (`sredstvo_id`),
  KEY `idx_osiguranje_datum_do` (`datum_do`),
  CONSTRAINT `fk_osiguranje_sredstvo` FOREIGN KEY (`sredstvo_id`)
      REFERENCES `osnovna_sredstva` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Polise osiguranja vezane za pojedinačna osnovna sredstva';


-- =====================================================================================
-- SEKCIJA 7: POPIS (INVENTURA)
-- =====================================================================================

CREATE TABLE `popisi_osnovnih_sredstava` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `naziv` VARCHAR(150) NOT NULL COMMENT 'npr. "Godišnji popis 2026"',
  `datum_od` DATE NOT NULL,
  `datum_do` DATE NULL,
  `status` ENUM('U_PRIPREMI','U_TOKU','ZAVRSEN','OTKAZAN') NOT NULL DEFAULT 'U_PRIPREMI',
  `predsednik_komisije` VARCHAR(150) NULL,
  `clanovi_komisije` TEXT NULL,
  `napomena` TEXT NULL,
  `datum_kreiranja` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Kampanje popisa (inventure) osnovnih sredstava';


CREATE TABLE `stavke_popisa` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `popis_id` INT UNSIGNED NOT NULL,
  `sredstvo_id` BIGINT UNSIGNED NOT NULL,
  `popisano_stanje` ENUM('PRONADJENO','NIJE_PRONADJENO','VISAK') NOT NULL,
  `popisana_lokacija_id` INT UNSIGNED NULL COMMENT 'Lokacija na kojoj je sredstvo fizički zatečeno prilikom popisa',
  `napomena` TEXT NULL,
  `datum_popisa_stavke` DATE NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stavka_popisa` (`popis_id`,`sredstvo_id`),
  KEY `idx_stavka_popisa_sredstvo` (`sredstvo_id`),
  KEY `idx_stavka_popisa_lokacija` (`popisana_lokacija_id`),
  CONSTRAINT `fk_stavka_popisa_popis` FOREIGN KEY (`popis_id`)
      REFERENCES `popisi_osnovnih_sredstava` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stavka_popisa_sredstvo` FOREIGN KEY (`sredstvo_id`)
      REFERENCES `osnovna_sredstva` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_stavka_popisa_lokacija` FOREIGN KEY (`popisana_lokacija_id`)
      REFERENCES `lokacije` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pojedinačne stavke (rezultati) popisa po sredstvu';


-- =====================================================================================
-- SEKCIJA 7B: REVERSI (formalno zaduženje sredstava zaposlenom)
-- =====================================================================================
-- Revers je dokument koji se štampa i potpisuje - jednom izdat revers se ne
-- menja (nema "izmene stavki"), samo se može poništiti (status PONISTEN).
-- Ako je nešto pogrešno uneto, izdaje se novi ispravan revers.

CREATE TABLE `reversi` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `broj_reversa` VARCHAR(30) NOT NULL COMMENT 'npr. REV-2026-001 - generiše se automatski',
  `datum_izdavanja` DATE NOT NULL,
  `zaposleni_id` INT UNSIGNED NOT NULL COMMENT 'Zaposleni kome se zadužuju sredstva',
  `korisnik_id` INT UNSIGNED NULL COMMENT 'Sistemski korisnik koji je izdao revers (audit trag)',
  `status` ENUM('IZDAT','PONISTEN') NOT NULL DEFAULT 'IZDAT',
  `napomena` TEXT NULL,
  `datum_kreiranja` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_revers_broj` (`broj_reversa`),
  KEY `idx_revers_zaposleni` (`zaposleni_id`),
  KEY `idx_revers_korisnik` (`korisnik_id`),
  CONSTRAINT `fk_revers_zaposleni` FOREIGN KEY (`zaposleni_id`)
      REFERENCES `zaposleni` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_revers_korisnik` FOREIGN KEY (`korisnik_id`)
      REFERENCES `korisnici` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Reversi - dokumenti kojima se formalno potvrđuje zaduženje sredstava zaposlenom';


CREATE TABLE `stavke_reversa` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `revers_id` INT UNSIGNED NOT NULL,
  `sredstvo_id` BIGINT UNSIGNED NOT NULL,
  `napomena` VARCHAR(500) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stavka_reversa` (`revers_id`,`sredstvo_id`),
  KEY `idx_stavka_reversa_sredstvo` (`sredstvo_id`),
  CONSTRAINT `fk_stavka_reversa_revers` FOREIGN KEY (`revers_id`)
      REFERENCES `reversi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stavka_reversa_sredstvo` FOREIGN KEY (`sredstvo_id`)
      REFERENCES `osnovna_sredstva` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stavke reversa - pojedinačna sredstva navedena na jednom reversu';


-- =====================================================================================
-- SEKCIJA 8: PRATEĆA DOKUMENTACIJA
-- =====================================================================================

CREATE TABLE `dokumenti_sredstva` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sredstvo_id` BIGINT UNSIGNED NOT NULL,
  `naziv_dokumenta` VARCHAR(200) NOT NULL,
  `tip_dokumenta` ENUM('RACUN','UGOVOR','GARANCIJA','FOTOGRAFIJA','ZAPISNIK','IZVESTAJ_PROCENE','OSTALO')
      NOT NULL DEFAULT 'OSTALO',
  `putanja_fajla` VARCHAR(500) NOT NULL COMMENT 'Putanja/URL do fajla na disku ili storage servisu',
  `datum_dokumenta` DATE NULL,
  `napomena` TEXT NULL,
  `datum_otpremanja` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dokument_sredstvo` (`sredstvo_id`),
  KEY `idx_dokument_tip` (`tip_dokumenta`),
  CONSTRAINT `fk_dokument_sredstvo` FOREIGN KEY (`sredstvo_id`)
      REFERENCES `osnovna_sredstva` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Prateća dokumentacija (računi, ugovori, garancije, fotografije...) vezana za sredstvo';


-- =====================================================================================
-- SEKCIJA 9: REFERENTNI (SEED) PODACI
-- =====================================================================================

-- --- Statusi sredstva -------------------------------------------------------
INSERT INTO `statusi_sredstva`
  (`sifra`,`naziv`,`opis`,`da_li_se_amortizuje_u_ovom_statusu`,`da_li_je_zavrsni_status`,`redosled_prikaza`)
VALUES
  ('U_PRIPREMI','U pripremi','Sredstvo u nabavci/izgradnji, još nije aktivirano',0,0,10),
  ('U_UPOTREBI','U upotrebi','Sredstvo je aktivno i koristi se',1,0,20),
  ('PRIVREMENO_VAN_UPOTREBE','Privremeno van upotrebe','Sredstvo trenutno miruje (npr. sezonska oprema)',1,0,30),
  ('NA_ODRZAVANJU','Na održavanju','Sredstvo je na servisu/popravci',1,0,40),
  ('PRIPREMLJENO_ZA_RASHODOVANJE','Pripremljeno za rashodovanje','Predlog za rashodovanje je pokrenut',1,0,50),
  ('RASHODOVANO','Rashodovano','Sredstvo je rashodovano/uklonjeno iz upotrebe',0,1,60),
  ('PRODATO','Prodato','Sredstvo je prodato trećem licu',0,1,70),
  ('OTPISANO','Otpisano','Sredstvo je otpisano (šteta, krađa i sl.)',0,1,80);

-- --- Metode amortizacije ------------------------------------------------------
INSERT INTO `metode_amortizacije` (`sifra`,`naziv`,`tip_obracuna`,`opis`) VALUES
  ('LINEARNA','Linearna (proporcionalna) metoda','LINEARNA','Ravnomeran otpis tokom veka trajanja'),
  ('DEGRESIVNA_DUPLA','Degresivna metoda - dupli opadajući saldo','DEGRESIVNA_DUPLA','Ubrzana amortizacija sa opadajućim iznosima'),
  ('SUMA_GODINA','Degresivna metoda - suma brojeva godina','SUMA_GODINA','Ubrzana amortizacija prema sumi godina veka trajanja'),
  ('FUNKCIONALNA','Funkcionalna metoda','FUNKCIONALNA','Amortizacija prema stvarnom učinku/obimu korišćenja (npr. pređeni km, radni sati)'),
  ('BEZ_AMORTIZACIJE','Bez amortizacije','BEZ_AMORTIZACIJE','Za sredstva koja se ne amortizuju (npr. zemljište)');

-- --- Vrste transakcija (životni ciklus) ---------------------------------------
INSERT INTO `vrste_transakcija` (`sifra`,`naziv`,`opis`,`utice_na_knjigovodstvenu_vrednost`,`smer_uticaja`) VALUES
  ('NABAVKA','Nabavka sredstva','Evidentiranje novog osnovnog sredstva',1,'POVECANJE'),
  ('AKTIVACIJA','Aktivacija/kapitalizacija','Prelazak sredstva iz pripreme (CIP) u upotrebu',0,'NEUTRALNO'),
  ('PREMESTAJ','Premeštaj','Promena lokacije, mesta troška ili zaduženog lica',0,'NEUTRALNO'),
  ('REVALORIZACIJA_POVECANJE','Revalorizacija - povećanje','Usklađivanje vrednosti naviše',1,'POVECANJE'),
  ('REVALORIZACIJA_SMANJENJE','Revalorizacija/obezvređenje - smanjenje','Usklađivanje vrednosti naniže / impairment',1,'SMANJENJE'),
  ('POBOLJSANJE','Investiciono poboljšanje','Kapitalno ulaganje koje povećava vrednost sredstva',1,'POVECANJE'),
  ('DELIMICAN_RASHOD','Delimičan rashod','Rashodovanje dela/komponente sredstva',1,'SMANJENJE'),
  ('RASHODOVANJE','Rashodovanje','Konačno isknjižavanje sredstva iz upotrebe',1,'SMANJENJE'),
  ('PRODAJA','Prodaja','Prodaja sredstva trećem licu',1,'SMANJENJE'),
  ('OTPIS_STETA_KRADJA','Otpis usled štete/krađe','Vanredni gubitak sredstva',1,'SMANJENJE'),
  ('OBRACUN_AMORTIZACIJE','Periodični obračun amortizacije','Redovan mesečni/godišnji obračun',1,'SMANJENJE'),
  ('POPIS_VISAK','Višak utvrđen popisom','Sredstvo zatečeno na popisu, a nije bilo u evidenciji',1,'POVECANJE'),
  ('POPIS_MANJAK','Manjak utvrđen popisom','Sredstvo iz evidencije nije pronađeno na popisu',1,'SMANJENJE'),
  ('STORNO','Storno prethodne transakcije','Ispravka/poništavanje prethodno unete transakcije',1,'NEUTRALNO');

-- --- Amortizacione grupe (stope NAMERNO prazne - videti napomenu na vrhu fajla) --
INSERT INTO `amortizacione_grupe` (`sifra`,`naziv`,`godisnja_stopa_procenat`,`vek_trajanja_godine`,`opis`) VALUES
  ('I','Amortizaciona grupa I',NULL,NULL,'Popuniti stopu/vek u skladu sa važećim propisom - obično nepokretnosti'),
  ('II','Amortizaciona grupa II',NULL,NULL,'Popuniti stopu/vek u skladu sa važećim propisom'),
  ('III','Amortizaciona grupa III',NULL,NULL,'Popuniti stopu/vek u skladu sa važećim propisom'),
  ('IV','Amortizaciona grupa IV',NULL,NULL,'Popuniti stopu/vek u skladu sa važećim propisom'),
  ('V','Amortizaciona grupa V',NULL,NULL,'Popuniti stopu/vek u skladu sa važećim propisom - obično IT/računarska oprema');

-- --- Primer hijerarhije klasa osnovnih sredstava -------------------------------
INSERT INTO `klase_osnovnih_sredstava` (`sifra`,`naziv`,`opis`,`nadredjena_klasa_id`,`tip_sredstva`) VALUES
  ('ZEM','Zemljište','Zemljišne parcele - po pravilu se ne amortizuju',NULL,'MATERIJALNO'),
  ('GRAD','Građevinski objekti','Poslovne zgrade, hale, magacini',NULL,'MATERIJALNO'),
  ('OPR','Postrojenja i oprema','Nadređena grupa za svu proizvodnu i poslovnu opremu',NULL,'MATERIJALNO'),
  ('NEMAT','Nematerijalna ulaganja','Softverske licence, patenti, koncesije',NULL,'NEMATERIJALNO'),
  ('INVNEKR','Investicione nekretnine','Nekretnine koje se drže radi izdavanja ili porasta vrednosti',NULL,'INVESTICIONA_NEKRETNINA');

INSERT INTO `klase_osnovnih_sredstava` (`sifra`,`naziv`,`opis`,`nadredjena_klasa_id`,`tip_sredstva`)
VALUES
  ('OPR-VOZ','Vozila','Putnička i teretna vozila',
      (SELECT id FROM (SELECT id FROM `klase_osnovnih_sredstava` WHERE `sifra`='OPR') AS t),'MATERIJALNO'),
  ('OPR-IT','IT oprema','Računari, serveri, mrežna oprema',
      (SELECT id FROM (SELECT id FROM `klase_osnovnih_sredstava` WHERE `sifra`='OPR') AS t),'MATERIJALNO'),
  ('OPR-NAM','Nameštaj i kancelarijska oprema','Kancelarijski nameštaj i oprema',
      (SELECT id FROM (SELECT id FROM `klase_osnovnih_sredstava` WHERE `sifra`='OPR') AS t),'MATERIJALNO');

-- Primer korišćenja ukljucuje_se_u_popis: nematerijalna ulaganja (licence, patenti)
-- se tipično NE popisuju fizičkim obilaskom, već proverom dokumentacije/ugovora -
-- zato su ovde po podrazumevanoj (primer) politici isključena iz popisa. Ostale
-- klase ostaju na podrazumevanom uključeno (1) - promeni po potrebi kroz UI.
UPDATE `klase_osnovnih_sredstava` SET `ukljucuje_se_u_popis` = 0 WHERE `sifra` = 'NEMAT';

-- --- Primer definicija dodatnih atributa po klasi (EAV) ------------------------
INSERT INTO `definicije_atributa`
  (`klasa_id`,`sifra`,`naziv`,`tip_podatka`,`jedinica_mere`,`obavezno_polje`,`redosled_prikaza`)
VALUES
  ((SELECT id FROM (SELECT id FROM `klase_osnovnih_sredstava` WHERE `sifra`='OPR-VOZ') AS t),
      'REG_OZNAKA','Registarska oznaka','TEKST',NULL,1,10),
  ((SELECT id FROM (SELECT id FROM `klase_osnovnih_sredstava` WHERE `sifra`='OPR-VOZ') AS t),
      'BROJ_SASIJE','Broj šasije (VIN)','TEKST',NULL,1,20),
  ((SELECT id FROM (SELECT id FROM `klase_osnovnih_sredstava` WHERE `sifra`='OPR-VOZ') AS t),
      'ZAPREMINA_MOTORA','Zapremina motora','CEO_BROJ','cm3',0,30),
  ((SELECT id FROM (SELECT id FROM `klase_osnovnih_sredstava` WHERE `sifra`='OPR-IT') AS t),
      'PROCESOR','Procesor','TEKST',NULL,0,10),
  ((SELECT id FROM (SELECT id FROM `klase_osnovnih_sredstava` WHERE `sifra`='OPR-IT') AS t),
      'RAM_MEMORIJA','RAM memorija','TEKST','GB',0,20),
  ((SELECT id FROM (SELECT id FROM `klase_osnovnih_sredstava` WHERE `sifra`='OPR-IT') AS t),
      'MAC_ADRESA','MAC adresa','TEKST',NULL,0,30),
  ((SELECT id FROM (SELECT id FROM `klase_osnovnih_sredstava` WHERE `sifra`='GRAD') AS t),
      'KVADRATURA','Korisna površina','DECIMALNI_BROJ','m2',1,10),
  ((SELECT id FROM (SELECT id FROM `klase_osnovnih_sredstava` WHERE `sifra`='GRAD') AS t),
      'BROJ_SPRATOVA','Broj spratova','CEO_BROJ',NULL,0,20),
  ((SELECT id FROM (SELECT id FROM `klase_osnovnih_sredstava` WHERE `sifra`='GRAD') AS t),
      'BROJ_KATASTARSKE_PARCELE','Broj katastarske parcele','TEKST',NULL,0,30);

-- --- Korisničke role -------------------------------------------------------
INSERT INTO `korisnicke_role` (`sifra`,`naziv`,`opis`) VALUES
  ('ADMIN','Administrator','Pun pristup svim modulima i podešavanjima sistema'),
  ('KNJIGOVODJA','Knjigovođa','Vođenje evidencije sredstava, amortizacije i popisa'),
  ('MAGACIONER','Magacioner / zadužena osoba','Unos i zaduženje sredstava, učešće u popisu'),
  ('PREGLED','Samo pregled','Read-only pristup evidenciji sredstava, bez izmena');

-- --- Dozvole (grupisane po modulu radi lakšeg prikaza u UI) -------------------
INSERT INTO `dozvole` (`sifra`,`naziv`,`modul`) VALUES
  ('SREDSTVA_PREGLED','Pregled osnovnih sredstava','SREDSTVA'),
  ('SREDSTVA_UNOS','Unos novog osnovnog sredstva','SREDSTVA'),
  ('SREDSTVA_IZMENA','Izmena osnovnog sredstva','SREDSTVA'),
  ('SREDSTVA_RASHODOVANJE','Rashodovanje i prodaja sredstva','SREDSTVA'),
  ('KLASE_UPRAVLJANJE','Upravljanje klasama osnovnih sredstava','SIFARNICI'),
  ('SIFARNICI_UPRAVLJANJE','Upravljanje ostalim šifarnicima (lokacije, mesta troška, dobavljači, amortizacione grupe/metode)','SIFARNICI'),
  ('AMORTIZACIJA_OBRACUN','Pokretanje i knjiženje obračuna amortizacije','AMORTIZACIJA'),
  ('POPIS_UPRAVLJANJE','Kreiranje i sprovođenje popisa (inventure)','POPIS'),
  ('ZAPOSLENI_UPRAVLJANJE','Upravljanje evidencijom zaposlenih','KORISNICI'),
  ('KORISNICI_UPRAVLJANJE','Upravljanje korisničkim nalozima, rolama i pravima pristupa','KORISNICI');

-- --- Dodela dozvola rolama -----------------------------------------------------
-- ADMIN dobija sve postojeće dozvole
INSERT INTO `role_dozvole` (`rola_id`,`dozvola_id`)
SELECT r.id, d.id
FROM `korisnicke_role` r
CROSS JOIN `dozvole` d
WHERE r.sifra = 'ADMIN';

-- KNJIGOVODJA
INSERT INTO `role_dozvole` (`rola_id`,`dozvola_id`)
SELECT r.id, d.id
FROM `korisnicke_role` r
CROSS JOIN `dozvole` d
WHERE r.sifra = 'KNJIGOVODJA'
  AND d.sifra IN ('SREDSTVA_PREGLED','SREDSTVA_UNOS','SREDSTVA_IZMENA','SREDSTVA_RASHODOVANJE',
                   'KLASE_UPRAVLJANJE','SIFARNICI_UPRAVLJANJE','AMORTIZACIJA_OBRACUN','POPIS_UPRAVLJANJE');

-- MAGACIONER
INSERT INTO `role_dozvole` (`rola_id`,`dozvola_id`)
SELECT r.id, d.id
FROM `korisnicke_role` r
CROSS JOIN `dozvole` d
WHERE r.sifra = 'MAGACIONER'
  AND d.sifra IN ('SREDSTVA_PREGLED','SREDSTVA_UNOS','SREDSTVA_IZMENA','POPIS_UPRAVLJANJE');

-- PREGLED
INSERT INTO `role_dozvole` (`rola_id`,`dozvola_id`)
SELECT r.id, d.id
FROM `korisnicke_role` r
CROSS JOIN `dozvole` d
WHERE r.sifra = 'PREGLED'
  AND d.sifra = 'SREDSTVA_PREGLED';


-- =====================================================================================
-- SEKCIJA 10: POMOĆNI PREGLEDNI VIEW (opciono)
-- =====================================================================================

CREATE OR REPLACE VIEW `pregled_osnovnih_sredstava` AS
SELECT
    os.id,
    os.inventarski_broj,
    os.naziv,
    k.naziv AS klasa,
    s.naziv AS status,
    l.naziv AS lokacija,
    mt.naziv AS mesto_troska,
    os.odgovorno_lice,
    CASE WHEN z.id IS NOT NULL THEN CONCAT(z.ime, ' ', z.prezime) ELSE NULL END AS zaduzeni_zaposleni,
    os.datum_nabavke,
    os.nabavna_vrednost,
    os.akumulirana_amortizacija,
    os.sadasnja_knjigovodstvena_vrednost,
    os.da_li_se_amortizuje
FROM `osnovna_sredstva` os
JOIN `klase_osnovnih_sredstava` k ON k.id = os.klasa_id
JOIN `statusi_sredstva` s ON s.id = os.status_id
LEFT JOIN `lokacije` l ON l.id = os.lokacija_id
LEFT JOIN `mesta_troska` mt ON mt.id = os.mesto_troska_id
LEFT JOIN `zaposleni` z ON z.id = os.zaposleni_id;


SET FOREIGN_KEY_CHECKS = 1;


-- =====================================================================================
-- IZMENA ŠEME: Razduženje reversa (vraćanje zaduženih sredstava)
-- =====================================================================================
-- Dodaje mogućnost da se pojedinačna stavka reversa označi kao vraćena, i da se
-- status celog reversa automatski prati kroz IZDAT -> DELIMICNO_VRACEN -> VRACEN.
-- Revers i dalje ostaje "nepromenljiv dokument" u smislu stavki (ne brišu se i
-- ne dodaju nove stavke posle izdavanja) - samo se svaka postojeća stavka može
-- označiti kao vraćena.

ALTER TABLE `stavke_reversa`
  ADD COLUMN `vraceno` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Da li je ova stavka (sredstvo) vraćena' AFTER `napomena`,
  ADD COLUMN `datum_vracanja` DATE NULL COMMENT 'Datum kada je sredstvo fizički vraćeno' AFTER `vraceno`,
  ADD COLUMN `napomena_vracanja` VARCHAR(500) NULL COMMENT 'Napomena uneta prilikom vraćanja (stanje sredstva i sl.)' AFTER `datum_vracanja`,
  ADD COLUMN `korisnik_vratio_id` INT UNSIGNED NULL COMMENT 'Sistemski korisnik koji je evidentirao vraćanje' AFTER `napomena_vracanja`,
  ADD CONSTRAINT `fk_stavka_reversa_korisnik_vratio` FOREIGN KEY (`korisnik_vratio_id`)
      REFERENCES `korisnici` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `reversi`
  MODIFY COLUMN `status` ENUM('IZDAT','DELIMICNO_VRACEN','VRACEN','PONISTEN') NOT NULL DEFAULT 'IZDAT';

INSERT IGNORE INTO `vrste_transakcija` (`sifra`,`naziv`,`opis`,`utice_na_knjigovodstvenu_vrednost`,`smer_uticaja`) VALUES
  ('RAZDUZENJE','Razduženje sredstva','Vraćanje ranije zaduženog sredstva (revers)',0,'NEUTRALNO');

-- =====================================================================================
-- IZMENA ŠEME: Zaduženje kao vrsta transakcije (za potpunu Istoriju kretanja)
-- =====================================================================================
-- Do sada se izdavanje reversa (zaduženje) nije upisivalo u centralni dnevnik
-- transakcije_sredstva - samo premeštaj i razduženje. Ovim se dodaje vrsta
-- transakcije ZADUZENJE koju revers_form.php sada koristi, tako da
-- "Istorija kretanja" prikazuje kompletan životni ciklus kretanja sredstva
-- (zaduženje -> premeštaj -> razduženje...) na jednom mestu.

INSERT IGNORE INTO `vrste_transakcija` (`sifra`,`naziv`,`opis`,`utice_na_knjigovodstvenu_vrednost`,`smer_uticaja`) VALUES
  ('ZADUZENJE','Zaduženje sredstva','Izdavanje sredstva zaposlenom putem reversa',0,'NEUTRALNO');




-- =====================================================================================
-- IZMENA ŠEME: Dokument premeštaja (broj dokumenta + štampa)
-- =====================================================================================
-- Do sada je premeštaj postojao samo kao niz pojedinačnih redova u
-- premestaji_sredstva, bez zajedničkog broja dokumenta. Ovim se dodaje
-- zaglavlje dokumenta (broj, datum, izabrana nova lokacija/mesto troška,
-- ko je izvršio) - isti obrazac kao `reversi` za zaduženje. Grupni premeštaj
-- više sredstava odjednom sada dobija JEDAN broj dokumenta koji povezuje sve
-- pojedinačne stavke u premestaji_sredstva.

CREATE TABLE `dokumenti_premestaja` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `broj_dokumenta` VARCHAR(30) NOT NULL COMMENT 'npr. PREM-2026-001 - generiše se automatski',
  `datum_premestaja` DATE NOT NULL,
  `nova_lokacija_id` INT UNSIGNED NULL COMMENT 'Izabrana nova lokacija na formi (NULL ako se lokacija ne menja ovim dokumentom)',
  `novo_mesto_troska_id` INT UNSIGNED NULL COMMENT 'Izabrano novo mesto troška na formi (NULL ako se ne menja ovim dokumentom)',
  `korisnik_id` INT UNSIGNED NULL COMMENT 'Sistemski korisnik koji je izvršio premeštaj (audit trag)',
  `napomena` TEXT NULL,
  `datum_kreiranja` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dokument_premestaja_broj` (`broj_dokumenta`),
  KEY `idx_dokument_premestaja_lokacija` (`nova_lokacija_id`),
  KEY `idx_dokument_premestaja_mesto_troska` (`novo_mesto_troska_id`),
  KEY `idx_dokument_premestaja_korisnik` (`korisnik_id`),
  CONSTRAINT `fk_dokument_premestaja_lokacija` FOREIGN KEY (`nova_lokacija_id`)
      REFERENCES `lokacije` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_dokument_premestaja_mesto_troska` FOREIGN KEY (`novo_mesto_troska_id`)
      REFERENCES `mesta_troska` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_dokument_premestaja_korisnik` FOREIGN KEY (`korisnik_id`)
      REFERENCES `korisnici` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Dokument (zaglavlje) grupnog premeštaja - broj dokumenta za štampu, povezuje više stavki u premestaji_sredstva';

ALTER TABLE `premestaji_sredstva`
  ADD COLUMN `dokument_premestaja_id` INT UNSIGNED NULL COMMENT 'Zaglavlje dokumenta premeštaja - NULL za zapise nastale pre uvođenja dokumenta' AFTER `id`,
  ADD KEY `idx_premestaj_dokument` (`dokument_premestaja_id`),
  ADD CONSTRAINT `fk_premestaj_dokument` FOREIGN KEY (`dokument_premestaja_id`)
      REFERENCES `dokumenti_premestaja` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;



-- =====================================================================================
-- IZMENA ŠEME: Status pipeline reversa (U_PRIPREMI -> IZDAT -> VRACEN)
-- =====================================================================================
-- Revers se sada kreira kao NACRT (U_PRIPREMI) - stvarno zaduženje (upis u
-- osnovna_sredstva.zaposleni_id i transakcije_sredstva) dešava se tek kada se
-- revers IZDA. Nema više ručnog parcijalnog vraćanja - kad se sredstvo ponovo
-- zaduži DRUGIM reversom, stara stavka se AUTOMATSKI označava vraćenom.
-- Poništavanje je dozvoljeno samo dok je revers U_PRIPREMI (pre izdavanja) -
-- jednom izdat revers je pravno obavezujući dokument i ne može se poništiti.

ALTER TABLE `reversi`
  MODIFY COLUMN `status` ENUM('U_PRIPREMI','IZDAT','VRACEN','PONISTEN') NOT NULL DEFAULT 'U_PRIPREMI';


-- =====================================================================================
-- KRAJ SKRIPTE
-- =====================================================================================
