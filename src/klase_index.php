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
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Klase Osnovnih Sredstava</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f6f9; }
        h1 { color: #333; }
        .nav-bar { margin-bottom: 20px; }
        .nav-bar a { margin-right: 15px; color: #007bff; text-decoration: none; font-weight: bold; }
        .nav-bar a:hover { text-decoration: underline; }
        .btn { display: inline-block; padding: 10px 15px; background: #28a745; color: #fff; text-decoration: none; border-radius: 4px; margin-bottom: 15px; border: none; cursor: pointer; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; vertical-align: middle; }
        th { background: #007bff; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .neaktivna-vrsta { opacity: 0.55; }
        .oznaka { display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 12px; color: #fff; }
        .oznaka-aktivna { background: #28a745; }
        .oznaka-neaktivna { background: #6c757d; }
        .akcije a, .akcije button { margin-right: 8px; font-size: 13px; }
        .akcije button { background: none; border: none; color: #dc3545; cursor: pointer; padding: 0; text-decoration: underline; font-family: inherit; }
        .akcije a { color: #007bff; text-decoration: none; }
        .akcije a:hover, .akcije button:hover { text-decoration: underline; }
        .poruka { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .poruka-success { background: #d4edda; color: #155724; }
        .poruka-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

    <div class="nav-bar">
        <a href="index.php">Osnovna sredstva</a>
        <a href="klase_index.php">Klase osnovnih sredstava</a>
    </div>

    <h1>Klase osnovnih sredstava</h1>
    <a href="klase_form.php" class="btn">+ Nova klasa</a>

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

</body>
</html>
