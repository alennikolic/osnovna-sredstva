<?php
require_once 'auth.php';
require_once 'db.php';

// Ako je korisnik već prijavljen, nema potrebe da ponovo vidi login formu
if (!empty($_SESSION['korisnik'])) {
    header("Location: index.php");
    exit;
}

// Ako još nijedan korisnički nalog ne postoji, preusmeri na podešavanje
// (bootstrap prvog administratorskog naloga)
$brojKorisnika = (int)$pdo->query("SELECT COUNT(*) FROM korisnici")->fetchColumn();
if ($brojKorisnika === 0) {
    header("Location: setup.php");
    exit;
}

$greska = '';
$korisnickoIme = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $korisnickoIme = trim($_POST['korisnicko_ime'] ?? '');
    $lozinka = $_POST['lozinka'] ?? '';

    if ($korisnickoIme === '' || $lozinka === '') {
        $greska = 'Unesite korisničko ime i lozinku.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT k.id, k.korisnicko_ime, k.lozinka_hash, k.aktivan, k.zakljucan_do,
                    k.broj_neuspesnih_prijava, r.sifra AS rola_sifra, r.naziv AS rola_naziv,
                    z.ime, z.prezime
             FROM korisnici k
             JOIN korisnicke_role r ON r.id = k.rola_id
             LEFT JOIN zaposleni z ON z.id = k.zaposleni_id
             WHERE k.korisnicko_ime = :ki"
        );
        $stmt->execute([':ki' => $korisnickoIme]);
        $korisnik = $stmt->fetch();

        if (!$korisnik) {
            // Namerno ista poruka kao za pogrešnu lozinku - ne otkrivamo da li nalog postoji
            $greska = 'Pogrešno korisničko ime ili lozinka.';
        } elseif (!$korisnik['aktivan']) {
            $greska = 'Nalog je deaktiviran. Obratite se administratoru.';
        } elseif ($korisnik['zakljucan_do'] !== null && strtotime($korisnik['zakljucan_do']) > time()) {
            $greska = 'Nalog je privremeno zaključan zbog više neuspešnih pokušaja prijave. Pokušajte kasnije.';
        } elseif (!password_verify($lozinka, $korisnik['lozinka_hash'])) {
            // Pogrešna lozinka - povećaj brojač, zaključaj nalog na 15 minuta posle 5 pokušaja
            $noviBroj = $korisnik['broj_neuspesnih_prijava'] + 1;
            $zakljucajDo = $noviBroj >= 5 ? date('Y-m-d H:i:s', time() + 15 * 60) : null;

            $upd = $pdo->prepare("UPDATE korisnici SET broj_neuspesnih_prijava = :broj, zakljucan_do = :zakljucaj WHERE id = :id");
            $upd->execute([':broj' => $noviBroj, ':zakljucaj' => $zakljucajDo, ':id' => $korisnik['id']]);

            $greska = $zakljucajDo
                ? 'Previše neuspešnih pokušaja. Nalog je zaključan na 15 minuta.'
                : 'Pogrešno korisničko ime ili lozinka.';
        } else {
            // Uspešna prijava
            $upd = $pdo->prepare("UPDATE korisnici SET broj_neuspesnih_prijava = 0, zakljucan_do = NULL, poslednja_prijava = NOW() WHERE id = :id");
            $upd->execute([':id' => $korisnik['id']]);

            $_SESSION['korisnik'] = [
                'id' => $korisnik['id'],
                'korisnicko_ime' => $korisnik['korisnicko_ime'],
                'ime_prezime' => trim(($korisnik['ime'] ?? '') . ' ' . ($korisnik['prezime'] ?? '')) ?: $korisnik['korisnicko_ime'],
                'rola_sifra' => $korisnik['rola_sifra'],
                'rola_naziv' => $korisnik['rola_naziv'],
            ];

            // Povratak na stranicu sa koje je korisnik preusmeren na login (ako postoji) -
            // dozvoljavamo samo relativnu putanju unutar aplikacije (zaštita od open-redirect)
            $povratak = ltrim($_GET['povratak'] ?? '', '/');
            if ($povratak === '' || !preg_match('/^[a-zA-Z0-9_\-]+\.php(\?[a-zA-Z0-9_\-\.=&%]*)?$/', $povratak)) {
                $povratak = 'index.php';
            }
            header("Location: " . $povratak);
            exit;
        }
    }
}

$naslovStranice = 'Prijava';
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($naslovStranice) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 90vh; }
        .login-container { width: 100%; max-width: 380px; }
    </style>
</head>
<body>
    <div class="form-container login-container">
        <h2 style="text-align:center;">Prijava</h2>
        <p class="napomena-polje" style="text-align:center; margin-bottom:20px;">Osnovna sredstva</p>

        <?php if ($greska): ?>
            <div class="error"><?= htmlspecialchars($greska) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Korisničko ime</label>
                <input type="text" name="korisnicko_ime" required autofocus value="<?= htmlspecialchars($korisnickoIme) ?>">
            </div>
            <div class="form-group">
                <label>Lozinka</label>
                <input type="password" name="lozinka" required>
            </div>
            <button type="submit" class="btn" style="width:100%;">Prijavi se</button>
        </form>
    </div>
</body>
</html>
