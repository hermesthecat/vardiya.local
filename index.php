<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personel Vardiya Sistemi</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- PWA için gerekli meta etiketleri -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#4caf50">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="Vardiya">
    <link rel="apple-touch-icon" href="icons/icon-152x152.png">

    <!-- PWA ve Bildirim Scripti -->
    <script>
        // Service Worker Kaydı
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then((registration) => {
                        console.log('ServiceWorker kaydı başarılı:', registration.scope);
                    })
                    .catch((error) => {
                        console.log('ServiceWorker kaydı başarısız:', error);
                    });
            });
        }

        // Bildirim İzni İsteme
        function bildirimIzniIste() {
            Notification.requestPermission().then((permission) => {
                if (permission === 'granted') {
                    console.log('Bildirim izni verildi');
                    // Push aboneliği oluştur
                    pushAboneligiOlustur();
                }
            });
        }

        // Push Aboneliği Oluşturma
        function pushAboneligiOlustur() {
            navigator.serviceWorker.ready.then((registration) => {
                const subscribeOptions = {
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array('YOUR_PUBLIC_VAPID_KEY')
                };

                return registration.pushManager.subscribe(subscribeOptions);
            }).then((pushSubscription) => {
                console.log('Push aboneliği başarılı:', JSON.stringify(pushSubscription));
                // Abonelik bilgilerini sunucuya gönder
                return fetch('push_abone_kaydet.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(pushSubscription)
                });
            });
        }

        // Base64 URL'yi Uint8Array'e Dönüştürme
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/\-/g, '+')
                .replace(/_/g, '/');

            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);

            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        // Sayfa yüklendiğinde bildirim izni iste
        window.addEventListener('load', () => {
            if ('Notification' in window) {
                if (Notification.permission !== 'granted' && Notification.permission !== 'denied') {
                    setTimeout(bildirimIzniIste, 3000); // 3 saniye sonra izin iste
                }
            }
        });
    </script>

    <!-- Tarih işlemleri için JavaScript -->
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

    // Mevcut ay ve yıl
    $ay = isset($_GET['ay']) ? (int)$_GET['ay'] : (int)date('m');
    $yil = isset($_GET['yil']) ? (int)$_GET['yil'] : (int)date('Y');

    // Vardiya ekleme işlemi
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['islem']) && $_POST['islem'] === 'vardiya_ekle') {
        try {
            // Yönetici ve admin rollerini kontrol et
            if (!in_array($_SESSION['rol'], ['yonetici', 'admin'])) {
                throw new Exception('Vardiya ekleme yetkiniz bulunmuyor.');
            }

            vardiyaEkle(
                $_POST['personel_id'],
                $_POST['tarih'],
                $_POST['vardiya_turu'],
                $_POST['notlar'] ?? ''
            );
            $basari = 'Vardiya başarıyla eklendi.';
        } catch (Exception $e) {
            $hata = $e->getMessage();
        }
    }
    ?>

    <div class="container">
        <h1>Vardiya Sistemi</h1>

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
                <?php if (in_array($_SESSION['rol'], ['yonetici', 'admin'])): ?>
                    <a href="personel.php"><i class="fas fa-users"></i> Personel Yönetimi</a>
                <?php endif; ?>

                <?php if ($_SESSION['rol'] === 'admin'): ?>
                    <a href="kullanicilar.php"><i class="fas fa-user-cog"></i> Kullanıcı Yönetimi</a>
                <?php endif; ?>

                <a href="izinler.php"><i class="fas fa-calendar-alt"></i> İzin İşlemleri</a>
                <a href="profil.php"><i class="fas fa-user-circle"></i> Profil</a>
            </div>
        </nav>

        <?php if ($hata): ?>
            <div class="hata-mesaji"><?php echo htmlspecialchars($hata); ?></div>
        <?php endif; ?>

        <?php if ($basari): ?>
            <div class="basari-mesaji"><?php echo htmlspecialchars($basari); ?></div>
        <?php endif; ?>

        <!-- Ay Seçimi -->
        <div class="section">
            <div class="ay-secimi">
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
                <a href="?ay=<?php echo $oncekiAy; ?>&yil=<?php echo $oncekiYil; ?>" class="btn-small">&lt; Önceki Ay</a>
                <span class="current-month">
                    <?php echo date('F Y', mktime(0, 0, 0, $ay, 1, $yil)); ?>
                </span>
                <a href="?ay=<?php echo $sonrakiAy; ?>&yil=<?php echo $sonrakiYil; ?>" class="btn-small">Sonraki Ay &gt;</a>
            </div>
        </div>

        <!-- Vardiya Ekleme Formu (Sadece yönetici ve admin için) -->
        <?php if (in_array($_SESSION['rol'], ['yonetici', 'admin'])): ?>
            <div class="section">
                <h2>Yeni Vardiya Ekle</h2>
                <form method="POST" class="form-section">
                    <input type="hidden" name="islem" value="vardiya_ekle">

                    <div class="form-group">
                        <label>Personel:</label>
                        <select name="personel_id" required>
                            <?php echo personelListesiGetir(); ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tarih:</label>
                        <input type="date" name="tarih" required>
                    </div>

                    <div class="form-group">
                        <label>Vardiya Türü:</label>
                        <select name="vardiya_turu" required>
                            <?php
                            $vardiyaTurleri = vardiyaTurleriniGetir();
                            foreach ($vardiyaTurleri as $id => $vardiya) {
                                echo '<option value="' . htmlspecialchars($id) . '">' .
                                    htmlspecialchars($vardiya['etiket']) . ' (' .
                                    htmlspecialchars($vardiya['baslangic']) . '-' .
                                    htmlspecialchars($vardiya['bitis']) . ')</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Notlar:</label>
                        <textarea name="notlar"></textarea>
                    </div>

                    <button type="submit" class="submit-btn">Vardiya Ekle</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Takvim -->
        <div class="section">
            <h2>Vardiya Takvimi</h2>
            <?php echo takvimOlustur($ay, $yil); ?>
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
                        <?php
                        foreach ($vardiyaTurleri as $id => $vardiya) {
                            echo '<label>
                                <input type="radio" name="vardiya_turu" value="' . htmlspecialchars($id) . '" required>
                                <span class="vardiya-btn ' . htmlspecialchars($id) . '">' .
                                htmlspecialchars($vardiya['etiket']) . ' (' .
                                htmlspecialchars($vardiya['baslangic']) . '-' .
                                htmlspecialchars($vardiya['bitis']) . ')</span>
                            </label>';
                        }
                        ?>
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

    <script>
        // Tarih formatlarının kullanım örnekleri
        document.addEventListener('DOMContentLoaded', function() {
            // Tarihleri timestamp'den Türkçe formata çevirme
            const tarihElementleri = document.querySelectorAll('[data-timestamp]');
            tarihElementleri.forEach(element => {
                const timestamp = element.getAttribute('data-timestamp');
                const format = element.getAttribute('data-format') || 'kisa';
                element.textContent = tarihFormatla(timestamp, format);
            });

            // Form inputlarını düzenleme
            const tarihInputlari = document.querySelectorAll('input[type="date"]');
            tarihInputlari.forEach(input => {
                // Varsayılan değeri varsa
                if (input.getAttribute('data-timestamp')) {
                    const timestamp = input.getAttribute('data-timestamp');
                    input.value = timestampToInputValue(timestamp);
                }

                // Form gönderilirken
                input.closest('form')?.addEventListener('submit', function(e) {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = input.name + '_timestamp';
                    hiddenInput.value = inputValueToTimestamp(input.value);
                    this.appendChild(hiddenInput);
                });
            });
        });
    </script>
</body>

</html>