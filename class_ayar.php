<?php

class Ayar {
    protected static $instance = null;
    protected $db;
    protected $core;
    protected $cache = [];

    protected function __construct() {
        $this->db = Database::getInstance();
        $this->core = Core::getInstance();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Ayar değerini getir
    public function get($anahtar, $varsayilan = null) {
        try {
            // Önbellekte varsa oradan döndür
            if (isset($this->cache[$anahtar])) {
                return $this->cache[$anahtar];
            }

            $sql = "SELECT deger, tur FROM ayarlar WHERE anahtar = ?";
            $ayar = $this->db->fetch($sql, [$anahtar]);

            if (!$ayar) {
                return $varsayilan;
            }

            // Veri türüne göre dönüşüm yap
            $deger = $this->degerDonustur($ayar['deger'], $ayar['tur']);
            
            // Önbelleğe al
            $this->cache[$anahtar] = $deger;
            
            return $deger;
        } catch (Exception $e) {
            error_log("Ayar getirme hatası: " . $e->getMessage());
            return $varsayilan;
        }
    }

    // Ayar değerini güncelle
    public function set($anahtar, $deger, $tur = null) {
        try {
            // Veri türünü otomatik belirle
            if ($tur === null) {
                $tur = $this->turBelirle($deger);
            }

            // Değeri türe göre dönüştür
            $deger = $this->degerKaydet($deger, $tur);

            $sql = "INSERT INTO ayarlar (anahtar, deger, tur, guncelleme_tarihi) 
                    VALUES (?, ?, ?, UNIX_TIMESTAMP())
                    ON DUPLICATE KEY UPDATE 
                        deger = VALUES(deger),
                        tur = VALUES(tur),
                        guncelleme_tarihi = UNIX_TIMESTAMP()";

            $this->db->query($sql, [$anahtar, $deger, $tur]);

            // Önbellekten sil
            unset($this->cache[$anahtar]);

            $this->core->islemLogKaydet(
                'ayar_guncelle', 
                "Ayar güncellendi: $anahtar"
            );
            return true;
        } catch (Exception $e) {
            throw new Exception("Ayar güncellenirken hata: " . $e->getMessage());
        }
    }

    // Ayarı sil
    public function sil($anahtar) {
        try {
            $sql = "DELETE FROM ayarlar WHERE anahtar = ?";
            $this->db->query($sql, [$anahtar]);

            // Önbellekten sil
            unset($this->cache[$anahtar]);

            $this->core->islemLogKaydet(
                'ayar_sil', 
                "Ayar silindi: $anahtar"
            );
            return true;
        } catch (Exception $e) {
            throw new Exception("Ayar silinirken hata: " . $e->getMessage());
        }
    }

    // Tüm ayarları getir
    public function tumAyarlariGetir() {
        try {
            $sql = "SELECT * FROM ayarlar ORDER BY anahtar";
            $ayarlar = $this->db->fetchAll($sql);

            $sonuc = [];
            foreach ($ayarlar as $ayar) {
                $deger = $this->degerDonustur($ayar['deger'], $ayar['tur']);
                $sonuc[$ayar['anahtar']] = [
                    'deger' => $deger,
                    'tur' => $ayar['tur'],
                    'guncelleme_tarihi' => $ayar['guncelleme_tarihi']
                ];
                
                // Önbelleğe al
                $this->cache[$ayar['anahtar']] = $deger;
            }

            return $sonuc;
        } catch (Exception $e) {
            throw new Exception("Ayarlar getirilirken hata: " . $e->getMessage());
        }
    }

    // Varsayılan ayarları yükle
    public function varsayilanAyarlariYukle() {
        try {
            $this->db->beginTransaction();

            $varsayilanAyarlar = [
                // Genel ayarlar
                'site_adi' => ['Vardiya Sistemi', 'metin'],
                'site_aciklama' => ['Personel Vardiya Yönetim Sistemi', 'metin'],
                'firma_adi' => ['Firma Adı', 'metin'],
                'firma_adres' => ['Firma Adresi', 'metin'],
                'firma_telefon' => ['0212 123 45 67', 'metin'],
                'firma_email' => ['info@firma.com', 'metin'],

                // Vardiya ayarları
                'maksimum_ardisik_vardiya' => [6, 'sayi'],
                'minimum_vardiya_arasi' => [11, 'sayi'], // saat
                'varsayilan_vardiya_suresi' => [8, 'sayi'], // saat
                'vardiya_degisim_talep_suresi' => [24, 'sayi'], // saat

                // İzin ayarları
                'yillik_izin_hakki' => [14, 'sayi'],
                'mazeret_izin_hakki' => [5, 'sayi'],
                'minimum_izin_talep_suresi' => [3, 'sayi'], // gün
                'izin_talep_onay_suresi' => [24, 'sayi'], // saat

                // E-posta ayarları
                'smtp_sunucu' => ['smtp.firma.com', 'metin'],
                'smtp_port' => [587, 'sayi'],
                'smtp_kullanici' => ['bildirim@firma.com', 'metin'],
                'smtp_sifre' => ['', 'sifre'],
                'smtp_ssl' => [true, 'boolean'],

                // Bildirim ayarları
                'bildirim_varsayilan_web_push' => [true, 'boolean'],
                'bildirim_varsayilan_eposta' => [true, 'boolean'],
                'bildirim_turleri' => [
                    [
                        'vardiya' => true,
                        'izin' => true,
                        'duyuru' => true
                    ],
                    'json'
                ],

                // Güvenlik ayarları
                'minimum_sifre_uzunlugu' => [6, 'sayi'],
                'sifre_karmasikligi' => [true, 'boolean'],
                'oturum_suresi' => [120, 'sayi'], // dakika
                'maksimum_giris_denemesi' => [5, 'sayi'],
                'giris_deneme_suresi' => [15, 'sayi'], // dakika

                // Görünüm ayarları
                'tema' => ['varsayilan', 'metin'],
                'logo' => ['logo.png', 'metin'],
                'favicon' => ['favicon.ico', 'metin'],
                'sayfa_basina_kayit' => [50, 'sayi']
            ];

            foreach ($varsayilanAyarlar as $anahtar => $ayar) {
                $this->set($anahtar, $ayar[0], $ayar[1]);
            }

            $this->db->commit();
            $this->core->islemLogKaydet(
                'varsayilan_ayarlar_yukle', 
                "Varsayılan ayarlar yüklendi"
            );
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Varsayılan ayarlar yüklenirken hata: " . $e->getMessage());
        }
    }

    // Önbelleği temizle
    public function onbellekTemizle() {
        $this->cache = [];
        return true;
    }

    // Değeri türüne göre dönüştür
    protected function degerDonustur($deger, $tur) {
        switch ($tur) {
            case 'sayi':
                return (int) $deger;
            case 'ondalik':
                return (float) $deger;
            case 'boolean':
                return (bool) $deger;
            case 'json':
                return json_decode($deger, true);
            case 'dizi':
                return explode(',', $deger);
            case 'sifre':
                return $deger; // Şifrelenmiş olarak saklanır
            default:
                return (string) $deger;
        }
    }

    // Değeri kaydetmek için dönüştür
    protected function degerKaydet($deger, $tur) {
        switch ($tur) {
            case 'json':
                return json_encode($deger, JSON_UNESCAPED_UNICODE);
            case 'dizi':
                return is_array($deger) ? implode(',', $deger) : $deger;
            case 'sifre':
                return !empty($deger) ? password_hash($deger, PASSWORD_DEFAULT) : $deger;
            default:
                return (string) $deger;
        }
    }

    // Değerin türünü otomatik belirle
    protected function turBelirle($deger) {
        if (is_int($deger)) return 'sayi';
        if (is_float($deger)) return 'ondalik';
        if (is_bool($deger)) return 'boolean';
        if (is_array($deger)) return 'json';
        return 'metin';
    }
} 