<?php
require_once 'db.php';
require_once 'helpers.php';

$poruka = '';
$tipPoruke = 'success';

// Aktivacija/deaktivacija mesta troška (soft-delete - može biti povezano sa
// sredstvima, zaposlenima i podređenim mestima troška, pa se ne briše fizički)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promeni_status_id'])) {
    $id = (int)$_POST['promeni_status_id'];
    try {
        $stmt = $pdo->prepare("UPDATE mesta_troska SET aktivno = 1 - aktivno WHERE id = :id");
        $stmt->execute([':id' => $id]);
        header("Location: mesta_troska_index.php");
        exit;
    } catch (\PDOException $e) {
        $poruka = "Greška pri promeni statusa: " . $e->getMessage();
        $tipPoruke = 'error';
    }
}

$mesta = ucitajMestaTroskaHijerarhijski($pdo);

$naslovStranice = 'Mesta Troška';
require_once 'header.php';
?>

    <h1>Mesta troška</h1>
    <a href="mesta_troska_form.php" class="btn-add">+ Novo mesto troška</a>

    <?php if ($poruka): ?>
        <div class="poruka poruka-<?= $tipPoruke ?>"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Šifra</th>
                <th>Naziv</th>
                <th>Status</th>
                <th>Akcije</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($mesta)): ?>
                <tr><td colspan="4" style="text-align:center;">Nema unetih mesta troška.</td></tr>
            <?php else: ?>
                <?php foreach ($mesta as $m): ?>
                    <tr class="<?= $m['aktivno'] ? '' : 'neaktivna-vrsta' ?>">
                        <td><?= htmlspecialchars($m['sifra']) ?></td>
                        <td><?= str_repeat('— ', $m['nivo']) . htmlspecialchars($m['naziv']) ?></td>
                        <td>
                            <?php if ($m['aktivno']): ?>
                                <span class="oznaka oznaka-aktivna">Aktivno</span>
                            <?php else: ?>
                                <span class="oznaka oznaka-neaktivna">Neaktivno</span>
                            <?php endif; ?>
                        </td>
                        <td class="akcije">
                            <a href="mesta_troska_form.php?id=<?= $m['id'] ?>">Izmeni</a>
                            <form method="POST" class="forma-status" style="display:inline;"
                                  data-naziv="<?= htmlspecialchars($m['naziv'], ENT_QUOTES) ?>"
                                  data-aktivna="<?= $m['aktivno'] ? '1' : '0' ?>">
                                <input type="hidden" name="promeni_status_id" value="<?= $m['id'] ?>">
                                <button type="submit"><?= $m['aktivno'] ? 'Deaktiviraj' : 'Aktiviraj' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        // Isti obrazac potvrde kao na klase_index.php - tekst se gradi u JS-u
        // umesto interpolacijom u HTML atribut.
        document.querySelectorAll('.forma-status').forEach(function (forma) {
            forma.addEventListener('submit', function (e) {
                var naziv = forma.dataset.naziv;
                var akcija = forma.dataset.aktivna === '1' ? 'Deaktivirati' : 'Aktivirati';
                if (!confirm(akcija + ' mesto troška "' + naziv + '"?')) {
                    e.preventDefault();
                }
            });
        });
    </script>

<?php require_once 'footer.php'; ?>
