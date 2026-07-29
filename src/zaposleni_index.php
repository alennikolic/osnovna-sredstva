<?php
require_once 'db.php';

$poruka = '';
$tipPoruke = 'success';

// Aktivacija/deaktivacija zaposlenog (soft-delete - zaposleni može biti
// povezan sa sredstvima, premeštajima i korisničkim nalogom, pa se ne briše fizički)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promeni_status_id'])) {
    $id = (int)$_POST['promeni_status_id'];
    try {
        $stmt = $pdo->prepare("UPDATE zaposleni SET aktivan = 1 - aktivan WHERE id = :id");
        $stmt->execute([':id' => $id]);
        header("Location: zaposleni_index.php");
        exit;
    } catch (\PDOException $e) {
        $poruka = "Greška pri promeni statusa: " . $e->getMessage();
        $tipPoruke = 'error';
    }
}

$stmt = $pdo->query(
    "SELECT
        z.id, z.sifra, z.ime, z.prezime, z.radno_mesto, z.aktivan,
        mt.naziv AS naziv_mesta_troska,
        l.naziv AS naziv_lokacije
     FROM zaposleni z
     LEFT JOIN mesta_troska mt ON mt.id = z.mesto_troska_id
     LEFT JOIN lokacije l ON l.id = z.lokacija_id
     ORDER BY z.prezime, z.ime"
);
$zaposleni = $stmt->fetchAll();

$naslovStranice = 'Zaposleni';
require_once 'header.php';
?>

    <h1>Zaposleni</h1>
    <a href="zaposleni_form.php" class="btn-add">+ Novi zaposleni</a>

    <?php if ($poruka): ?>
        <div class="poruka poruka-<?= $tipPoruke ?>"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Šifra</th>
                <th>Ime i prezime</th>
                <th>Radno mesto</th>
                <th>Mesto troška</th>
                <th>Lokacija</th>
                <th>Status</th>
                <th>Akcije</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($zaposleni)): ?>
                <tr><td colspan="7" style="text-align:center;">Nema unetih zaposlenih.</td></tr>
            <?php else: ?>
                <?php foreach ($zaposleni as $z): ?>
                    <tr class="<?= $z['aktivan'] ? '' : 'neaktivna-vrsta' ?>">
                        <td><?= htmlspecialchars($z['sifra'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($z['prezime'] . ' ' . $z['ime']) ?></td>
                        <td><?= htmlspecialchars($z['radno_mesto'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($z['naziv_mesta_troska'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($z['naziv_lokacije'] ?? '—') ?></td>
                        <td>
                            <?php if ($z['aktivan']): ?>
                                <span class="oznaka oznaka-aktivna">Aktivan</span>
                            <?php else: ?>
                                <span class="oznaka oznaka-neaktivna">Neaktivan</span>
                            <?php endif; ?>
                        </td>
                        <td class="akcije">
                            <a href="zaposleni_form.php?id=<?= $z['id'] ?>">Izmeni</a>
                            <form method="POST" class="forma-status" style="display:inline;"
                                  data-naziv="<?= htmlspecialchars($z['ime'] . ' ' . $z['prezime'], ENT_QUOTES) ?>"
                                  data-aktivna="<?= $z['aktivan'] ? '1' : '0' ?>">
                                <input type="hidden" name="promeni_status_id" value="<?= $z['id'] ?>">
                                <button type="submit"><?= $z['aktivan'] ? 'Deaktiviraj' : 'Aktiviraj' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        // Isti obrazac potvrde kao na klase_index.php - tekst se gradi u JS-u
        // umesto interpolacijom u HTML atribut, da bi radilo bez obzira na
        // znakove (npr. apostrofe) u imenu/prezimenu.
        document.querySelectorAll('.forma-status').forEach(function (forma) {
            forma.addEventListener('submit', function (e) {
                var naziv = forma.dataset.naziv;
                var akcija = forma.dataset.aktivna === '1' ? 'Deaktivirati' : 'Aktivirati';
                if (!confirm(akcija + ' zaposlenog "' + naziv + '"?')) {
                    e.preventDefault();
                }
            });
        });
    </script>

<?php require_once 'footer.php'; ?>
