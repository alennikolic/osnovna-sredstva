<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$poruka = '';
$tipPoruke = 'success';

// Aktivacija/deaktivacija dobavljača (soft-delete - dobavljač može biti
// povezan sa sredstvima, pa se fizički ne briše)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promeni_status_id'])) {
    $id = (int)$_POST['promeni_status_id'];
    try {
        $stmt = $pdo->prepare("UPDATE dobavljaci SET aktivan = 1 - aktivan WHERE id = :id");
        $stmt->execute([':id' => $id]);
        header("Location: dobavljaci_index.php");
        exit;
    } catch (\PDOException $e) {
        $poruka = "Greška pri promeni statusa: " . $e->getMessage();
        $tipPoruke = 'error';
    }
}

$dobavljaci = $pdo->query("SELECT * FROM dobavljaci ORDER BY naziv")->fetchAll();

$naslovStranice = 'Dobavljači';
require_once 'header.php';
?>

    <h1>Dobavljači</h1>
    <a href="dobavljaci_form.php" class="btn-add">+ Novi dobavljač</a>

    <?php if ($poruka): ?>
        <div class="poruka poruka-<?= $tipPoruke ?>"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Šifra</th>
                <th>Naziv</th>
                <th>PIB</th>
                <th>Grad</th>
                <th>Kontakt osoba</th>
                <th>Status</th>
                <th>Akcije</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dobavljaci)): ?>
                <tr><td colspan="7" style="text-align:center;">Nema unetih dobavljača.</td></tr>
            <?php else: ?>
                <?php foreach ($dobavljaci as $d): ?>
                    <tr class="<?= $d['aktivan'] ? '' : 'neaktivna-vrsta' ?>">
                        <td><?= htmlspecialchars($d['sifra'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($d['naziv']) ?></td>
                        <td><?= htmlspecialchars($d['pib'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($d['grad'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($d['kontakt_osoba'] ?? '—') ?></td>
                        <td>
                            <?php if ($d['aktivan']): ?>
                                <span class="oznaka oznaka-aktivna">Aktivan</span>
                            <?php else: ?>
                                <span class="oznaka oznaka-neaktivna">Neaktivan</span>
                            <?php endif; ?>
                        </td>
                        <td class="akcije">
                            <a href="dobavljaci_form.php?id=<?= $d['id'] ?>">Izmeni</a>
                            <form method="POST" class="forma-status" style="display:inline;"
                                  data-naziv="<?= htmlspecialchars($d['naziv'], ENT_QUOTES) ?>"
                                  data-aktivan="<?= $d['aktivan'] ? '1' : '0' ?>">
                                <input type="hidden" name="promeni_status_id" value="<?= $d['id'] ?>">
                                <button type="submit"><?= $d['aktivan'] ? 'Deaktiviraj' : 'Aktiviraj' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        // Isti obrazac potvrde kao na lokacije_index.php
        document.querySelectorAll('.forma-status').forEach(function (forma) {
            forma.addEventListener('submit', function (e) {
                var naziv = forma.dataset.naziv;
                var akcija = forma.dataset.aktivan === '1' ? 'Deaktivirati' : 'Aktivirati';
                if (!confirm(akcija + ' dobavljača "' + naziv + '"?')) {
                    e.preventDefault();
                }
            });
        });
    </script>

<?php require_once 'footer.php'; ?>
