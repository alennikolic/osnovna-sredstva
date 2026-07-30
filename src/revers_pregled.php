<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';
require_once 'helpers.php';

$id = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : null;
if (empty($id)) {
    header("Location: reversi_index.php");
    exit;
}

$stmt = $pdo->prepare(
    "SELECT r.*, CONCAT(z.ime, ' ', z.prezime) AS ime_zaposlenog, z.radno_mesto,
            k.korisnicko_ime AS izdao_korisnicko_ime
     FROM reversi r
     JOIN zaposleni z ON z.id = r.zaposleni_id
     LEFT JOIN korisnici k ON k.id = r.korisnik_id
     WHERE r.id = :id"
);
$stmt->execute([':id' => $id]);
$revers = $stmt->fetch();

if (!$revers) {
    header("Location: reversi_index.php");
    exit;
}

$poruka = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akcija'])) {

    if ($_POST['akcija'] === 'ponisti' && $revers['status'] === 'IZDAT') {
        try {
            $pdo->prepare("UPDATE reversi SET status = 'PONISTEN' WHERE id = :id")->execute([':id' => $id]);
            header("Location: revers_pregled.php?id=" . $id);
            exit;
        } catch (\PDOException $e) {
            $poruka = "Greška pri poništavanju: " . $e->getMessage();
        }
    }

    if ($_POST['akcija'] === 'vrati' && in_array($revers['status'], ['IZDAT', 'DELIMICNO_VRACEN'], true)) {
        $stavkeZaVracanje = array_map('intval', $_POST['stavke'] ?? []);
        $datumVracanja = trim($_POST['datum_vracanja'] ?? '');
        $napomenaVracanja = trim($_POST['napomena_vracanja'] ?? '');

        if (empty($stavkeZaVracanje)) {
            $poruka = "Izaberite bar jednu stavku za vraćanje.";
        } elseif ($datumVracanja === '') {
            $poruka = "Datum vraćanja je obavezan.";
        } else {
            try {
                $pdo->beginTransaction();

                $trenutni = trenutniKorisnik();

                $vrstaTransakcije = $pdo->query(
                    "SELECT id FROM vrste_transakcija WHERE sifra = 'RAZDUZENJE'"
                )->fetch();
                if (!$vrstaTransakcije) {
                    throw new \RuntimeException('Vrsta transakcije RAZDUZENJE ne postoji u bazi - proverite da li je izmena šeme za razduženje reversa primenjena (dodatak na kraju init.sql).');
                }

                // Uzimamo samo stavke koje pripadaju OVOM reversu i koje još
                // nisu vraćene - zaštita od manipulacije parametrima forme.
                $stmtStavka = $pdo->prepare(
                    "SELECT id, sredstvo_id FROM stavke_reversa
                     WHERE id = :id AND revers_id = :revers AND vraceno = 0"
                );
                $stmtOznaciVraceno = $pdo->prepare(
                    "UPDATE stavke_reversa
                     SET vraceno = 1, datum_vracanja = :datum, napomena_vracanja = :napomena, korisnik_vratio_id = :korisnik
                     WHERE id = :id"
                );
                $stmtTransakcija = $pdo->prepare(
                    "INSERT INTO transakcije_sredstva
                        (sredstvo_id, vrsta_transakcije_id, datum_transakcije, opis, korisnik_id, napomena)
                     VALUES
                        (:sredstvo, :vrsta, :datum, :opis, :korisnik, :napomena)"
                );
                // Sredstvo se razdužuje (zaposleni_id se briše) samo ako je i dalje
                // zaduženo baš na zaposlenog sa OVOG reversa - ako je u međuvremenu
                // premešteno ili zaduženo nekim drugim, novijim reversom, ne diramo
                // trenutno stanje sredstva.
                $stmtOslobodiZaduzenje = $pdo->prepare(
                    "UPDATE osnovna_sredstva SET zaposleni_id = NULL
                     WHERE id = :sredstvo AND zaposleni_id = :zaposleni"
                );

                $brojVracenih = 0;
                foreach ($stavkeZaVracanje as $stavkaId) {
                    $stmtStavka->execute([':id' => $stavkaId, ':revers' => $id]);
                    $stavka = $stmtStavka->fetch();
                    if (!$stavka) {
                        continue; // već vraćeno ili ne pripada ovom reversu - preskoči
                    }

                    $stmtOznaciVraceno->execute([
                        ':datum'    => $datumVracanja,
                        ':napomena' => $napomenaVracanja !== '' ? $napomenaVracanja : null,
                        ':korisnik' => $trenutni['id'] ?? null,
                        ':id'       => $stavkaId,
                    ]);

                    $stmtTransakcija->execute([
                        ':sredstvo' => $stavka['sredstvo_id'],
                        ':vrsta'    => $vrstaTransakcije['id'],
                        ':datum'    => $datumVracanja,
                        ':opis'     => 'Razduženje - vraćanje sredstva po reversu ' . $revers['broj_reversa'],
                        ':korisnik' => $trenutni['id'] ?? null,
                        ':napomena' => $napomenaVracanja !== '' ? $napomenaVracanja : null,
                    ]);

                    $stmtOslobodiZaduzenje->execute([
                        ':sredstvo'  => $stavka['sredstvo_id'],
                        ':zaposleni' => $revers['zaposleni_id'],
                    ]);

                    $brojVracenih++;
                }

                if ($brojVracenih === 0) {
                    $pdo->rollBack();
                    $poruka = "Izabrane stavke su već vraćene ili ne pripadaju ovom reversu.";
                } else {
                    $ukupnoStavki = (int)$pdo->query(
                        "SELECT COUNT(*) FROM stavke_reversa WHERE revers_id = " . (int)$id
                    )->fetchColumn();
                    $ukupnoVracenih = (int)$pdo->query(
                        "SELECT COUNT(*) FROM stavke_reversa WHERE revers_id = " . (int)$id . " AND vraceno = 1"
                    )->fetchColumn();

                    $noviStatus = $ukupnoVracenih >= $ukupnoStavki ? 'VRACEN' : 'DELIMICNO_VRACEN';
                    $pdo->prepare("UPDATE reversi SET status = :status WHERE id = :id")
                        ->execute([':status' => $noviStatus, ':id' => $id]);

                    $pdo->commit();

                    header("Location: revers_pregled.php?id=" . $id);
                    exit;
                }
            } catch (\PDOException $e) {
                $pdo->rollBack();
                $poruka = "Greška pri upisu u bazu: " . $e->getMessage();
            } catch (\RuntimeException $e) {
                $pdo->rollBack();
                $poruka = $e->getMessage();
            }
        }
    }
}

