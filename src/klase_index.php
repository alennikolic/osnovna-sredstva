<?php
require_once 'db.php';
require_once 'helpers.php';

$poruka = '';
$tipPoruke = 'success';

// Aktivacija/deaktivacija klase (soft-delete - klasa može biti povezana sa
// sredstvima, definicijama atributa i podklasama, pa se fizički ne briše)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promeni_status_id'])) {
    $id = (int)$_POST['promeni_status_id'];
    try {
        $stmt = $pdo->prepare("UPDATE klase_osnovnih_sredstava SET aktivna = 1 - aktivna WHERE id = :id");
        $stmt->execute([':id' => $id]);
        header("Location: klase_index.php");
        exit;
    } catch (\PDOException $e) {
        $poruka = "Greška pri promeni statusa: " . $e->getMessage();
        $tipPoruke = 'error';
    }
}

$klase = ucitajKlaseHijerarhijski($pdo);

$naslovStranice = 'Klase Osnovnih Sredstava';
require_once 'header.php';
?>

    <h1>Klase osnovnih sredstava</h1>
    <a href="klase_form.php" class="btn-add">+ Nova klasa</a>

    <?php if ($poruka): ?>
        <div class="poruka poruka-<?= $tipPoruke ?>"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Šifra</th>
                <th>Naziv</th>
                <th>Tip sredstva</th>
                <th>Amortizaciona grupa</th>
                <th>Metoda amortizacije</th>
                <th>Status</th>
                <th>Akcije</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($klase)): ?>
                <tr><td colspan="7" style="text-align:center;">Nema unetih klasa osnovnih sredstava.</td></tr>
            <?php else: ?>
                <?php foreach ($klase as $k): ?>
                    <tr class="<?= $k['aktivna'] ? '' : 'neaktivna-vrsta' ?>">
                        <td><?= htmlspecialchars($k['sifra']) ?></td>
                        <td><?= str_repeat('— ', $k['nivo']) . htmlspecialchars($k['naziv']) ?></td>
                        <td><?= htmlspecialchars(nazivTipaSredstva($k['tip_sredstva'])) ?></td>
                        <td><?= htmlspecialchars($k['naziv_amort_grupe'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($k['naziv_metode_amort'] ?? '—') ?></td>
                        <td>
                            <?php if ($k['aktivna']): ?>
                                <span class="oznaka oznaka-aktivna">Aktivna</span>
                            <?php else: ?>
                                <span class="oznaka oznaka-neaktivna">Neaktivna</span>
                            <?php endif; ?>
                        </td>
                        <td class="akcije">
                            <a href="klase_form.php?id=<?= $k['id'] ?>">Izmeni</a>
                            <form method="POST" class="forma-status" style="display:inline;"
                                  data-naziv="<?= htmlspecialchars($k['naziv'], ENT_QUOTES) ?>"
                                  data-aktivna="<?= $k['aktivna'] ? '1' : '0' ?>">
                                <input type="hidden" name="promeni_status_id" value="<?= $k['id'] ?>">
                                <button type="submit"><?= $k['aktivna'] ? 'Deaktiviraj' : 'Aktiviraj' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        // Potvrda pre aktivacije/deaktivacije - tekst se gradi u JS-u (a ne
        // interpolacijom u HTML atribut) da bi ispravno radilo bez obzira
        // na to koje znakove (npr. apostrofe) naziv klase sadrži.
        document.querySelectorAll('.forma-status').forEach(function (forma) {
            forma.addEventListener('submit', function (e) {
                var naziv = forma.dataset.naziv;
                var akcija = forma.dataset.aktivna === '1' ? 'Deaktivirati' : 'Aktivirati';
                if (!confirm(akcija + ' klasu "' + naziv + '"?')) {
                    e.preventDefault();
                }
            });
        });
    </script>

<?php require_once 'footer.php'; ?>
