<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İstatistikler ve Raporlar - Vardiya Sistemi</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <?php
    require_once 'functions.php';

    // Tarih parametreleri
    $ay = isset($_GET['ay']) ? intval($_GET['ay']) : intval(date('m'));
    $yil = isset($_GET['yil']) ? intval($_GET['yil']) : intval(date('Y'));

    // Rapor verilerini al
    $baslangicTarih = sprintf('%04d-%02d-01', $yil, $ay);
    $bitisTarih = date('Y-m-t', strtotime($baslangicTarih));

    $aylikRapor = aylikCalismaRaporu($ay, $yil);
    $vardiyaDagilimi = vardiyaTuruDagilimi($baslangicTarih, $bitisTarih);
    $personelDagilimi = personelVardiyaDagilimi($baslangicTarih, $bitisTarih);

    // Export işlemleri
    if (isset($_POST['export'])) {
        $format = $_POST['format'];
        $dosyaAdi = sprintf('vardiya_raporu_%04d_%02d', $yil, $ay);

        if ($format === 'excel') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $dosyaAdi . '.csv');
            echo excelRaporuOlustur($ay, $yil);
            exit;
        } elseif ($format === 'pdf') {
            header('Content-Type: text/html; charset=utf-8');
            echo pdfRaporuIcinHtmlOlustur($ay, $yil);
            exit;
        }
    }
    ?>

    <div class="container">
        <div class="header-nav">
            <h1>İstatistikler ve Raporlar</h1>
            <div>
                <a href="index.php" class="nav-btn">Vardiya Takvimine Dön</a>
                <a href="personel.php" class="nav-btn">Personel Yönetimi</a>
                <a href="izin.php" class="nav-btn">İzin Yönetimi</a>
            </div>
        </div>

        <!-- Tarih Seçimi -->
        <div class="form-section">
            <form method="GET" class="inline-form">
                <div class="form-group">
                    <label>Ay/Yıl Seçin:</label>
                    <select name="ay">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i === $ay ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <select name="yil">
                        <?php for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i === $yil ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit">Göster</button>
                </div>
            </form>
        </div>

        <!-- Export Seçenekleri -->
        <div class="form-section">
            <form method="POST" class="inline-form">
                <div class="form-group">
                    <label>Rapor İndir:</label>
                    <select name="format">
                        <option value="excel">Excel (CSV)</option>
                        <option value="pdf">PDF</option>
                    </select>
                    <button type="submit" name="export">İndir</button>
                </div>
            </form>
        </div>

        <!-- Aylık Çalışma Saatleri -->
        <div class="rapor-section">
            <h2>Aylık Çalışma Saatleri</h2>
            <div class="tablo-container">
                <table>
                    <thead>
                        <tr>
                            <th>Personel</th>
                            <th>Toplam Saat</th>
                            <?php
                            $vardiyaTurleri = vardiyaTurleriniGetir();
                            foreach ($vardiyaTurleri as $id => $vardiya) {
                                echo '<th>' . htmlspecialchars($vardiya['etiket']) . '</th>';
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($aylikRapor as $personelRapor): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($personelRapor['personel']); ?></td>
                                <td><?php echo $personelRapor['toplam_saat']; ?> saat</td>
                                <?php
                                foreach ($vardiyaTurleri as $id => $vardiya) {
                                    echo '<td>' . ($personelRapor['vardiyalar'][$id] ?? 0) . '</td>';
                                }
                                ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Grafikler -->
        <div class="grafik-container">
            <!-- Vardiya Türü Dağılımı -->
            <div class="grafik-section">
                <h2>Vardiya Türü Dağılımı</h2>
                <canvas id="vardiyaDagilimGrafik"></canvas>
            </div>

            <!-- Personel Vardiya Dağılımı -->
            <div class="grafik-section">
                <h2>Personel Vardiya Dağılımı</h2>
                <canvas id="personelDagilimGrafik"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Vardiya türlerini PHP'den al
        const vardiyaTurleri = <?php echo json_encode($vardiyaTurleri); ?>;
        const vardiyaEtiketleri = Object.values(vardiyaTurleri).map(v => v.etiket);
        const vardiyaRenkleri = Object.values(vardiyaTurleri).map(v => v.renk || '#' + Math.floor(Math.random() * 16777215).toString(16));

        // Vardiya Türü Dağılımı Grafiği
        new Chart(document.getElementById('vardiyaDagilimGrafik'), {
            type: 'pie',
            data: {
                labels: vardiyaEtiketleri,
                datasets: [{
                    data: [
                        <?php
                        $data = [];
                        foreach ($vardiyaTurleri as $id => $vardiya) {
                            $data[] = $vardiyaDagilimi[$id] ?? 0;
                        }
                        echo implode(',', $data);
                        ?>
                    ],
                    backgroundColor: vardiyaRenkleri
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

        // Personel Vardiya Dağılımı Grafiği
        new Chart(document.getElementById('personelDagilimGrafik'), {
            type: 'bar',
            data: {
                labels: [<?php
                            $labels = [];
                            foreach ($personelDagilimi as $personel) {
                                $labels[] = "'" . addslashes($personel['personel']) . "'";
                            }
                            echo implode(',', $labels);
                            ?>],
                datasets: [
                    <?php
                    $datasetStr = [];
                    foreach ($vardiyaTurleri as $id => $vardiya) {
                        $data = [];
                        foreach ($personelDagilimi as $personel) {
                            $data[] = $personel['vardiyalar'][$id] ?? 0;
                        }
                        $datasetStr[] = "{
                            label: '" . addslashes($vardiya['etiket']) . "',
                            data: [" . implode(',', $data) . "],
                            backgroundColor: '" . ($vardiya['renk'] ?? '#' . substr(md5($id), 0, 6)) . "'
                        }";
                    }
                    echo implode(',', $datasetStr);
                    ?>
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>

</html>