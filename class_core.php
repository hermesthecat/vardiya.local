<?php

class Core {
    protected static $instance = null;
    protected $db;

    protected function __construct() {
        $this->db = Database::getInstance();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Tarih formatlama fonksiyonu
    public function tarihFormatla($timestamp, $format = 'kisa') {
        if (!is_numeric($timestamp)) {
            $timestamp = strtotime($timestamp);
        }

        $tarih = date_create(date('Y-m-d H:i:s', $timestamp));

        $gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
        $aylar = [
            'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
            'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'
        ];

        switch ($format) {
            case 'kisa':
                return date_format($tarih, 'd.m.Y');
            case 'uzun':
                return date_format($tarih, 'j') . ' ' . $aylar[date_format($tarih, 'n') - 1] . ' ' . date_format($tarih, 'Y');
            case 'tam':
                return $gunler[date_format($tarih, 'w')] . ', ' . date_format($tarih, 'j') . ' ' . $aylar[date_format($tarih, 'n') - 1] . ' ' . date_format($tarih, 'Y');
            case 'saat':
                return date_format($tarih, 'H:i');
            case 'tam_saat':
                return date_format($tarih, 'j') . ' ' . $aylar[date_format($tarih, 'n') - 1] . ' ' . date_format($tarih, 'Y') . ' ' . date_format($tarih, 'H:i');
            case 'veritabani':
                return date_format($tarih, 'Y-m-d H:i:s');
            case 'gun':
                return $gunler[date_format($tarih, 'w')];
            case 'ay':
                return $aylar[date_format($tarih, 'n') - 1];
            default:
                return date_format($tarih, 'd.m.Y');
        }
    }

    // İşlem logu kaydetme
    public function islemLogKaydet($islemTuru, $aciklama) {
        try {
            $sql = "INSERT INTO islem_log (
                        kullanici_id, kullanici_rol, islem_turu, 
                        aciklama, ip_adresi, tarayici
                    ) VALUES (?, ?, ?, ?, ?, ?)";
            
            return $this->db->query($sql, [
                $_SESSION['kullanici_id'] ?? null,
                $_SESSION['rol'] ?? null,
                $islemTuru,
                $aciklama,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Log kayıt hatası: " . $e->getMessage());
            return false;
        }
    }

    // İşlem loglarını getir
    public function islemLoglariGetir($baslangicTarih = null, $bitisTarih = null, $islemTuru = null, $kullaniciId = null) {
        try {
            $params = [];
            $where = [];

            if ($baslangicTarih) {
                $where[] = "tarih >= ?";
                $params[] = $baslangicTarih;
            }

            if ($bitisTarih) {
                $where[] = "tarih <= ?";
                $params[] = $bitisTarih;
            }

            if ($islemTuru) {
                $where[] = "islem_turu = ?";
                $params[] = $islemTuru;
            }

            if ($kullaniciId) {
                $where[] = "kullanici_id = ?";
                $params[] = $kullaniciId;
            }

            $whereStr = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

            $sql = "SELECT l.*, 
                    CONCAT(p.ad, ' ', p.soyad) as kullanici_adi
                    FROM islem_log l
                    LEFT JOIN personel p ON l.kullanici_id = p.id
                    $whereStr
                    ORDER BY l.tarih DESC";

            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            throw new Exception("İşlem logları getirilirken hata: " . $e->getMessage());
        }
    }

    // Yetki kontrolü
    public function yetkiKontrol($gerekliRoller = ['admin']) {
        if (!isset($_SESSION['kullanici_id']) || !isset($_SESSION['rol'])) {
            header('Location: giris.php');
            exit;
        }

        if (!in_array($_SESSION['rol'], $gerekliRoller)) {
            throw new Exception('Bu işlem için yetkiniz bulunmuyor.');
        }

        return true;
    }
} 