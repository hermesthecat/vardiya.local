<?php

class Duyuru {
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

    // Duyuru ekle
    public function duyuruEkle($baslik, $icerik, $olusturanId, $hedefKitle = 'tum', $hedefPersoneller = [], $dosyalar = []) {
        try {
            $this->db->beginTransaction();

            $duyuruId = uniqid();
            $sql = "INSERT INTO duyurular (
                        id, baslik, icerik, olusturan_id,
                        hedef_kitle, olusturma_tarihi
                    ) VALUES (?, ?, ?, ?, ?, UNIX_TIMESTAMP())";

            $this->db->query($sql, [
                $duyuruId,
                $baslik,
                $icerik,
                $olusturanId,
                $hedefKitle
            ]);

            // Hedef personelleri kaydet
            if ($hedefKitle === 'secili' && !empty($hedefPersoneller)) {
                $sql = "INSERT INTO duyuru_hedef_personeller (
                            duyuru_id, personel_id
                        ) VALUES (?, ?)";

                foreach ($hedefPersoneller as $personelId) {
                    $this->db->query($sql, [$duyuruId, $personelId]);
                }
            }

            // Dosyaları kaydet
            if (!empty($dosyalar)) {
                $sql = "INSERT INTO duyuru_dosyalar (
                            duyuru_id, dosya_adi, dosya_yolu,
                            dosya_boyutu, dosya_turu
                        ) VALUES (?, ?, ?, ?, ?)";

