<?php

// Aylık çalışma saatleri raporu
function aylikCalismaRaporu($ay, $yil)
{
    $data = veriOku();
    $rapor = [];
    $vardiyaTurleri = vardiyaTurleriniGetir();

    foreach ($data['personel'] as $personel) {
        $vardiyalar = [];
        foreach ($vardiyaTurleri as $id => $vardiya) {
            $vardiyalar[$id] = 0;
        }

        $rapor[$personel['id']] = [
            'personel' => $personel['ad'] . ' ' . $personel['soyad'],
            'toplam_saat' => 0,
            'vardiyalar' => $vardiyalar
        ];
    }

    $baslangicTimestamp = strtotime(sprintf('%04d-%02d-01', $yil, $ay));
    $bitisTimestamp = strtotime(date('Y-m-t', $baslangicTimestamp));

    foreach ($data['vardiyalar'] as $vardiya) {
        $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);

        if ($vardiyaTarihi >= $baslangicTimestamp && $vardiyaTarihi <= $bitisTimestamp) {
            $saatler = vardiyaSaatleriHesapla($vardiya['vardiya_turu']);
            $rapor[$vardiya['personel_id']]['toplam_saat'] += $saatler['sure'];
            $rapor[$vardiya['personel_id']]['vardiyalar'][$vardiya['vardiya_turu']]++;
        }
    }

    return $rapor;
}

// Excel raporu oluştur
function excelRaporuOlustur($ay, $yil)
{
    $rapor = aylikCalismaRaporu($ay, $yil);
    $vardiyaTurleri = vardiyaTurleriniGetir();

    // Başlık satırı
    $basliklar = ['Personel', 'Toplam Saat'];
    foreach ($vardiyaTurleri as $id => $vardiya) {
        $basliklar[] = $vardiya['etiket'] . ' Vardiyası';
    }
    $csv = implode(',', array_map('str_putcsv', $basliklar)) . "\n";

    // Veri satırları
    foreach ($rapor as $personelRapor) {
        $satir = [
            str_putcsv($personelRapor['personel']),
            number_format($personelRapor['toplam_saat'], 2)
        ];
        foreach ($vardiyaTurleri as $id => $vardiya) {
            $satir[] = $personelRapor['vardiyalar'][$id];
        }
        $csv .= implode(',', $satir) . "\n";
    }

    return $csv;
}

// CSV için özel karakter düzenleme
function str_putcsv($str)
{
    $str = str_replace('"', '""', $str);
    if (strpbrk($str, ",\"\r\n") !== false) {
        $str = '"' . $str . '"';
    }
    return $str;
}

// PDF raporu için HTML oluştur
function pdfRaporuIcinHtmlOlustur($ay, $yil)
{
    $rapor = aylikCalismaRaporu($ay, $yil);
    $vardiyaTurleri = vardiyaTurleriniGetir();
    $ayAdi = tarihFormatla(mktime(0, 0, 0, $ay, 1, $yil), 'uzun');

    $html = '<h1>Aylık Çalışma Raporu - ' . $ayAdi . '</h1>';
    $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';

    // Başlık satırı
    $html .= '<tr><th>Personel</th><th>Toplam Saat</th>';
    foreach ($vardiyaTurleri as $vardiya) {
        $html .= sprintf(
            '<th style="background-color: %s">%s</th>',
            $vardiya['renk'],
            htmlspecialchars($vardiya['etiket']) . ' Vardiyası'
        );
    }
    $html .= '</tr>';

    // Veri satırları
    foreach ($rapor as $personelRapor) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($personelRapor['personel']) . '</td>';
        $html .= '<td>' . number_format($personelRapor['toplam_saat'], 2) . ' Saat</td>';

        foreach ($vardiyaTurleri as $id => $vardiya) {
            $html .= sprintf(
                '<td style="text-align: center; background-color: %s">%d</td>',
                adjustBrightness($vardiya['renk'], 0.9),
                $personelRapor['vardiyalar'][$id]
            );
        }

        $html .= '</tr>';
    }

    $html .= '</table>';
    return $html;
}

