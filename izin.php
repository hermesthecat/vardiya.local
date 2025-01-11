<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İzin Yönetimi - Vardiya Sistemi</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

    // POST işlemleri
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['izin_talebi'])) {
            try {
                izinTalebiOlustur(
                    $_POST['personel_id'],
                    $_POST['baslangic_tarihi'],
                    $_POST['bitis_tarihi'],
                    $_POST['izin_turu'],
                    $_POST['aciklama']
                );
                $basari = 'İzin talebi başarıyla oluşturuldu.';
            } catch (Exception $e) {
                $hata = $e->getMessage();
            }
        } elseif (isset($_POST['talep_guncelle'])) {
            try {
                izinTalebiGuncelle(
                    $_POST['talep_id'],
                    $_POST['durum'],
                    $_POST['yonetici_notu']
                );
                $basari = 'İzin talebi durumu güncellendi.';
            } catch (Exception $e) {
                $hata = $e->getMessage();
            }
        }
    }

    // Personel listesi
    $personeller = tumPersonelleriGetir();
    ?>

    <div class="container">
        <h1><i class="fas fa-calendar-alt"></i> İzin Yönetimi</h1>

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
                <a href="izin.php" class="active"><i class="fas fa-calendar-alt"></i> İzin İşlemleri</a>
                <a href="profil.php"><i class="fas fa-user-circle"></i> Profil</a>
            </div>
        </nav>

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

        <!-- İzin Talep Formu -->
        <div class="section">
            <h2><i class="fas fa-plus-circle"></i> Yeni İzin Talebi</h2>
            <form method="POST" class="form-section">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Personel:</label>
                    <select name="personel_id" required>
                        <?php foreach ($personeller as $personel): ?>
                            <option value="<?php echo $personel['id']; ?>">
                                <?php echo htmlspecialchars($personel['ad'] . ' ' . $personel['soyad']); ?>
                                (Kalan Yıllık İzin: <?php echo yillikIzinHakkiHesapla($personel['id']); ?> gün)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> İzin Türü:</label>
                    <select name="izin_turu" required>
                        <?php foreach (izinTurleriniGetir() as $tur => $aciklama): ?>
                            <option value="<?php echo $tur; ?>"><?php echo $aciklama; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Başlangıç Tarihi:</label>
                    <input type="date" name="baslangic_tarihi" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Bitiş Tarihi:</label>
                    <input type="date" name="bitis_tarihi" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-comment"></i> Açıklama:</label>
                    <textarea name="aciklama" rows="4" required placeholder="İzin talebinizin nedenini açıklayın..."></textarea>
                </div>
                <button type="submit" name="izin_talebi" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> İzin Talebi Oluştur
                </button>
            </form>
        </div>

        <!-- İzin Talepleri Listesi -->
        <div class="section">
            <h2><i class="fas fa-list"></i> İzin Talepleri</h2>
            <div class="tablo-container">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Personel</th>
                            <th><i class="fas fa-tag"></i> İzin Türü</th>
                            <th><i class="fas fa-calendar"></i> Başlangıç</th>
                            <th><i class="fas fa-calendar"></i> Bitiş</th>
                            <th><i class="fas fa-clock"></i> Süre</th>
                            <th><i class="fas fa-comment"></i> Açıklama</th>
                            <th><i class="fas fa-info-circle"></i> Durum</th>
                            <th><i class="fas fa-comment-dots"></i> Yönetici Notu</th>
                            <th><i class="fas fa-cogs"></i> İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $data = veriOku();
                        $izinTurleri = izinTurleriniGetir();
                        foreach ($data['izin_talepleri'] as $talep):
                            $personel = array_filter($personeller, function ($p) use ($talep) {
                                return $p['id'] === $talep['personel_id'];
                            });
                            $personel = reset($personel);

                            $baslangic = is_numeric($talep['baslangic_tarihi']) 
                                ? DateTime::createFromFormat('U', $talep['baslangic_tarihi']) 
                                : new DateTime($talep['baslangic_tarihi']);
                            
                            $bitis = is_numeric($talep['bitis_tarihi']) 
                                ? DateTime::createFromFormat('U', $talep['bitis_tarihi']) 
                                : new DateTime($talep['bitis_tarihi']);

                            $fark = $bitis->diff($baslangic);
                            $gunSayisi = $fark->days + 1;

                            $durumRenk = '';
                            $durumIcon = '';
                            switch ($talep['durum']) {
                                case 'beklemede':
                                    $durumRenk = 'text-warning';
                                    $durumIcon = 'clock';
                                    break;
                                case 'onaylandi':
                                    $durumRenk = 'text-success';
                                    $durumIcon = 'check-circle';
                                    break;
                                case 'reddedildi':
                                    $durumRenk = 'text-danger';
                                    $durumIcon = 'times-circle';
                                    break;
                            }
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($personel['ad'] . ' ' . $personel['soyad']); ?></td>
                                <td><?php echo $izinTurleri[$talep['izin_turu']]; ?></td>
                                <td><?php echo date('d.m.Y', strtotime($talep['baslangic_tarihi'])); ?></td>
                                <td><?php echo date('d.m.Y', strtotime($talep['bitis_tarihi'])); ?></td>
                                <td><?php echo $gunSayisi; ?> gün</td>
                                <td><?php echo htmlspecialchars($talep['aciklama']); ?></td>
                                <td class="durum-cell <?php echo $durumRenk; ?>">
                                    <i class="fas fa-<?php echo $durumIcon; ?>"></i>
                                    <?php echo ucfirst($talep['durum']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($talep['yonetici_notu'] ?? ''); ?></td>
                                <td>
                                    <?php if ($talep['durum'] === 'beklemede'): ?>
                                        <form method="POST" class="islem-butonlar">
                                            <input type="hidden" name="talep_id" value="<?php echo $talep['id']; ?>">
                                            <textarea name="yonetici_notu" class="yonetici-notu" placeholder="Yönetici notu ekleyin..."></textarea>
                                            <button type="submit" name="talep_guncelle" value="onaylandi" class="btn-onayla">
                                                <i class="fas fa-check"></i> Onayla
                                            </button>
                                            <button type="submit" name="talep_guncelle" value="reddedildi" class="btn-reddet">
                                                <i class="fas fa-times"></i> Reddet
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Onaylanmış İzinler -->
        <div class="section">
            <h2><i class="fas fa-check-circle"></i> Onaylanmış İzinler</h2>
            <div class="tablo-container">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Personel</th>
                            <th><i class="fas fa-tag"></i> İzin Türü</th>
                            <th><i class="fas fa-calendar"></i> Başlangıç</th>
                            <th><i class="fas fa-calendar"></i> Bitiş</th>
                            <th><i class="fas fa-clock"></i> Süre</th>
                            <th><i class="fas fa-comment"></i> Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['izinler'] as $izin):
                            $personel = array_filter($personeller, function ($p) use ($izin) {
                                return $p['id'] === $izin['personel_id'];
                            });
                            $personel = reset($personel);

                            $baslangic = is_numeric($izin['baslangic_tarihi']) 
                                ? DateTime::createFromFormat('U', $izin['baslangic_tarihi']) 
                                : new DateTime($izin['baslangic_tarihi']);
                            
                            $bitis = is_numeric($izin['bitis_tarihi']) 
                                ? DateTime::createFromFormat('U', $izin['bitis_tarihi']) 
                                : new DateTime($izin['bitis_tarihi']);

                            $fark = $bitis->diff($baslangic);
                            $gunSayisi = $fark->days + 1;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($personel['ad'] . ' ' . $personel['soyad']); ?></td>
                                <td><?php echo $izinTurleri[$izin['izin_turu']]; ?></td>
                                <td><?php echo date('d.m.Y', strtotime($izin['baslangic_tarihi'])); ?></td>
                                <td><?php echo date('d.m.Y', strtotime($izin['bitis_tarihi'])); ?></td>
                                <td><?php echo $gunSayisi; ?> gün</td>
                                <td><?php echo htmlspecialchars($izin['aciklama']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Bitiş tarihinin başlangıç tarihinden önce seçilmesini engelle
        document.addEventListener('DOMContentLoaded', function() {
            const baslangicInput = document.querySelector('input[name="baslangic_tarihi"]');
            const bitisInput = document.querySelector('input[name="bitis_tarihi"]');

            baslangicInput.addEventListener('change', function() {
                bitisInput.min = this.value;
                if (bitisInput.value && bitisInput.value < this.value) {
                    bitisInput.value = this.value;
                }
            });
        });
    </script>
</body>

</html>