                foreach ($dosyalar as $dosya) {
                    $this->db->query($sql, [
                        $duyuruId,
                        $dosya['adi'],
                        $dosya['yolu'],
                        $dosya['boyut'],
                        $dosya['tur']
                    ]);
                }
            }

            // Bildirimleri oluştur
            $bildirim = Bildirim::getInstance();
            
            if ($hedefKitle === 'tum') {
                $sql = "SELECT id FROM personel WHERE durum = 'aktif'";
                $personeller = $this->db->fetchAll($sql);
                
                foreach ($personeller as $personel) {
                    $bildirim->olustur(
                        $personel['id'],
                        'Yeni Duyuru: ' . $baslik,
                        mb_substr(strip_tags($icerik), 0, 100) . '...',
                        'duyuru',
                        "duyuru.php?id=$duyuruId"
                    );
                }
            } else {
                foreach ($hedefPersoneller as $personelId) {
                    $bildirim->olustur(
                        $personelId,
                        'Yeni Duyuru: ' . $baslik,
                        mb_substr(strip_tags($icerik), 0, 100) . '...',
                        'duyuru',
                        "duyuru.php?id=$duyuruId"
                    );
                }
            }

            $this->db->commit();
            $this->core->islemLogKaydet(
                'duyuru_ekle', 
                "Duyuru eklendi: $baslik"
            );
            return $duyuruId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Duyuru eklenirken hata: " . $e->getMessage());
        }
    }

    // Duyuru düzenle
    public function duyuruDuzenle($duyuruId, $baslik, $icerik, $hedefKitle = null, $hedefPersoneller = [], $dosyalar = []) {
        try {
            $this->db->beginTransaction();

            // Duyuruyu kontrol et
            $duyuru = $this->db->fetch("SELECT * FROM duyurular WHERE id = ?", [$duyuruId]);
            if (!$duyuru) {
                throw new Exception('Duyuru bulunamadı.');
            }

            $sql = "UPDATE duyurular 
                    SET baslik = ?,
                        icerik = ?,
                        guncelleme_tarihi = UNIX_TIMESTAMP()";
            
            $params = [$baslik, $icerik];

            if ($hedefKitle !== null) {
                $sql .= ", hedef_kitle = ?";
                $params[] = $hedefKitle;
            }

            $sql .= " WHERE id = ?";
            $params[] = $duyuruId;

            $this->db->query($sql, $params);

            // Hedef personelleri güncelle
            if ($hedefKitle === 'secili') {
                $this->db->query(
                    "DELETE FROM duyuru_hedef_personeller WHERE duyuru_id = ?",
                    [$duyuruId]
                );

                if (!empty($hedefPersoneller)) {
                    $sql = "INSERT INTO duyuru_hedef_personeller (
                                duyuru_id, personel_id
                            ) VALUES (?, ?)";

                    foreach ($hedefPersoneller as $personelId) {
                        $this->db->query($sql, [$duyuruId, $personelId]);
                    }
                }
            }

            // Yeni dosyaları ekle
            if (!empty($dosyalar)) {
                $sql = "INSERT INTO duyuru_dosyalar (
                            duyuru_id, dosya_adi, dosya_yolu,
                            dosya_boyutu, dosya_turu
                        ) VALUES (?, ?, ?, ?, ?)";

                foreach ($dosyalar as $dosya) {
                    $this->db->query($sql, [
                        $duyuruId,
                        $dosya['adi'],
                        $dosya['yolu'],
                        $dosya['boyut'],
                        $dosya['tur']
                    ]);
                }
            }

            $this->db->commit();
            $this->core->islemLogKaydet(
                'duyuru_duzenle', 
                "Duyuru düzenlendi: $duyuruId - $baslik"
            );
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Duyuru düzenlenirken hata: " . $e->getMessage());
        }
    }

    // Duyuru sil
    public function duyuruSil($duyuruId) {
        try {
            $this->db->beginTransaction();

            // Duyuruyu kontrol et
            $duyuru = $this->db->fetch("SELECT * FROM duyurular WHERE id = ?", [$duyuruId]);
            if (!$duyuru) {
                throw new Exception('Duyuru bulunamadı.');
            }

            // İlişkili kayıtları sil
            $this->db->query(
                "DELETE FROM duyuru_hedef_personeller WHERE duyuru_id = ?",
                [$duyuruId]
            );

            // Dosyaları sil
            $dosyalar = $this->db->fetchAll(
                "SELECT * FROM duyuru_dosyalar WHERE duyuru_id = ?",
                [$duyuruId]
            );

            foreach ($dosyalar as $dosya) {
                if (file_exists($dosya['dosya_yolu'])) {
                    unlink($dosya['dosya_yolu']);
                }
            }

            $this->db->query(
                "DELETE FROM duyuru_dosyalar WHERE duyuru_id = ?",
                [$duyuruId]
            );

            // Duyuruyu sil
            $this->db->query("DELETE FROM duyurular WHERE id = ?", [$duyuruId]);

            $this->db->commit();
            $this->core->islemLogKaydet(
                'duyuru_sil', 
                "Duyuru silindi: $duyuruId"
            );
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Duyuru silinirken hata: " . $e->getMessage());
        }
    }

    // Duyuru detaylarını getir
    public function duyuruDetayGetir($duyuruId) {
        try {
            $sql = "SELECT d.*,
                    CONCAT(p.ad, ' ', p.soyad) as olusturan_adi
                    FROM duyurular d
                    LEFT JOIN personel p ON d.olusturan_id = p.id
                    WHERE d.id = ?";

            $duyuru = $this->db->fetch($sql, [$duyuruId]);
            if (!$duyuru) {
                throw new Exception('Duyuru bulunamadı.');
            }

            // Hedef personelleri getir
            if ($duyuru['hedef_kitle'] === 'secili') {
                $sql = "SELECT p.id, CONCAT(p.ad, ' ', p.soyad) as ad_soyad
                        FROM duyuru_hedef_personeller dhp
                        LEFT JOIN personel p ON dhp.personel_id = p.id
                        WHERE dhp.duyuru_id = ?";

                $duyuru['hedef_personeller'] = $this->db->fetchAll($sql, [$duyuruId]);
            }

            // Dosyaları getir
            $sql = "SELECT * FROM duyuru_dosyalar WHERE duyuru_id = ?";
            $duyuru['dosyalar'] = $this->db->fetchAll($sql, [$duyuruId]);

            return $duyuru;
        } catch (Exception $e) {
            throw new Exception("Duyuru detayları getirilirken hata: " . $e->getMessage());
        }
    }

    // Duyuruları listele
    public function duyurulariGetir($limit = 50, $offset = 0, $personelId = null) {
        try {
            $params = [];
            $where = [];

            if ($personelId !== null) {
                $where[] = "(d.hedef_kitle = 'tum' OR 
                            (d.hedef_kitle = 'secili' AND EXISTS (
                                SELECT 1 FROM duyuru_hedef_personeller dhp 
                                WHERE dhp.duyuru_id = d.id AND dhp.personel_id = ?
                            )))";
                $params[] = $personelId;
            }

            $whereStr = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

            $sql = "SELECT d.*,
                    CONCAT(p.ad, ' ', p.soyad) as olusturan_adi
                    FROM duyurular d
                    LEFT JOIN personel p ON d.olusturan_id = p.id
                    $whereStr
                    ORDER BY d.olusturma_tarihi DESC
                    LIMIT ? OFFSET ?";

            $params[] = $limit;
            $params[] = $offset;

            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            throw new Exception("Duyurular getirilirken hata: " . $e->getMessage());
        }
    }

    // Dosya sil
    public function dosyaSil($dosyaId) {
        try {
            $dosya = $this->db->fetch(
                "SELECT * FROM duyuru_dosyalar WHERE id = ?",
                [$dosyaId]
            );

            if (!$dosya) {
                throw new Exception('Dosya bulunamadı.');
            }

            if (file_exists($dosya['dosya_yolu'])) {
                unlink($dosya['dosya_yolu']);
            }

            $this->db->query(
                "DELETE FROM duyuru_dosyalar WHERE id = ?",
                [$dosyaId]
            );

            $this->core->islemLogKaydet(
                'duyuru_dosya_sil', 
                "Duyuru dosyası silindi: $dosyaId"
            );
            return true;
        } catch (Exception $e) {
            throw new Exception("Dosya silinirken hata: " . $e->getMessage());
        }
    }
} 