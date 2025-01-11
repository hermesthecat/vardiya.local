<?php

class Izin {
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

    // İzin talebi oluştur
    public function izinTalebiOlustur($personelId, $izinTipi, $baslangicTarihi, $bitisTarihi, $aciklama = '') {
        try {
            $this->db->beginTransaction();

            // İzin gün sayısını hesapla
            $gunSayisi = ($bitisTarihi - $baslangicTarihi) / 86400;
            if ($gunSayisi < 0) {
                throw new Exception('Bitiş tarihi başlangıç tarihinden önce olamaz.');
            }

            // İzin hakkı kontrolü
            $izinHakki = $this->izinHakkiKontrol($personelId, $izinTipi, $gunSayisi);
            if (!$izinHakki['yeterli']) {
                throw new Exception($izinHakki['mesaj']);
            }

            $izinId = uniqid();
            $sql = "INSERT INTO izinler (
                        id, personel_id, izin_tipi, baslangic_tarihi,
                        bitis_tarihi, gun_sayisi, aciklama, durum,
                        olusturma_tarihi
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'beklemede', UNIX_TIMESTAMP())";

            $this->db->query($sql, [
                $izinId,
                $personelId,
                $izinTipi,
                $baslangicTarihi,
                $bitisTarihi,
                $gunSayisi,
                $aciklama
            ]);

            // Yöneticilere bildirim gönder
            $bildirim = Bildirim::getInstance();
            $sql = "SELECT p.id 
                    FROM personel p 
                    INNER JOIN roller r ON p.rol_id = r.id 
                    WHERE r.kod IN ('ik_yonetici', 'yonetici') 
                    AND p.durum = 'aktif'";
            
            $yoneticiler = $this->db->fetchAll($sql);
            
            foreach ($yoneticiler as $yonetici) {
                $bildirim->olustur(
                    $yonetici['id'],
                    'Yeni İzin Talebi',
                    "$gunSayisi günlük $izinTipi izin talebi.",
                    'izin',
                    "izin.php?id=$izinId"
                );
            }

            $this->db->commit();
            $this->core->islemLogKaydet(
                'izin_talebi_olustur', 
                "İzin talebi oluşturuldu: $izinId - $izinTipi ($gunSayisi gün)"
            );
            return $izinId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("İzin talebi oluşturulurken hata: " . $e->getMessage());
        }
    }

    // İzin hakkı kontrolü
    protected function izinHakkiKontrol($personelId, $izinTipi, $talep) {
        try {
            // Yıllık izin kontrolü
            if ($izinTipi === 'yillik') {
                $sql = "SELECT 
                        COALESCE(yillik_izin_hakki, 0) as toplam_hak,
                        COALESCE((
                            SELECT SUM(gun_sayisi)
                            FROM izinler
                            WHERE personel_id = p.id
                            AND izin_tipi = 'yillik'
                            AND durum = 'onaylandi'
                            AND YEAR(FROM_UNIXTIME(baslangic_tarihi)) = YEAR(CURRENT_DATE)
                        ), 0) as kullanilan
                        FROM personel p
                        WHERE id = ?";

                $izinDurum = $this->db->fetch($sql, [$personelId]);
                $kalanHak = $izinDurum['toplam_hak'] - $izinDurum['kullanilan'];

                if ($kalanHak < $talep) {
                    return [
                        'yeterli' => false,
                        'mesaj' => "Yeterli izin hakkınız bulunmamaktadır. Kalan hak: $kalanHak gün"
                    ];
                }
            }

            return ['yeterli' => true, 'mesaj' => ''];
        } catch (Exception $e) {
            throw new Exception("İzin hakkı kontrolünde hata: " . $e->getMessage());
        }
    }

    // İzin durumunu güncelle
    public function izinDurumGuncelle($izinId, $durum, $aciklama = null) {
        try {
            $this->db->beginTransaction();

            $izin = $this->db->fetch("SELECT * FROM izinler WHERE id = ?", [$izinId]);
            if (!$izin) {
                throw new Exception('İzin talebi bulunamadı.');
            }

            $sql = "UPDATE izinler 
                    SET durum = ?,
                        durum_aciklama = ?,
                        guncelleme_tarihi = UNIX_TIMESTAMP()
                    WHERE id = ?";

            $this->db->query($sql, [$durum, $aciklama, $izinId]);

            // İzin sahibine bildirim gönder
            $bildirim = Bildirim::getInstance();
            $durumText = $durum === 'onaylandi' ? 'onaylandı' : 
                        ($durum === 'reddedildi' ? 'reddedildi' : 'güncellendi');

            $bildirim->olustur(
                $izin['personel_id'],
                'İzin Talebi Durumu Güncellendi',
                "İzin talebiniz $durumText." . ($aciklama ? " Açıklama: $aciklama" : ''),
                'izin',
                "izin.php?id=$izinId"
            );

            $this->db->commit();
            $this->core->islemLogKaydet(
                'izin_durum_guncelle', 
                "İzin durumu güncellendi: $izinId - $durum"
            );
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("İzin durumu güncellenirken hata: " . $e->getMessage());
        }
    }

    // İzin detaylarını getir
    public function izinDetayGetir($izinId) {
        try {
            $sql = "SELECT i.*,
                    CONCAT(p.ad, ' ', p.soyad) as personel_adi,
                    p.yillik_izin_hakki
                    FROM izinler i
                    LEFT JOIN personel p ON i.personel_id = p.id
                    WHERE i.id = ?";

            $izin = $this->db->fetch($sql, [$izinId]);
            if (!$izin) {
                throw new Exception('İzin talebi bulunamadı.');
            }

            return $izin;
        } catch (Exception $e) {
            throw new Exception("İzin detayları getirilirken hata: " . $e->getMessage());
        }
    }

    // İzinleri listele
    public function izinleriGetir($limit = 50, $offset = 0, $filtreler = []) {
        try {
            $params = [];
            $where = [];

            if (!empty($filtreler['personel_id'])) {
                $where[] = "i.personel_id = ?";
                $params[] = $filtreler['personel_id'];
            }

            if (!empty($filtreler['durum'])) {
                $where[] = "i.durum = ?";
                $params[] = $filtreler['durum'];
            }

            if (!empty($filtreler['izin_tipi'])) {
                $where[] = "i.izin_tipi = ?";
                $params[] = $filtreler['izin_tipi'];
            }

            if (!empty($filtreler['baslangic_tarihi'])) {
                $where[] = "i.baslangic_tarihi >= ?";
                $params[] = $filtreler['baslangic_tarihi'];
            }

            if (!empty($filtreler['bitis_tarihi'])) {
                $where[] = "i.bitis_tarihi <= ?";
                $params[] = $filtreler['bitis_tarihi'];
            }

            $whereStr = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

            $sql = "SELECT i.*,
                    CONCAT(p.ad, ' ', p.soyad) as personel_adi
                    FROM izinler i
                    LEFT JOIN personel p ON i.personel_id = p.id
                    $whereStr
                    ORDER BY i.olusturma_tarihi DESC
                    LIMIT ? OFFSET ?";

            $params[] = $limit;
            $params[] = $offset;

            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            throw new Exception("İzinler getirilirken hata: " . $e->getMessage());
        }
    }

    // İzin iptal et
    public function izinIptalEt($izinId, $aciklama = '') {
        try {
            $this->db->beginTransaction();

            $izin = $this->db->fetch("SELECT * FROM izinler WHERE id = ?", [$izinId]);
            if (!$izin) {
                throw new Exception('İzin talebi bulunamadı.');
            }

            if ($izin['durum'] !== 'onaylandi') {
                throw new Exception('Sadece onaylanmış izinler iptal edilebilir.');
            }

            // İzin başlangıç tarihinden önce mi kontrol et
            if ($izin['baslangic_tarihi'] <= time()) {
                throw new Exception('Başlamış veya tamamlanmış izinler iptal edilemez.');
            }

            $sql = "UPDATE izinler 
                    SET durum = 'iptal_edildi',
                        durum_aciklama = ?,
                        guncelleme_tarihi = UNIX_TIMESTAMP()
                    WHERE id = ?";

            $this->db->query($sql, [$aciklama, $izinId]);

            // Yöneticilere bildirim gönder
            $bildirim = Bildirim::getInstance();
            $sql = "SELECT p.id 
                    FROM personel p 
                    INNER JOIN roller r ON p.rol_id = r.id 
                    WHERE r.kod IN ('ik_yonetici', 'yonetici') 
                    AND p.durum = 'aktif'";
            
            $yoneticiler = $this->db->fetchAll($sql);
            
            foreach ($yoneticiler as $yonetici) {
                $bildirim->olustur(
                    $yonetici['id'],
                    'İzin İptali',
                    "Bir izin talebi iptal edildi.",
                    'izin',
                    "izin.php?id=$izinId"
                );
            }

            $this->db->commit();
            $this->core->islemLogKaydet(
                'izin_iptal', 
                "İzin iptal edildi: $izinId"
            );
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("İzin iptal edilirken hata: " . $e->getMessage());
        }
    }

    // Personelin izin bakiyesini getir
    public function izinBakiyesiGetir($personelId) {
        try {
            $sql = "SELECT 
                    p.yillik_izin_hakki as toplam_hak,
                    COALESCE((
                        SELECT SUM(gun_sayisi)
                        FROM izinler
                        WHERE personel_id = p.id
                        AND izin_tipi = 'yillik'
                        AND durum = 'onaylandi'
                        AND YEAR(FROM_UNIXTIME(baslangic_tarihi)) = YEAR(CURRENT_DATE)
                    ), 0) as kullanilan
                    FROM personel p
                    WHERE id = ?";

            $izinDurum = $this->db->fetch($sql, [$personelId]);
            if (!$izinDurum) {
                throw new Exception('Personel bulunamadı.');
            }

            $izinDurum['kalan'] = $izinDurum['toplam_hak'] - $izinDurum['kullanilan'];
            return $izinDurum;
        } catch (Exception $e) {
            throw new Exception("İzin bakiyesi getirilirken hata: " . $e->getMessage());
        }
    }
} 