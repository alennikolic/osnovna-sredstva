<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akcija']) && $_POST['akcija'] === 'ponisti' && $revers['status'] === 'IZDAT') {
    try {
        $pdo->prepare("UPDATE reversi SET status = 'PONISTEN' WHERE id = :id")->execute([':id' => $id]);
        header("Location: revers_pregled.php?id=" . $id);
        exit;
    } catch (\PDOException $e) {
        $poruka = "Greška pri poništavanju: " . $e->getMessage();
    }
}

$stmt = $pdo->prepare(
    "SELECT sr.id, os.id AS sredstvo_id, os.inventarski_broj, os.naziv, k.naziv AS naziv_klase
     FROM stavke_reversa sr
     JOIN osnovna_sredstva os ON os.id = sr.sredstvo_id
     JOIN klase_osnovnih_sredstava k ON k.id = os.klasa_id
     WHERE sr.revers_id = :id
     ORDER BY os.naziv"
);
$stmt->execute([':id' => $id]);
$stavke = $stmt->fetchAll();

$naslovStranice = 'Revers ' . $revers['broj_reversa'];
require_once 'header.php';
?>

<div class="form-container forma-siroka">
    <h2>
        Revers <?= htmlspecialchars($revers['broj_reversa']) ?>
        <span class="oznaka <?= $revers['status'] === 'IZDAT' ? 'oznaka-aktivna' : 'oznaka-otkazana' ?>">
            <?= $revers['status'] === 'IZDAT' ? 'Izdat' : 'Poništen' ?>
        </span>
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

<div style="margin-top: 20px;">
    <table>
        <thead>
            <tr>
                <th>Inventarski broj</th>
                <th>Naziv</th>
                <th>Klasa</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stavke as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['inventarski_broj']) ?></td>
                    <td><?= htmlspecialchars($s['naziv']) ?></td>
                    <td><?= htmlspecialchars($s['naziv_klase']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div style="margin-top: 20px;">
    <a href="reversi_index.php" class="btn-cancel">Nazad na listu reversa</a>
</div>

<?php require_once 'footer.php'; ?>