$stmt = $pdo->prepare(
    "SELECT sr.id, sr.vraceno, sr.datum_vracanja, sr.napomena_vracanja,
            os.id AS sredstvo_id, os.inventarski_broj, os.naziv, k.naziv AS naziv_klase
     FROM stavke_reversa sr
     JOIN osnovna_sredstva os ON os.id = sr.sredstvo_id
     JOIN klase_osnovnih_sredstava k ON k.id = os.klasa_id
     WHERE sr.revers_id = :id
     ORDER BY sr.vraceno ASC, os.naziv"
);
$stmt->execute([':id' => $id]);
$stavke = $stmt->fetchAll();

[$nazivStatusaReversa, $klasaOznakeReversa] = oznakaStatusaReversa($revers['status']);

$naslovStranice = 'Revers ' . $revers['broj_reversa'];
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2>
        Revers <?= htmlspecialchars($revers['broj_reversa']) ?>
        <span class="oznaka <?= $klasaOznakeReversa ?>"><?= $nazivStatusaReversa ?></span>
    </h2>

    <?php if ($poruka): ?>
        <div class="error"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <div class="detalj-red">
        <span class="detalj-labela">Zaposleni</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($revers['ime_zaposlenog']) ?><?= $revers['radno_mesto'] ? ' (' . htmlspecialchars($revers['radno_mesto']) . ')' : '' ?></span>
    </div>
    <div class="detalj-red">
        <span class="detalj-labela">Datum izdavanja</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($revers['datum_izdavanja']) ?></span>
    </div>
    <div class="detalj-red">
        <span class="detalj-labela">Izdao</span>
        <span class="detalj-vrednost"><?= htmlspecialchars($revers['izdao_korisnicko_ime'] ?? '—') ?></span>
    </div>
    <?php if (!empty($revers['napomena'])): ?>
    <div class="detalj-red">
        <span class="detalj-labela">Napomena</span>
        <span class="detalj-vrednost"><?= nl2br(htmlspecialchars($revers['napomena'])) ?></span>
    </div>
    <?php endif; ?>

    <div style="margin-top: 20px;">
        <a href="revers_stampa.php?id=<?= $revers['id'] ?>" class="btn" target="_blank">Odštampaj revers</a>
        <?php if ($revers['status'] === 'IZDAT'): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Poništiti ovaj revers? Napomena: poništavanje NE menja automatski trenutno zaduženje sredstava - to se radi posebno, po potrebi.');">
                <input type="hidden" name="akcija" value="ponisti">
                <button type="submit" class="btn" style="background:#dc3545;">Poništi revers</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (in_array($revers['status'], ['IZDAT', 'DELIMICNO_VRACEN'], true)): ?>
