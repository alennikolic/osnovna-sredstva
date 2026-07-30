<?php
require_once 'auth.php';
require_once 'db.php';

// Ova stranica radi SAMO dok ne postoji nijedan korisnički nalog - sprečava
// da je neko iskoristi kasnije za tiho pravljenje dodatnog admin naloga.
$brojKorisnika = (int)$pdo->query("SELECT COUNT(*) FROM korisnici")->fetchColumn();
if ($brojKorisnika > 0) {
    header("Location: login.php");
    exit;
}

$greska = '';
$korisnickoIme = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $korisnickoIme = trim($_POST['korisnicko_ime'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $lozinka = $_POST['lozinka'] ?? '';
    $lozinkaPotvrda = $_POST['lozinka_potvrda'] ?? '';

    if ($korisnickoIme === '' || $email === '' || $lozinka === '') {
        $greska = 'Sva polja su obavezna.';
    } elseif (strlen($lozinka) < 8) {
        $greska = 'Lozinka mora imati bar 8 karaktera.';
    } elseif ($lozinka !== $lozinkaPotvrda) {
        $greska = 'Lozinke se ne poklapaju.';
    } else {
        try {
            $pdo->beginTransaction();

            // Ponovna provera unutar transakcije - da spreči trku (race condition)
            // ako se ova stranica iz nekog razloga otvori dva puta odjednom.
            $trenutniBroj = (int)$pdo->query("SELECT COUNT(*) FROM korisnici")->fetchColumn();
            if ($trenutniBroj > 0) {
                $pdo->rollBack();
                header("Location: login.php");
                exit;
            }

            $rolaAdmin = $pdo->query("SELECT id FROM korisnicke_role WHERE sifra = 'ADMIN'")->fetch();
            if (!$rolaAdmin) {
                throw new \RuntimeException('Rola ADMIN ne postoji u bazi - proverite da li je init.sql ispravno učitan.');
            }

            $hash = password_hash($lozinka, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "INSERT INTO korisnici (korisnicko_ime, email, lozinka_hash, rola_id, aktivan)
                 VALUES (:ki, :email, :hash, :rola, 1)"
            );
            $stmt->execute([
                ':ki'    => $korisnickoIme,
                ':email' => $email,
                ':hash'  => $hash,
                ':rola'  => $rolaAdmin['id'],
            ]);

            $pdo->commit();

            header("Location: login.php");
            exit;
        } catch (\PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                $greska = 'Korisničko ime ili email već postoji.';
            } else {
                $greska = 'Greška pri kreiranju naloga: ' . $e->getMessage();
            }
        } catch (\RuntimeException $e) {
            $pdo->rollBack();
            $greska = $e->getMessage();
        }
    }
}

$naslovStranice = 'Podešavanje - prvi administratorski nalog';
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
        .login-container { width: 100%; max-width: 420px; }
    </style>
</head>
<body>
    <div class="form-container login-container">
        <h2 style="text-align:center;">Dobrodošli</h2>
        <p class="napomena-polje" style="text-align:center; margin-bottom:20px;">
            Baza još nema nijedan korisnički nalog - napravite prvi, administratorski.
        </p>

        <?php if ($greska): ?>
            <div class="error"><?= htmlspecialchars($greska) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Korisničko ime *</label>
                <input type="text" name="korisnicko_ime" maxlength="50" required value="<?= htmlspecialchars($korisnickoIme) ?>">
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" maxlength="150" required value="<?= htmlspecialchars($email) ?>">
            </div>
            <div class="form-group">
                <label>Lozinka * <span class="napomena-polje">(minimum 8 karaktera)</span></label>
                <input type="password" name="lozinka" minlength="8" required>
            </div>
            <div class="form-group">
                <label>Potvrda lozinke *</label>
                <input type="password" name="lozinka_potvrda" minlength="8" required>
            </div>
            <button type="submit" class="btn" style="width:100%;">Kreiraj administratorski nalog</button>
        </form>
    </div>
</body>
</html>
