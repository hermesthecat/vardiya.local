<?php

class Personel {
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

    // Tüm personelleri getir
    public function tumPersonelleriGetir() {
        try {
            return $this->db->fetchAll("SELECT * FROM personel ORDER BY ad, soyad");
        } catch (Exception $e) {
            throw new Exception("Personel listesi getirilirken hata: " . $e->getMessage());
        }
    }

    // Personel ekleme
    public function ekle($ad, $soyad, $email, $telefon = '', $yetki = 'personel') {
        try {
            // E-posta kontrolü
            $varolan = $this->db->fetch("SELECT id FROM personel WHERE email = ?", [$email]);
            if ($varolan) {
                throw new Exception('Bu e-posta adresi zaten kullanılıyor.');
            }

            $id = uniqid();
            $sql = "INSERT INTO personel (
                        id, ad, soyad, email, telefon, yetki, sifre
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $this->db->query($sql, [
                $id, $ad, $soyad, $email, $telefon, $yetki, '123456' // Varsayılan şifre
            ]);

            // Varsayılan izin haklarını ekle
            $izinHaklari = [
                ['yillik', 14],
                ['mazeret', 5]
            ];

            foreach ($izinHaklari as $hak) {
                $this->db->query(
                    "INSERT INTO izin_haklari (
                        personel_id, izin_turu, toplam, kullanilan, kalan, son_guncelleme
                    ) VALUES (?, ?, ?, 0, ?, UNIX_TIMESTAMP())",
                    [$id, $hak[0], $hak[1], $hak[1]]
                );
            }

            $this->core->islemLogKaydet('personel_ekle', "Yeni personel eklendi: $ad $soyad ($email)");
            return $id;
        } catch (Exception $e) {
            throw new Exception("Personel eklenirken hata: " . $e->getMessage());
        }
    }

    // Personel düzenleme
    public function duzenle($personelId, $ad, $soyad, $email, $telefon = null, $yetki = null) {
        try {
            // E-posta kontrolü
            $varolan = $this->db->fetch(
                "SELECT id FROM personel WHERE email = ? AND id != ?", 
                [$email, $personelId]
            );
            if ($varolan) {
                throw new Exception('Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.');
            }

            $params = [$ad, $soyad, $email];
            $sql = "UPDATE personel SET ad = ?, soyad = ?, email = ?";

            if ($telefon !== null) {
                $sql .= ", telefon = ?";
                $params[] = $telefon;
            }

            if ($yetki !== null) {
                $sql .= ", yetki = ?";
                $params[] = $yetki;
            }

            $sql .= " WHERE id = ?";
            $params[] = $personelId;

            $this->db->query($sql, $params);
            $this->core->islemLogKaydet('personel_duzenle', "Personel güncellendi: $ad $soyad ($email)");
            return true;
        } catch (Exception $e) {
            throw new Exception("Personel güncellenirken hata: " . $e->getMessage());
        }
    }

    // Personel silme
    public function sil($personelId) {
        try {
            $this->db->beginTransaction();

            // Vardiya kontrolü
            $vardiyaSayisi = $this->db->fetch(
                "SELECT COUNT(*) as sayi FROM vardiyalar WHERE personel_id = ?", 
                [$personelId]
            );

            if ($vardiyaSayisi['sayi'] > 0) {
                throw new Exception('Bu personele ait vardiyalar bulunduğu için silinemez. Önce vardiyaları silmelisiniz.');
            }

            // İlişkili kayıtları sil
            $this->db->query("DELETE FROM izin_haklari WHERE personel_id = ?", [$personelId]);
            $this->db->query("DELETE FROM personel_tercihler WHERE personel_id = ?", [$personelId]);
            $this->db->query("DELETE FROM personel WHERE id = ?", [$personelId]);

            $this->db->commit();
            $this->core->islemLogKaydet('personel_sil', "Personel silindi: ID: $personelId");
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Personel silinirken hata: " . $e->getMessage());
        }
    }

    // Şifre değiştirme
    public function sifreDegistir($personelId, $eskiSifre, $yeniSifre) {
        try {
            $personel = $this->db->fetch(
                "SELECT sifre FROM personel WHERE id = ?", 
                [$personelId]
            );

            if (!$personel) {
                throw new Exception('Personel bulunamadı.');
            }

            if ($personel['sifre'] !== $eskiSifre) {
                throw new Exception('Mevcut şifre hatalı.');
            }

            $this->db->query(
                "UPDATE personel SET sifre = ? WHERE id = ?",
                [$yeniSifre, $personelId]
            );

            $this->core->islemLogKaydet('sifre_degistir', 'Kullanıcı şifresi değiştirildi');
            return true;
        } catch (Exception $e) {
            throw new Exception("Şifre değiştirilirken hata: " . $e->getMessage());
        }
    }

    // Kullanıcı girişi
    public function giris($email, $sifre) {
        try {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Geçersiz e-posta formatı.');
            }

            $personel = $this->db->fetch(
                "SELECT * FROM personel WHERE email = ?",
                [$email]
            );

            if (!$personel) {
                throw new Exception('Bu e-posta adresi ile kayıtlı personel bulunamadı.');
            }

            if ($personel['sifre'] !== $sifre) {
                throw new Exception('Hatalı şifre.');
            }

            // Varolan session'ı temizle
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }

            // Yeni session başlat
            session_start();

            // Session'a kullanıcı bilgilerini kaydet
            $_SESSION['kullanici_id'] = $personel['id'];
            $_SESSION['email'] = $personel['email'];
            $_SESSION['rol'] = $personel['yetki'];
            $_SESSION['ad_soyad'] = $personel['ad'] . ' ' . $personel['soyad'];
            $_SESSION['giris_zamani'] = time();

            // Tercihleri getir
            $tercihler = $this->db->fetch(
                "SELECT * FROM personel_tercihler WHERE personel_id = ?",
                [$personel['id']]
            );
            if ($tercihler) {
                $_SESSION['tercihler'] = json_decode($tercihler['tercihler'], true);
            }

            $this->core->islemLogKaydet('giris', 'Başarılı giriş: ' . $personel['email']);
            return true;
        } catch (Exception $e) {
            $this->core->islemLogKaydet('giris_hata', 'Başarısız giriş denemesi: ' . $email . ' - ' . $e->getMessage());
            throw $e;
        }
    }

    // Kullanıcı çıkışı
    public function cikis() {
        $this->core->islemLogKaydet('cikis', 'Kullanıcı çıkışı yapıldı');
        session_destroy();
    }

    // Tercihleri güncelle
    public function tercihGuncelle($personelId, $tercihler) {
        try {
            $sql = "INSERT INTO personel_tercihler (personel_id, tercihler) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE tercihler = ?";
            
            $tercihlerJson = json_encode($tercihler, JSON_UNESCAPED_UNICODE);
            
            $this->db->query($sql, [
                $personelId, 
                $tercihlerJson,
                $tercihlerJson
            ]);

            $this->core->islemLogKaydet('tercih_guncelle', "Personel tercihleri güncellendi: $personelId");
            return true;
        } catch (Exception $e) {
            throw new Exception("Tercihler güncellenirken hata: " . $e->getMessage());
        }
    }
} 