// Renk parlaklığını ayarla
function adjustBrightness($hex, $factor)
{
    $hex = ltrim($hex, '#');

    if (strlen($hex) == 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    $r = hexdec(substr($hex, 0, 2)) * $factor;
    $g = hexdec(substr($hex, 2, 2)) * $factor;
    $b = hexdec(substr($hex, 4, 2)) * $factor;

    $r = min(255, max(0, $r));
    $g = min(255, max(0, $g));
    $b = min(255, max(0, $b));

    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

/**
 * Belirtilen tarih aralığı için vardiya raporunu getirir
 * 
 * @param string $baslangic_tarihi Y-m-d formatında başlangıç tarihi
 * @param string $bitis_tarihi Y-m-d formatında bitiş tarihi
 * @param string|null $personel_id Belirli bir personel için filtreleme (opsiyonel)
 * @param string|null $vardiya_turu Belirli bir vardiya türü için filtreleme (opsiyonel)
 * @return array Rapor verileri
 * @throws Exception Hata durumunda
 */
function vardiyaRaporuGetir($baslangic_tarihi, $bitis_tarihi, $personel_id = null, $vardiya_turu = null)
{
    $data = veriOku();
    $vardiyalar = $data['vardiyalar'] ?? [];
    $personeller = $data['personel'] ?? [];
    $vardiyaTurleri = vardiyaTurleriniGetir();

    // Filtreleme
    $filtrelenmisVardiyalar = array_filter($vardiyalar, function ($vardiya) use ($baslangic_tarihi, $bitis_tarihi, $personel_id, $vardiya_turu) {
        if ($vardiya['tarih'] < $baslangic_tarihi || $vardiya['tarih'] > $bitis_tarihi) {
            return false;
        }
        if ($personel_id !== null && $vardiya['personel_id'] !== $personel_id) {
            return false;
        }
        if ($vardiya_turu !== null && $vardiya['vardiya_turu'] !== $vardiya_turu) {
            return false;
        }
        return true;
    });

    // Vardiya dağılımını hesapla
    $vardiyaDagilimi = [];
    foreach ($filtrelenmisVardiyalar as $vardiya) {
        $tur = $vardiya['vardiya_turu'];
        if (!isset($vardiyaDagilimi[$tur])) {
            $vardiyaDagilimi[$tur] = [
                'etiket' => $vardiyaTurleri[$tur]['etiket'],
                'sayi' => 0
            ];
        }
        $vardiyaDagilimi[$tur]['sayi']++;
    }

    // Günlük dağılımı hesapla
    $gunlukDagilim = [];
    $current = strtotime($baslangic_tarihi);
    $end = strtotime($bitis_tarihi);
    while ($current <= $end) {
        $tarih = date('Y-m-d', $current);
        $gunlukDagilim[$tarih] = [
            'tarih' => date('d.m.Y', $current),
            'sayi' => 0
        ];
        $current = strtotime('+1 day', $current);
    }

    foreach ($filtrelenmisVardiyalar as $vardiya) {
        $gunlukDagilim[$vardiya['tarih']]['sayi']++;
    }

    // Toplam saatleri hesapla
    $toplamSaat = 0;
    foreach ($filtrelenmisVardiyalar as $vardiya) {
        $tur = $vardiyaTurleri[$vardiya['vardiya_turu']];
        $baslangic = strtotime($tur['baslangic']);
        $bitis = strtotime($tur['bitis']);
        if ($bitis < $baslangic) {
            $bitis = strtotime('+1 day', $bitis);
        }
        $toplamSaat += ($bitis - $baslangic) / 3600;
    }

    // Detaylı vardiya listesi
    $detayliVardiyalar = [];
    foreach ($filtrelenmisVardiyalar as $vardiya) {
        $personel = array_filter($personeller, function ($p) use ($vardiya) {
            return $p['id'] === $vardiya['personel_id'];
        });
        $personel = reset($personel);
        $tur = $vardiyaTurleri[$vardiya['vardiya_turu']];

        $detayliVardiyalar[] = [
            'tarih' => $vardiya['tarih'],
            'personel_adi' => $personel['ad'] . ' ' . $personel['soyad'],
            'vardiya_turu' => $tur['etiket'],
            'baslangic' => $tur['baslangic'],
            'bitis' => $tur['bitis'],
            'durum' => $vardiya['durum'] ?? 'Normal'
        ];
    }

    // Sonuçları döndür
    return [
        'toplam_vardiya' => count($filtrelenmisVardiyalar),
        'toplam_personel' => count(array_unique(array_column($filtrelenmisVardiyalar, 'personel_id'))),
        'toplam_saat' => round($toplamSaat),
        'vardiya_dagilimi' => array_values($vardiyaDagilimi),
        'gunluk_dagilim' => array_values($gunlukDagilim),
        'vardiyalar' => $detayliVardiyalar
    ];
}
