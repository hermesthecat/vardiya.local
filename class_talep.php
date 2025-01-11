<?php

class Talep {
    protected static $instance = null;
    protected $db;
    protected $core;

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

    // Talep oluştur
    public function talepOlustur($personelId, $talepTipi, $aciklama, $dosyalar = []) {
        try {
            $this->db->beginTransaction();

            $talepId = uniqid();
            $sql = "INSERT INTO talepler (
                        id, personel_id, talep_tipi, aciklama,
                        durum, olusturma_tarihi
                    ) VALUES (?, ?, ?, ?, 'beklemede', UNIX_TIMESTAMP())";

            $this->db->query($sql, [
                $talepId,
                $personelId,
                $talepTipi,
                $aciklama
            ]);

            // Dosyaları kaydet
            if (!empty($dosyalar)) {
                $sql = "INSERT INTO talep_dosyalar (
                            talep_id, dosya_adi, dosya_yolu,
                            dosya_boyutu, dosya_turu
                        ) VALUES (?, ?, ?, ?, ?)";

                foreach ($dosyalar as $dosya) {
                    $this->db->query($sql, [
                        $talepId,
                        $dosya['adi'],
                        $dosya['yolu'],
                        $dosya['boyut'],
                        $dosya['tur']
                    ]);
                }
            }

            // İK yöneticilerine bildirim gönder
            $bildirim = Bildirim::getInstance();
            $sql = "SELECT p.id 
                    FROM personel p 
                    INNER JOIN roller r ON p.rol_id = r.id 
                    WHERE r.kod = 'ik_yonetici' AND p.durum = 'aktif'";
            
            $ikYoneticileri = $this->db->fetchAll($sql);
            
            foreach ($ikYoneticileri as $yonetici) {
                $bildirim->olustur(
                    $yonetici['id'],
                    'Yeni Personel Talebi',
                    "Yeni bir $talepTipi talebi oluşturuldu.",
                    'talep',
                    "talep.php?id=$talepId"
                );
            }

            $this->db->commit();
            $this->core->islemLogKaydet(
                'talep_olustur', 
                "Talep oluşturuldu: $talepId - $talepTipi"
            );
            return $talepId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Talep oluşturulurken hata: " . $e->getMessage());
        }
    }

    // Talep durumunu güncelle
    public function talepDurumGuncelle($talepId, $durum, $aciklama = null) {
        try {
            $this->db->beginTransaction();

            $talep = $this->db->fetch("SELECT * FROM talepler WHERE id = ?", [$talepId]);
            if (!$talep) {
                throw new Exception('Talep bulunamadı.');
            }

            $sql = "UPDATE talepler 
                    SET durum = ?,
                        durum_aciklama = ?,
                        guncelleme_tarihi = UNIX_TIMESTAMP()
                    WHERE id = ?";

            $this->db->query($sql, [$durum, $aciklama, $talepId]);

            // Talep sahibine bildirim gönder
            $bildirim = Bildirim::getInstance();
            $durumText = $durum === 'onaylandi' ? 'onaylandı' : 
                        ($durum === 'reddedildi' ? 'reddedildi' : 'güncellendi');

            $bildirim->olustur(
                $talep['personel_id'],
                'Talep Durumu Güncellendi',
                "Talebiniz $durumText." . ($aciklama ? " Açıklama: $aciklama" : ''),
                'talep',
                "talep.php?id=$talepId"
            );

            $this->db->commit();
            $this->core->islemLogKaydet(
                'talep_durum_guncelle', 
                "Talep durumu güncellendi: $talepId - $durum"
            );
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Talep durumu güncellenirken hata: " . $e->getMessage());
        }
    }

    // Talep detaylarını getir
    public function talepDetayGetir($talepId) {
        try {
            $sql = "SELECT t.*,
                    CONCAT(p.ad, ' ', p.soyad) as personel_adi
                    FROM talepler t
                    LEFT JOIN personel p ON t.personel_id = p.id
                    WHERE t.id = ?";

            $talep = $this->db->fetch($sql, [$talepId]);
            if (!$talep) {
                throw new Exception('Talep bulunamadı.');
            }

            // Dosyaları getir
            $sql = "SELECT * FROM talep_dosyalar WHERE talep_id = ?";
            $talep['dosyalar'] = $this->db->fetchAll($sql, [$talepId]);

            return $talep;
        } catch (Exception $e) {
            throw new Exception("Talep detayları getirilirken hata: " . $e->getMessage());
        }
    }

    // Talepleri listele
    public function talepleriGetir($limit = 50, $offset = 0, $filtreler = []) {
        try {
            $params = [];
            $where = [];

            if (!empty($filtreler['personel_id'])) {
                $where[] = "t.personel_id = ?";
                $params[] = $filtreler['personel_id'];
            }

            if (!empty($filtreler['durum'])) {
                $where[] = "t.durum = ?";
                $params[] = $filtreler['durum'];
            }

            if (!empty($filtreler['talep_tipi'])) {
                $where[] = "t.talep_tipi = ?";
                $params[] = $filtreler['talep_tipi'];
            }

            if (!empty($filtreler['baslangic_tarihi'])) {
                $where[] = "t.olusturma_tarihi >= ?";
                $params[] = $filtreler['baslangic_tarihi'];
            }

            if (!empty($filtreler['bitis_tarihi'])) {
                $where[] = "t.olusturma_tarihi <= ?";
                $params[] = $filtreler['bitis_tarihi'];
            }

            $whereStr = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

            $sql = "SELECT t.*,
                    CONCAT(p.ad, ' ', p.soyad) as personel_adi
                    FROM talepler t
                    LEFT JOIN personel p ON t.personel_id = p.id
                    $whereStr
                    ORDER BY t.olusturma_tarihi DESC
                    LIMIT ? OFFSET ?";

            $params[] = $limit;
            $params[] = $offset;

            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            throw new Exception("Talepler getirilirken hata: " . $e->getMessage());
        }
    }

    // Dosya sil
    public function dosyaSil($dosyaId) {
        try {
            $dosya = $this->db->fetch(
                "SELECT * FROM talep_dosyalar WHERE id = ?",
                [$dosyaId]
            );

            if (!$dosya) {
                throw new Exception('Dosya bulunamadı.');
            }

            if (file_exists($dosya['dosya_yolu'])) {
                unlink($dosya['dosya_yolu']);
            }

            $this->db->query(
                "DELETE FROM talep_dosyalar WHERE id = ?",
                [$dosyaId]
            );

            $this->core->islemLogKaydet(
                'talep_dosya_sil', 
                "Talep dosyası silindi: $dosyaId"
            );
            return true;
        } catch (Exception $e) {
            throw new Exception("Dosya silinirken hata: " . $e->getMessage());
        }
    }
} 