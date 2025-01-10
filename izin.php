<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İzin Yönetimi - Vardiya Sistemi</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php
    require_once 'functions.php';

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
        <div class="header-nav">
            <h1>İzin Yönetimi</h1>
            <div>
                <a href="index.php" class="nav-btn">Vardiya Takvimine Dön</a>
                <a href="personel.php" class="nav-btn">Personel Yönetimi</a>
            </div>
        </div>

        <?php if ($hata): ?>
            <div class="hata-mesaji"><?php echo htmlspecialchars($hata); ?></div>
        <?php endif; ?>

        <?php if ($basari): ?>
            <div class="basari-mesaji"><?php echo htmlspecialchars($basari); ?></div>
        <?php endif; ?>

        <!-- İzin Talep Formu -->
        <div class="form-section">
            <h2>Yeni İzin Talebi</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Personel:</label>
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
                    <label>İzin Türü:</label>
                    <select name="izin_turu" required>
                        <?php foreach (izinTurleriniGetir() as $tur => $aciklama): ?>
                            <option value="<?php echo $tur; ?>"><?php echo $aciklama; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Başlangıç Tarihi:</label>
                    <input type="date" name="baslangic_tarihi" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Bitiş Tarihi:</label>
                    <input type="date" name="bitis_tarihi" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Açıklama:</label>
                    <textarea name="aciklama" rows="4" required></textarea>
                </div>
                <button type="submit" name="izin_talebi" class="submit-btn">İzin Talebi Oluştur</button>
            </form>
        </div>

        <!-- İzin Talepleri Listesi -->
        <div class="tablo-container">
            <h2>İzin Talepleri</h2>
            <table>
                <thead>
                    <tr>
                        <th>Personel</th>
                        <th>İzin Türü</th>
                        <th>Başlangıç</th>
                        <th>Bitiş</th>
                        <th>Süre</th>
                        <th>Açıklama</th>
                        <th>Durum</th>
                        <th>Yönetici Notu</th>
                        <th>İşlemler</th>
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

                        $baslangic = new DateTime($talep['baslangic_tarihi']);
                        $bitis = new DateTime($talep['bitis_tarihi']);
                        $fark = $bitis->diff($baslangic);
                        $gunSayisi = $fark->days + 1;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($personel['ad'] . ' ' . $personel['soyad']); ?></td>
                            <td><?php echo $izinTurleri[$talep['izin_turu']]; ?></td>
                            <td><?php echo date('d.m.Y', strtotime($talep['baslangic_tarihi'])); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($talep['bitis_tarihi'])); ?></td>
                            <td><?php echo $gunSayisi; ?> gün</td>
                            <td><?php echo htmlspecialchars($talep['aciklama']); ?></td>
                            <td><?php echo $talep['durum']; ?></td>
                            <td><?php echo htmlspecialchars($talep['yonetici_notu'] ?? ''); ?></td>
                            <td>
                                <?php if ($talep['durum'] === 'beklemede'): ?>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="talep_id" value="<?php echo $talep['id']; ?>">
                                        <div class="form-group">
                                            <textarea name="yonetici_notu" placeholder="Yönetici notu..." rows="2"></textarea>
                                        </div>
                                        <button type="submit" name="talep_guncelle" value="onaylandi" class="btn-duzenle">Onayla</button>
                                        <button type="submit" name="talep_guncelle" value="reddedildi" class="btn-sil">Reddet</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Onaylanmış İzinler -->
        <div class="tablo-container">
            <h2>Onaylanmış İzinler</h2>
            <table>
                <thead>
                    <tr>
                        <th>Personel</th>
                        <th>İzin Türü</th>
                        <th>Başlangıç</th>
                        <th>Bitiş</th>
                        <th>Süre</th>
                        <th>Açıklama</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['izinler'] as $izin):
                        $personel = array_filter($personeller, function ($p) use ($izin) {
                            return $p['id'] === $izin['personel_id'];
                        });
                        $personel = reset($personel);

                        $baslangic = new DateTime($izin['baslangic_tarihi']);
                        $bitis = new DateTime($izin['bitis_tarihi']);
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

    <script>
        // Bitiş tarihinin başlangıç tarihinden önce seçilmesini engelle
        document.querySelector('input[name="baslangic_tarihi"]').addEventListener('change', function() {
            document.querySelector('input[name="bitis_tarihi"]').min = this.value;
        });
    </script>
</body>

</html>