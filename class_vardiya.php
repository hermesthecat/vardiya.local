<?php

class Vardiya {
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

    // Vardiya oluştur
    public function vardiyaOlustur($personelId, $baslangicTarihi, $bitisTarihi, $vardiyaTipi, $aciklama = '') {
        try {
            $this->db->beginTransaction();

            // Tarih kontrolü
            if ($baslangicTarihi >= $bitisTarihi) {
                throw new Exception('Bitiş tarihi başlangıç tarihinden sonra olmalıdır.');
            }

            // İzin çakışması kontrolü
            $izinCakisma = $this->db->fetch(
                "SELECT COUNT(*) as sayi 
                FROM izinler 
                WHERE personel_id = ? 
                AND durum = 'onaylandi'
                AND (
                    (baslangic_tarihi <= ? AND bitis_tarihi >= ?) OR
                    (baslangic_tarihi <= ? AND bitis_tarihi >= ?) OR
                    (baslangic_tarihi >= ? AND bitis_tarihi <= ?)
                )",
                [
                    $personelId,
                    $baslangicTarihi, $baslangicTarihi,
                    $bitisTarihi, $bitisTarihi,
                    $baslangicTarihi, $bitisTarihi
                ]
            );

            if ($izinCakisma['sayi'] > 0) {
                throw new Exception('Seçilen tarih aralığında onaylanmış izin bulunmaktadır.');
            }

            // Vardiya çakışması kontrolü
            $vardiyaCakisma = $this->db->fetch(
                "SELECT COUNT(*) as sayi 
                FROM vardiyalar 
                WHERE personel_id = ? 
                AND (
                    (baslangic_tarihi <= ? AND bitis_tarihi >= ?) OR
                    (baslangic_tarihi <= ? AND bitis_tarihi >= ?) OR
                    (baslangic_tarihi >= ? AND bitis_tarihi <= ?)
                )",
                [
                    $personelId,
                    $baslangicTarihi, $baslangicTarihi,
                    $bitisTarihi, $bitisTarihi,
                    $baslangicTarihi, $bitisTarihi
                ]
            );

            if ($vardiyaCakisma['sayi'] > 0) {
                throw new Exception('Seçilen tarih aralığında başka bir vardiya bulunmaktadır.');
            }

            $vardiyaId = uniqid();
            $sql = "INSERT INTO vardiyalar (
                        id, personel_id, baslangic_tarihi, bitis_tarihi,
                        vardiya_tipi, aciklama, olusturma_tarihi
                    ) VALUES (?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP())";

            $this->db->query($sql, [
                $vardiyaId,
                $personelId,
                $baslangicTarihi,
                $bitisTarihi,
                $vardiyaTipi,
                $aciklama
            ]);

            // Personele bildirim gönder
            $bildirim = Bildirim::getInstance();
            $bildirim->olustur(
                $personelId,
                'Yeni Vardiya Ataması',
                "Size yeni bir vardiya ataması yapıldı.",
                'vardiya',
                "vardiya.php?id=$vardiyaId"
            );

            $this->db->commit();
            $this->core->islemLogKaydet(
                'vardiya_olustur', 
                "Vardiya oluşturuldu: $vardiyaId - $vardiyaTipi"
            );
            return $vardiyaId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Vardiya oluşturulurken hata: " . $e->getMessage());
        }
    }

    // Vardiya güncelle
    public function vardiyaGuncelle($vardiyaId, $baslangicTarihi, $bitisTarihi, $vardiyaTipi, $aciklama = '') {
        try {
            $this->db->beginTransaction();

            $vardiya = $this->db->fetch("SELECT * FROM vardiyalar WHERE id = ?", [$vardiyaId]);
            if (!$vardiya) {
                throw new Exception('Vardiya bulunamadı.');
            }

            // Tarih kontrolü
            if ($baslangicTarihi >= $bitisTarihi) {
                throw new Exception('Bitiş tarihi başlangıç tarihinden sonra olmalıdır.');
            }

            // İzin çakışması kontrolü
            $izinCakisma = $this->db->fetch(
                "SELECT COUNT(*) as sayi 
                FROM izinler 
                WHERE personel_id = ? 
                AND durum = 'onaylandi'
                AND (
                    (baslangic_tarihi <= ? AND bitis_tarihi >= ?) OR
                    (baslangic_tarihi <= ? AND bitis_tarihi >= ?) OR
                    (baslangic_tarihi >= ? AND bitis_tarihi <= ?)
                )",
                [
                    $vardiya['personel_id'],
                    $baslangicTarihi, $baslangicTarihi,
                    $bitisTarihi, $bitisTarihi,
                    $baslangicTarihi, $bitisTarihi
                ]
            );

            if ($izinCakisma['sayi'] > 0) {
                throw new Exception('Seçilen tarih aralığında onaylanmış izin bulunmaktadır.');
            }

            // Vardiya çakışması kontrolü (kendisi hariç)
            $vardiyaCakisma = $this->db->fetch(
                "SELECT COUNT(*) as sayi 
                FROM vardiyalar 
                WHERE personel_id = ? 
                AND id != ?
                AND (
                    (baslangic_tarihi <= ? AND bitis_tarihi >= ?) OR
                    (baslangic_tarihi <= ? AND bitis_tarihi >= ?) OR
                    (baslangic_tarihi >= ? AND bitis_tarihi <= ?)
                )",
                [
                    $vardiya['personel_id'],
                    $vardiyaId,
                    $baslangicTarihi, $baslangicTarihi,
                    $bitisTarihi, $bitisTarihi,
                    $baslangicTarihi, $bitisTarihi
                ]
            );

            if ($vardiyaCakisma['sayi'] > 0) {
                throw new Exception('Seçilen tarih aralığında başka bir vardiya bulunmaktadır.');
            }

            $sql = "UPDATE vardiyalar 
                    SET baslangic_tarihi = ?,
                        bitis_tarihi = ?,
                        vardiya_tipi = ?,
                        aciklama = ?,
                        guncelleme_tarihi = UNIX_TIMESTAMP()
                    WHERE id = ?";

            $this->db->query($sql, [
                $baslangicTarihi,
                $bitisTarihi,
                $vardiyaTipi,
                $aciklama,
                $vardiyaId
            ]);

            // Personele bildirim gönder
            $bildirim = Bildirim::getInstance();
            $bildirim->olustur(
                $vardiya['personel_id'],
                'Vardiya Güncellemesi',
                "Vardiyanızda güncelleme yapıldı.",
                'vardiya',
                "vardiya.php?id=$vardiyaId"
            );

            $this->db->commit();
            $this->core->islemLogKaydet(
                'vardiya_guncelle', 
                "Vardiya güncellendi: $vardiyaId"
            );
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Vardiya güncellenirken hata: " . $e->getMessage());
        }
    }

    // Vardiya sil
    public function vardiyaSil($vardiyaId) {
        try {
            $this->db->beginTransaction();

            $vardiya = $this->db->fetch("SELECT * FROM vardiyalar WHERE id = ?", [$vardiyaId]);
            if (!$vardiya) {
                throw new Exception('Vardiya bulunamadı.');
            }

            // Başlamış vardiyaları silmeyi engelle
            if ($vardiya['baslangic_tarihi'] <= time()) {
                throw new Exception('Başlamış veya tamamlanmış vardiyalar silinemez.');
            }

            $this->db->query("DELETE FROM vardiyalar WHERE id = ?", [$vardiyaId]);

            // Personele bildirim gönder
            $bildirim = Bildirim::getInstance();
            $bildirim->olustur(
                $vardiya['personel_id'],
                'Vardiya İptali',
                "Bir vardiyanız iptal edildi.",
                'vardiya',
                "vardiyalar.php"
            );

            $this->db->commit();
            $this->core->islemLogKaydet(
                'vardiya_sil', 
                "Vardiya silindi: $vardiyaId"
            );
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Vardiya silinirken hata: " . $e->getMessage());
        }
    }

    // Vardiya detaylarını getir
    public function vardiyaDetayGetir($vardiyaId) {
        try {
            $sql = "SELECT v.*,
                    CONCAT(p.ad, ' ', p.soyad) as personel_adi
                    FROM vardiyalar v
                    LEFT JOIN personel p ON v.personel_id = p.id
                    WHERE v.id = ?";

            $vardiya = $this->db->fetch($sql, [$vardiyaId]);
            if (!$vardiya) {
                throw new Exception('Vardiya bulunamadı.');
            }

            return $vardiya;
        } catch (Exception $e) {
            throw new Exception("Vardiya detayları getirilirken hata: " . $e->getMessage());
        }
    }

    // Vardiyaları listele
    public function vardiyalariGetir($limit = 50, $offset = 0, $filtreler = []) {
        try {
            $params = [];
            $where = [];

            if (!empty($filtreler['personel_id'])) {
                $where[] = "v.personel_id = ?";
                $params[] = $filtreler['personel_id'];
            }

            if (!empty($filtreler['vardiya_tipi'])) {
                $where[] = "v.vardiya_tipi = ?";
                $params[] = $filtreler['vardiya_tipi'];
            }

            if (!empty($filtreler['baslangic_tarihi'])) {
                $where[] = "v.baslangic_tarihi >= ?";
                $params[] = $filtreler['baslangic_tarihi'];
            }

            if (!empty($filtreler['bitis_tarihi'])) {
                $where[] = "v.bitis_tarihi <= ?";
                $params[] = $filtreler['bitis_tarihi'];
            }

            $whereStr = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

            $sql = "SELECT v.*,
                    CONCAT(p.ad, ' ', p.soyad) as personel_adi
                    FROM vardiyalar v
                    LEFT JOIN personel p ON v.personel_id = p.id
                    $whereStr
                    ORDER BY v.baslangic_tarihi ASC
                    LIMIT ? OFFSET ?";

            $params[] = $limit;
            $params[] = $offset;

            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            throw new Exception("Vardiyalar getirilirken hata: " . $e->getMessage());
        }
    }

    // Personelin vardiya planını getir
    public function personelVardiyaPlaniniGetir($personelId, $baslangicTarihi, $bitisTarihi) {
        try {
            $sql = "SELECT v.*,
                    CONCAT(p.ad, ' ', p.soyad) as personel_adi
                    FROM vardiyalar v
                    LEFT JOIN personel p ON v.personel_id = p.id
                    WHERE v.personel_id = ?
                    AND v.baslangic_tarihi >= ?
                    AND v.bitis_tarihi <= ?
                    ORDER BY v.baslangic_tarihi ASC";

            return $this->db->fetchAll($sql, [
                $personelId,
                $baslangicTarihi,
                $bitisTarihi
            ]);
        } catch (Exception $e) {
            throw new Exception("Vardiya planı getirilirken hata: " . $e->getMessage());
        }
    }

    // Vardiya türlerini getir
    public function vardiyaTurleriniGetir() {
        try {
            $sql = "SELECT * FROM vardiya_turleri ORDER BY sira";
            $vardiyaTurleri = $this->db->fetchAll($sql);
            
            if (empty($vardiyaTurleri)) {
                // Varsayılan vardiya türlerini ekle
                $varsayilanTurler = [
                    [
                        'id' => 'sabah',
                        'ad' => 'Sabah',
                        'baslangic' => '08:00',
                        'bitis' => '16:00',
                        'etiket' => 'Sabah',
                        'renk' => '#4CAF50',
                        'sira' => 1,
                        'min_personel' => 2,
                        'max_personel' => 5
                    ],
                    [
                        'id' => 'aksam',
                        'ad' => 'Akşam',
                        'baslangic' => '16:00',
                        'bitis' => '24:00',
                        'etiket' => 'Akşam',
                        'renk' => '#2196F3',
                        'sira' => 2,
                        'min_personel' => 2,
                        'max_personel' => 4
                    ],
                    [
                        'id' => 'gece',
                        'ad' => 'Gece',
                        'baslangic' => '24:00',
                        'bitis' => '08:00',
                        'etiket' => 'Gece',
                        'renk' => '#9C27B0',
                        'sira' => 3,
                        'min_personel' => 1,
                        'max_personel' => 3
                    ]
                ];
                
                foreach ($varsayilanTurler as $tur) {
                    $this->db->query(
                        "INSERT INTO vardiya_turleri (id, ad, baslangic, bitis, etiket, renk, sira, min_personel, max_personel) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $tur['id'],
                            $tur['ad'],
                            $tur['baslangic'],
                            $tur['bitis'],
                            $tur['etiket'],
                            $tur['renk'],
                            $tur['sira'],
                            $tur['min_personel'],
                            $tur['max_personel']
                        ]
                    );
                }
                
                return $varsayilanTurler;
            }
            
            return $vardiyaTurleri;
        } catch (Exception $e) {
            throw new Exception("Vardiya türleri getirilirken hata: " . $e->getMessage());
        }
    }

    // Vardiya türü etiketini getir
    public function vardiyaTuruEtiketGetir($vardiyaTuru) {
        try {
            $sql = "SELECT etiket FROM vardiya_turleri WHERE id = ?";
            $vardiya = $this->db->fetch($sql, [$vardiyaTuru]);
            
            if (!$vardiya) {
                throw new Exception('Geçersiz vardiya türü: ' . $vardiyaTuru);
            }
            
            return $vardiya['etiket'];
        } catch (Exception $e) {
            throw new Exception("Vardiya türü etiketi getirilirken hata: " . $e->getMessage());
        }
    }

    // Vardiya saatlerini hesapla
    public function vardiyaSaatleriHesapla($vardiyaTuru) {
        try {
            $sql = "SELECT baslangic, bitis FROM vardiya_turleri WHERE id = ?";
            $vardiya = $this->db->fetch($sql, [$vardiyaTuru]);

            if (!$vardiya) {
                throw new Exception('Vardiya türü bulunamadı.');
            }

            // Saat formatını kontrol et
            if ($vardiya['baslangic'] === '24:00') {
                $vardiya['baslangic'] = '00:00';
            }
            if ($vardiya['bitis'] === '24:00') {
                $vardiya['bitis'] = '00:00';
            }

            $baslangicSaat = strtotime('1970-01-01 ' . $vardiya['baslangic']);
            $bitisSaat = strtotime('1970-01-01 ' . $vardiya['bitis']);

            // Gece vardiyası kontrolü
            $geceVardiyasi = false;
            if ($bitisSaat <= $baslangicSaat) {
                $bitisSaat = strtotime('+1 day', $bitisSaat);
                $geceVardiyasi = true;
            }

            // Toplam çalışma süresini hesapla (saat cinsinden)
            $calismaSuresi = ($bitisSaat - $baslangicSaat) / 3600;

            // Mola süresini hesapla ve düş
            $molaSuresi = $calismaSuresi >= 7.5 ? 0.5 : ($calismaSuresi >= 4 ? 0.25 : 0);
            $netCalismaSuresi = $calismaSuresi - $molaSuresi;

            return [
                'baslangic' => $vardiya['baslangic'],
                'bitis' => $vardiya['bitis'],
                'sure' => $netCalismaSuresi,
                'brut_sure' => $calismaSuresi,
                'mola_suresi' => $molaSuresi,
                'gece_vardiyasi' => $geceVardiyasi
            ];
        } catch (Exception $e) {
            throw new Exception("Vardiya saatleri hesaplanırken hata: " . $e->getMessage());
        }
    }

    // Günlük vardiyaları getir
    public function gunlukVardiyalariGetir($tarih) {
        try {
            // Tarihi timestamp'e çevir
            if (!is_numeric($tarih)) {
                $tarih = strtotime($tarih);
            }

            $sql = "SELECT v.*,
                    CONCAT(p.ad, ' ', p.soyad) as personel_adi,
                    vt.etiket as vardiya_adi,
                    vt.baslangic,
                    vt.bitis,
                    vt.renk
                    FROM vardiyalar v
                    LEFT JOIN personel p ON v.personel_id = p.id
                    LEFT JOIN vardiya_turleri vt ON v.vardiya_tipi = vt.id
                    WHERE DATE(FROM_UNIXTIME(v.baslangic_tarihi)) = DATE(FROM_UNIXTIME(?))
                    ORDER BY vt.sira, p.ad, p.soyad";

            return $this->db->fetchAll($sql, [$tarih]);
        } catch (Exception $e) {
            throw new Exception("Günlük vardiyalar getirilirken hata: " . $e->getMessage());
        }
    }

    // Aylık çalışma raporu
    public function aylikCalismaRaporu($ay, $yil) {
        try {
            $baslangicTarihi = mktime(0, 0, 0, $ay, 1, $yil);
            $bitisTarihi = mktime(23, 59, 59, $ay + 1, 0, $yil);

            $sql = "SELECT 
                    p.id as personel_id,
                    CONCAT(p.ad, ' ', p.soyad) as personel_adi,
                    COUNT(v.id) as toplam_vardiya,
                    SUM(
                        TIMESTAMPDIFF(HOUR, 
                            FROM_UNIXTIME(v.baslangic_tarihi), 
                            FROM_UNIXTIME(v.bitis_tarihi)
                        )
                    ) as toplam_saat,
                    SUM(CASE WHEN HOUR(FROM_UNIXTIME(v.baslangic_tarihi)) >= 22 
                            OR HOUR(FROM_UNIXTIME(v.baslangic_tarihi)) < 6 
                        THEN 1 ELSE 0 END) as gece_vardiyasi_sayisi,
                    SUM(CASE WHEN DAYOFWEEK(FROM_UNIXTIME(v.baslangic_tarihi)) IN (1,7) 
                        THEN 1 ELSE 0 END) as hafta_sonu_vardiyasi
                    FROM personel p
                    LEFT JOIN vardiyalar v ON p.id = v.personel_id 
                    AND v.baslangic_tarihi BETWEEN ? AND ?
                    GROUP BY p.id
                    ORDER BY p.ad, p.soyad";

            $rapor = $this->db->fetchAll($sql, [$baslangicTarihi, $bitisTarihi]);

            // Vardiya türlerine göre dağılımı hesapla
            foreach ($rapor as &$personel) {
                $sql = "SELECT 
                        v.vardiya_tipi,
                        COUNT(*) as sayi,
                        SUM(
                            TIMESTAMPDIFF(HOUR, 
                                FROM_UNIXTIME(v.baslangic_tarihi), 
                                FROM_UNIXTIME(v.bitis_tarihi)
                            )
                        ) as saat
                        FROM vardiyalar v
                        WHERE v.personel_id = ?
                        AND v.baslangic_tarihi BETWEEN ? AND ?
                        GROUP BY v.vardiya_tipi";

                $personel['vardiya_dagilimi'] = $this->db->fetchAll($sql, [
                    $personel['personel_id'],
                    $baslangicTarihi,
                    $bitisTarihi
                ]);
            }

            return $rapor;
        } catch (Exception $e) {
            throw new Exception("Aylık çalışma raporu oluşturulurken hata: " . $e->getMessage());
        }
    }

    // Akıllı vardiya önerisi oluştur
    public function akilliVardiyaOnerisiOlustur($tarih, $vardiyaTipi) {
        try {
            // Tarihi timestamp'e çevir
            if (!is_numeric($tarih)) {
                $tarih = strtotime($tarih);
            }

            // Vardiya türü bilgilerini al
            $vardiyaBilgisi = $this->db->fetch(
                "SELECT * FROM vardiya_turleri WHERE id = ?",
                [$vardiyaTipi]
            );

            if (!$vardiyaBilgisi) {
                throw new Exception('Geçersiz vardiya türü.');
            }

            // Tüm aktif personeli getir
            $sql = "SELECT p.*,
                    COALESCE((
                        SELECT COUNT(*) 
                        FROM vardiyalar v 
                        WHERE v.personel_id = p.id 
                        AND v.baslangic_tarihi >= UNIX_TIMESTAMP(DATE_SUB(FROM_UNIXTIME(?), INTERVAL 7 DAY))
                    ), 0) as son_7_gun_vardiya
                    FROM personel p
                    WHERE p.durum = 'aktif'";

            $personeller = $this->db->fetchAll($sql, [$tarih]);
            $oneriler = [];

            foreach ($personeller as $personel) {
                $puan = 100;

                // Son 7 gündeki vardiya sayısı kontrolü
                $puan -= ($personel['son_7_gun_vardiya'] * 10);

                // İzin kontrolü
                $izinVarMi = $this->db->fetch(
                    "SELECT COUNT(*) as sayi 
                    FROM izinler 
                    WHERE personel_id = ? 
                    AND ? BETWEEN baslangic_tarihi AND bitis_tarihi
                    AND durum = 'onaylandi'",
                    [$personel['id'], $tarih]
                );

                if ($izinVarMi['sayi'] > 0) {
                    continue; // İzinli personeli önerme
                }

                // Vardiya çakışması kontrolü
                $vardiyaCakismasi = $this->db->fetch(
                    "SELECT COUNT(*) as sayi 
                    FROM vardiyalar 
                    WHERE personel_id = ? 
                    AND ? BETWEEN baslangic_tarihi AND bitis_tarihi",
                    [$personel['id'], $tarih]
                );

                if ($vardiyaCakismasi['sayi'] > 0) {
                    continue; // Vardiya çakışması olan personeli önerme
                }

                // Hafta sonu kontrolü
                if (date('N', $tarih) >= 6) { // 6=Cumartesi, 7=Pazar
                    $puan -= 20;
                }

                // Gece vardiyası kontrolü
                if ($vardiyaBilgisi['baslangic'] >= '22:00' || $vardiyaBilgisi['bitis'] <= '06:00') {
                    $puan -= 15;
                }

                $oneriler[] = [
                    'personel_id' => $personel['id'],
                    'ad_soyad' => $personel['ad'] . ' ' . $personel['soyad'],
                    'puan' => $puan
                ];
            }

            // Puana göre sırala
            usort($oneriler, function($a, $b) {
                return $b['puan'] - $a['puan'];
            });

            return array_slice($oneriler, 0, 5); // En yüksek puanlı 5 öneriyi döndür
        } catch (Exception $e) {
            throw new Exception("Vardiya önerisi oluşturulurken hata: " . $e->getMessage());
        }
    }
} 