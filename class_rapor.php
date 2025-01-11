<?php

class Rapor {
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

    // Personel çalışma saatleri raporu
    public function personelCalismaSaatleri($personelId, $baslangicTarihi, $bitisTarihi) {
        try {
            $baslangicTimestamp = is_numeric($baslangicTarihi) ? $baslangicTarihi : strtotime($baslangicTarihi);
            $bitisTimestamp = is_numeric($bitisTarihi) ? $bitisTarihi : strtotime($bitisTarihi);

            $sql = "SELECT v.*, 
                    vt.ad as vardiya_adi,
                    vt.baslangic,
                    vt.bitis,
                    CONCAT(p.ad, ' ', p.soyad) as personel_adi
                    FROM vardiyalar v
                    LEFT JOIN vardiya_turleri vt ON v.vardiya_turu = vt.id
                    LEFT JOIN personel p ON v.personel_id = p.id
                    WHERE v.personel_id = ?
                    AND v.tarih BETWEEN ? AND ?
                    ORDER BY v.tarih";

            $vardiyalar = $this->db->fetchAll($sql, [
                $personelId,
                $baslangicTimestamp,
                $bitisTimestamp
            ]);

            $toplamSaat = 0;
            $vardiyaSayilari = [];
            $gunlukDetaylar = [];

            foreach ($vardiyalar as $vardiya) {
                $baslangic = strtotime($vardiya['baslangic']);
                $bitis = strtotime($vardiya['bitis']);

                if ($bitis < $baslangic) {
                    $bitis = strtotime('+1 day', $bitis);
                }

                $calismaSuresi = ($bitis - $baslangic) / 3600;
                $toplamSaat += $calismaSuresi;

                // Vardiya türü bazında sayıları tut
                $vardiyaSayilari[$vardiya['vardiya_turu']] = ($vardiyaSayilari[$vardiya['vardiya_turu']] ?? 0) + 1;

                // Günlük detayları kaydet
                $gunlukDetaylar[] = [
                    'tarih' => $this->core->tarihFormatla($vardiya['tarih']),
                    'vardiya_turu' => $vardiya['vardiya_adi'],
                    'baslangic' => $vardiya['baslangic'],
                    'bitis' => $vardiya['bitis'],
                    'saat' => $calismaSuresi
                ];
            }

            return [
                'personel_adi' => $vardiyalar[0]['personel_adi'] ?? null,
                'baslangic_tarihi' => $this->core->tarihFormatla($baslangicTimestamp),
                'bitis_tarihi' => $this->core->tarihFormatla($bitisTimestamp),
                'toplam_saat' => $toplamSaat,
                'vardiya_sayilari' => $vardiyaSayilari,
                'gunluk_detaylar' => $gunlukDetaylar
            ];
        } catch (Exception $e) {
            throw new Exception("Çalışma saatleri raporu oluşturulurken hata: " . $e->getMessage());
        }
    }

