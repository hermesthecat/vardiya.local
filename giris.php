<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - Vardiya Sistemi</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php
    require_once 'functions.php';
    session_start();

    // Zaten giriş yapmış kullanıcıyı ana sayfaya yönlendir
    if (isset($_SESSION['kullanici_id'])) {
        header('Location: index.php');
        exit;
    }

    $hata = '';
    $basari = '';

    // POST işlemleri
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            kullaniciGiris($_POST['email'], $_POST['sifre']);
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $hata = $e->getMessage();
        }
    }
    ?>

    <div class="container">
        <div class="login-form">
            <h1>Vardiya Sistemi</h1>

            <?php if ($hata): ?>
                <div class="hata-mesaji"><?php echo htmlspecialchars($hata); ?></div>
            <?php endif; ?>

            <?php if ($basari): ?>
                <div class="basari-mesaji"><?php echo htmlspecialchars($basari); ?></div>
            <?php endif; ?>

            <form method="POST" class="form-section">
                <div class="form-group">
                    <label>E-posta:</label>
                    <input type="email" name="email" required>
                </div>

                <div class="form-group">
                    <label>Şifre:</label>
                    <input type="password" name="sifre" required>
                </div>

                <button type="submit" class="submit-btn">Giriş Yap</button>
            </form>
        </div>
    </div>
</body>

</html>