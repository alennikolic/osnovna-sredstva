<?php
/**
 * revers_form.php
 * ----------------
 * Kreira NACRT reversa (status U_PRIPREMI) - stvarno zaduženje (upis
 * osnovna_sredstva.zaposleni_id i transakcije_sredstva) dešava se tek kada
 * se revers IZDA na revers_pregled.php. Ovo omogućava da se nacrt ispravi
 * ili poništi pre nego što postane pravno obavezujući dokument.
 */

require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';
require_once 'helpers.php';

$poruka = '';

$podaci = [
    'zaposleni_id' => '',
    'datum_izdavanja' => date('Y-m-d'),
    'napomena' => '',
    'sredstva' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $podaci['zaposleni_id'] = $_POST['zaposleni_id'] ?? '';
    $podaci['datum_izdavanja'] = trim($_POST['datum_izdavanja'] ?? '');
    $podaci['napomena'] = trim($_POST['napomena'] ?? '');
    $podaci['sredstva'] = array_map('intval', $_POST['sredstva'] ?? []);

    if ($podaci['zaposleni_id'] === '' || $podaci['datum_izdavanja'] === '') {
        $poruka = "Zaposleni i datum izdavanja su obavezni!";
    } elseif (empty($podaci['sredstva'])) {
        $poruka = "Izaberite bar jedno sredstvo za revers.";
    } else {
        try {
            $pdo->beginTransaction();

            $brojReversa = sledeciBrojReversa($pdo);
            $trenutni = trenutniKorisnik();

            $stmt = $pdo->prepare(
                "INSERT INTO reversi (broj_reversa, datum_izdavanja, zaposleni_id, korisnik_id, napomena, status)
                 VALUES (:broj, :datum, :zaposleni, :korisnik, :napomena, 'U_PRIPREMI')"
            );
            $stmt->execute([
                ':broj'      => $brojReversa,
                ':datum'     => $podaci['datum_izdavanja'],
                ':zaposleni' => (int)$podaci['zaposleni_id'],
                ':korisnik'  => $trenutni['id'] ?? null,
                ':napomena'  => $podaci['napomena'] !== '' ? $podaci['napomena'] : null,
            ]);
            $reversId = (int)$pdo->lastInsertId();

            $stmtStavka = $pdo->prepare(
                "INSERT INTO stavke_reversa (revers_id, sredstvo_id) VALUES (:revers, :sredstvo)"
            );
            foreach ($podaci['sredstva'] as $sredstvoId) {
                $stmtStavka->execute([':revers' => $reversId, ':sredstvo' => $sredstvoId]);
            }

            $pdo->commit();

            header("Location: revers_pregled.php?id=" . $reversId);
            exit;
        } catch (\PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                $poruka = "Došlo je do konflikta pri generisanju broja reversa (verovatno je neko drugi baš u ovom trenutku napravio revers). Pokušajte ponovo.";
            } else {
                $poruka = "Greška pri upisu u bazu: " . $e->getMessage();
            }
        }
    }
}

$zaposleniLista = $pdo->query("SELECT id, ime, prezime FROM zaposleni WHERE aktivan = 1 ORDER BY prezime, ime")->fetchAll();

// Sredstva dostupna za revers - isto pravilo kao kod popisa: svi statusi koji
// NISU završni. "trenutno_zaduzen" je informativan prikaz - stvarna promena
// se dešava tek pri IZDAVANJU ovog nacrta (automatski razdužuje staro).
$stmt = $pdo->query(
    "SELECT os.id, os.inventarski_broj, os.naziv, k.naziv AS naziv_klase,
            CASE WHEN z.id IS NOT NULL THEN CONCAT(z.ime, ' ', z.prezime) ELSE NULL END AS trenutno_zaduzen
     FROM osnovna_sredstva os
     JOIN klase_osnovnih_sredstava k ON k.id = os.klasa_id
     JOIN statusi_sredstva s ON s.id = os.status_id
     LEFT JOIN zaposleni z ON z.id = os.zaposleni_id
     WHERE s.da_li_je_zavrsni_status = 0
     ORDER BY os.naziv"
);
$sveSredstva = $stmt->fetchAll();

$naslovStranice = 'Novi revers';
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2>Novi revers</h2>
    <p class="napomena-polje" style="margin-top:-10px; margin-bottom: 20px;">
        Revers se prvo čuva kao nacrt ("U pripremi") - zaduženje se stvarno izvršava tek kada ga izdate na sledećem ekranu.
    </p>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="red-2">
            <div class="form-group">
                <label>Zaposleni *</label>
                <select name="zaposleni_id" required>
                    <option value="">-- Izaberite zaposlenog --</option>
                    <?php foreach ($zaposleniLista as $z): ?>
                        <option value="<?= $z['id'] ?>" <?= (string)$podaci['zaposleni_id'] === (string)$z['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($z['prezime'] . ' ' . $z['ime']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Datum izdavanja *</label>
                <input type="date" name="datum_izdavanja" required value="<?= htmlspecialchars($podaci['datum_izdavanja']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Napomena</label>
            <textarea name="napomena"><?= htmlspecialchars($podaci['napomena']) ?></textarea>
        </div>

        <div class="form-group">
            <label>Sredstva na reversu * <span class="napomena-polje">(izaberite jedno ili više)</span></label>
            <div class="lista-checkboxova">
                <?php if (empty($sveSredstva)): ?>
                    <p class="napomena-polje">Nema dostupnih sredstava.</p>
                <?php endif; ?>
                <?php foreach ($sveSredstva as $sr): ?>
                    <label class="stavka-checkboxa">
                        <input type="checkbox" name="sredstva[]" value="<?= $sr['id'] ?>"
                               <?= in_array($sr['id'], $podaci['sredstva'], true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($sr['inventarski_broj'] . ' - ' . $sr['naziv']) ?>
                        <span class="napomena-polje">(<?= htmlspecialchars($sr['naziv_klase']) ?>)</span>
                        <?php if ($sr['trenutno_zaduzen']): ?>
                            <span class="napomena-polje">— trenutno zaduženo: <?= htmlspecialchars($sr['trenutno_zaduzen']) ?> (automatski razduženo pri izdavanju ovog reversa)</span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" class="btn">Sačuvaj nacrt reversa</button>
        <a href="reversi_index.php" class="btn-cancel">Otkaži</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>
