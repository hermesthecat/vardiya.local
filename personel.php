<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personel Yönetimi - Vardiya Sistemi</title>
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

    // Yetki kontrolü
    if (!in_array($_SESSION['rol'], ['yonetici', 'admin'])) {
        header('Location: index.php');
        exit;
    }

    $hata = '';
    $basari = '';

    // POST işlemleri
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['islem'])) {
            try {
                switch ($_POST['islem']) {
                    case 'personel_duzenle':
                        personelDuzenle($_POST['personel_id'], $_POST['ad'], $_POST['soyad'], $_POST['notlar']);
                        $basari = 'Personel bilgileri güncellendi.';
                        break;

                    case 'personel_sil':
                        personelSil($_POST['personel_id']);
                        $basari = 'Personel silindi.';
                        break;

                    case 'ekle':
                        $tercihler = [
                            'bildirimler' => isset($_POST['bildirimler']) ? true : false,
                            'tercih_edilen_vardiyalar' => $_POST['tercih_edilen_vardiyalar'] ?? [],
                            'tercih_edilmeyen_gunler' => $_POST['tercih_edilmeyen_gunler'] ?? []
                        ];

                        kullaniciOlustur(
                            $_POST['ad'],
                            $_POST['soyad'],
                            $_POST['email'],
                            $_POST['sifre'],
                            $_POST['rol'],
                            $_POST['telefon'],
                            $tercihler
                        );
                        $basari = 'Yeni personel başarıyla eklendi.';
                        break;

                    case 'guncelle':
                        if ($_SESSION['rol'] === 'admin') {
                            $tercihler = [
                                'bildirimler' => isset($_POST['bildirimler']) ? true : false,
                                'tercih_edilen_vardiyalar' => $_POST['tercih_edilen_vardiyalar'] ?? [],
                                'tercih_edilmeyen_gunler' => $_POST['tercih_edilmeyen_gunler'] ?? []
                            ];

                            kullaniciGuncelle(
                                $_POST['kullanici_id'],
                                $_POST['ad'],
                                $_POST['soyad'],
                                $_POST['email'],
                                $_POST['rol'],
                                $_POST['telefon'],
                                $tercihler
                            );
                            $basari = 'Personel bilgileri güncellendi.';
                        }
                        break;
                }
            } catch (Exception $e) {
                $hata = $e->getMessage();
            }
        }
    }

    // Mevcut personelleri getir
    $data = veriOku();
    $personeller = $data['personel'] ?? [];
    ?>

    <div class="container">
        <h1>Personel Yönetimi</h1>

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
                <a href="personel.php" class="active"><i class="fas fa-users"></i> Personel Yönetimi</a>
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

        <?php if ($_SESSION['rol'] === 'admin'): ?>
        <!-- Yeni Personel Ekleme Formu -->
        <div class="section">
            <h2>Yeni Personel Ekle</h2>
            <form method="POST" class="form-section">
                <input type="hidden" name="islem" value="ekle">

                <div class="form-group">
                    <label>Ad:</label>
                    <input type="text" name="ad" required>
                </div>

                <div class="form-group">
                    <label>Soyad:</label>
                    <input type="text" name="soyad" required>
                </div>

                <div class="form-group">
                    <label>E-posta:</label>
                    <input type="email" name="email" required>
                </div>

                <div class="form-group">
                    <label>Telefon:</label>
                    <input type="tel" name="telefon" pattern="[0-9]{10}" required placeholder="5XX1234567">
                </div>

                <div class="form-group">
                    <label>Şifre:</label>
                    <input type="password" name="sifre" required minlength="6">
                </div>

                <div class="form-group">
                    <label>Rol:</label>
                    <select name="rol" required>
                        <option value="personel">Personel</option>
                        <option value="yonetici">Yönetici</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tercihler:</label>
                    <div class="tercih-grup">
                        <div>
                            <label>
                                <input type="checkbox" name="bildirimler" checked>
                                Bildirimleri aktif et
                            </label>
                        </div>
                        
                        <div>
                            <label>Tercih Edilen Vardiyalar:</label>
                            <select name="tercih_edilen_vardiyalar[]" multiple>
                                <?php
                                $vardiyaTurleri = vardiyaTurleriniGetir();
                                foreach ($vardiyaTurleri as $kod => $vardiya) {
                                    echo '<option value="' . htmlspecialchars($kod) . '">' . htmlspecialchars($vardiya['etiket']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div>
                            <label>Tercih Edilmeyen Günler:</label>
                            <select name="tercih_edilmeyen_gunler[]" multiple>
                                <option value="1">Pazartesi</option>
                                <option value="2">Salı</option>
                                <option value="3">Çarşamba</option>
                                <option value="4">Perşembe</option>
                                <option value="5">Cuma</option>
                                <option value="6">Cumartesi</option>
                                <option value="0">Pazar</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Personel Ekle</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Personel Listesi -->
        <div class="section">
            <h2>Personel Listesi</h2>
            <div class="tablo-container">
                <table>
                    <thead>
                        <tr>
                            <th>Ad Soyad</th>
                            <th>E-posta</th>
                            <th>Telefon</th>
                            <th>Rol</th>
                            <?php if ($_SESSION['rol'] === 'admin'): ?>
                                <th>Bildirimler</th>
                                <th>Tercih Edilen Vardiyalar</th>
                                <th>Tercih Edilmeyen Günler</th>
                            <?php endif; ?>
                            <th>Toplam Vardiya</th>
                            <th>Son Vardiya</th>
                            <th>Notlar</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($personeller as $personel): 
                            $vardiyaBilgisi = personelVardiyaBilgisiGetir($personel['id']);
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($personel['ad'] . ' ' . $personel['soyad']); ?></td>
                                <td><?php echo htmlspecialchars($personel['email']); ?></td>
                                <td><?php echo htmlspecialchars($personel['telefon']); ?></td>
                                <td><?php echo htmlspecialchars($personel['yetki']); ?></td>
                                <?php if ($_SESSION['rol'] === 'admin'): ?>
                                    <td><?php echo $personel['tercihler']['bildirimler'] ? 'Aktif' : 'Pasif'; ?></td>
                                    <td><?php 
                                        $vardiyalar = array_map(function($vardiyaTuru) {
                                            return vardiyaTuruEtiketGetir($vardiyaTuru);
                                        }, $personel['tercihler']['tercih_edilen_vardiyalar'] ?? []);
                                        echo htmlspecialchars(implode(', ', $vardiyalar)); 
                                    ?></td>
                                    <td><?php 
                                        $gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
                                        $tercihEdilmeyenGunler = array_map(function($gun) use ($gunler) {
                                            return $gunler[$gun];
                                        }, $personel['tercihler']['tercih_edilmeyen_gunler'] ?? []);
                                        echo htmlspecialchars(implode(', ', $tercihEdilmeyenGunler));
                                    ?></td>
                                <?php endif; ?>
                                <td><?php echo $vardiyaBilgisi['toplam_vardiya']; ?></td>
                                <td>
                                    <?php 
                                    echo $vardiyaBilgisi['son_vardiya'] 
                                        ? date('d.m.Y', $vardiyaBilgisi['son_vardiya'])
                                        : '-';
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($personel['notlar'] ?? ''); ?></td>
                                <td class="islem-butonlar">
                                    <?php if ($_SESSION['rol'] === 'admin'): ?>
                                        <button onclick="personelDuzenle(<?php echo htmlspecialchars(json_encode($personel)); ?>)"
                                                class="btn-duzenle">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="personelSil('<?php echo $personel['id']; ?>')"
                                                class="btn-sil">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Personel Düzenleme Modal -->
    <div id="personelDuzenleModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Personel Düzenle</h2>
            <form method="POST" id="personelDuzenleForm">
                <input type="hidden" name="islem" value="guncelle">
                <input type="hidden" name="kullanici_id" id="duzenle_kullanici_id">

                <div class="form-group">
                    <label>Ad:</label>
                    <input type="text" name="ad" id="duzenle_ad" required>
                </div>

                <div class="form-group">
                    <label>Soyad:</label>
                    <input type="text" name="soyad" id="duzenle_soyad" required>
                </div>

                <div class="form-group">
                    <label>E-posta:</label>
                    <input type="email" name="email" id="duzenle_email" required>
                </div>

                <div class="form-group">
                    <label>Telefon:</label>
                    <input type="tel" name="telefon" id="duzenle_telefon" pattern="[0-9]{10}" required>
                </div>

                <div class="form-group">
                    <label>Rol:</label>
                    <select name="rol" id="duzenle_rol" required>
                        <option value="personel">Personel</option>
                        <option value="yonetici">Yönetici</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tercihler:</label>
                    <div class="tercih-grup">
                        <div>
                            <label>
                                <input type="checkbox" name="bildirimler" id="duzenle_bildirimler">
                                Bildirimleri aktif et
                            </label>
                        </div>
                        
                        <div>
                            <label>Tercih Edilen Vardiyalar:</label>
                            <select name="tercih_edilen_vardiyalar[]" id="duzenle_tercih_edilen_vardiyalar" multiple>
                                <?php foreach ($vardiyaTurleri as $kod => $vardiya): ?>
                                    <option value="<?php echo htmlspecialchars($kod); ?>">
                                        <?php echo htmlspecialchars($vardiya['etiket']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label>Tercih Edilmeyen Günler:</label>
                            <select name="tercih_edilmeyen_gunler[]" id="duzenle_tercih_edilmeyen_gunler" multiple>
                                <option value="1">Pazartesi</option>
                                <option value="2">Salı</option>
                                <option value="3">Çarşamba</option>
                                <option value="4">Perşembe</option>
                                <option value="5">Cuma</option>
                                <option value="6">Cumartesi</option>
                                <option value="0">Pazar</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit-btn">Güncelle</button>
            </form>
        </div>
    </div>

    <script>
        // Modal işlemleri
        const modal = document.getElementById('personelDuzenleModal');
        const span = document.getElementsByClassName('close')[0];

        span.onclick = function() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Personel düzenleme
        function personelDuzenle(personel) {
            document.getElementById('duzenle_kullanici_id').value = personel.id;
            document.getElementById('duzenle_ad').value = personel.ad;
            document.getElementById('duzenle_soyad').value = personel.soyad;
            document.getElementById('duzenle_email').value = personel.email;
            document.getElementById('duzenle_telefon').value = personel.telefon;
            document.getElementById('duzenle_rol').value = personel.yetki;
            document.getElementById('duzenle_bildirimler').checked = personel.tercihler.bildirimler;

            // Tercih edilen vardiyaları seç
            const tercihEdilenVardiyalar = document.getElementById('duzenle_tercih_edilen_vardiyalar');
            Array.from(tercihEdilenVardiyalar.options).forEach(option => {
                option.selected = personel.tercihler.tercih_edilen_vardiyalar.includes(option.value);
            });

            // Tercih edilmeyen günleri seç
            const tercihEdilmeyenGunler = document.getElementById('duzenle_tercih_edilmeyen_gunler');
            Array.from(tercihEdilmeyenGunler.options).forEach(option => {
                option.selected = personel.tercihler.tercih_edilmeyen_gunler.includes(option.value);
            });

            modal.style.display = 'block';
        }

        // Personel silme
        function personelSil(personelId) {
            if (confirm('Bu personeli silmek istediğinizden emin misiniz?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="islem" value="personel_sil">
                    <input type="hidden" name="personel_id" value="${personelId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>

</html>