<?php
require_once 'auth.php';
zahtevajPrijavu();
require_once 'db.php';

$poruka = '';
$tipPoruke = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promeni_status_id'])) {
    $id = (int)$_POST['promeni_status_id'];
    try {
        $pdo->prepare("UPDATE amortizacione_grupe SET aktivna = 1 - aktivna WHERE id = :id")->execute([':id' => $id]);
        header("Location: amortizacione_grupe_index.php");
        exit;
    } catch (\PDOException $e) {
        $poruka = "Greška pri promeni statusa: " . $e->getMessage();
        $tipPoruke = 'error';
    }
}

$grupe = $pdo->query("SELECT * FROM amortizacione_grupe ORDER BY sifra")->fetchAll();

$naslovStranice = 'Amortizacione grupe';
require_once 'header.php';
?>

    <h1>Amortizacione grupe</h1>
    <a href="amortizacione_grupe_form.php" class="btn-add">+ Nova amortizaciona grupa</a>

    <?php if ($poruka): ?>
        <div class="poruka poruka-<?= $tipPoruke ?>"><?= htmlspecialchars($poruka) ?></div>
    <?php endif; ?>

    <p class="napomena-polje" style="margin-bottom:15px;">
        Godišnja stopa i vek trajanja treba da budu popunjeni u skladu sa važećim poreskim propisom ili internim pravilnikom - obračun amortizacije koristi vek trajanja za izračun.
    </p>

    <table>
        <thead>
            <tr>
                <th>Šifra</th>
                <th>Naziv</th>
                <th>Godišnja stopa (%)</th>
                <th>Vek trajanja (god.)</th>
                <th>Status</th>
                <th>Akcije</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($grupe)): ?>
                <tr><td colspan="6" style="text-align:center;">Nema unetih amortizacionih grupa.</td></tr>
            <?php else: ?>
                <?php foreach ($grupe as $g): ?>
                    <tr class="<?= $g['aktivna'] ? '' : 'neaktivna-vrsta' ?>">
                        <td><?= htmlspecialchars($g['sifra']) ?></td>
                        <td><?= htmlspecialchars($g['naziv']) ?></td>
                        <td>
                            <?= $g['godisnja_stopa_procenat'] !== null
                                ? number_format($g['godisnja_stopa_procenat'], 2, ',', '.') . '%'
                                : '<span class="napomena-polje">nije podešeno</span>' ?>
                        </td>
                        <td>
                            <?= $g['vek_trajanja_godine'] !== null
                                ? number_format($g['vek_trajanja_godine'], 2, ',', '.')
                                : '<span class="napomena-polje">nije podešeno</span>' ?>
                        </td>
                        <td>
                            <?php if ($g['aktivna']): ?>
                                <span class="oznaka oznaka-aktivna">Aktivna</span>
                            <?php else: ?>
                                <span class="oznaka oznaka-neaktivna">Neaktivna</span>
                            <?php endif; ?>
                        </td>
                        <td class="akcije">
                            <a href="amortizacione_grupe_form.php?id=<?= $g['id'] ?>">Izmeni</a>
                            <form method="POST" class="forma-status" style="display:inline;"
                                  data-naziv="<?= htmlspecialchars($g['naziv'], ENT_QUOTES) ?>"
                                  data-aktivna="<?= $g['aktivna'] ? '1' : '0' ?>">
                                <input type="hidden" name="promeni_status_id" value="<?= $g['id'] ?>">
                                <button type="submit"><?= $g['aktivna'] ? 'Deaktiviraj' : 'Aktiviraj' ?></button>
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
                if (!confirm(akcija + ' amortizacionu grupu "' + naziv + '"?')) {
                    e.preventDefault();
                }
            });
        });
    </script>

<?php require_once 'footer.php'; ?>
