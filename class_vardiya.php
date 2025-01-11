<?php
/**
 * Vardiya sınıfı - Vardiya işlemlerini yönetir
 * PHP 7.4+
 */
class Vardiya
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
     * Vardiya silme
     */
    public function sil($vardiyaId)
    {
        // Vardiya kontrolü
        $sql = "SELECT v.*, p.ad, p.soyad 
                FROM vardiyalar v 
                LEFT JOIN personel p ON v.personel_id = p.id 
                WHERE v.id = ?";
        $vardiya = $this->db->fetch($sql, [$vardiyaId]);
        
        if (!$vardiya) {
            throw new Exception('Vardiya bulunamadı.');
        }

        // Vardiyayı sil
        $sql = "DELETE FROM vardiyalar WHERE id = ?";
        $this->db->query($sql, [$vardiyaId]);

        $this->islemLog->logKaydet('vardiya_sil', "Vardiya silindi: {$vardiya['ad']} {$vardiya['soyad']} - " . date('d.m.Y', $vardiya['tarih']));
    }

    /**
     * Vardiya düzenleme
     */
    public function duzenle($vardiyaId, $personelId, $tarih, $vardiyaTuru, $notlar = '')
    {
        // Vardiya çakışması kontrolü
        if ($this->cakismaVarMi($personelId, $tarih, $vardiyaTuru, $vardiyaId)) {
            throw new Exception('Bu personelin seçilen tarihte başka bir vardiyası bulunuyor.');
        }

        // Ardışık çalışma günlerini kontrol et
        if (!$this->ardisikCalismaGunleriniKontrolEt($personelId, $tarih)) {
            throw new Exception('Bu personel 6 gün üst üste çalıştığı için bu tarihe vardiya eklenemez. En az 1 gün izin kullanması gerekiyor.');
        }

        // Tarihi timestamp'e çevir
        if (!is_numeric($tarih)) {
            $tarih = strtotime($tarih);
        }

        // Vardiya kontrolü
        $sql = "SELECT * FROM vardiyalar WHERE id = ?";
        $vardiya = $this->db->fetch($sql, [$vardiyaId]);
        
        if (!$vardiya) {
            throw new Exception('Vardiya bulunamadı.');
        }

        // Vardiyayı güncelle
        $sql = "UPDATE vardiyalar SET 
                personel_id = ?, 
                tarih = ?, 
                vardiya_turu = ?, 
                notlar = ? 
                WHERE id = ?";
                
        $params = [
            $personelId,
            $tarih,
            $vardiyaTuru,
            $notlar,
            $vardiyaId
        ];

        $this->db->query($sql, $params);

        $this->islemLog->logKaydet('vardiya_duzenle', "Vardiya düzenlendi: ID: $vardiyaId, Personel: $personelId, Tarih: " . date('d.m.Y', $tarih));
    }

    /**
     * Vardiya çakışması kontrolü
     */
    public function cakismaVarMi($personelId, $tarih, $vardiyaTuru, $haricVardiyaId = null)
    {
        // Tarihi timestamp'e çevir
        if (!is_numeric($tarih)) {
            $tarih = strtotime($tarih);
        }

        $sql = "SELECT COUNT(*) as adet 
                FROM vardiyalar 
                WHERE personel_id = ? 
                AND DATE(FROM_UNIXTIME(tarih)) = DATE(FROM_UNIXTIME(?))";
        
        $params = [$personelId, $tarih];

        if ($haricVardiyaId !== null) {
            $sql .= " AND id != ?";
            $params[] = $haricVardiyaId;
        }

        $sonuc = $this->db->fetch($sql, $params);
        return $sonuc['adet'] > 0;
    }

    /**
     * Vardiya değişim talebi oluşturma
     */
    public function degisimTalebiOlustur($vardiyaId, $talepEdenPersonelId, $aciklama)
    {
        // Vardiya kontrolü
        $sql = "SELECT v.*, p.id as personel_id 
                FROM vardiyalar v 
                LEFT JOIN personel p ON v.personel_id = p.id 
                WHERE v.id = ?";
        $vardiya = $this->db->fetch($sql, [$vardiyaId]);

        if (!$vardiya) {
            throw new Exception('Vardiya bulunamadı.');
        }

        // Kendi vardiyası için talep oluşturamaz
        if ($vardiya['personel_id'] === $talepEdenPersonelId) {
            throw new Exception('Kendi vardiyanız için değişim talebi oluşturamazsınız.');
        }

        // Talebi ekle
        $sql = "INSERT INTO vardiya_talepleri 
                (vardiya_id, talep_eden_personel_id, durum, aciklama, olusturma_tarihi, guncelleme_tarihi) 
                VALUES (?, ?, 'beklemede', ?, ?, ?)";
                
        $params = [
            $vardiyaId,
            $talepEdenPersonelId,
            $aciklama,
            time(),
            time()
        ];

        $this->db->query($sql, $params);
        $talepId = $this->db->lastInsertId();

        $this->islemLog->logKaydet('vardiya_talep', "Vardiya değişim talebi oluşturuldu: Vardiya ID: $vardiyaId");
        return $talepId;
    }

    /**
     * Vardiya değişim talebini onayla/reddet
     */
    public function talepGuncelle($talepId, $durum, $yoneticiNotu = '')
    {
        // Talep kontrolü
        $sql = "SELECT * FROM vardiya_talepleri WHERE id = ?";
        $talep = $this->db->fetch($sql, [$talepId]);

        if (!$talep) {
            throw new Exception('Vardiya talebi bulunamadı.');
        }

        // Talebi güncelle
        $sql = "UPDATE vardiya_talepleri 
                SET durum = ?, 
                    yonetici_notu = ?, 
                    guncelleme_tarihi = ? 
                WHERE id = ?";
                
        $params = [
            $durum,
            $yoneticiNotu,
            time(),
            $talepId
        ];

        $this->db->query($sql, $params);

        $this->islemLog->logKaydet('vardiya_talebi_guncelle', "Vardiya talebi güncellendi: $talepId - Durum: $durum");
        return true;
    }

    /**
     * Vardiya detaylarını getir
     */
    public function detayGetir($vardiyaId)
    {
        $sql = "SELECT v.*, p.ad, p.soyad, vt.etiket as vardiya_turu_adi 
                FROM vardiyalar v 
                LEFT JOIN personel p ON v.personel_id = p.id 
                LEFT JOIN vardiya_turleri vt ON v.vardiya_turu = vt.id 
                WHERE v.id = ?";
                
        $vardiya = $this->db->fetch($sql, [$vardiyaId]);

        if (!$vardiya) {
            return null;
        }

        return [
            'id' => $vardiya['id'],
            'personel_id' => $vardiya['personel_id'],
            'personel_ad' => $vardiya['ad'] . ' ' . $vardiya['soyad'],
            'tarih' => $vardiya['tarih'],
            'vardiya_turu' => $vardiya['vardiya_turu'],
            'vardiya_turu_adi' => $vardiya['vardiya_turu_adi'],
            'notlar' => $vardiya['notlar'] ?? ''
        ];
    }

    /**
     * Ardışık çalışma günlerini kontrol et
     */
    private function ardisikCalismaGunleriniKontrolEt($personelId, $tarih)
    {
        // Tarihi timestamp'e çevir
        if (!is_numeric($tarih)) {
            $tarih = strtotime($tarih);
        }

        // Önceki 6 günü kontrol et
        $sql = "SELECT COUNT(DISTINCT DATE(FROM_UNIXTIME(tarih))) as ardisik_gun 
                FROM vardiyalar 
                WHERE personel_id = ? 
                AND tarih BETWEEN ? AND ?";

        // Seçilen tarihten önceki 6 gün
        $baslangic = strtotime('-6 days', $tarih);
        $bitis = $tarih;
        
        $oncekiGunler = $this->db->fetch($sql, [$personelId, $baslangic, $bitis]);
        if ($oncekiGunler['ardisik_gun'] >= 6) {
            return false;
        }

        // Seçilen tarihten sonraki 6 gün
        $baslangic = $tarih;
        $bitis = strtotime('+6 days', $tarih);
        
        $sonrakiGunler = $this->db->fetch($sql, [$personelId, $baslangic, $bitis]);
        if ($sonrakiGunler['ardisik_gun'] >= 6) {
            return false;
        }

        return true;
    }

    /**
     * Vardiya ekleme
     */
    public function ekle($personelId, $tarih, $vardiyaTuru, $notlar = '')
    {
        // Vardiya çakışması kontrolü
        if ($this->cakismaVarMi($personelId, $tarih, $vardiyaTuru)) {
            throw new Exception('Bu personelin seçilen tarihte başka bir vardiyası bulunuyor.');
        }

        // Ardışık çalışma günlerini kontrol et
        if (!$this->ardisikCalismaGunleriniKontrolEt($personelId, $tarih)) {
            throw new Exception('Bu personel 6 gün üst üste çalıştığı için bu tarihe vardiya eklenemez. En az 1 gün izin kullanması gerekiyor.');
        }

        // Tarihi timestamp'e çevir
        if (!is_numeric($tarih)) {
            $tarih = strtotime($tarih);
        }

        // Vardiyayı ekle
        $sql = "INSERT INTO vardiyalar (personel_id, tarih, vardiya_turu, notlar) VALUES (?, ?, ?, ?)";
        $params = [$personelId, $tarih, $vardiyaTuru, $notlar];

        $this->db->query($sql, $params);
        $vardiyaId = $this->db->lastInsertId();

        $this->islemLog->logKaydet('vardiya_ekle', "Yeni vardiya eklendi: Personel: $personelId, Tarih: " . date('d.m.Y', $tarih));
        return $vardiyaId;
    }

    /**
     * Günlük vardiyaları getir
     */
    public function gunlukVardiyalariGetir($tarih)
    {
        // Tarihi timestamp'e çevir
        if (!is_numeric($tarih)) {
            $tarih = strtotime($tarih);
        }

        $sql = "SELECT v.*, p.ad, p.soyad, vt.etiket as vardiya_turu_adi 
                FROM vardiyalar v 
                LEFT JOIN personel p ON v.personel_id = p.id 
                LEFT JOIN vardiya_turleri vt ON v.vardiya_turu = vt.id 
                WHERE DATE(FROM_UNIXTIME(v.tarih)) = DATE(FROM_UNIXTIME(?))
                ORDER BY vt.baslangic ASC";

        $vardiyalar = $this->db->fetchAll($sql, [$tarih]);

        return array_map(function($vardiya) {
            return [
                'id' => $vardiya['id'],
                'personel_id' => $vardiya['personel_id'],
                'personel_ad' => $vardiya['ad'] . ' ' . $vardiya['soyad'],
                'vardiya_turu' => $vardiya['vardiya_turu'],
                'vardiya_turu_adi' => $vardiya['vardiya_turu_adi'],
                'notlar' => $vardiya['notlar'] ?? ''
            ];
        }, $vardiyalar);
    }

    /**
     * Vardiya saatlerini hesapla
     */
    public function saatleriHesapla($vardiyaTuru)
    {
        $sql = "SELECT * FROM vardiya_turleri WHERE id = ?";
        $vardiya = $this->db->fetch($sql, [$vardiyaTuru]);

        if (!$vardiya) {
            throw new Exception('Vardiya türü bulunamadı.');
        }

        $baslangic = strtotime($vardiya['baslangic']);
        $bitis = strtotime($vardiya['bitis']);

        // Eğer bitiş saati başlangıç saatinden küçükse ertesi güne geçiyor demektir
        if ($bitis < $baslangic) {
            $bitis = strtotime('+1 day', $bitis);
        }

        $sure = ($bitis - $baslangic) / 3600; // Saat cinsinden süre

        return [
            'baslangic' => $baslangic,
            'bitis' => $bitis,
            'sure' => $sure
        ];
    }

    /**
     * Vardiya türü dağılımını getir
     */
    public function turuDagilimi($baslangicTarih, $bitisTarih)
    {
        $sql = "SELECT vt.id, vt.etiket, COUNT(v.id) as sayi 
                FROM vardiya_turleri vt 
                LEFT JOIN vardiyalar v ON v.vardiya_turu = vt.id 
                AND v.tarih BETWEEN ? AND ? 
                GROUP BY vt.id, vt.etiket 
                ORDER BY vt.id";

        return $this->db->fetchAll($sql, [strtotime($baslangicTarih), strtotime($bitisTarih)]);
    }

    /**
     * Akıllı vardiya önerisi oluştur
     */
    public function akilliOneriOlustur($tarih, $vardiyaTuru)
    {
        // Tarihi timestamp'e çevir
        if (!is_numeric($tarih)) {
            $tarih = strtotime($tarih);
        }

        // Son 30 gündeki vardiya sayılarını hesapla
        $sql = "SELECT p.id, p.ad, p.soyad,
                COUNT(v.id) as son_otuz_gun_vardiya,
                MAX(v.tarih) as son_calisma,
                pt.tercih_puani
                FROM personel p
                LEFT JOIN vardiyalar v ON v.personel_id = p.id 
                AND v.tarih BETWEEN ? AND ?
                LEFT JOIN personel_tercihleri pt ON pt.personel_id = p.id 
                AND pt.vardiya_turu = ?
                WHERE NOT EXISTS (
                    SELECT 1 FROM vardiyalar v2 
                    WHERE v2.personel_id = p.id 
                    AND DATE(FROM_UNIXTIME(v2.tarih)) = DATE(FROM_UNIXTIME(?))
                )
                AND NOT EXISTS (
                    SELECT 1 FROM vardiyalar v3 
                    WHERE v3.personel_id = p.id 
                    GROUP BY DATE(FROM_UNIXTIME(v3.tarih))
                    HAVING COUNT(*) >= 6
                )
                GROUP BY p.id, p.ad, p.soyad, pt.tercih_puani
                ORDER BY son_otuz_gun_vardiya ASC, son_calisma ASC, pt.tercih_puani DESC
                LIMIT 5";

        $params = [
            strtotime('-30 days', $tarih),
            $tarih,
            $vardiyaTuru,
            $tarih
        ];

        $oneriler = $this->db->fetchAll($sql, $params);

        return array_map(function($oneri) use ($tarih) {
            $puan = 100;
            
            // Vardiya sayısına göre puan düşür
            $puan -= ($oneri['son_otuz_gun_vardiya'] ?? 0) * 2;
            
            // Tercihe göre puan ekle
            $puan += ($oneri['tercih_puani'] ?? 0) * 10;
            
            // Son çalışma tarihine göre puan ekle
            if ($oneri['son_calisma']) {
                $gunFarki = floor(($tarih - $oneri['son_calisma']) / (60 * 60 * 24));
                $puan += $gunFarki * 5;
            }

            return [
                'personel_id' => $oneri['id'],
                'ad_soyad' => $oneri['ad'] . ' ' . $oneri['soyad'],
                'puan' => max(0, $puan)
            ];
        }, $oneriler);
    }

    /**
     * Detaylı vardiya çakışması kontrolü
     */
    public function cakismaDetayliKontrol($personelId, $baslangicZamani, $bitisZamani, $haricVardiyaId = null)
    {
        $sql = "SELECT v.*, vt.etiket, vt.baslangic, vt.bitis
                FROM vardiyalar v
                LEFT JOIN vardiya_turleri vt ON v.vardiya_turu = vt.id
                WHERE v.personel_id = ?
                AND DATE(FROM_UNIXTIME(v.tarih)) = DATE(FROM_UNIXTIME(?))";

        $params = [$personelId, $baslangicZamani];

        if ($haricVardiyaId !== null) {
            $sql .= " AND v.id != ?";
            $params[] = $haricVardiyaId;
        }

        $vardiyalar = $this->db->fetchAll($sql, $params);

        foreach ($vardiyalar as $vardiya) {
            $vardiyaBaslangic = strtotime(date('Y-m-d', $vardiya['tarih']) . ' ' . $vardiya['baslangic']);
            $vardiyaBitis = strtotime(date('Y-m-d', $vardiya['tarih']) . ' ' . $vardiya['bitis']);

            if ($vardiyaBitis < $vardiyaBaslangic) {
                $vardiyaBitis = strtotime('+1 day', $vardiyaBitis);
            }

            // Çakışma kontrolü
            if (
                ($baslangicZamani >= $vardiyaBaslangic && $baslangicZamani < $vardiyaBitis) ||
                ($bitisZamani > $vardiyaBaslangic && $bitisZamani <= $vardiyaBitis) ||
                ($baslangicZamani <= $vardiyaBaslangic && $bitisZamani >= $vardiyaBitis)
            ) {
                return [
                    'cakisma_var' => true,
                    'vardiya' => [
                        'id' => $vardiya['id'],
                        'baslangic' => date('Y-m-d H:i', $vardiyaBaslangic),
                        'bitis' => date('Y-m-d H:i', $vardiyaBitis),
                        'tur' => $vardiya['etiket']
                    ]
                ];
            }
        }

        return ['cakisma_var' => false];
    }

    /**
     * Vardiya süresi kontrolü
     */
    public function suresiKontrol($vardiyaTuru, $personelId, $tarih)
    {
        // Tarihi timestamp'e çevir
        if (!is_numeric($tarih)) {
            $tarih = strtotime($tarih);
        }

        // Vardiya türü bilgilerini al
        $sql = "SELECT * FROM vardiya_turleri WHERE id = ?";
        $tur = $this->db->fetch($sql, [$vardiyaTuru]);

        if (!$tur) {
            throw new Exception('Vardiya türü bulunamadı.');
        }

        $baslangicZamani = strtotime(date('Y-m-d', $tarih) . ' ' . $tur['baslangic']);
        $bitisZamani = strtotime(date('Y-m-d', $tarih) . ' ' . $tur['bitis']);

        if ($bitisZamani < $baslangicZamani) {
            $bitisZamani = strtotime('+1 day', $bitisZamani);
        }

        // Önceki ve sonraki vardiyalarla minimum süre kontrolü
        $minimumSure = 11 * 3600; // 11 saat (saniye cinsinden)

        $sql = "SELECT v.*, vt.baslangic, vt.bitis
                FROM vardiyalar v
                LEFT JOIN vardiya_turleri vt ON v.vardiya_turu = vt.id
                WHERE v.personel_id = ?
                AND DATE(FROM_UNIXTIME(v.tarih)) BETWEEN DATE(FROM_UNIXTIME(?)) AND DATE(FROM_UNIXTIME(?))";

        $params = [
            $personelId,
            strtotime('-1 day', $tarih),
            strtotime('+1 day', $tarih)
        ];

        $vardiyalar = $this->db->fetchAll($sql, $params);

        foreach ($vardiyalar as $vardiya) {
            $digerBaslangic = strtotime(date('Y-m-d', $vardiya['tarih']) . ' ' . $vardiya['baslangic']);
            $digerBitis = strtotime(date('Y-m-d', $vardiya['tarih']) . ' ' . $vardiya['bitis']);

            if ($digerBitis < $digerBaslangic) {
                $digerBitis = strtotime('+1 day', $digerBitis);
            }

            // Önceki vardiya kontrolü
            if ($digerBitis <= $baslangicZamani) {
                $araSure = $baslangicZamani - $digerBitis;
                if ($araSure < $minimumSure) {
                    return [
                        'uygun' => false,
                        'mesaj' => 'İki vardiya arası en az 11 saat olmalıdır.',
                        'onceki_vardiya' => [
                            'id' => $vardiya['id'],
                            'bitis' => date('Y-m-d H:i', $digerBitis)
                        ]
                    ];
                }
            }

            // Sonraki vardiya kontrolü
            if ($digerBaslangic >= $bitisZamani) {
                $araSure = $digerBaslangic - $bitisZamani;
                if ($araSure < $minimumSure) {
                    return [
                        'uygun' => false,
                        'mesaj' => 'İki vardiya arası en az 11 saat olmalıdır.',
                        'sonraki_vardiya' => [
                            'id' => $vardiya['id'],
                            'baslangic' => date('Y-m-d H:i', $digerBaslangic)
                        ]
                    ];
                }
            }
        }

        return ['uygun' => true];
    }
}
