<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vardiya Tercihleri - Vardiya Sistemi</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <?php
    require_once 'functions.php';

    $hata = '';
    $basari = '';

    // POST işlemleri
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['personel_id'])) {
        $tercihler = [
            'tercih_edilen_vardiyalar' => isset($_POST['tercih_edilen_vardiyalar']) ? $_POST['tercih_edilen_vardiyalar'] : [],
            'tercih_edilmeyen_gunler' => isset($_POST['tercih_edilmeyen_gunler']) ? $_POST['tercih_edilmeyen_gunler'] : [],
            'max_ardisik_vardiya' => isset($_POST['max_ardisik_vardiya']) ? intval($_POST['max_ardisik_vardiya']) : 5
        ];

        try {
            personelTercihKaydet($_POST['personel_id'], $tercihler);
            $basari = 'Tercihleriniz başarıyla kaydedildi.';
        } catch (Exception $e) {
            $hata = $e->getMessage();
        }
    }

    $personeller = tumPersonelleriGetir();
    ?>

    <div class="container">
        <div class="header-nav">
            <h1>Vardiya Tercihleri</h1>
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

        <div class="form-section">
            <h2>Vardiya Tercihlerini Düzenle</h2>
            <form method="POST">
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
                        foreach ($vardiyaTurleri as $id => $vardiya) {
                            echo '<label>
                                <input type="checkbox" name="tercih_edilen_vardiyalar[]" value="' . $id . '">
                                ' . $vardiya['etiket'] . '
                            </label>';
                        }
                        ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Tercih Edilmeyen Günler:</label>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="tercih_edilmeyen_gunler[]" value="0">
                            Pazar
                        </label>
                        <label>
                            <input type="checkbox" name="tercih_edilmeyen_gunler[]" value="1">
                            Pazartesi
                        </label>
                        <label>
                            <input type="checkbox" name="tercih_edilmeyen_gunler[]" value="2">
                            Salı
                        </label>
                        <label>
                            <input type="checkbox" name="tercih_edilmeyen_gunler[]" value="3">
                            Çarşamba
                        </label>
                        <label>
                            <input type="checkbox" name="tercih_edilmeyen_gunler[]" value="4">
                            Perşembe
                        </label>
                        <label>
                            <input type="checkbox" name="tercih_edilmeyen_gunler[]" value="5">
                            Cuma
                        </label>
                        <label>
                            <input type="checkbox" name="tercih_edilmeyen_gunler[]" value="6">
                            Cumartesi
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Maksimum Ardışık Vardiya:</label>
                    <input type="number" name="max_ardisik_vardiya" min="1" max="6" value="5">
                </div>

                <button type="submit" class="submit-btn">Tercihleri Kaydet</button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('personel_id').addEventListener('change', function() {
            // AJAX ile personel tercihlerini getir
            var personelId = this.value;
            fetch('get_tercihler.php?personel_id=' + personelId)
                .then(response => response.json())
                .then(data => {
                    // Tercih edilen vardiyaları işaretle
                    document.querySelectorAll('input[name="tercih_edilen_vardiyalar[]"]').forEach(input => {
                        input.checked = data.tercih_edilen_vardiyalar.includes(input.value);
                    });

                    // Tercih edilmeyen günleri işaretle
                    document.querySelectorAll('input[name="tercih_edilmeyen_gunler[]"]').forEach(input => {
                        input.checked = data.tercih_edilmeyen_gunler.includes(input.value);
                    });

                    // Maksimum ardışık vardiyayı ayarla
                    document.querySelector('input[name="max_ardisik_vardiya"]').value = data.max_ardisik_vardiya;
                });
        });
    </script>
</body>

</html>