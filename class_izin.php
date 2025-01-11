<?php

/**
 * Izin sınıfı - İzin işlemlerini yönetir
 * PHP 7.4+
 */
class Izin
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
     * İzin talebi oluşturma
     */
    public function talepOlustur($personelId, $baslangicTarihi, $bitisTarihi, $izinTuru, $aciklama = '')
    {
        // Tarihleri timestamp'e çevir
        $baslangicTimestamp = is_numeric($baslangicTarihi) ? $baslangicTarihi : strtotime($baslangicTarihi);
        $bitisTimestamp = is_numeric($bitisTarihi) ? $bitisTarihi : strtotime($bitisTarihi);

        // Tarih kontrolü
        if ($baslangicTimestamp > $bitisTimestamp) {
            throw new Exception('Bitiş tarihi başlangıç tarihinden önce olamaz.');
        }

        // Geçmiş tarih kontrolü
        if ($baslangicTimestamp < strtotime('today')) {
            throw new Exception('Geçmiş tarihli izin talebi oluşturamazsınız.');
        }

        // Personel kontrolü
        $sql = "SELECT * FROM personel WHERE id = ?";
        $personel = $this->db->fetch($sql, [$personelId]);
        if (!$personel) {
            throw new Exception('Personel bulunamadı.');
        }

        // Çakışma kontrolü
        $sql = "SELECT COUNT(*) as adet FROM izinler 
                WHERE personel_id = ? 
                AND ((baslangic_tarihi BETWEEN ? AND ?) 
                OR (bitis_tarihi BETWEEN ? AND ?))
                AND durum != 'reddedildi'";
        
        $params = [
            $personelId,
            $baslangicTimestamp,
            $bitisTimestamp,
            $baslangicTimestamp,
            $bitisTimestamp
        ];
        
        $cakisma = $this->db->fetch($sql, $params);
        if ($cakisma['adet'] > 0) {
            throw new Exception('Seçilen tarih aralığında başka bir izin talebi bulunmaktadır.');
        }

        // Vardiya kontrolü
        $sql = "SELECT COUNT(*) as adet FROM vardiyalar 
                WHERE personel_id = ? 
                AND tarih BETWEEN ? AND ?";
        
        $vardiya = $this->db->fetch($sql, [$personelId, $baslangicTimestamp, $bitisTimestamp]);
        if ($vardiya['adet'] > 0) {
            throw new Exception('Seçilen tarih aralığında vardiyalarınız bulunmaktadır.');
        }

        // İzin talebini kaydet
        $sql = "INSERT INTO izinler (personel_id, baslangic_tarihi, bitis_tarihi, izin_turu, aciklama, durum, olusturma_tarihi) 
                VALUES (?, ?, ?, ?, ?, 'beklemede', ?)";
        
        $params = [
            $personelId,
            $baslangicTimestamp,
            $bitisTimestamp,
            $izinTuru,
            $aciklama,
            time()
        ];

        $this->db->query($sql, $params);
        $izinId = $this->db->lastInsertId();

        $this->islemLog->logKaydet('izin_talebi', "İzin talebi oluşturuldu: Personel ID: $personelId, Başlangıç: " . date('d.m.Y', $baslangicTimestamp));
        return $izinId;
    }

    /**
     * İzin talebini güncelleme
     */
    public function talepGuncelle($izinId, $durum, $aciklama = '')
    {
        // İzin kontrolü
        $sql = "SELECT i.*, p.ad, p.soyad 
                FROM izinler i 
                LEFT JOIN personel p ON i.personel_id = p.id 
                WHERE i.id = ?";
        
        $izin = $this->db->fetch($sql, [$izinId]);
        if (!$izin) {
            throw new Exception('İzin talebi bulunamadı.');
        }

        if ($izin['durum'] !== 'beklemede') {
            throw new Exception('Sadece bekleyen izin talepleri güncellenebilir.');
        }

        // İzin durumunu güncelle
        $sql = "UPDATE izinler SET durum = ?, guncelleme_tarihi = ?, guncelleme_aciklama = ? WHERE id = ?";
        $this->db->query($sql, [$durum, time(), $aciklama, $izinId]);

        $this->islemLog->logKaydet(
            'izin_guncelle', 
            "İzin talebi güncellendi: {$izin['ad']} {$izin['soyad']}, Durum: $durum"
        );

        return true;
    }

    /**
     * Yıllık izin hakkı hesaplama
     */
    public function yillikIzinHakkiHesapla($personelId, $yil = null)
    {
        if ($yil === null) {
            $yil = date('Y');
        }

        // Personel bilgilerini al
        $sql = "SELECT ise_giris_tarihi FROM personel WHERE id = ?";
        $personel = $this->db->fetch($sql, [$personelId]);
        if (!$personel) {
            throw new Exception('Personel bulunamadı.');
        }

        $iseGirisTimestamp = $personel['ise_giris_tarihi'];
        $calismaYili = floor((time() - $iseGirisTimestamp) / (365 * 24 * 60 * 60));

        // Çalışma yılına göre izin hakkı
        $izinHakki = $calismaYili < 5 ? 14 : ($calismaYili < 15 ? 20 : 26);

        // Kullanılan izinleri hesapla
        $yilBaslangic = mktime(0, 0, 0, 1, 1, $yil);
        $yilBitis = mktime(23, 59, 59, 12, 31, $yil);

        $sql = "SELECT SUM(DATEDIFF(FROM_UNIXTIME(bitis_tarihi), FROM_UNIXTIME(baslangic_tarihi)) + 1) as toplam_gun 
                FROM izinler 
                WHERE personel_id = ? 
                AND izin_turu = 'yillik' 
                AND durum = 'onaylandi' 
                AND baslangic_tarihi BETWEEN ? AND ?";

        $kullanilan = $this->db->fetch($sql, [$personelId, $yilBaslangic, $yilBitis]);
        $kullanilanGun = $kullanilan['toplam_gun'] ?? 0;

        return [
            'toplam_hak' => $izinHakki,
            'kullanilan' => $kullanilanGun,
            'kalan' => $izinHakki - $kullanilanGun
        ];
    }

    /**
     * İzin türlerini getir
     */
    public function turleriniGetir()
    {
        return [
            'yillik' => [
                'id' => 'yillik',
                'ad' => 'Yıllık İzin',
                'max_gun' => 30,
                'ucretli' => true
            ],
            'dogum' => [
                'id' => 'dogum',
                'ad' => 'Doğum İzni',
                'max_gun' => 56,
                'ucretli' => true
            ],
            'olum' => [
                'id' => 'olum',
                'ad' => 'Ölüm İzni',
                'max_gun' => 3,
                'ucretli' => true
            ],
            'evlilik' => [
                'id' => 'evlilik',
                'ad' => 'Evlilik İzni',
                'max_gun' => 3,
                'ucretli' => true
            ],
            'ucretsiz' => [
                'id' => 'ucretsiz',
                'ad' => 'Ücretsiz İzin',
                'max_gun' => 90,
                'ucretli' => false
            ]
        ];
    }
}
