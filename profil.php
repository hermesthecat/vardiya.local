<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Vardiya Sistemi</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php
    require_once 'functions.php';
    session_start();
    
    // Oturum kontrolü
    if (!isset($_SESSION['kullanici_id'])) {
        header('Location: giris.php');
        exit;
    }
    
    $hata = '';
    $basari = '';
    
    // Şifre değiştirme işlemi
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['islem']) && $_POST['islem'] === 'sifre_degistir') {
        try {
            sifreDegistir(
                $_SESSION['kullanici_id'],
                $_POST['eski_sifre'],
                $_POST['yeni_sifre']
            );
            $basari = 'Şifreniz başarıyla değiştirildi.';
        } catch (Exception $e) {
            $hata = $e->getMessage();
        }
    }
    
    // Kullanıcı bilgilerini getir
    $data = veriOku();
    $kullanici = null;
    foreach ($data['kullanicilar'] as $k) {
        if ($k['id'] === $_SESSION['kullanici_id']) {
            $kullanici = $k;
            break;
        }
    }
    
    if (!$kullanici) {
        header('Location: cikis.php');
        exit;
    }
    ?>

    <div class="container">
        <h1>Profil</h1>
        
        <nav>
            <a href="index.php">Ana Sayfa</a>
            <a href="cikis.php">Çıkış Yap</a>
        </nav>
        
        <?php if ($hata): ?>
            <div class="hata-mesaji"><?php echo htmlspecialchars($hata); ?></div>
        <?php endif; ?>

        <?php if ($basari): ?>
            <div class="basari-mesaji"><?php echo htmlspecialchars($basari); ?></div>
        <?php endif; ?>

        <!-- Kullanıcı Bilgileri -->
        <div class="section">
            <h2>Kullanıcı Bilgileri</h2>
            <div class="info-group">
                <label>Ad:</label>
                <span><?php echo htmlspecialchars($kullanici['ad']); ?></span>
            </div>
            
            <div class="info-group">
                <label>Soyad:</label>
                <span><?php echo htmlspecialchars($kullanici['soyad']); ?></span>
            </div>
            
            <div class="info-group">
                <label>E-posta:</label>
                <span><?php echo htmlspecialchars($kullanici['email']); ?></span>
            </div>
            
            <div class="info-group">
                <label>Rol:</label>
                <span><?php echo htmlspecialchars($kullanici['rol']); ?></span>
            </div>
            
            <div class="info-group">
                <label>Hesap Oluşturma Tarihi:</label>
                <span><?php echo htmlspecialchars($kullanici['olusturma_tarihi']); ?></span>
            </div>
        </div>

        <!-- Şifre Değiştirme -->
        <div class="section">
            <h2>Şifre Değiştir</h2>
            <form method="POST" class="form-section">
                <input type="hidden" name="islem" value="sifre_degistir">
                
                <div class="form-group">
                    <label>Mevcut Şifre:</label>
                    <input type="password" name="eski_sifre" required>
                </div>
                
                <div class="form-group">
                    <label>Yeni Şifre:</label>
                    <input type="password" name="yeni_sifre" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label>Yeni Şifre (Tekrar):</label>
                    <input type="password" name="yeni_sifre_tekrar" required minlength="6">
                </div>
                
                <button type="submit" class="submit-btn">Şifre Değiştir</button>
            </form>
        </div>

        <!-- Son İşlemler -->
        <div class="section">
            <h2>Son İşlemler</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>İşlem</th>
                        <th>Açıklama</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $loglar = islemLoglariGetir(null, null, null, $_SESSION['kullanici_id']);
                    $loglar = array_slice(array_reverse($loglar), 0, 10); // Son 10 işlem
                    foreach ($loglar as $log):
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['tarih']); ?></td>
                            <td><?php echo htmlspecialchars($log['islem_turu']); ?></td>
                            <td><?php echo htmlspecialchars($log['aciklama']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    // Şifre eşleşme kontrolü
    document.querySelector('form').addEventListener('submit', function(e) {
        var yeniSifre = document.querySelector('input[name="yeni_sifre"]').value;
        var yeniSifreTekrar = document.querySelector('input[name="yeni_sifre_tekrar"]').value;
        
        if (yeniSifre !== yeniSifreTekrar) {
            e.preventDefault();
            alert('Yeni şifreler eşleşmiyor!');
        }
    });
    </script>
</body>
</html> 