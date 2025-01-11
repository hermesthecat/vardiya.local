<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vardiya Tercihleri - Vardiya Sistemi</title>
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
        try {
            $tercihler = [
                'tercih_edilen_vardiyalar' => $_POST['tercih_edilen_vardiyalar'] ?? [],
                'tercih_edilmeyen_gunler' => $_POST['tercih_edilmeyen_gunler'] ?? [],
                'max_ardisik_vardiya' => (int)$_POST['max_ardisik_vardiya']
            ];

            personelTercihKaydet($_POST['personel_id'], $tercihler);
            $basari = 'Tercihler başarıyla güncellendi.';
        } catch (Exception $e) {
            $hata = $e->getMessage();
        }
    }

    // Personel listesini getir
    $personeller = tumPersonelleriGetir();
    ?>

    <div class="container">
        <h1>Vardiya Tercihleri</h1>

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
                <a href="profil.php"><i class="fas fa-user-circle"></i> Profil</a>
            </div>
        </nav>

        <?php if ($hata): ?>
            <div class="hata-mesaji"><?php echo htmlspecialchars($hata); ?></div>
        <?php endif; ?>

        <?php if ($basari): ?>
            <div class="basari-mesaji"><?php echo htmlspecialchars($basari); ?></div>
        <?php endif; ?>

        <div class="section">
            <h2>Vardiya Tercihlerini Düzenle</h2>
            <form method="POST" class="form-section">
                <div class="form-group">
                    <label>Personel:</label>
                    <select name="personel_id" id="personel_id" required>
                        <?php foreach ($personeller as $personel): ?>
                            <option value="<?php echo $personel['id']; ?>">
                                <?php echo htmlspecialchars($personel['ad'] . ' ' . $personel['soyad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tercih Edilen Vardiyalar:</label>
                    <div class="checkbox-group">
                        <?php
                        $vardiyaTurleri = vardiyaTurleriniGetir();
                        foreach ($vardiyaTurleri as $id => $vardiya):
                        ?>
                            <label>
                                <input type="checkbox" name="tercih_edilen_vardiyalar[]" value="<?php echo $id; ?>">
                                <?php echo htmlspecialchars($vardiya['etiket']); ?>
                                (<?php echo $vardiya['baslangic']; ?>-<?php echo $vardiya['bitis']; ?>)
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Tercih Edilmeyen Günler:</label>
                    <div class="checkbox-group">
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
                        foreach ($gunler as $key => $label):
                        ?>
                            <label>
                                <input type="checkbox" name="tercih_edilmeyen_gunler[]" value="<?php echo $key; ?>">
                                <?php echo $label; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Maksimum Ardışık Vardiya:</label>
                    <input type="number" name="max_ardisik_vardiya" min="1" max="7" value="5" required>
                </div>

                <button type="submit" class="submit-btn">Tercihleri Kaydet</button>
            </form>
        </div>
    </div>

    <script>
        // Personel seçildiğinde tercihlerini getir
        document.getElementById('personel_id').addEventListener('change', function() {
            const personelId = this.value;
            fetch('get_tercihler.php?personel_id=' + personelId)
                .then(response => response.json())
                .then(data => {
                    // Tercih edilen vardiyaları işaretle
                    document.querySelectorAll('input[name="tercih_edilen_vardiyalar[]"]').forEach(checkbox => {
                        checkbox.checked = data.tercih_edilen_vardiyalar.includes(checkbox.value);
                    });

                    // Tercih edilmeyen günleri işaretle
                    document.querySelectorAll('input[name="tercih_edilmeyen_gunler[]"]').forEach(checkbox => {
                        checkbox.checked = data.tercih_edilmeyen_gunler.includes(checkbox.value);
                    });

                    // Maksimum ardışık vardiyayı ayarla
                    document.querySelector('input[name="max_ardisik_vardiya"]').value = data.max_ardisik_vardiya;
                });
        });

        // Sayfa yüklendiğinde ilk personelin tercihlerini getir
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('personel_id').dispatchEvent(new Event('change'));
        });
    </script>
</body>

</html>