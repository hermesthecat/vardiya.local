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
    ?>

    <div class="container">
        <h1>Personel Vardiya Sistemi</h1>
        
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

        <!-- Vardiya Listesi -->
        <div class="list-section">
            <h2>Vardiya Listesi</h2>
            <?php echo vardiyaListesiGetir(); ?>
        </div>
    </div>
</body>
</html> 