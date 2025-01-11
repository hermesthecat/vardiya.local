<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vardiya Raporları - Vardiya Sistemi</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

    // Sadece yönetici ve admin erişebilir
    if (!in_array($_SESSION['rol'], ['yonetici', 'admin'])) {
        header('Location: index.php');
        exit;
    }

    $hata = '';
    $basari = '';

    // Rapor parametreleri
    $baslangic_tarihi = $_GET['baslangic_tarihi'] ?? date('Y-m-01');
    $bitis_tarihi = $_GET['bitis_tarihi'] ?? date('Y-m-t');
    $personel_id = $_GET['personel_id'] ?? null;
    $vardiya_turu = $_GET['vardiya_turu'] ?? null;

    // Rapor verilerini getir
    try {
        $rapor = vardiyaRaporuGetir($baslangic_tarihi, $bitis_tarihi, $personel_id, $vardiya_turu);
    } catch (Exception $e) {
        $hata = $e->getMessage();
    }
    ?>

    <div class="container">
        <h1>Vardiya Raporları</h1>

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

        <!-- Rapor Filtreleri -->
        <div class="section">
            <h2><i class="fas fa-filter"></i> Rapor Filtreleri</h2>
            <form method="GET" class="form-section">
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Başlangıç Tarihi:</label>
                    <input type="date" name="baslangic_tarihi" value="<?php echo $baslangic_tarihi; ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Bitiş Tarihi:</label>
                    <input type="date" name="bitis_tarihi" value="<?php echo $bitis_tarihi; ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-user"></i> Personel:</label>
                    <select name="personel_id">
                        <option value="">Tüm Personel</option>
                        <?php
                        $personeller = tumPersonelleriGetir();
                        foreach ($personeller as $p):
                            $selected = ($p['id'] === $personel_id) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($p['ad'] . ' ' . $p['soyad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Vardiya Türü:</label>
                    <select name="vardiya_turu">
                        <option value="">Tüm Vardiyalar</option>
                        <?php
                        $vardiyaTurleri = vardiyaTurleriniGetir();
                        foreach ($vardiyaTurleri as $id => $v):
                            $selected = ($id === $vardiya_turu) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $id; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($v['etiket']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-search"></i> Raporu Göster
                </button>
            </form>
        </div>

        <!-- Rapor Sonuçları -->
        <?php if (isset($rapor) && !$hata): ?>
            <div class="section">
                <h2><i class="fas fa-chart-bar"></i> Rapor Sonuçları</h2>
                
                <!-- Özet Bilgiler -->
                <div class="rapor-ozet">
                    <div class="ozet-kart">
                        <i class="fas fa-calendar-check"></i>
                        <h3>Toplam Vardiya</h3>
                        <p><?php echo $rapor['toplam_vardiya']; ?></p>
                    </div>
                    <div class="ozet-kart">
                        <i class="fas fa-users"></i>
                        <h3>Toplam Personel</h3>
                        <p><?php echo $rapor['toplam_personel']; ?></p>
                    </div>
                    <div class="ozet-kart">
                        <i class="fas fa-clock"></i>
                        <h3>Toplam Saat</h3>
                        <p><?php echo $rapor['toplam_saat']; ?></p>
                    </div>
                </div>

                <!-- Vardiya Dağılımı Grafiği -->
                <div class="grafik-container">
                    <div class="grafik-section">
                        <h3><i class="fas fa-chart-pie"></i> Vardiya Dağılımı</h3>
                        <canvas id="vardiyaDagilimi"></canvas>
                    </div>
                    <div class="grafik-section">
                        <h3><i class="fas fa-chart-line"></i> Günlük Vardiya Dağılımı</h3>
                        <canvas id="gunlukDagilim"></canvas>
                    </div>
                </div>

                <!-- Detaylı Tablo -->
                <div class="tablo-container">
                    <h3><i class="fas fa-table"></i> Detaylı Vardiya Listesi</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Personel</th>
                                <th>Vardiya</th>
                                <th>Saat</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rapor['vardiyalar'] as $vardiya): ?>
                                <tr>
                                    <td><?php echo date('d.m.Y', strtotime($vardiya['tarih'])); ?></td>
                                    <td><?php echo htmlspecialchars($vardiya['personel_adi']); ?></td>
                                    <td><?php echo htmlspecialchars($vardiya['vardiya_turu']); ?></td>
                                    <td><?php echo $vardiya['baslangic'] . ' - ' . $vardiya['bitis']; ?></td>
                                    <td><?php echo $vardiya['durum']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Rapor Dışa Aktarma -->
            <div class="section">
                <h2><i class="fas fa-file-export"></i> Raporu Dışa Aktar</h2>
                <div class="button-group">
                    <a href="rapor_export.php?format=excel&<?php echo http_build_query($_GET); ?>" class="btn-small">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="rapor_export.php?format=pdf&<?php echo http_build_query($_GET); ?>" class="btn-small">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="rapor_export.php?format=csv&<?php echo http_build_query($_GET); ?>" class="btn-small">
                        <i class="fas fa-file-csv"></i> CSV
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Vardiya Dağılımı Grafiği
        const vardiyaDagilimi = document.getElementById('vardiyaDagilimi');
        if (vardiyaDagilimi) {
            new Chart(vardiyaDagilimi, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_column($rapor['vardiya_dagilimi'], 'etiket')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($rapor['vardiya_dagilimi'], 'sayi')); ?>,
                        backgroundColor: [
                            '#4caf50',
                            '#2196f3',
                            '#9c27b0',
                            '#ff9800',
                            '#f44336'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Günlük Vardiya Dağılımı Grafiği
        const gunlukDagilim = document.getElementById('gunlukDagilim');
        if (gunlukDagilim) {
            new Chart(gunlukDagilim, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($rapor['gunluk_dagilim'], 'tarih')); ?>,
                    datasets: [{
                        label: 'Vardiya Sayısı',
                        data: <?php echo json_encode(array_column($rapor['gunluk_dagilim'], 'sayi')); ?>,
                        borderColor: '#4caf50',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>

</html>