<div class="form-container forma-siroka" style="margin-top: 20px;">
    <h3 style="margin-top:0;">Razduženje - vraćanje sredstava</h3>
    <p class="napomena-polje">Označite koja sredstva se vraćaju i unesite datum vraćanja.</p>

    <form method="POST" action="">
        <input type="hidden" name="akcija" value="vrati">

        <div class="red-2">
            <div class="form-group">
                <label>Datum vraćanja *</label>
                <input type="date" name="datum_vracanja" required value="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Napomena o vraćanju <span class="napomena-polje">(stanje sredstva i sl., opciono)</span></label>
            <textarea name="napomena_vracanja"></textarea>
        </div>

        <table style="margin-bottom: 15px;">
            <thead>
                <tr>
                    <th></th>
                    <th>Inventarski broj</th>
                    <th>Naziv</th>
                    <th>Klasa</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stavke as $s): ?>
                    <?php if (!$s['vraceno']): ?>
                    <tr>
                        <td><input type="checkbox" name="stavke[]" value="<?= $s['id'] ?>"></td>
                        <td><?= htmlspecialchars($s['inventarski_broj']) ?></td>
                        <td><?= htmlspecialchars($s['naziv']) ?></td>
                        <td><?= htmlspecialchars($s['naziv_klase']) ?></td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <button type="submit" class="btn">Evidentiraj vraćanje izabranih</button>
    </form>
</div>
<?php endif; ?>

<div style="margin-top: 20px;">
    <div class="detalj-sekcija" style="margin-top:0;">Sve stavke reversa</div>
    <table>
        <thead>
            <tr>
                <th>Inventarski broj</th>
                <th>Naziv</th>
                <th>Klasa</th>
                <th>Status</th>
                <th>Datum vraćanja</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stavke as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['inventarski_broj']) ?></td>
                    <td><?= htmlspecialchars($s['naziv']) ?></td>
                    <td><?= htmlspecialchars($s['naziv_klase']) ?></td>
                    <td>
                        <?php if ($s['vraceno']): ?>
                            <span class="oznaka oznaka-neaktivna">Vraćeno</span>
                        <?php else: ?>
                            <span class="oznaka oznaka-aktivna">Zaduženo</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($s['datum_vracanja'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div style="margin-top: 20px;">
    <a href="reversi_index.php" class="btn-cancel">Nazad na listu reversa</a>
</div>

<?php require_once 'footer.php'; ?>
