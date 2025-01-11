<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vardiya Yönetimi - Vardiya Sistemi</title>
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
        if (isset($_POST['vardiya_duzenle'])) {
            try {
                vardiyaDuzenle(
                    $_POST['vardiya_id'],
                    $_POST['personel_id'],
                    $_POST['tarih'],
                    $_POST['vardiya_turu'],
                    $_POST['notlar']
                );
                $basari = 'Vardiya başarıyla güncellendi.';
            } catch (Exception $e) {
                $hata = $e->getMessage();
            }
        } elseif (isset($_POST['vardiya_sil'])) {
            try {
                vardiyaSil($_POST['vardiya_id']);
                $basari = 'Vardiya başarıyla silindi.';
            } catch (Exception $e) {
                $hata = $e->getMessage();
            }
        } elseif (isset($_POST['degisim_talebi'])) {
            try {
                vardiyaDegisimTalebiOlustur(
                    $_POST['vardiya_id'],
                    $_POST['talep_eden_personel_id'],
                    $_POST['aciklama']
                );
                $basari = 'Vardiya değişim talebi oluşturuldu.';
            } catch (Exception $e) {
                $hata = $e->getMessage();
            }
        } elseif (isset($_POST['talep_guncelle'])) {
            try {
                vardiyaTalebiGuncelle($_POST['talep_id'], $_POST['durum']);
                $basari = 'Talep durumu güncellendi.';
            } catch (Exception $e) {
                $hata = $e->getMessage();
            }
        }
    }

    // GET ile vardiya detayı
    $vardiyaDetay = null;
    if (isset($_GET['vardiya_id'])) {
        $vardiyaDetay = vardiyaDetayGetir($_GET['vardiya_id']);
    }
    ?>

    <div class="container">
        <h1>Vardiya Yönetimi</h1>

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

        <?php if ($vardiyaDetay): ?>
            <!-- Vardiya Düzenleme Formu -->
            <div class="form-section">
                <h2>Vardiya Düzenle</h2>
                <form method="POST">
                    <input type="hidden" name="vardiya_id" value="<?php echo $vardiyaDetay['id']; ?>">
                    <div class="form-group">
                        <label>Personel:</label>
                        <select name="personel_id" required>
                            <?php
                            $personeller = tumPersonelleriGetir();
                            foreach ($personeller as $personel) {
                                $selected = $personel['id'] === $vardiyaDetay['personel_id'] ? 'selected' : '';
                                echo sprintf(
                                    '<option value="%s" %s>%s %s</option>',
                                    $personel['id'],
                                    $selected,
                                    htmlspecialchars($personel['ad']),
                                    htmlspecialchars($personel['soyad'])
                                );
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tarih:</label>
                        <input type="date" name="tarih" value="<?php echo $vardiyaDetay['tarih']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Vardiya Türü:</label>
                        <select name="vardiya_turu" required>
                            <?php
                            $vardiyaTurleri = vardiyaTurleriniGetir();
                            foreach ($vardiyaTurleri as $id => $vardiya) {
                                $selected = ($vardiyaDetay && $vardiyaDetay['vardiya_turu'] === $id) ? 'selected' : '';
                                echo "<option value=\"$id\" $selected>{$vardiya['etiket']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notlar:</label>
                        <textarea name="notlar" rows="4"><?php echo htmlspecialchars($vardiyaDetay['notlar']); ?></textarea>
                    </div>
                    <div class="button-group">
                        <button type="submit" name="vardiya_duzenle" class="submit-btn">Kaydet</button>
                        <button type="button" onclick="vardiyaSilOnay('<?php echo $vardiyaDetay['id']; ?>')" class="btn-sil">Vardiyayı Sil</button>
                    </div>
                </form>
            </div>

            <!-- Vardiya Değişim Talebi Formu -->
            <div class="form-section">
                <h2>Vardiya Değişim Talebi Oluştur</h2>
                <form method="POST">
                    <input type="hidden" name="vardiya_id" value="<?php echo $vardiyaDetay['id']; ?>">
                    <div class="form-group">
                        <label>Talep Eden Personel:</label>
                        <select name="talep_eden_personel_id" required>
                            <?php
                            foreach ($personeller as $personel) {
                                if ($personel['id'] !== $vardiyaDetay['personel_id']) {
                                    echo sprintf(
                                        '<option value="%s">%s %s</option>',
                                        $personel['id'],
                                        htmlspecialchars($personel['ad']),
                                        htmlspecialchars($personel['soyad'])
                                    );
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Açıklama:</label>
                        <textarea name="aciklama" rows="4" required></textarea>
                    </div>
                    <button type="submit" name="degisim_talebi" class="submit-btn">Değişim Talebi Oluştur</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Vardiya Değişim Talepleri -->
        <div class="tablo-container">
            <h2>Vardiya Değişim Talepleri</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Vardiya</th>
                        <th>Mevcut Personel</th>
                        <th>Talep Eden</th>
                        <th>Açıklama</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $data = veriOku();
                    foreach ($data['vardiya_talepleri'] as $talep):
                        // Vardiya kontrolü
                        $vardiya = array_filter($data['vardiyalar'], function ($v) use ($talep) {
                            return isset($talep['vardiya_id']) && isset($v['id']) && $v['id'] === $talep['vardiya_id'];
                        });
                        $vardiya = reset($vardiya);
                        if (!$vardiya) continue;

                        // Mevcut personel kontrolü
                        $mevcutPersonel = array_filter($data['personel'], function ($p) use ($vardiya) {
                            return isset($vardiya['personel_id']) && isset($p['id']) && $p['id'] === $vardiya['personel_id'];
                        });
                        $mevcutPersonel = reset($mevcutPersonel);
                        if (!$mevcutPersonel) continue;

                        // Talep eden personel kontrolü
                        $talepEden = array_filter($data['personel'], function ($p) use ($talep) {
                            return isset($talep['talep_eden_personel_id']) && isset($p['id']) && $p['id'] === $talep['talep_eden_personel_id'];
                        });
                        $talepEden = reset($talepEden);
                        if (!$talepEden) continue;
                    ?>
                        <tr>
                            <td><?php echo date('d.m.Y', $vardiya['tarih']); ?></td>
                            <td><?php echo vardiyaTuruEtiketGetir($vardiya['vardiya_turu']); ?></td>
                            <td><?php echo htmlspecialchars($mevcutPersonel['ad'] . ' ' . $mevcutPersonel['soyad']); ?></td>
                            <td><?php echo htmlspecialchars($talepEden['ad'] . ' ' . $talepEden['soyad']); ?></td>
                            <td><?php echo htmlspecialchars($talep['aciklama'] ?? ''); ?></td>
                            <td><?php echo $talep['durum'] ?? 'beklemede'; ?></td>
                            <td>
                                <?php if (isset($talep['durum']) && $talep['durum'] === 'beklemede'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="talep_id" value="<?php echo $talep['id']; ?>">
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
    </div>

    <!-- Vardiya Silme Form -->
    <form id="silForm" method="POST" style="display: none;">
        <input type="hidden" name="vardiya_id" id="sil_vardiya_id">
        <input type="hidden" name="vardiya_sil" value="1">
    </form>

    <script>
        function vardiyaSilOnay(vardiyaId) {
            if (confirm('Bu vardiyayı silmek istediğinizden emin misiniz?')) {
                document.getElementById('sil_vardiya_id').value = vardiyaId;
                document.getElementById('silForm').submit();
            }
        }

        // Vardiya türü seçildiğinde stil değişikliği
        document.querySelectorAll('.vardiya-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.vardiya-btn').forEach(function(b) {
                    b.classList.remove('active');
                });
                this.classList.add('active');
            });
        });
    </script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="script.js"></script>
</body>

</html>