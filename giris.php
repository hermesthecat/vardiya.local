<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - Vardiya Sistemi</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        $email = trim($_POST['email'] ?? '');
        $sifre = $_POST['sifre'] ?? '';

        try {
            // E-posta ve şifre boş kontrolü
            if (empty($email) || empty($sifre)) {
                throw new Exception('E-posta ve şifre alanları boş bırakılamaz.');
            }

            // E-posta formatı kontrolü
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Geçersiz e-posta formatı.');
            }

            // Kullanıcı girişi
            if (kullaniciGiris($email, $sifre)) {
                // Başarılı giriş sonrası yönlendirme
                header('Location: index.php');
                exit;
            }
        } catch (Exception $e) {
            $hata = $e->getMessage();
        }
    }
    ?>

    <div class="login-container">
        <div class="login-form">
            <h1>
                <i class="fas fa-calendar-alt"></i>
                Vardiya Sistemi
            </h1>

            <?php if ($hata): ?>
                <div class="hata-mesaji">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($hata); ?>
                </div>
            <?php endif; ?>

            <?php if ($basari): ?>
                <div class="basari-mesaji">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($basari); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>
                        <i class="fas fa-envelope"></i>
                        E-posta
                    </label>
                    <input type="email" name="email" required
                        placeholder="E-posta adresinizi girin"
                        value="<?php echo htmlspecialchars($email ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>
                        <i class="fas fa-lock"></i>
                        Şifre
                    </label>
                    <input type="password" name="sifre" required
                        placeholder="Şifrenizi girin"
                        minlength="6">
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Giriş Yap
                </button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form gönderilmeden önce kontroller
            document.querySelector('form').addEventListener('submit', function(e) {
                const email = document.querySelector('input[name="email"]').value.trim();
                const sifre = document.querySelector('input[name="sifre"]').value;

                if (!email || !sifre) {
                    e.preventDefault();
                    alert('Lütfen tüm alanları doldurun.');
                    return;
                }

                if (sifre.length < 6) {
                    e.preventDefault();
                    alert('Şifre en az 6 karakter olmalıdır.');
                    return;
                }
            });
        });
    </script>
</body>

</html>