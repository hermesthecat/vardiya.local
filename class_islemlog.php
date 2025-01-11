<?php

/**
 * IslemLog sınıfı - İşlem loglarını yönetir
 * PHP 7.4+
 */
class IslemLog
{
    private $db;
    private static $instance = null;

    private function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * İşlem logu kaydetme
     */
    public function logKaydet($islemTuru, $aciklama)
    {
        $sql = "INSERT INTO islem_loglari (kullanici_id, kullanici_rol, islem_turu, aciklama, ip_adresi, tarih, tarayici) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $_SESSION['kullanici_id'] ?? null,
            $_SESSION['rol'] ?? null,
            $islemTuru,
            $aciklama,
            $_SERVER['REMOTE_ADDR'] ?? null,
            time(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ];

        $this->db->query($sql, $params);
        return $this->db->lastInsertId();
    }

    /**
     * İşlem loglarını getir
     */
    public function loglariGetir($baslangicTarih = null, $bitisTarih = null, $islemTuru = null, $kullaniciId = null)
    {
        $sql = "SELECT * FROM islem_loglari WHERE 1=1";
        $params = [];

        if ($baslangicTarih) {
            $sql .= " AND tarih >= ?";
            $params[] = $baslangicTarih;
        }

        if ($bitisTarih) {
            $sql .= " AND tarih <= ?";
            $params[] = $bitisTarih;
        }

        if ($islemTuru) {
            $sql .= " AND islem_turu = ?";
            $params[] = $islemTuru;
        }

        if ($kullaniciId) {
            $sql .= " AND kullanici_id = ?";
            $params[] = $kullaniciId;
        }

        $sql .= " ORDER BY tarih DESC";
        return $this->db->fetchAll($sql, $params);
    }
}