    // İzin kullanım raporu
    public function izinKullanimRaporu($personelId, $baslangicTarihi, $bitisTarihi) {
        try {
            $baslangicTimestamp = is_numeric($baslangicTarihi) ? $baslangicTarihi : strtotime($baslangicTarihi);
            $bitisTimestamp = is_numeric($bitisTarihi) ? $bitisTarihi : strtotime($bitisTarihi);

            // İzin haklarını getir
            $izinHaklari = $this->db->fetchAll(
                "SELECT * FROM izin_haklari WHERE personel_id = ?",
                [$personelId]
            );

            // Kullanılan izinleri getir
            $sql = "SELECT i.*, 
                    it.ad as izin_turu_adi,
                    CONCAT(p.ad, ' ', p.soyad) as personel_adi,
                    CONCAT(op.ad, ' ', op.soyad) as onaylayan_adi
                    FROM izinler i
                    LEFT JOIN izin_turleri it ON i.izin_turu = it.id
                    LEFT JOIN personel p ON i.personel_id = p.id
                    LEFT JOIN personel op ON i.onaylayan_id = op.id
                    WHERE i.personel_id = ?
                    AND i.baslangic_tarihi BETWEEN ? AND ?
                    ORDER BY i.baslangic_tarihi";

            $izinler = $this->db->fetchAll($sql, [
                $personelId,
                $baslangicTimestamp,
                $bitisTimestamp
            ]);

            $izinDetaylari = [];
            $toplamGun = 0;
            $izinTuruBazindaGunler = [];

            foreach ($izinler as $izin) {
                $gunSayisi = floor(($izin['bitis_tarihi'] - $izin['baslangic_tarihi']) / (60 * 60 * 24)) + 1;
                $toplamGun += $gunSayisi;

                // İzin türü bazında günleri tut
                $izinTuruBazindaGunler[$izin['izin_turu']] = ($izinTuruBazindaGunler[$izin['izin_turu']] ?? 0) + $gunSayisi;

                // İzin detaylarını kaydet
                $izinDetaylari[] = [
                    'baslangic' => $this->core->tarihFormatla($izin['baslangic_tarihi']),
                    'bitis' => $this->core->tarihFormatla($izin['bitis_tarihi']),
                    'gun_sayisi' => $gunSayisi,
                    'izin_turu' => $izin['izin_turu_adi'],
                    'aciklama' => $izin['aciklama'],
                    'onaylayan' => $izin['onaylayan_adi'],
                    'onay_tarihi' => $this->core->tarihFormatla($izin['onay_tarihi'])
                ];
            }

            // İzin haklarını düzenle
            $haklarDurum = [];
            foreach ($izinHaklari as $hak) {
                $haklarDurum[$hak['izin_turu']] = [
                    'toplam' => $hak['toplam'],
                    'kullanilan' => $hak['kullanilan'],
                    'kalan' => $hak['kalan']
                ];
            }

            return [
                'personel_adi' => $izinler[0]['personel_adi'] ?? null,
                'baslangic_tarihi' => $this->core->tarihFormatla($baslangicTimestamp),
                'bitis_tarihi' => $this->core->tarihFormatla($bitisTimestamp),
                'toplam_izin_gunu' => $toplamGun,
                'izin_turu_bazinda_gunler' => $izinTuruBazindaGunler,
                'izin_haklari' => $haklarDurum,
                'izin_detaylari' => $izinDetaylari
            ];
        } catch (Exception $e) {
            throw new Exception("İzin kullanım raporu oluşturulurken hata: " . $e->getMessage());
        }
    }

