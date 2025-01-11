<?php
/**
 * Rapor sınıfı - Raporlama işlemlerini yönetir
 * PHP 7.4+
 */
class Rapor
{
    private $db;
    private $islemLog;
    private static $instance = null;

    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->islemLog = IslemLog::getInstance();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Aylık çalışma saatleri raporu
     */
    public function aylikCalisma($ay, $yil)
    {
        $baslangicTimestamp = mktime(0, 0, 0, $ay, 1, $yil);
        $bitisTimestamp = mktime(23, 59, 59, $ay + 1, 0, $yil);

        // Personel listesini al
        $sql = "SELECT id, CONCAT(ad, ' ', soyad) as personel FROM personel ORDER BY ad, soyad";
        $personeller = $this->db->fetchAll($sql);

        // Vardiya türlerini al
        $sql = "SELECT id, etiket, baslangic, bitis FROM vardiya_turleri ORDER BY id";
        $vardiyaTurleri = $this->db->fetchAll($sql);

        $rapor = [];
        foreach ($personeller as $personel) {
            $vardiyalar = [];
            foreach ($vardiyaTurleri as $vardiya) {
                $vardiyalar[$vardiya['id']] = 0;
            }

            $rapor[$personel['id']] = [
                'personel' => $personel['personel'],
                'toplam_saat' => 0,
                'vardiyalar' => $vardiyalar
            ];
        }

        // Vardiyaları al ve hesapla
        $sql = "SELECT v.personel_id, v.vardiya_turu, vt.baslangic, vt.bitis 
                FROM vardiyalar v 
                LEFT JOIN vardiya_turleri vt ON v.vardiya_turu = vt.id 
                WHERE v.tarih BETWEEN ? AND ?";
        
        $vardiyalar = $this->db->fetchAll($sql, [$baslangicTimestamp, $bitisTimestamp]);

        foreach ($vardiyalar as $vardiya) {
            $baslangic = strtotime($vardiya['baslangic']);
            $bitis = strtotime($vardiya['bitis']);
            if ($bitis < $baslangic) {
                $bitis = strtotime('+1 day', $bitis);
            }
            $sure = ($bitis - $baslangic) / 3600;

            $rapor[$vardiya['personel_id']]['toplam_saat'] += $sure;
            $rapor[$vardiya['personel_id']]['vardiyalar'][$vardiya['vardiya_turu']]++;
        }

        return $rapor;
    }

    /**
     * Excel raporu oluştur
     */
    public function excelOlustur($ay, $yil)
    {
        $rapor = $this->aylikCalisma($ay, $yil);
        
        // Vardiya türlerini al
        $sql = "SELECT id, etiket FROM vardiya_turleri ORDER BY id";
        $vardiyaTurleri = $this->db->fetchAll($sql);

        // Başlık satırı
        $basliklar = ['Personel', 'Toplam Saat'];
        foreach ($vardiyaTurleri as $vardiya) {
            $basliklar[] = $vardiya['etiket'] . ' Vardiyası';
        }
        $csv = implode(',', array_map([$this, 'strPutCsv'], $basliklar)) . "\n";

        // Veri satırları
        foreach ($rapor as $personelRapor) {
            $satir = [
                $this->strPutCsv($personelRapor['personel']),
                number_format($personelRapor['toplam_saat'], 2)
            ];
            foreach ($vardiyaTurleri as $vardiya) {
                $satir[] = $personelRapor['vardiyalar'][$vardiya['id']];
            }
            $csv .= implode(',', $satir) . "\n";
        }

        $this->islemLog->logKaydet('excel_rapor', "Excel raporu oluşturuldu: $ay/$yil");
        return $csv;
    }

    /**
     * CSV için özel karakter düzenleme
     */
    private function strPutCsv($str)
    {
        $str = str_replace('"', '""', $str);
        if (strpbrk($str, ",\"\r\n") !== false) {
            $str = '"' . $str . '"';
        }
        return $str;
    }

    /**
     * PDF raporu için HTML oluştur
     */
    public function pdfIcinHtmlOlustur($ay, $yil)
    {
        $rapor = $this->aylikCalisma($ay, $yil);
        
        // Vardiya türlerini al
        $sql = "SELECT id, etiket, renk FROM vardiya_turleri ORDER BY id";
        $vardiyaTurleri = $this->db->fetchAll($sql);
        
        $ayAdi = date('F Y', mktime(0, 0, 0, $ay, 1, $yil));

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

            foreach ($vardiyaTurleri as $vardiya) {
                $html .= sprintf(
                    '<td style="text-align: center; background-color: %s">%d</td>',
                    $this->renkParlaklikAyarla($vardiya['renk'], 0.9),
                    $personelRapor['vardiyalar'][$vardiya['id']]
                );
            }

            $html .= '</tr>';
        }

