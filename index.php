<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personel Vardiya Sistemi</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php
    require_once 'functions.php';
    
    // POST işlemleri
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['yeni_personel'])) {
            personelEkle($_POST['ad'], $_POST['soyad']);
        } elseif (isset($_POST['vardiya_ekle'])) {
            vardiyaEkle($_POST['personel_id'], $_POST['tarih'], $_POST['vardiya_turu']);
        }
    }

    // Takvim için ay ve yıl
    $ay = isset($_GET['ay']) ? intval($_GET['ay']) : intval(date('m'));
    $yil = isset($_GET['yil']) ? intval($_GET['yil']) : intval(date('Y'));
    ?>

    <div class="container">
        <h1>Personel Vardiya Sistemi</h1>
        
        <!-- Takvim Navigasyonu -->
        <div class="takvim-nav">
            <?php
            $oncekiAy = $ay - 1;
            $oncekiYil = $yil;
            if ($oncekiAy < 1) {
                $oncekiAy = 12;
                $oncekiYil--;
            }
            
            $sonrakiAy = $ay + 1;
            $sonrakiYil = $yil;
            if ($sonrakiAy > 12) {
                $sonrakiAy = 1;
                $sonrakiYil++;
            }
            ?>
            <a href="?ay=<?php echo $oncekiAy; ?>&yil=<?php echo $oncekiYil; ?>" class="nav-btn">&lt; Önceki Ay</a>
            <h2><?php echo date('F Y', mktime(0, 0, 0, $ay, 1, $yil)); ?></h2>
            <a href="?ay=<?php echo $sonrakiAy; ?>&yil=<?php echo $sonrakiYil; ?>" class="nav-btn">Sonraki Ay &gt;</a>
        </div>

        <!-- Takvim -->
        <div class="takvim">
            <?php echo takvimOlustur($ay, $yil); ?>
        </div>
        
        <!-- Personel Ekleme Formu -->
        <div class="form-section">
            <h2>Yeni Personel Ekle</h2>
            <form method="POST">
                <input type="text" name="ad" placeholder="Ad" required>
                <input type="text" name="soyad" placeholder="Soyad" required>
                <button type="submit" name="yeni_personel">Personel Ekle</button>
            </form>
        </div>

        <!-- Vardiya Ekleme Formu -->
        <div class="form-section">
            <h2>Vardiya Ekle</h2>
            <form method="POST">
                <select name="personel_id" required>
                    <?php echo personelListesiGetir(); ?>
                </select>
                <input type="date" name="tarih" required>
                <select name="vardiya_turu" required>
                    <option value="sabah">Sabah (08:00-16:00)</option>
                    <option value="aksam">Akşam (16:00-24:00)</option>
                    <option value="gece">Gece (24:00-08:00)</option>
                </select>
                <button type="submit" name="vardiya_ekle">Vardiya Ekle</button>
            </form>
        </div>
    </div>
</body>
</html> 