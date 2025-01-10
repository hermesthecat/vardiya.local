<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - Vardiya Sistemi</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php
    require_once 'functions.php';
    session_start();
    
    // Sadece admin rolündeki kullanıcılar erişebilir
    try {
        yetkiKontrol(['admin']);
    } catch (Exception $e) {
        header('Location: giris.php');
        exit;
    }
    
    $hata = '';
    $basari = '';
    
    // Yeni kullanıcı ekleme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['islem']) && $_POST['islem'] === 'ekle') {
        try {
            kullaniciOlustur(
                $_POST['ad'],
                $_POST['soyad'],
                $_POST['email'],
                $_POST['sifre'],
                $_POST['rol']
            );
            $basari = 'Kullanıcı başarıyla oluşturuldu.';
        } catch (Exception $e) {
            $hata = $e->getMessage();
        }
    }
    
    // Kullanıcı güncelleme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['islem']) && $_POST['islem'] === 'guncelle') {
        try {
            kullaniciGuncelle(
                $_POST['kullanici_id'],
                $_POST['ad'],
                $_POST['soyad'],
                $_POST['email'],
                $_POST['rol']
            );
            $basari = 'Kullanıcı başarıyla güncellendi.';
        } catch (Exception $e) {
            $hata = $e->getMessage();
        }
    }
    
    // Mevcut kullanıcıları getir
    $data = veriOku();
    $kullanicilar = $data['kullanicilar'] ?? [];
    ?>

    <div class="container">
        <h1>Kullanıcı Yönetimi</h1>
        
        <nav>
            <a href="index.php">Ana Sayfa</a>
        </nav>
        
        <?php if ($hata): ?>
            <div class="hata-mesaji"><?php echo htmlspecialchars($hata); ?></div>
        <?php endif; ?>

        <?php if ($basari): ?>
            <div class="basari-mesaji"><?php echo htmlspecialchars($basari); ?></div>
        <?php endif; ?>

        <!-- Yeni Kullanıcı Ekleme Formu -->
        <div class="section">
            <h2>Yeni Kullanıcı Ekle</h2>
            <form method="POST" class="form-section">
                <input type="hidden" name="islem" value="ekle">
                
                <div class="form-group">
                    <label>Ad:</label>
                    <input type="text" name="ad" required>
                </div>
                
                <div class="form-group">
                    <label>Soyad:</label>
                    <input type="text" name="soyad" required>
                </div>
                
                <div class="form-group">
                    <label>E-posta:</label>
                    <input type="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label>Şifre:</label>
                    <input type="password" name="sifre" required>
                </div>
                
                <div class="form-group">
                    <label>Rol:</label>
                    <select name="rol" required>
                        <option value="personel">Personel</option>
                        <option value="yonetici">Yönetici</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <button type="submit" class="submit-btn">Kullanıcı Ekle</button>
            </form>
        </div>

        <!-- Kullanıcı Listesi -->
        <div class="section">
            <h2>Kullanıcı Listesi</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ad Soyad</th>
                        <th>E-posta</th>
                        <th>Rol</th>
                        <th>Oluşturma Tarihi</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kullanicilar as $kullanici): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($kullanici['ad'] . ' ' . $kullanici['soyad']); ?></td>
                            <td><?php echo htmlspecialchars($kullanici['email']); ?></td>
                            <td><?php echo htmlspecialchars($kullanici['rol']); ?></td>
                            <td><?php echo htmlspecialchars($kullanici['olusturma_tarihi']); ?></td>
                            <td>
                                <button onclick="kullaniciDuzenle('<?php echo $kullanici['id']; ?>')" class="btn-small">Düzenle</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Kullanıcı Düzenleme Modal -->
    <div id="duzenleModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Kullanıcı Düzenle</h2>
            <form method="POST" class="form-section">
                <input type="hidden" name="islem" value="guncelle">
                <input type="hidden" name="kullanici_id" id="duzenle_kullanici_id">
                
                <div class="form-group">
                    <label>Ad:</label>
                    <input type="text" name="ad" id="duzenle_ad" required>
                </div>
                
                <div class="form-group">
                    <label>Soyad:</label>
                    <input type="text" name="soyad" id="duzenle_soyad" required>
                </div>
                
                <div class="form-group">
                    <label>E-posta:</label>
                    <input type="email" name="email" id="duzenle_email" required>
                </div>
                
                <div class="form-group">
                    <label>Rol:</label>
                    <select name="rol" id="duzenle_rol" required>
                        <option value="personel">Personel</option>
                        <option value="yonetici">Yönetici</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <button type="submit" class="submit-btn">Güncelle</button>
            </form>
        </div>
    </div>

    <script>
    // Modal işlemleri için JavaScript
    const modal = document.getElementById('duzenleModal');
    const span = document.getElementsByClassName('close')[0];
    
    function kullaniciDuzenle(kullaniciId) {
        const kullanicilar = <?php echo json_encode($kullanicilar); ?>;
        const kullanici = kullanicilar.find(k => k.id === kullaniciId);
        
        if (kullanici) {
            document.getElementById('duzenle_kullanici_id').value = kullanici.id;
            document.getElementById('duzenle_ad').value = kullanici.ad;
            document.getElementById('duzenle_soyad').value = kullanici.soyad;
            document.getElementById('duzenle_email').value = kullanici.email;
            document.getElementById('duzenle_rol').value = kullanici.rol;
            
            modal.style.display = 'block';
        }
    }
    
    span.onclick = function() {
        modal.style.display = 'none';
    }
    
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
    </script>
</body>
</html> 