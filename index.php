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
                <a href="?ay=<?php echo $oncekiAy; ?>&yil=<?php echo $oncekiYil; ?>" class="btn-small">
                    <i class="fas fa-chevron-left"></i> Önceki Ay
                </a>
                <span class="current-month">
                    <i class="fas fa-calendar-alt"></i>
                    <?php
                    $formatter = new IntlDateFormatter(
                        'tr_TR.UTF-8',
                        IntlDateFormatter::LONG,
                        IntlDateFormatter::NONE,
                        'Europe/Istanbul',
                        IntlDateFormatter::GREGORIAN,
                        'LLLL Y'
                    );
                    echo ucfirst($formatter->format(mktime(0, 0, 0, $ay, 1, $yil)));
                    ?>
                </span>
                <a href="?ay=<?php echo $sonrakiAy; ?>&yil=<?php echo $sonrakiYil; ?>" class="btn-small">
                    Sonraki Ay <i class="fas fa-chevron-right"></i>
                </a>
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
            <div class="takvim-tablo">
                <table>
                    <thead>
                        <tr>
                            <th>Pazartesi</th>
                            <th>Salı</th>
                            <th>Çarşamba</th>
                            <th>Perşembe</th>
                            <th>Cuma</th>
                            <th>Cumartesi</th>
                            <th>Pazar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php echo takvimOlustur($ay, $yil); ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Vardiya Ekleme Modalı -->
    <div id="vardiyaModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2><i class="fas fa-calendar-plus"></i> Vardiya Ekle</h2>
            
            <div id="modalMessage" style="display: none; margin-bottom: 15px;"></div>

            <!-- Akıllı Vardiya Önerileri -->
            <div id="vardiyaOnerileri" class="oneri-section" style="display: none;">
                <h3><i class="fas fa-lightbulb"></i> Önerilen Personeller</h3>
                <div class="oneri-liste"></div>
            </div>
            
            <form id="vardiyaEkleForm" method="POST" class="form-section">
                <input type="hidden" name="islem" value="vardiya_ekle">
                <input type="hidden" name="tarih" id="modalTarih">

                <div class="form-group">
                    <label><i class="fas fa-user"></i> Personel:</label>
                    <select name="personel_id" id="personelSelect" required>
                        <?php echo personelListesiGetir(); ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Vardiya Türü:</label>
                    <select name="vardiya_turu" id="vardiyaTuruSelect" required>
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
                    <label><i class="fas fa-sticky-note"></i> Notlar:</label>
                    <textarea name="notlar" placeholder="Vardiya ile ilgili notları buraya yazabilirsiniz..."></textarea>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-save"></i> Vardiya Ekle
                </button>
            </form>
        </div>
    </div>

    <script>
        // Modal ve form işlemleri
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('vardiyaModal');
            const span = document.getElementsByClassName('close')[0];
            const form = document.getElementById('vardiyaEkleForm');
            const modalTarih = document.getElementById('modalTarih');
            const modalMessage = document.getElementById('modalMessage');
            const vardiyaOnerileri = document.getElementById('vardiyaOnerileri');
            const oneriListe = vardiyaOnerileri.querySelector('.oneri-liste');
            const personelSelect = document.getElementById('personelSelect');
            const vardiyaTuruSelect = document.getElementById('vardiyaTuruSelect');

            // Mesaj gösterme fonksiyonu
            function showMessage(message, isError = false) {
                modalMessage.textContent = message;
                modalMessage.style.display = 'block';
                modalMessage.className = isError ? 'hata-mesaji' : 'basari-mesaji';
            }

            // Vardiya önerilerini getir
            function getVardiyaOnerileri() {
                const tarih = modalTarih.value;
                const vardiyaTuru = vardiyaTuruSelect.value;

                if (tarih && vardiyaTuru) {
                    fetch('get_oneriler.php?tarih=' + tarih + '&vardiya_turu=' + vardiyaTuru)
                        .then(response => response.json())
                        .then(data => {
                            if (data.length > 0) {
                                oneriListe.innerHTML = '';
                                data.forEach(oneri => {
                                    const div = document.createElement('div');
                                    div.className = 'oneri-item';
                                    div.innerHTML = `
                                        <div class="oneri-info">
                                            <span class="oneri-ad">${oneri.ad_soyad}</span>
                                            <span class="oneri-puan">Uygunluk: %${Math.round(oneri.puan)}</span>
                                        </div>
                                        <button type="button" class="oneri-sec" data-personel-id="${oneri.personel_id}">
                                            <i class="fas fa-check"></i> Seç
                                        </button>
                                    `;
                                    oneriListe.appendChild(div);
                                });
                                vardiyaOnerileri.style.display = 'block';

                                // Öneri seçme butonlarına tıklama olayı
                                document.querySelectorAll('.oneri-sec').forEach(btn => {
                                    btn.addEventListener('click', function() {
                                        const personelId = this.getAttribute('data-personel-id');
                                        personelSelect.value = personelId;
                                        vardiyaOnerileri.style.display = 'none';
                                    });
                                });
                            } else {
                                vardiyaOnerileri.style.display = 'none';
                            }
                        })
                        .catch(error => {
                            console.error('Öneri getirme hatası:', error);
                            vardiyaOnerileri.style.display = 'none';
                        });
                }
            }

            // Vardiya türü değiştiğinde önerileri güncelle
            vardiyaTuruSelect.addEventListener('change', getVardiyaOnerileri);

            // Takvim hücrelerine tıklama olayı
            document.querySelectorAll('.gun').forEach(gun => {
                gun.addEventListener('click', function() {
                    if (this.dataset.tarih) {
                        modalTarih.value = this.dataset.tarih;
                        modalMessage.style.display = 'none';
                        vardiyaOnerileri.style.display = 'none';
                        form.reset();
                        modal.style.display = 'block';
                        // Önerileri getir
                        if (vardiyaTuruSelect.value) {
                            getVardiyaOnerileri();
                        }
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

            // Form gönderme olayı
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    if (data.includes('basari-mesaji')) {
                        showMessage('Vardiya başarıyla eklendi!');
                        setTimeout(() => {
                            modal.style.display = 'none';
                            location.reload();
                        }, 1500);
                    } else if (data.includes('hata-mesaji')) {
                        const errorMatch = data.match(/<div class="hata-mesaji">(.*?)<\/div>/);
                        if (errorMatch) {
                            showMessage(errorMatch[1], true);
                        } else {
                            showMessage('Vardiya eklenirken bir hata oluştu.', true);
                        }
                    } else {
                        showMessage('Vardiya eklenirken bir hata oluştu.', true);
                    }
                })
                .catch(error => {
                    console.error('Hata:', error);
                    showMessage('Vardiya eklenirken bir hata oluştu.', true);
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