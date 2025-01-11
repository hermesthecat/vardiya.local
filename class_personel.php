<?php

/**
 * Personel sınıfı - Personel işlemlerini yönetir
 * PHP 7.4+
 */
class Personel
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
     * Tüm personelleri getir
     */
    public function tumPersonelleriGetir()
    {
        $sql = "SELECT * FROM personel ORDER BY ad, soyad";
        return $this->db->fetchAll($sql);
    }

    /**
     * Personel vardiya bilgisini getir
     */
    public function vardiyaBilgisiGetir($personelId)
    {
        $sql = "SELECT v.*, vt.etiket as vardiya_turu_adi, vt.renk 
                FROM vardiyalar v 
                LEFT JOIN vardiya_turleri vt ON v.vardiya_turu = vt.id 
                WHERE v.personel_id = ? 
                ORDER BY v.tarih DESC 
                LIMIT 10";

        return $this->db->fetchAll($sql, [$personelId]);
    }

    /**
     * Personel sil
     */
    public function sil($personelId)
    {
        // Personel kontrolü
        $sql = "SELECT * FROM personel WHERE id = ?";
        $personel = $this->db->fetch($sql, [$personelId]);
        if (!$personel) {
            throw new Exception('Personel bulunamadı.');
        }

        // Vardiya kontrolü
        $sql = "SELECT COUNT(*) as adet FROM vardiyalar WHERE personel_id = ? AND tarih > ?";
        $vardiya = $this->db->fetch($sql, [$personelId, time()]);
        if ($vardiya['adet'] > 0) {
            throw new Exception('Personelin gelecek vardiyaları bulunmaktadır. Önce vardiyaları silinmelidir.');
        }

        // İzin kontrolü
        $sql = "SELECT COUNT(*) as adet FROM izinler WHERE personel_id = ? AND bitis_tarihi > ?";
        $izin = $this->db->fetch($sql, [$personelId, time()]);
        if ($izin['adet'] > 0) {
            throw new Exception('Personelin aktif izinleri bulunmaktadır. Önce izinleri silinmelidir.');
        }

        // Personeli sil
        $sql = "DELETE FROM personel WHERE id = ?";
        $this->db->query($sql, [$personelId]);

        $this->islemLog->logKaydet('personel_sil', "Personel silindi: {$personel['ad']} {$personel['soyad']}");
        return true;
    }

    /**
     * Personel ekle
     */
    public function ekle($ad, $soyad, $email, $telefon = '', $yetki = 'personel')
    {
        // Email kontrolü
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Geçersiz e-posta formatı.');
        }

        // Email benzersizlik kontrolü
        $sql = "SELECT COUNT(*) as adet FROM personel WHERE email = ?";
        $kontrol = $this->db->fetch($sql, [$email]);
        if ($kontrol['adet'] > 0) {
            throw new Exception('Bu e-posta adresi zaten kullanılıyor.');
        }

        // Varsayılan şifre oluştur
        $sifre = substr(md5(uniqid()), 0, 8);
        $sifreHash = password_hash($sifre, PASSWORD_DEFAULT);

        // Personel ekle
        $sql = "INSERT INTO personel (ad, soyad, email, telefon, yetki, sifre, ise_giris_tarihi) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $ad,
            $soyad,
            $email,
            $telefon,
            $yetki,
            $sifreHash,
            time()
        ];

        $this->db->query($sql, $params);
        $personelId = $this->db->lastInsertId();

        $this->islemLog->logKaydet('personel_ekle', "Yeni personel eklendi: $ad $soyad");
        
        return [
            'id' => $personelId,
            'sifre' => $sifre // İlk şifreyi döndür
        ];
    }

    /**
     * Personel listesini getir (select için)
     */
    public function listesiGetir()
    {
        $sql = "SELECT id, ad, soyad FROM personel ORDER BY ad, soyad";
        return $this->db->fetchAll($sql);
    }

    /**
     * Personel izinlerini getir
     */
    public function izinleriniGetir($personelId)
    {
        $sql = "SELECT i.*, it.ad as izin_turu_adi 
                FROM izinler i 
                LEFT JOIN izin_turleri it ON i.izin_turu = it.id 
                WHERE i.personel_id = ? 
                ORDER BY i.baslangic_tarihi DESC";

        return $this->db->fetchAll($sql, [$personelId]);
    }

    /**
     * Personel izin taleplerini getir
     */
    public function izinTalepleriniGetir($personelId)
    {
        $sql = "SELECT i.*, it.ad as izin_turu_adi 
                FROM izinler i 
                LEFT JOIN izin_turleri it ON i.izin_turu = it.id 
                WHERE i.personel_id = ? AND i.durum = 'beklemede' 
                ORDER BY i.baslangic_tarihi DESC";

        return $this->db->fetchAll($sql, [$personelId]);
    }

    /**
     * Personel düzenle
     */
    public function duzenle($personelId, $ad, $soyad, $email, $telefon = '', $yetki = null)
    {
        // Personel kontrolü
        $sql = "SELECT * FROM personel WHERE id = ?";
        $personel = $this->db->fetch($sql, [$personelId]);
        if (!$personel) {
            throw new Exception('Personel bulunamadı.');
        }

        // Email kontrolü
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Geçersiz e-posta formatı.');
        }

        // Email benzersizlik kontrolü
        $sql = "SELECT COUNT(*) as adet FROM personel WHERE email = ? AND id != ?";
        $kontrol = $this->db->fetch($sql, [$email, $personelId]);
        if ($kontrol['adet'] > 0) {
            throw new Exception('Bu e-posta adresi zaten kullanılıyor.');
        }

        // Personeli güncelle
        $sql = "UPDATE personel SET ad = ?, soyad = ?, email = ?, telefon = ?";
        $params = [$ad, $soyad, $email, $telefon];

        if ($yetki !== null) {
            $sql .= ", yetki = ?";
            $params[] = $yetki;
        }

        $sql .= " WHERE id = ?";
        $params[] = $personelId;

        $this->db->query($sql, $params);

        $this->islemLog->logKaydet('personel_duzenle', "Personel düzenlendi: $ad $soyad");
        return true;
    }

    /**
     * Personel vardiya dağılımı
     */
    public function vardiyaDagilimi($baslangicTarih, $bitisTarih)
    {
        $sql = "SELECT p.id, p.ad, p.soyad, 
                COUNT(v.id) as toplam_vardiya,
                SUM(CASE WHEN DAYOFWEEK(FROM_UNIXTIME(v.tarih)) IN (1,7) THEN 1 ELSE 0 END) as hafta_sonu,
                SUM(CASE WHEN vt.gece_vardiyasi = 1 THEN 1 ELSE 0 END) as gece_vardiyasi
                FROM personel p
                LEFT JOIN vardiyalar v ON p.id = v.personel_id
                LEFT JOIN vardiya_turleri vt ON v.vardiya_turu = vt.id
                WHERE v.tarih BETWEEN ? AND ?
                GROUP BY p.id
                ORDER BY p.ad, p.soyad";

        return $this->db->fetchAll($sql, [$baslangicTarih, $bitisTarih]);
    }

    /**
     * Personel tercihleri getir
     */
    public function tercihGetir($personelId)
    {
        $sql = "SELECT * FROM personel_tercihleri WHERE personel_id = ?";
        return $this->db->fetch($sql, [$personelId]);
    }

    /**
     * Personel aylık vardiya sayısı
     */
    public function aylikVardiyaSayisi($personelId, $ay, $yil)
    {
        $baslangic = mktime(0, 0, 0, $ay, 1, $yil);
        $bitis = mktime(23, 59, 59, $ay + 1, 0, $yil);

        $sql = "SELECT COUNT(*) as toplam FROM vardiyalar WHERE personel_id = ? AND tarih BETWEEN ? AND ?";
        $sonuc = $this->db->fetch($sql, [$personelId, $baslangic, $bitis]);

        return $sonuc['toplam'];
    }
}
