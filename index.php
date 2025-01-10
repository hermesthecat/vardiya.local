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

    $hata = '';
    $basari = '';

    // POST işlemleri
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['yeni_personel'])) {
            personelEkle($_POST['ad'], $_POST['soyad']);
            $basari = 'Personel başarıyla eklendi.';
        } elseif (isset($_POST['vardiya_ekle'])) {
            try {
                vardiyaEkle($_POST['personel_id'], $_POST['tarih'], $_POST['vardiya_turu']);
                $basari = 'Vardiya başarıyla eklendi.';
            } catch (Exception $e) {
                $hata = $e->getMessage();
            }
        }
    }

    // Takvim için ay ve yıl
    $ay = isset($_GET['ay']) ? intval($_GET['ay']) : intval(date('m'));
    $yil = isset($_GET['yil']) ? intval($_GET['yil']) : intval(date('Y'));
    ?>

    <div class="container">
        <div class="header-nav">
            <h1>Personel Vardiya Sistemi</h1>
            <div>
                <a href="personel.php" class="nav-btn">Personel Yönetimi</a>
                <a href="izin.php" class="nav-btn">İzin Yönetimi</a>
                <a href="rapor.php" class="nav-btn">İstatistikler ve Raporlar</a>
            </div>
        </div>

        <?php if ($hata): ?>
            <div class="hata-mesaji">
                <?php echo htmlspecialchars($hata); ?>
            </div>
        <?php endif; ?>

        <?php if ($basari): ?>
            <div class="basari-mesaji">
                <?php echo htmlspecialchars($basari); ?>
            </div>
        <?php endif; ?>

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
    </div>

    <!-- Vardiya Ekleme Modal -->
    <div id="vardiyaModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Vardiya Ekle</h2>
            
            <!-- Akıllı Vardiya Önerileri -->
            <div id="vardiyaOnerileri" class="oneri-section" style="display: none;">
                <h3>Önerilen Personeller</h3>
                <div class="oneri-liste"></div>
            </div>

            <form method="POST" id="vardiyaForm">
                <input type="hidden" name="tarih" id="seciliTarih">
                <div class="form-group">
                    <label>Personel:</label>
                    <select name="personel_id" id="personelSelect" required>
                        <?php echo personelListesiGetir(); ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Vardiya:</label>
                    <div class="vardiya-butonlar">
                        <label>
                            <input type="radio" name="vardiya_turu" value="sabah" required>
                            <span class="vardiya-btn sabah">Sabah (08:00-16:00)</span>
                        </label>
                        <label>
                            <input type="radio" name="vardiya_turu" value="aksam" required>
                            <span class="vardiya-btn aksam">Akşam (16:00-24:00)</span>
                        </label>
                        <label>
                            <input type="radio" name="vardiya_turu" value="gece" required>
                            <span class="vardiya-btn gece">Gece (24:00-08:00)</span>
                        </label>
                    </div>
                </div>
                <button type="submit" name="vardiya_ekle" class="submit-btn">Vardiya Ekle</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById('vardiyaModal');
            var span = document.getElementsByClassName('close')[0];
            var seciliTarihInput = document.getElementById('seciliTarih');
            var vardiyaForm = document.getElementById('vardiyaForm');
            var personelSelect = document.getElementById('personelSelect');
            var vardiyaOnerileri = document.getElementById('vardiyaOnerileri');
            var oneriListe = vardiyaOnerileri.querySelector('.oneri-liste');

            // Takvim hücrelerine tıklama olayı ekle
            document.querySelectorAll('.gun').forEach(function(gun) {
                gun.addEventListener('click', function() {
                    var tarih = this.getAttribute('data-tarih');
                    if (tarih) {
                        seciliTarihInput.value = tarih;
                        modal.style.display = 'block';
                        // Form resetle
                        vardiyaForm.reset();
                        document.querySelectorAll('.vardiya-btn').forEach(function(btn) {
                            btn.classList.remove('active');
                        });
                    }
                });
            });

            // Vardiya türü seçildiğinde önerileri getir
            document.querySelectorAll('input[name="vardiya_turu"]').forEach(function(radio) {
                radio.addEventListener('change', function() {
                    if (seciliTarihInput.value && this.value) {
                        // AJAX ile önerileri getir
                        fetch('get_oneriler.php?tarih=' + seciliTarihInput.value + '&vardiya_turu=' + this.value)
                            .then(response => response.json())
                            .then(data => {
                                if (data.length > 0) {
                                    oneriListe.innerHTML = '';
                                    data.forEach(function(oneri) {
                                        var div = document.createElement('div');
                                        div.className = 'oneri-item';
                                        div.innerHTML = `
                                            <span>${oneri.ad_soyad}</span>
                                            <span class="oneri-puan">Uygunluk: %${Math.round(oneri.puan)}</span>
                                            <button type="button" class="oneri-sec" data-personel-id="${oneri.personel_id}">Seç</button>
                                        `;
                                        oneriListe.appendChild(div);
                                    });
                                    vardiyaOnerileri.style.display = 'block';

                                    // Öneri seçme butonlarına tıklama olayı ekle
                                    document.querySelectorAll('.oneri-sec').forEach(function(btn) {
                                        btn.addEventListener('click', function() {
                                            var personelId = this.getAttribute('data-personel-id');
                                            personelSelect.value = personelId;
                                        });
                                    });
                                } else {
                                    vardiyaOnerileri.style.display = 'none';
                                }
                            });
                    }
                });
            });

            // Modal kapatma olayları
            span.onclick = function() {
                modal.style.display = 'none';
            }

            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }

            // Vardiya butonları için tıklama olayı
            document.querySelectorAll('.vardiya-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.vardiya-btn').forEach(function(b) {
                        b.classList.remove('active');
                    });
                    this.classList.add('active');
                });
            });
        });
    </script>
</body>

</html>