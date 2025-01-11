<?php

class Bildirim {
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

    // Bildirim oluştur
    public function olustur($personelId, $baslik, $mesaj, $tur = 'genel', $link = null) {
        try {
            $bildirimId = uniqid();
            $sql = "INSERT INTO bildirimler (
                        id, personel_id, baslik, mesaj, tur,
                        link, okundu, olusturma_tarihi
                    ) VALUES (?, ?, ?, ?, ?, ?, 0, UNIX_TIMESTAMP())";

            $this->db->query($sql, [
                $bildirimId,
                $personelId,
                $baslik,
                $mesaj,
                $tur,
                $link
            ]);

            // Web push bildirimi gönder
            if ($this->webPushAktifMi($personelId, $tur)) {
                $this->webPushGonder($personelId, $baslik, $mesaj, $link);
            }

            // E-posta bildirimi gönder
            if ($this->epostaAktifMi($personelId, $tur)) {
                $this->epostaGonder($personelId, $baslik, $mesaj, $link);
            }

            $this->core->islemLogKaydet(
                'bildirim_olustur', 
                "Bildirim oluşturuldu: $personelId - $baslik"
            );
            return $bildirimId;
        } catch (Exception $e) {
            throw new Exception("Bildirim oluşturulurken hata: " . $e->getMessage());
        }
    }

    // Toplu bildirim oluştur
    public function topluOlustur($personelIds, $baslik, $mesaj, $tur = 'genel', $link = null) {
        try {
            $this->db->beginTransaction();

            foreach ($personelIds as $personelId) {
                $bildirimId = uniqid();
                $sql = "INSERT INTO bildirimler (
                            id, personel_id, baslik, mesaj, tur,
                            link, okundu, olusturma_tarihi
                        ) VALUES (?, ?, ?, ?, ?, ?, 0, UNIX_TIMESTAMP())";

                $this->db->query($sql, [
                    $bildirimId,
                    $personelId,
                    $baslik,
                    $mesaj,
                    $tur,
                    $link
                ]);

                // Web push bildirimi gönder
                if ($this->webPushAktifMi($personelId, $tur)) {
                    $this->webPushGonder($personelId, $baslik, $mesaj, $link);
                }

                // E-posta bildirimi gönder
                if ($this->epostaAktifMi($personelId, $tur)) {
                    $this->epostaGonder($personelId, $baslik, $mesaj, $link);
                }
            }

            $this->db->commit();
            $this->core->islemLogKaydet(
                'toplu_bildirim_olustur', 
                "Toplu bildirim oluşturuldu: " . count($personelIds) . " personel - $baslik"
            );
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Toplu bildirim oluşturulurken hata: " . $e->getMessage());
        }
    }

    // Bildirimi okundu olarak işaretle
    public function okunduIsaretle($bildirimId) {
        try {
            $sql = "UPDATE bildirimler 
                    SET okundu = 1,
                        okunma_tarihi = UNIX_TIMESTAMP()
                    WHERE id = ?";

            $this->db->query($sql, [$bildirimId]);
            return true;
        } catch (Exception $e) {
            throw new Exception("Bildirim okundu işaretlenirken hata: " . $e->getMessage());
        }
    }

    // Tüm bildirimleri okundu olarak işaretle
    public function tumunuOkunduIsaretle($personelId) {
        try {
            $sql = "UPDATE bildirimler 
                    SET okundu = 1,
                        okunma_tarihi = UNIX_TIMESTAMP()
                    WHERE personel_id = ? 
                    AND okundu = 0";

            $this->db->query($sql, [$personelId]);
            return true;
        } catch (Exception $e) {
            throw new Exception("Tüm bildirimler okundu işaretlenirken hata: " . $e->getMessage());
        }
    }

    // Personelin bildirimlerini getir
    public function personelBildirimleriniGetir($personelId, $limit = 50, $offset = 0) {
        try {
            $sql = "SELECT * FROM bildirimler 
                    WHERE personel_id = ?
                    ORDER BY olusturma_tarihi DESC
                    LIMIT ? OFFSET ?";

            return $this->db->fetchAll($sql, [
                $personelId,
                $limit,
                $offset
            ]);
        } catch (Exception $e) {
            throw new Exception("Bildirimler getirilirken hata: " . $e->getMessage());
        }
    }

    // Okunmamış bildirim sayısını getir
    public function okunmamisBildirimSayisi($personelId) {
        try {
            $sql = "SELECT COUNT(*) as sayi 
                    FROM bildirimler 
                    WHERE personel_id = ? 
                    AND okundu = 0";

            $sonuc = $this->db->fetch($sql, [$personelId]);
            return $sonuc['sayi'];
        } catch (Exception $e) {
            throw new Exception("Okunmamış bildirim sayısı getirilirken hata: " . $e->getMessage());
        }
    }

    // Bildirim tercihlerini güncelle
    public function tercihGuncelle($personelId, $tercihler) {
        try {
            $sql = "INSERT INTO bildirim_tercihleri (
                        personel_id, web_push, eposta, tercihler,
                        guncelleme_tarihi
                    ) VALUES (?, ?, ?, ?, UNIX_TIMESTAMP())
                    ON DUPLICATE KEY UPDATE
                        web_push = VALUES(web_push),
                        eposta = VALUES(eposta),
                        tercihler = VALUES(tercihler),
                        guncelleme_tarihi = UNIX_TIMESTAMP()";

            $this->db->query($sql, [
                $personelId,
                $tercihler['web_push'] ?? 0,
                $tercihler['eposta'] ?? 0,
                json_encode($tercihler['bildirim_turleri'] ?? [], JSON_UNESCAPED_UNICODE)
            ]);

            $this->core->islemLogKaydet(
                'bildirim_tercih_guncelle', 
                "Bildirim tercihleri güncellendi: $personelId"
            );
            return true;
        } catch (Exception $e) {
            throw new Exception("Bildirim tercihleri güncellenirken hata: " . $e->getMessage());
        }
    }

    // Web push bildirimi aktif mi kontrol et
    protected function webPushAktifMi($personelId, $bildirimTuru) {
        try {
            $sql = "SELECT web_push, tercihler 
                    FROM bildirim_tercihleri 
                    WHERE personel_id = ?";

            $tercih = $this->db->fetch($sql, [$personelId]);
            if (!$tercih) return false;

            if (!$tercih['web_push']) return false;

            $tercihler = json_decode($tercih['tercihler'], true);
            return in_array($bildirimTuru, $tercihler ?? []);
        } catch (Exception $e) {
            error_log("Web push kontrolünde hata: " . $e->getMessage());
            return false;
        }
    }

    // E-posta bildirimi aktif mi kontrol et
    protected function epostaAktifMi($personelId, $bildirimTuru) {
        try {
            $sql = "SELECT eposta, tercihler 
                    FROM bildirim_tercihleri 
                    WHERE personel_id = ?";

            $tercih = $this->db->fetch($sql, [$personelId]);
            if (!$tercih) return false;

            if (!$tercih['eposta']) return false;

            $tercihler = json_decode($tercih['tercihler'], true);
            return in_array($bildirimTuru, $tercihler ?? []);
        } catch (Exception $e) {
            error_log("E-posta kontrolünde hata: " . $e->getMessage());
            return false;
        }
    }

    // Web push bildirimi gönder
    protected function webPushGonder($personelId, $baslik, $mesaj, $link = null) {
        try {
            // Web push abonelik bilgilerini getir
            $sql = "SELECT push_endpoint, push_p256dh, push_auth 
                    FROM personel_push_abonelikleri 
                    WHERE personel_id = ?";

            $abonelik = $this->db->fetch($sql, [$personelId]);
            if (!$abonelik) return false;

            // Web push bildirimi gönderme işlemi burada yapılacak
            // Örnek: webpush/push-api kullanılabilir
            return true;
        } catch (Exception $e) {
            error_log("Web push gönderiminde hata: " . $e->getMessage());
            return false;
        }
    }

    // E-posta bildirimi gönder
    protected function epostaGonder($personelId, $baslik, $mesaj, $link = null) {
        try {
            // Personel e-posta adresini getir
            $sql = "SELECT email FROM personel WHERE id = ?";
            $personel = $this->db->fetch($sql, [$personelId]);
            if (!$personel) return false;

            // E-posta gönderme işlemi burada yapılacak
            // Örnek: PHPMailer kullanılabilir
            return true;
        } catch (Exception $e) {
            error_log("E-posta gönderiminde hata: " . $e->getMessage());
            return false;
        }
    }
} 