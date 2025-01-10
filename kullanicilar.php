<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - Vardiya Sistemi</title>
    <link rel="stylesheet" href="style.css">
    <script src="js/date_functions.js"></script>
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
            $tercihler = [
                'bildirimler' => isset($_POST['bildirimler']) ? true : false,
                'tercih_edilen_vardiyalar' => $_POST['tercih_edilen_vardiyalar'] ?? [],
                'tercih_edilmeyen_gunler' => $_POST['tercih_edilmeyen_gunler'] ?? []
            ];
            
            kullaniciOlustur(
                $_POST['ad'],
                $_POST['soyad'],
                $_POST['email'],
                $_POST['sifre'],
                $_POST['rol'],
                $_POST['telefon'],
                $tercihler
            );
            $basari = 'Kullanıcı başarıyla oluşturuldu.';
        } catch (Exception $e) {
            $hata = $e->getMessage();
        }
    }
    
    // Kullanıcı güncelleme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['islem']) && $_POST['islem'] === 'guncelle') {
        try {
            $tercihler = [
                'bildirimler' => isset($_POST['bildirimler']) ? true : false,
                'tercih_edilen_vardiyalar' => $_POST['tercih_edilen_vardiyalar'] ?? [],
                'tercih_edilmeyen_gunler' => $_POST['tercih_edilmeyen_gunler'] ?? []
            ];
            
            kullaniciGuncelle(
                $_POST['kullanici_id'],
                $_POST['ad'],
                $_POST['soyad'],
                $_POST['email'],
                $_POST['rol'],
                $_POST['telefon'],
                $tercihler
            );
            $basari = 'Kullanıcı başarıyla güncellendi.';
        } catch (Exception $e) {
            $hata = $e->getMessage();
        }
    }
    
    // Mevcut kullanıcıları getir
    $data = veriOku();
    $kullanicilar = $data['personel'] ?? [];
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
                    <label>Telefon:</label>
                    <input type="tel" name="telefon" pattern="[0-9]{10}" required placeholder="5XX1234567">
                </div>
                
                <div class="form-group">
                    <label>Şifre:</label>
                    <input type="password" name="sifre" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label>Rol:</label>
                    <select name="rol" required>
                        <option value="personel">Personel</option>
                        <option value="yonetici">Yönetici</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tercihler:</label>
                    <div class="tercihler">
                        <label class="checkbox-label">
                            <input type="checkbox" name="bildirimler" value="1">
                            Bildirimleri aktif et
                        </label>
                        
                        <div class="tercih-grup">
                            <label>Tercih Edilen Vardiyalar:</label>
                            <select name="tercih_edilen_vardiyalar[]" multiple>
                                <option value="sabah">Sabah (08:00-16:00)</option>
                                <option value="aksam">Akşam (16:00-24:00)</option>
                                <option value="gece">Gece (00:00-08:00)</option>
                            </select>
                        </div>
                        
                        <div class="tercih-grup">
                            <label>Tercih Edilmeyen Günler:</label>
                            <select name="tercih_edilmeyen_gunler[]" multiple>
                                <option value="pazartesi">Pazartesi</option>
                                <option value="sali">Salı</option>
                                <option value="carsamba">Çarşamba</option>
                                <option value="persembe">Perşembe</option>
                                <option value="cuma">Cuma</option>
                                <option value="cumartesi">Cumartesi</option>
                                <option value="pazar">Pazar</option>
                            </select>
                        </div>
                    </div>
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
                        <th>Telefon</th>
                        <th>Rol</th>
                        <th>Bildirimler</th>
                        <th>Tercih Edilen Vardiyalar</th>
                        <th>Tercih Edilmeyen Günler</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kullanicilar as $kullanici): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($kullanici['ad'] . ' ' . $kullanici['soyad']); ?></td>
                            <td><?php echo htmlspecialchars($kullanici['email']); ?></td>
                            <td><?php echo htmlspecialchars($kullanici['telefon']); ?></td>
                            <td><?php echo htmlspecialchars($kullanici['yetki']); ?></td>
                            <td><?php echo $kullanici['tercihler']['bildirimler'] ? 'Aktif' : 'Pasif'; ?></td>
                            <td><?php echo implode(', ', $kullanici['tercihler']['tercih_edilen_vardiyalar']); ?></td>
                            <td><?php echo implode(', ', $kullanici['tercihler']['tercih_edilmeyen_gunler']); ?></td>
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
                    <label>Telefon:</label>
                    <input type="tel" name="telefon" id="duzenle_telefon" pattern="[0-9]{10}" required>
                </div>
                
                <div class="form-group">
                    <label>Rol:</label>
                    <select name="rol" id="duzenle_rol" required>
                        <option value="personel">Personel</option>
                        <option value="yonetici">Yönetici</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tercihler:</label>
                    <div class="tercihler">
                        <label class="checkbox-label">
                            <input type="checkbox" name="bildirimler" id="duzenle_bildirimler" value="1">
                            Bildirimleri aktif et
                        </label>
                        
                        <div class="tercih-grup">
                            <label>Tercih Edilen Vardiyalar:</label>
                            <select name="tercih_edilen_vardiyalar[]" id="duzenle_tercih_edilen_vardiyalar" multiple>
                                <option value="sabah">Sabah (08:00-16:00)</option>
                                <option value="aksam">Akşam (16:00-24:00)</option>
                                <option value="gece">Gece (00:00-08:00)</option>
                            </select>
                        </div>
                        
                        <div class="tercih-grup">
                            <label>Tercih Edilmeyen Günler:</label>
                            <select name="tercih_edilmeyen_gunler[]" id="duzenle_tercih_edilmeyen_gunler" multiple>
                                <option value="pazartesi">Pazartesi</option>
                                <option value="sali">Salı</option>
                                <option value="carsamba">Çarşamba</option>
                                <option value="persembe">Perşembe</option>
                                <option value="cuma">Cuma</option>
                                <option value="cumartesi">Cumartesi</option>
                                <option value="pazar">Pazar</option>
                            </select>
                        </div>
                    </div>
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
            document.getElementById('duzenle_telefon').value = kullanici.telefon;
            document.getElementById('duzenle_rol').value = kullanici.yetki;
            document.getElementById('duzenle_bildirimler').checked = kullanici.tercihler.bildirimler;

            // Tercih edilen vardiyaları seç
            const tercihEdilenVardiyalar = document.getElementById('duzenle_tercih_edilen_vardiyalar');
            Array.from(tercihEdilenVardiyalar.options).forEach(option => {
                option.selected = kullanici.tercihler.tercih_edilen_vardiyalar.includes(option.value);
            });

            // Tercih edilmeyen günleri seç
            const tercihEdilmeyenGunler = document.getElementById('duzenle_tercih_edilmeyen_gunler');
            Array.from(tercihEdilmeyenGunler.options).forEach(option => {
                option.selected = kullanici.tercihler.tercih_edilmeyen_gunler.includes(option.value);
            });
            
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