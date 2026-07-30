<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';
require_once 'helpers.php';

$poruka = '';
$tipPoruke = 'success';

// Aktivacija/deaktivacija lokacije (soft-delete - lokacija može biti povezana
// sa sredstvima, zaposlenima i podlokacijama, pa se fizički ne briše)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promeni_status_id'])) {
    $id = (int)$_POST['promeni_status_id'];
    try {
        $stmt = $pdo->prepare("UPDATE lokacije SET aktivna = 1 - aktivna WHERE id = :id");
        $stmt->execute([':id' => $id]);
        header("Location: lokacije_index.php");
        exit;
    } catch (\PDOException $e) {
        $poruka = "Greška pri promeni statusa: " . $e->getMessage();
        $tipPoruke = 'error';
    }
}

$lokacije = ucitajLokacijeHijerarhijski($pdo);

$naslovStranice = 'Lokacije';
require_once 'header.php';
?>

    <h1>Lokacije</h1>
    <a href="lokacije_form.php" class="btn-add">+ Nova lokacija</a>

    <?php if ($poruka): ?>
        <div class="poruka poruka-<?= $tipPoruke ?>"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Šifra</th>
                <th>Naziv</th>
                <th>Adresa</th>
                <th>Grad</th>
                <th>Status</th>
                <th>Akcije</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($lokacije)): ?>
                <tr><td colspan="6" style="text-align:center;">Nema unetih lokacija.</td></tr>
            <?php else: ?>
                <?php foreach ($lokacije as $l): ?>
                    <tr class="<?= $l['aktivna'] ? '' : 'neaktivna-vrsta' ?>">
                        <td><?= htmlspecialchars($l['sifra']) ?></td>
                        <td><?= str_repeat('— ', $l['nivo']) . htmlspecialchars($l['naziv']) ?></td>
                        <td><?= htmlspecialchars($l['adresa'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($l['grad'] ?? '—') ?></td>
                        <td>
                            <?php if ($l['aktivna']): ?>
                                <span class="oznaka oznaka-aktivna">Aktivna</span>
                            <?php else: ?>
                                <span class="oznaka oznaka-neaktivna">Neaktivna</span>
                            <?php endif; ?>
                        </td>
                        <td class="akcije">
                            <a href="lokacije_form.php?id=<?= $l['id'] ?>">Izmeni</a>
                            <form method="POST" class="forma-status" style="display:inline;"
                                  data-naziv="<?= htmlspecialchars($l['naziv'], ENT_QUOTES) ?>"
                                  data-aktivna="<?= $l['aktivna'] ? '1' : '0' ?>">
                                <input type="hidden" name="promeni_status_id" value="<?= $l['id'] ?>">
                                <button type="submit"><?= $l['aktivna'] ? 'Deaktiviraj' : 'Aktiviraj' ?></button>
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
                if (!confirm(akcija + ' lokaciju "' + naziv + '"?')) {
                    e.preventDefault();
                }
            });
        });
    </script>

<?php require_once 'footer.php'; ?>