        $html .= '</table>';
        
        $this->islemLog->logKaydet('pdf_rapor', "PDF raporu oluşturuldu: $ay/$yil");
        return $html;
    }

    /**
     * Renk parlaklığını ayarla
     */
    private function renkParlaklikAyarla($hex, $factor)
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
     */
    public function vardiyaRaporuGetir($baslangic_tarihi, $bitis_tarihi, $personel_id = null, $vardiya_turu = null)
    {
        $params = [$baslangic_tarihi, $bitis_tarihi];
        $where = "v.tarih BETWEEN ? AND ?";
        
        if ($personel_id !== null) {
            $where .= " AND v.personel_id = ?";
            $params[] = $personel_id;
        }
        
        if ($vardiya_turu !== null) {
            $where .= " AND v.vardiya_turu = ?";
            $params[] = $vardiya_turu;
        }

        // Vardiyaları getir
        $sql = "SELECT v.*, vt.etiket as vardiya_turu_adi, vt.baslangic, vt.bitis,
                p.ad, p.soyad, vt.renk
                FROM vardiyalar v 
                LEFT JOIN vardiya_turleri vt ON v.vardiya_turu = vt.id
                LEFT JOIN personel p ON v.personel_id = p.id
                WHERE $where
                ORDER BY v.tarih ASC";
                
        $vardiyalar = $this->db->fetchAll($sql, $params);

        // Vardiya dağılımını hesapla
        $vardiyaDagilimi = [];
        $gunlukDagilim = [];
        $toplamSaat = 0;
        $personelIds = [];

        foreach ($vardiyalar as $vardiya) {
            // Vardiya türü dağılımı
            if (!isset($vardiyaDagilimi[$vardiya['vardiya_turu']])) {
                $vardiyaDagilimi[$vardiya['vardiya_turu']] = [
                    'etiket' => $vardiya['vardiya_turu_adi'],
                    'sayi' => 0
                ];
            }
            $vardiyaDagilimi[$vardiya['vardiya_turu']]['sayi']++;

            // Günlük dağılım
            $tarih = date('Y-m-d', $vardiya['tarih']);
            if (!isset($gunlukDagilim[$tarih])) {
                $gunlukDagilim[$tarih] = [
                    'tarih' => date('d.m.Y', $vardiya['tarih']),
                    'sayi' => 0
                ];
            }
            $gunlukDagilim[$tarih]['sayi']++;

            // Toplam saat hesapla
            $baslangic = strtotime($vardiya['baslangic']);
            $bitis = strtotime($vardiya['bitis']);
            if ($bitis < $baslangic) {
                $bitis = strtotime('+1 day', $bitis);
            }
            $toplamSaat += ($bitis - $baslangic) / 3600;

            // Personel sayısı için
            $personelIds[$vardiya['personel_id']] = true;
        }

        // Detaylı vardiya listesi
        $detayliVardiyalar = array_map(function($vardiya) {
            return [
                'tarih' => $vardiya['tarih'],
                'personel_adi' => $vardiya['ad'] . ' ' . $vardiya['soyad'],
                'vardiya_turu' => $vardiya['vardiya_turu_adi'],
                'baslangic' => $vardiya['baslangic'],
                'bitis' => $vardiya['bitis'],
                'durum' => $vardiya['durum'] ?? 'Normal'
            ];
        }, $vardiyalar);

        $this->islemLog->logKaydet('vardiya_rapor', "Vardiya raporu oluşturuldu: " . date('d.m.Y', $baslangic_tarihi) . " - " . date('d.m.Y', $bitis_tarihi));

        return [
            'toplam_vardiya' => count($vardiyalar),
            'toplam_personel' => count($personelIds),
            'toplam_saat' => round($toplamSaat),
            'vardiya_dagilimi' => array_values($vardiyaDagilimi),
            'gunluk_dagilim' => array_values($gunlukDagilim),
            'vardiyalar' => $detayliVardiyalar
        ];
    }
}