    // Vardiya dağılım raporu
    public function vardiyaDagilimRaporu($baslangicTarihi, $bitisTarihi) {
        try {
            $baslangicTimestamp = is_numeric($baslangicTarihi) ? $baslangicTarihi : strtotime($baslangicTarihi);
            $bitisTimestamp = is_numeric($bitisTarihi) ? $bitisTarihi : strtotime($bitisTarihi);

            // Vardiya türlerini getir
            $vardiyaTurleri = $this->db->fetchAll("SELECT * FROM vardiya_turleri ORDER BY sira");
            
            // Personel bazında vardiya dağılımını getir
            $sql = "SELECT 
                    p.id as personel_id,
                    CONCAT(p.ad, ' ', p.soyad) as personel_adi,
                    v.vardiya_turu,
                    vt.ad as vardiya_adi,
                    COUNT(*) as vardiya_sayisi
                    FROM vardiyalar v
                    LEFT JOIN personel p ON v.personel_id = p.id
                    LEFT JOIN vardiya_turleri vt ON v.vardiya_turu = vt.id
                    WHERE v.tarih BETWEEN ? AND ?
                    GROUP BY p.id, v.vardiya_turu
                    ORDER BY p.ad, p.soyad, vt.sira";

            $vardiyalar = $this->db->fetchAll($sql, [
                $baslangicTimestamp,
                $bitisTimestamp
            ]);

            // Sonuçları düzenle
            $personelBazindaDagilim = [];
            $toplamVardiyalar = array_fill_keys(array_column($vardiyaTurleri, 'id'), 0);

            foreach ($vardiyalar as $vardiya) {
                if (!isset($personelBazindaDagilim[$vardiya['personel_id']])) {
                    $personelBazindaDagilim[$vardiya['personel_id']] = [
                        'personel_adi' => $vardiya['personel_adi'],
                        'vardiyalar' => array_fill_keys(array_column($vardiyaTurleri, 'id'), 0)
                    ];
                }

                $personelBazindaDagilim[$vardiya['personel_id']]['vardiyalar'][$vardiya['vardiya_turu']] = $vardiya['vardiya_sayisi'];
                $toplamVardiyalar[$vardiya['vardiya_turu']] += $vardiya['vardiya_sayisi'];
            }

            return [
                'baslangic_tarihi' => $this->core->tarihFormatla($baslangicTimestamp),
                'bitis_tarihi' => $this->core->tarihFormatla($bitisTimestamp),
                'vardiya_turleri' => $vardiyaTurleri,
                'personel_bazinda_dagilim' => $personelBazindaDagilim,
                'toplam_vardiyalar' => $toplamVardiyalar
            ];
        } catch (Exception $e) {
            throw new Exception("Vardiya dağılım raporu oluşturulurken hata: " . $e->getMessage());
        }
    }

    // İşlem log raporu
    public function islemLogRaporu($baslangicTarihi, $bitisTarihi, $islemTuru = null, $kullaniciId = null) {
        try {
            $params = [];
            $where = [];

            $baslangicTimestamp = is_numeric($baslangicTarihi) ? $baslangicTarihi : strtotime($baslangicTarihi);
            $bitisTimestamp = is_numeric($bitisTarihi) ? $bitisTarihi : strtotime($bitisTarihi);

            $where[] = "l.tarih BETWEEN ? AND ?";
            $params[] = $baslangicTimestamp;
            $params[] = $bitisTimestamp;

            if ($islemTuru) {
                $where[] = "l.islem_turu = ?";
                $params[] = $islemTuru;
            }

            if ($kullaniciId) {
                $where[] = "l.kullanici_id = ?";
                $params[] = $kullaniciId;
            }

            $whereStr = implode(" AND ", $where);

            $sql = "SELECT l.*, 
                    CONCAT(p.ad, ' ', p.soyad) as kullanici_adi
                    FROM islem_log l
                    LEFT JOIN personel p ON l.kullanici_id = p.id
                    WHERE $whereStr
                    ORDER BY l.tarih DESC";

            $loglar = $this->db->fetchAll($sql, $params);

            // İşlem türü bazında istatistikler
            $islemTuruBazindaSayilar = [];
            foreach ($loglar as $log) {
                $islemTuruBazindaSayilar[$log['islem_turu']] = ($islemTuruBazindaSayilar[$log['islem_turu']] ?? 0) + 1;
            }

            return [
                'baslangic_tarihi' => $this->core->tarihFormatla($baslangicTimestamp),
                'bitis_tarihi' => $this->core->tarihFormatla($bitisTimestamp),
                'toplam_islem' => count($loglar),
                'islem_turu_bazinda_sayilar' => $islemTuruBazindaSayilar,
                'islem_detaylari' => array_map(function($log) {
                    return [
                        'tarih' => $this->core->tarihFormatla($log['tarih'], 'tam_saat'),
                        'kullanici' => $log['kullanici_adi'],
                        'islem_turu' => $log['islem_turu'],
                        'aciklama' => $log['aciklama'],
                        'ip_adresi' => $log['ip_adresi'],
                        'tarayici' => $log['tarayici']
                    ];
                }, $loglar)
            ];
        } catch (Exception $e) {
            throw new Exception("İşlem log raporu oluşturulurken hata: " . $e->getMessage());
        }
    }
} 