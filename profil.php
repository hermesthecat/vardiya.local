<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Vardiya Sistemi</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="js/date_functions.js"></script>
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['islem']) && $_POST['islem'] === 'sifre_degistir') {
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
        } elseif (isset($_POST['islem']) && $_POST['islem'] === 'tercih_guncelle') {
            try {
                $tercihler = [
                    'bildirimler' => isset($_POST['bildirimler']) ? true : false,
                    'tercih_edilen_vardiyalar' => $_POST['tercih_edilen_vardiyalar'] ?? [],
                    'tercih_edilmeyen_gunler' => $_POST['tercih_edilmeyen_gunler'] ?? []
                ];

                kullaniciTercihleriniGuncelle($_SESSION['kullanici_id'], $tercihler);
                $basari = 'Tercihleriniz başarıyla güncellendi.';
            } catch (Exception $e) {
                $hata = $e->getMessage();
            }
        }
    }

    // Kullanıcı bilgilerini getir
    $data = veriOku();
    $kullanici = null;
    foreach ($data['personel'] as $k) {
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
            <div class="user-info">
                <div>
                    <i class="fas fa-user"></i>
                    Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['ad_soyad']); ?>
                    (<?php echo htmlspecialchars($_SESSION['rol']); ?>)
                </div>
                <a href="cikis.php"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a>
            </div>

            <div class="menu">
                <a href="index.php"><i class="fas fa-calendar-week"></i> Vardiya Takvimi</a>
                <?php if (in_array($_SESSION['rol'], ['yonetici', 'admin'])): ?>
                    <a href="personel.php"><i class="fas fa-users"></i> Personel Yönetimi</a>
                <?php endif; ?>
                <?php if ($_SESSION['rol'] === 'admin'): ?>
                    <a href="kullanicilar.php"><i class="fas fa-user-cog"></i> Kullanıcı Yönetimi</a>
                <?php endif; ?>
                <a href="izin.php"><i class="fas fa-calendar-alt"></i> İzin İşlemleri</a>
                <a href="profil.php" class="active"><i class="fas fa-user-circle"></i> Profil</a>
            </div>
        </nav>

        <?php if ($hata): ?>
            <div class="hata-mesaji"><?php echo htmlspecialchars($hata); ?></div>
        <?php endif; ?>

        <?php if ($basari): ?>
            <div class="basari-mesaji"><?php echo htmlspecialchars($basari); ?></div>
        <?php endif; ?>

        <!-- Kullanıcı Bilgileri -->
        <div class="kullanici-bilgi-karti">
            <div class="kullanici-baslik">
                <div class="kullanici-avatar">
                    <?php echo strtoupper(substr($kullanici['ad'], 0, 1)); ?>
                </div>
                <div class="kullanici-isim">
                    <h2><?php echo htmlspecialchars($kullanici['ad'] . ' ' . $kullanici['soyad']); ?></h2>
                    <div class="kullanici-rol"><?php echo htmlspecialchars(ucfirst($kullanici['yetki'])); ?></div>
                </div>
            </div>
            
            <div class="kullanici-detaylar">
                <div class="detay-grup">
                    <div class="detay-baslik">E-posta</div>
                    <div class="detay-icerik"><?php echo htmlspecialchars($kullanici['email']); ?></div>
                </div>
                
                <div class="detay-grup">
                    <div class="detay-baslik">Telefon</div>
                    <div class="detay-icerik"><?php echo htmlspecialchars($kullanici['telefon'] ?: 'Belirtilmemiş'); ?></div>
                </div>
                
                <div class="detay-grup">
                    <div class="detay-baslik">Tercih Edilen Vardiyalar</div>
                    <div class="detay-icerik">
                        <?php
                        if (!empty($kullanici['tercihler']['tercih_edilen_vardiyalar'])) {
                            $vardiyalar = array_map(function($vardiyaTuru) {
                                return vardiyaTuruEtiketGetir($vardiyaTuru);
                            }, $kullanici['tercihler']['tercih_edilen_vardiyalar']);
                            echo htmlspecialchars(implode(', ', $vardiyalar));
                        } else {
                            echo 'Belirtilmemiş';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="detay-grup">
                    <div class="detay-baslik">Tercih Edilmeyen Günler</div>
                    <div class="detay-icerik">
                        <?php
                        if (!empty($kullanici['tercihler']['tercih_edilmeyen_gunler'])) {
                            $gunler = [
                                'pazartesi' => 'Pazartesi',
                                'sali' => 'Salı',
                                'carsamba' => 'Çarşamba',
                                'persembe' => 'Perşembe',
                                'cuma' => 'Cuma',
                                'cumartesi' => 'Cumartesi',
                                'pazar' => 'Pazar'
                            ];
                            $tercihEdilmeyenGunler = array_map(function($gun) use ($gunler) {
                                return $gunler[$gun];
                            }, $kullanici['tercihler']['tercih_edilmeyen_gunler']);
                            echo htmlspecialchars(implode(', ', $tercihEdilmeyenGunler));
                        } else {
                            echo 'Belirtilmemiş';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tercihler -->
        <div class="section">
            <h2>Tercihler</h2>
            <form method="POST" class="form-section">
                <input type="hidden" name="islem" value="tercih_guncelle">

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="bildirimler" value="1"
                            <?php echo $kullanici['tercihler']['bildirimler'] ? 'checked' : ''; ?>>
                        Bildirimleri aktif et
                    </label>
                </div>

                <div class="form-group">
                    <label>Tercih Edilen Vardiyalar:</label>
                    <select name="tercih_edilen_vardiyalar[]" multiple>
                        <?php
                        $vardiyaTurleri = vardiyaTurleriniGetir();
                        foreach ($vardiyaTurleri as $id => $vardiya) {
                            $selected = in_array($id, $kullanici['tercihler']['tercih_edilen_vardiyalar']) ? 'selected' : '';
                            echo "<option value=\"$id\" $selected>{$vardiya['etiket']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tercih Edilmeyen Günler:</label>
                    <select name="tercih_edilmeyen_gunler[]" multiple>
                        <?php
                        $gunler = [
                            'pazartesi' => 'Pazartesi',
                            'sali' => 'Salı',
                            'carsamba' => 'Çarşamba',
                            'persembe' => 'Perşembe',
                            'cuma' => 'Cuma',
                            'cumartesi' => 'Cumartesi',
                            'pazar' => 'Pazar'
                        ];
                        foreach ($gunler as $key => $label) {
                            $selected = in_array($key, $kullanici['tercihler']['tercih_edilmeyen_gunler']) ? 'selected' : '';
                            echo "<option value=\"$key\" $selected>$label</option>";
                        }
                        ?>
                    </select>
                </div>

                <button type="submit" class="submit-btn">Tercihleri Güncelle</button>
            </form>
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
        <div class="islem-tablosu">
            <h2>Son İşlemler</h2>
            <div class="tablo-container">
                <table>
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>İşlem Türü</th>
                            <th>Kullanıcı</th>
                            <th>Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $data = veriOku();
                        // Sadece kullanıcının kendi işlemlerini göster
                        $kullaniciIslemleri = array_filter($data['islem_loglari'], function($islem) {
                            return $islem['kullanici_id'] === $_SESSION['kullanici_id'];
                        });
                        $islemler = array_slice(array_reverse($kullaniciIslemleri), 0, 10);
                        foreach ($islemler as $islem):
                        ?>
                            <tr>
                                <td><?php echo date('d.m.Y H:i', $islem['tarih']); ?></td>
                                <td><?php echo htmlspecialchars($islem['islem_turu']); ?></td>
                                <td><?php 
                                    if ($islem['kullanici_id']) {
                                        foreach ($data['personel'] as $personel) {
                                            if ($personel['id'] === $islem['kullanici_id']) {
                                                echo htmlspecialchars($personel['ad'] . ' ' . $personel['soyad']);
                                                break;
                                            }
                                        }
                                    } else {
                                        echo 'Sistem';
                                    }
                                ?></td>
                                <td><?php echo htmlspecialchars($islem['aciklama']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Şifre eşleşme kontrolü
        document.querySelector('form[name="sifre_degistir"]').addEventListener('submit', function(e) {
            var yeniSifre = document.querySelector('input[name="yeni_sifre"]').value;
            var yeniSifreTekrar = document.querySelector('input[name="yeni_sifre_tekrar"]').value;

            if (yeniSifre !== yeniSifreTekrar) {
                e.preventDefault();
                alert('Yeni şifreler eşleşmiyor!');
            }
        });

        // Tarih formatlamayı uygula
        document.addEventListener('DOMContentLoaded', function() {
            const tarihElementleri = document.querySelectorAll('[data-timestamp]');
            tarihElementleri.forEach(element => {
                const timestamp = element.getAttribute('data-timestamp');
                element.textContent = tarihFormatla(timestamp, 'tam_saat');
            });
        });
    </script>
</body>

</html>