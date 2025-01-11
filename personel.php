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
        if (isset($_POST['personel_duzenle'])) {
            try {
                personelDuzenle($_POST['personel_id'], $_POST['ad'], $_POST['soyad'], $_POST['notlar']);
                $basari = 'Personel bilgileri güncellendi.';
            } catch (Exception $e) {
                $hata = $e->getMessage();
            }
        } elseif (isset($_POST['personel_sil'])) {
            try {
                personelSil($_POST['personel_id']);
                $basari = 'Personel silindi.';
            } catch (Exception $e) {
                $hata = $e->getMessage();
            }
        }
    }
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
                <?php if (in_array($_SESSION['rol'], ['yonetici', 'admin'])): ?>
                    <a href="personel.php" class="active"><i class="fas fa-users"></i> Personel Yönetimi</a>
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

        <!-- Personel Listesi -->
        <div class="personel-liste">
            <h2>Personel Listesi</h2>
            <div class="tablo-container">
                <table>
                    <thead>
                        <tr>
                            <th>Ad Soyad</th>
                            <th>Toplam Vardiya</th>
                            <th>Son Vardiya</th>
                            <th>Notlar</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $personeller = tumPersonelleriGetir();
                        foreach ($personeller as $personel):
                            $vardiyaBilgisi = personelVardiyaBilgisiGetir($personel['id']);
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($personel['ad'] . ' ' . $personel['soyad']); ?></td>
                                <td><?php echo $vardiyaBilgisi['toplam_vardiya']; ?></td>
                                <td><?php echo $vardiyaBilgisi['son_vardiya'] ? date('d.m.Y', strtotime($vardiyaBilgisi['son_vardiya'])) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($personel['notlar'] ?? ''); ?></td>
                                <td>
                                    <button onclick="personelDuzenleModal('<?php echo $personel['id']; ?>')" class="btn-duzenle">Düzenle</button>
                                    <button onclick="personelSilOnay('<?php echo $personel['id']; ?>')" class="btn-sil">Sil</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Personel Düzenleme Modal -->
    <div id="personelModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Personel Düzenle</h2>
            <form method="POST" id="personelForm">
                <input type="hidden" name="personel_id" id="duzenle_personel_id">
                <div class="form-group">
                    <label>Ad:</label>
                    <input type="text" name="ad" id="duzenle_ad" required>
                </div>
                <div class="form-group">
                    <label>Soyad:</label>
                    <input type="text" name="soyad" id="duzenle_soyad" required>
                </div>
                <div class="form-group">
                    <label>Notlar:</label>
                    <textarea name="notlar" id="duzenle_notlar" rows="4"></textarea>
                </div>
                <button type="submit" name="personel_duzenle" class="submit-btn">Kaydet</button>
            </form>
        </div>
    </div>

    <!-- Personel Silme Form -->
    <form id="silForm" method="POST" style="display: none;">
        <input type="hidden" name="personel_id" id="sil_personel_id">
        <input type="hidden" name="personel_sil" value="1">
    </form>

    <script>
        function personelDuzenleModal(personelId) {
            var personeller = <?php echo json_encode($personeller); ?>;
            var personel = personeller.find(p => p.id === personelId);

            document.getElementById('duzenle_personel_id').value = personel.id;
            document.getElementById('duzenle_ad').value = personel.ad;
            document.getElementById('duzenle_soyad').value = personel.soyad;
            document.getElementById('duzenle_notlar').value = personel.notlar || '';

            document.getElementById('personelModal').style.display = 'block';
        }

        function personelSilOnay(personelId) {
            if (confirm('Bu personeli silmek istediğinizden emin misiniz?')) {
                document.getElementById('sil_personel_id').value = personelId;
                document.getElementById('silForm').submit();
            }
        }

        // Modal kapatma
        document.querySelector('.close').onclick = function() {
            document.getElementById('personelModal').style.display = 'none';
        }

        window.onclick = function(event) {
            var modal = document.getElementById('personelModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>

</html>