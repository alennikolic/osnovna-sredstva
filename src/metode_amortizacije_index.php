<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$poruka = '';
$tipPoruke = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promeni_status_id'])) {
    $id = (int)$_POST['promeni_status_id'];
    try {
        $pdo->prepare("UPDATE metode_amortizacije SET aktivna = 1 - aktivna WHERE id = :id")->execute([':id' => $id]);
        header("Location: metode_amortizacije_index.php");
        exit;
    } catch (\PDOException $e) {
        $poruka = "Greška pri promeni statusa: " . $e->getMessage();
        $tipPoruke = 'error';
    }
}

$metode = $pdo->query("SELECT * FROM metode_amortizacije ORDER BY naziv")->fetchAll();

$nazivTipa = [
    'LINEARNA'          => 'Linearna',
    'DEGRESIVNA_DUPLA'  => 'Degresivna (dupla)',
    'SUMA_GODINA'       => 'Suma godina',
    'FUNKCIONALNA'      => 'Funkcionalna',
    'BEZ_AMORTIZACIJE'  => 'Bez amortizacije',
];

$naslovStranice = 'Metode amortizacije';
require_once 'header.php';
?>

    <h1>Metode amortizacije</h1>
    <a href="metode_amortizacije_form.php" class="btn-add">+ Nova metoda</a>

    <?php if ($poruka): ?>
        <div class="poruka poruka-<?= $tipPoruke ?>"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <p class="napomena-polje" style="margin-bottom:15px;">
        Napomena: obračun amortizacije trenutno stvarno računa samo za tip "Linearna" - sredstva sa drugim metodama biće preskočena pri obračunu (planirano za budući razvoj).
    </p>

    <table>
        <thead>
            <tr>
                <th>Šifra</th>
                <th>Naziv</th>
                <th>Tip obračuna</th>
                <th>Status</th>
                <th>Akcije</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($metode)): ?>
                <tr><td colspan="5" style="text-align:center;">Nema unetih metoda amortizacije.</td></tr>
            <?php else: ?>
                <?php foreach ($metode as $m): ?>
                    <tr class="<?= $m['aktivna'] ? '' : 'neaktivna-vrsta' ?>">
                        <td><?= htmlspecialchars($m['sifra']) ?></td>
                        <td><?= htmlspecialchars($m['naziv']) ?></td>
                        <td><?= htmlspecialchars($nazivTipa[$m['tip_obracuna']] ?? $m['tip_obracuna']) ?></td>
                        <td>
                            <?php if ($m['aktivna']): ?>
                                <span class="oznaka oznaka-aktivna">Aktivna</span>
                            <?php else: ?>
                                <span class="oznaka oznaka-neaktivna">Neaktivna</span>
                            <?php endif; ?>
                        </td>
                        <td class="akcije">
                            <a href="metode_amortizacije_form.php?id=<?= $m['id'] ?>">Izmeni</a>
                            <form method="POST" class="forma-status" style="display:inline;"
                                  data-naziv="<?= htmlspecialchars($m['naziv'], ENT_QUOTES) ?>"
                                  data-aktivna="<?= $m['aktivna'] ? '1' : '0' ?>">
                                <input type="hidden" name="promeni_status_id" value="<?= $m['id'] ?>">
                                <button type="submit"><?= $m['aktivna'] ? 'Deaktiviraj' : 'Aktiviraj' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        document.querySelectorAll('.forma-status').forEach(function (forma) {
            forma.addEventListener('submit', function (e) {
                var naziv = forma.dataset.naziv;
                var akcija = forma.dataset.aktivna === '1' ? 'Deaktivirati' : 'Aktivirati';
                if (!confirm(akcija + ' metodu "' + naziv + '"?')) {
                    e.preventDefault();
                }
            });
        });
    </script>

<?php require_once 'footer.php'; ?>
