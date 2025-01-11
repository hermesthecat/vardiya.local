<?php
/**
 * Vardiya sınıfı - Vardiya işlemlerini yönetir
 * PHP 7.4+
 */
class Vardiya
{
    /**
     * Vardiya silme
     */
    public function sil($vardiyaId)
    {
        $data = veriOku();
        $data['vardiyalar'] = array_filter($data['vardiyalar'], function ($vardiya) use ($vardiyaId) {
            return $vardiya['id'] !== $vardiyaId;
        });
        $this->veriYaz($data);
    }

    /**
     * Vardiya düzenleme
     */
    public function duzenle($vardiyaId, $personelId, $tarih, $vardiyaTuru, $notlar = '')
    {
        // Vardiya çakışması kontrolü
        if ($this->cakismaVarMi($personelId, $tarih, $vardiyaTuru, $vardiyaId)) {
            throw new Exception('Bu personelin seçilen tarihte başka bir vardiyası bulunuyor.');
        }

        // Ardışık çalışma günlerini kontrol et
        if (!$this->ardisikCalismaGunleriniKontrolEt($personelId, $tarih)) {
            throw new Exception('Bu personel 6 gün üst üste çalıştığı için bu tarihe vardiya eklenemez. En az 1 gün izin kullanması gerekiyor.');
        }

        // Tarihi timestamp'e çevir
        if (!is_numeric($tarih)) {
            $tarih = strtotime($tarih);
        }

        $data = veriOku();
        $vardiyaBulundu = false;

        foreach ($data['vardiyalar'] as &$vardiya) {
            if ($vardiya['id'] === $vardiyaId) {
                $vardiya['personel_id'] = $personelId;
                $vardiya['tarih'] = $tarih;
                $vardiya['vardiya_turu'] = $vardiyaTuru;
                $vardiya['notlar'] = $notlar;
                $vardiyaBulundu = true;
                break;
            }
        }

        if (!$vardiyaBulundu) {
            throw new Exception('Vardiya bulunamadı.');
        }

        $this->veriYaz($data);
        islemLogKaydet('vardiya_duzenle', "Vardiya düzenlendi: ID: $vardiyaId, Personel: $personelId, Tarih: " . date('Y-m-d', $tarih));
    }

    /**
     * Vardiya çakışması kontrolü
     */
    public function cakismaVarMi($personelId, $tarih, $vardiyaTuru, $haricVardiyaId = null)
    {
        // Tarihi timestamp'e çevir
        if (!is_numeric($tarih)) {
            $tarih = strtotime($tarih);
        }

        $data = veriOku();
        foreach ($data['vardiyalar'] as $vardiya) {
            if ($vardiya['personel_id'] === $personelId && $vardiya['id'] !== $haricVardiyaId) {
                $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);
                if (date('Y-m-d', $vardiyaTarihi) === date('Y-m-d', $tarih)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Vardiya değişim talebi oluşturma
     */
    public function degisimTalebiOlustur($vardiyaId, $talepEdenPersonelId, $aciklama)
    {
        $data = veriOku();

        // Vardiya kontrolü
        $vardiya = null;
        foreach ($data['vardiyalar'] as $v) {
            if ($v['id'] === $vardiyaId) {
                $vardiya = $v;
                break;
            }
        }

        if (!$vardiya) {
            throw new Exception('Vardiya bulunamadı.');
        }

        // Kendi vardiyası için talep oluşturamaz
        if ($vardiya['personel_id'] === $talepEdenPersonelId) {
            throw new Exception('Kendi vardiyanız için değişim talebi oluşturamazsınız.');
        }

        $yeniTalep = [
            'id' => uniqid(),
            'vardiya_id' => $vardiyaId,
            'talep_eden_personel_id' => $talepEdenPersonelId,
            'durum' => 'beklemede',
            'aciklama' => $aciklama,
            'olusturma_tarihi' => time(),
            'guncelleme_tarihi' => time()
        ];

        if (!isset($data['vardiya_talepleri'])) {
            $data['vardiya_talepleri'] = [];
        }

        $data['vardiya_talepleri'][] = $yeniTalep;
        $this->veriYaz($data);

        islemLogKaydet('vardiya_talep', "Vardiya değişim talebi oluşturuldu: Vardiya ID: $vardiyaId");
        return $yeniTalep['id'];
    }

    /**
     * Vardiya değişim talebini onayla/reddet
     */
    public function talepGuncelle($talepId, $durum, $yoneticiNotu = '')
    {
        $data = veriOku();
        $talep = null;

        foreach ($data['vardiya_talepleri'] as &$talep) {
            if ($talep['id'] === $talepId) {
                $talep['durum'] = $durum;
                $talep['yonetici_notu'] = $yoneticiNotu;
                $talep['guncelleme_tarihi'] = time();
                break;
            }
        }

        if ($talep === null) {
            throw new Exception('Vardiya talebi bulunamadı.');
        }

        $this->veriYaz($data);
        islemLogKaydet('vardiya_talebi_guncelle', "Vardiya talebi güncellendi: $talepId - Durum: $durum");
        return true;
    }

    /**
     * Vardiya detaylarını getir
     */
    public function detayGetir($vardiyaId)
    {
        $data = veriOku();
        foreach ($data['vardiyalar'] as $vardiya) {
            if ($vardiya['id'] === $vardiyaId) {
                $personel = array_filter($data['personel'], function ($p) use ($vardiya) {
                    return $p['id'] === $vardiya['personel_id'];
                });
                $personel = reset($personel);

                return [
                    'id' => $vardiya['id'],
                    'personel_id' => $vardiya['personel_id'],
                    'personel_ad' => $personel['ad'] . ' ' . $personel['soyad'],
                    'tarih' => $vardiya['tarih'],
                    'vardiya_turu' => $vardiya['vardiya_turu'],
                    'notlar' => $vardiya['notlar'] ?? ''
                ];
            }
        }
        return null;
    }

    /**
     * Ardışık çalışma günlerini kontrol et
     */
    private function ardisikCalismaGunleriniKontrolEt($personelId, $tarih)
    {
        $data = veriOku();

        // Tarihi timestamp'e çevir
        if (!is_numeric($tarih)) {
            $tarih = strtotime($tarih);
        }

        $ardisikGunler = 0;

        // Seçilen tarihten geriye doğru 6 günü kontrol et
        for ($i = 0; $i < 6; $i++) {
            $kontrolTarihi = strtotime("-$i day", $tarih);

            foreach ($data['vardiyalar'] as $vardiya) {
                if ($vardiya['personel_id'] === $personelId) {
                    $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);
                    if (date('Y-m-d', $vardiyaTarihi) === date('Y-m-d', $kontrolTarihi)) {
                        $ardisikGunler++;
                        break;
                    }
                }
            }
        }

        // Eğer 6 gün üst üste çalışmışsa, 7. gün çalışamaz
        if ($ardisikGunler >= 6) {
            return false;
        }

        // Seçilen tarihten sonraki günleri de kontrol et
        for ($i = 1; $i < 6; $i++) {
            $kontrolTarihi = strtotime("+$i day", $tarih);

            foreach ($data['vardiyalar'] as $vardiya) {
                if ($vardiya['personel_id'] === $personelId) {
                    $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);
                    if (date('Y-m-d', $vardiyaTarihi) === date('Y-m-d', $kontrolTarihi)) {
                        $ardisikGunler++;
                        if ($ardisikGunler >= 6) {
                            return false;
                        }
                        break;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Vardiya ekleme
     */
    public function ekle($personelId, $tarih, $vardiyaTuru, $notlar = '')
    {
        // Vardiya çakışması kontrolü
        if ($this->cakismaVarMi($personelId, $tarih, $vardiyaTuru)) {
            throw new Exception('Bu personelin seçilen tarihte başka bir vardiyası bulunuyor.');
        }

        // Ardışık çalışma günlerini kontrol et
        if (!$this->ardisikCalismaGunleriniKontrolEt($personelId, $tarih)) {
            throw new Exception('Bu personel 6 gün üst üste çalıştığı için bu tarihe vardiya eklenemez. En az 1 gün izin kullanması gerekiyor.');
        }

        // Tarihi timestamp'e çevir
        if (!is_numeric($tarih)) {
            $tarih = strtotime($tarih);
        }

        $data = veriOku();
        $yeniVardiya = [
            'id' => uniqid(),
            'personel_id' => $personelId,
            'tarih' => $tarih,
            'vardiya_turu' => $vardiyaTuru,
            'notlar' => $notlar,
            'olusturma_tarihi' => time()
        ];

        $data['vardiyalar'][] = $yeniVardiya;
        $this->veriYaz($data);

        islemLogKaydet('vardiya_ekle', "Vardiya eklendi: Personel: $personelId, Tarih: " . date('Y-m-d', $tarih));
        return $yeniVardiya['id'];
    }

    /**
     * Günlük vardiyaları getir
     */
    public function gunlukVardiyalariGetir($tarih)
    {
        // Tarihi timestamp'e çevir
        if (!is_numeric($tarih)) {
            $tarih = strtotime($tarih);
        }

        $data = veriOku();
        $vardiyalar = [];

        foreach ($data['vardiyalar'] as $vardiya) {
            $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);
            if (date('Y-m-d', $vardiyaTarihi) === date('Y-m-d', $tarih)) {
                $personel = array_filter($data['personel'], function ($p) use ($vardiya) {
                    return $p['id'] === $vardiya['personel_id'];
                });
                $personel = reset($personel);

                $vardiyalar[] = [
                    'id' => $vardiya['id'],
                    'personel_id' => $vardiya['personel_id'],
                    'personel_ad' => $personel['ad'] . ' ' . $personel['soyad'],
                    'vardiya_turu' => $vardiya['vardiya_turu'],
                    'notlar' => $vardiya['notlar'] ?? ''
                ];
            }
        }

        return $vardiyalar;
    }

    /**
     * Vardiya saatlerini hesapla
     */
    public function saatleriHesapla($vardiyaTuru)
    {
        $vardiyaTurleri = $this->turleriniGetir();
        $vardiya = $vardiyaTurleri[$vardiyaTuru];

        $baslangic = strtotime($vardiya['baslangic']);
        $bitis = strtotime($vardiya['bitis']);

        // Eğer bitiş saati başlangıç saatinden küçükse ertesi güne geçiyor demektir
        if ($bitis < $baslangic) {
            $bitis = strtotime('+1 day', $bitis);
        }

        $sure = ($bitis - $baslangic) / 3600; // Saat cinsinden süre

        return [
            'baslangic' => $baslangic,
            'bitis' => $bitis,
            'sure' => $sure
        ];
    }

    /**
     * Vardiya türü dağılımını getir
     */
    public function turuDagilimi($baslangicTarih, $bitisTarih)
    {
        $data = veriOku();
        $vardiyaTurleri = $this->turleriniGetir();
        $dagilim = [];

        foreach ($vardiyaTurleri as $id => $tur) {
            $dagilim[$id] = [
                'etiket' => $tur['etiket'],
                'sayi' => 0
            ];
        }

        foreach ($data['vardiyalar'] as $vardiya) {
            $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);
            if ($vardiyaTarihi >= strtotime($baslangicTarih) && $vardiyaTarihi <= strtotime($bitisTarih)) {
                $dagilim[$vardiya['vardiya_turu']]['sayi']++;
            }
        }

        return array_values($dagilim);
    }

    /**
     * Akıllı vardiya önerisi oluştur
     */
    public function akilliOneriOlustur($tarih, $vardiyaTuru)
    {
        $data = veriOku();
        $personeller = $data['personel'];
        $puanlar = [];

        // Tarihi timestamp'e çevir
        if (!is_numeric($tarih)) {
            $tarih = strtotime($tarih);
        }

        foreach ($personeller as $personel) {
            $puan = 100;

            // Vardiya çakışması kontrolü
            if ($this->cakismaVarMi($personel['id'], $tarih, $vardiyaTuru)) {
                continue; // Bu personeli değerlendirmeye alma
            }

            // Ardışık çalışma günleri kontrolü
            if (!$this->ardisikCalismaGunleriniKontrolEt($personel['id'], $tarih)) {
                continue; // Bu personeli değerlendirmeye alma
            }

            // Son 30 günde toplam vardiya sayısı
            $sonOtuzGunVardiyaSayisi = 0;
            $kontrolTarihi = strtotime('-30 days', $tarih);
            foreach ($data['vardiyalar'] as $vardiya) {
                if ($vardiya['personel_id'] === $personel['id']) {
                    $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);
                    if ($vardiyaTarihi >= $kontrolTarihi && $vardiyaTarihi <= $tarih) {
                        $sonOtuzGunVardiyaSayisi++;
                    }
                }
            }

            // Vardiya sayısına göre puan düşür (ne kadar çok çalışmışsa o kadar düşük puan)
            $puan -= $sonOtuzGunVardiyaSayisi * 2;

            // Personel tercihlerine göre puan ekle/çıkar
            if (isset($personel['tercihler'][$vardiyaTuru])) {
                $puan += $personel['tercihler'][$vardiyaTuru] * 10;
            }

            // Son çalışma tarihine göre puan ekle (ne kadar uzun süre çalışmadıysa o kadar yüksek puan)
            $sonCalisma = 0;
            foreach ($data['vardiyalar'] as $vardiya) {
                if ($vardiya['personel_id'] === $personel['id']) {
                    $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);
                    if ($vardiyaTarihi > $sonCalisma) {
                        $sonCalisma = $vardiyaTarihi;
                    }
                }
            }
            $gunFarki = floor(($tarih - $sonCalisma) / (60 * 60 * 24));
            $puan += $gunFarki * 5;

            $puanlar[] = [
                'personel_id' => $personel['id'],
                'ad_soyad' => $personel['ad'] . ' ' . $personel['soyad'],
                'puan' => max(0, $puan)
            ];
        }

        // Puanlara göre sırala
        usort($puanlar, function ($a, $b) {
            return $b['puan'] - $a['puan'];
        });

        return array_slice($puanlar, 0, 5); // En yüksek puanlı 5 personeli döndür
    }

    /**
     * Vardiya türlerini getir
     */
    public function turleriniGetir()
    {
        return [
            'sabah' => [
                'etiket' => 'Sabah',
                'baslangic' => '08:00',
                'bitis' => '16:00',
                'renk' => '#4CAF50',
                'aciklama' => 'Sabah vardiyası (08:00-16:00)',
                'mola' => [
                    ['baslangic' => '10:00', 'bitis' => '10:15', 'tur' => 'çay molası'],
                    ['baslangic' => '12:00', 'bitis' => '12:30', 'tur' => 'yemek molası'],
                    ['baslangic' => '14:00', 'bitis' => '14:15', 'tur' => 'çay molası']
                ]
            ],
            'aksam' => [
                'etiket' => 'Akşam',
                'baslangic' => '16:00',
                'bitis' => '24:00',
                'renk' => '#2196F3',
                'aciklama' => 'Akşam vardiyası (16:00-24:00)',
                'mola' => [
                    ['baslangic' => '18:00', 'bitis' => '18:15', 'tur' => 'çay molası'],
                    ['baslangic' => '20:00', 'bitis' => '20:30', 'tur' => 'yemek molası'],
                    ['baslangic' => '22:00', 'bitis' => '22:15', 'tur' => 'çay molası']
                ]
            ],
            'gece' => [
                'etiket' => 'Gece',
                'baslangic' => '24:00',
                'bitis' => '08:00',
                'renk' => '#9C27B0',
                'aciklama' => 'Gece vardiyası (24:00-08:00)',
                'mola' => [
                    ['baslangic' => '02:00', 'bitis' => '02:15', 'tur' => 'çay molası'],
                    ['baslangic' => '04:00', 'bitis' => '04:30', 'tur' => 'yemek molası'],
                    ['baslangic' => '06:00', 'bitis' => '06:15', 'tur' => 'çay molası']
                ]
            ]
        ];
    }

    /**
     * Vardiya türü etiketini getir
     */
    public function turuEtiketGetir($vardiyaTuru)
    {
        $turler = $this->turleriniGetir();
        return $turler[$vardiyaTuru]['etiket'] ?? 'Bilinmeyen Vardiya';
    }

    /**
     * Vardiya detaylarını getir
     */
    public function turuDetayGetir($vardiyaTuru)
    {
        $turler = $this->turleriniGetir();
        if (!isset($turler[$vardiyaTuru])) {
            throw new Exception('Geçersiz vardiya türü.');
        }

        $vardiya = $turler[$vardiyaTuru];
        $baslangic = strtotime($vardiya['baslangic']);
        $bitis = strtotime($vardiya['bitis']);

        if ($bitis < $baslangic) {
            $bitis = strtotime('+1 day', $bitis);
        }

        $sure = ($bitis - $baslangic) / 3600;

        return [
            'etiket' => $vardiya['etiket'],
            'baslangic' => $vardiya['baslangic'],
            'bitis' => $vardiya['bitis'],
            'sure' => $sure,
            'renk' => $vardiya['renk'],
            'aciklama' => $vardiya['aciklama'],
            'mola' => $vardiya['mola']
        ];
    }

    /**
     * Detaylı vardiya çakışması kontrolü
     */
    public function cakismaDetayliKontrol($personelId, $baslangicZamani, $bitisZamani, $haricVardiyaId = null)
    {
        $data = veriOku();
        $vardiyaTurleri = $this->turleriniGetir();

        foreach ($data['vardiyalar'] as $vardiya) {
            if ($vardiya['personel_id'] === $personelId && $vardiya['id'] !== $haricVardiyaId) {
                $tur = $vardiyaTurleri[$vardiya['vardiya_turu']];
                $vardiyaBaslangic = strtotime(date('Y-m-d', $vardiya['tarih']) . ' ' . $tur['baslangic']);
                $vardiyaBitis = strtotime(date('Y-m-d', $vardiya['tarih']) . ' ' . $tur['bitis']);

                if ($vardiyaBitis < $vardiyaBaslangic) {
                    $vardiyaBitis = strtotime('+1 day', $vardiyaBitis);
                }

                // Çakışma kontrolü
                if (
                    ($baslangicZamani >= $vardiyaBaslangic && $baslangicZamani < $vardiyaBitis) ||
                    ($bitisZamani > $vardiyaBaslangic && $bitisZamani <= $vardiyaBitis) ||
                    ($baslangicZamani <= $vardiyaBaslangic && $bitisZamani >= $vardiyaBitis)
                ) {
                    return [
                        'cakisma_var' => true,
                        'vardiya' => [
                            'id' => $vardiya['id'],
                            'baslangic' => date('Y-m-d H:i', $vardiyaBaslangic),
                            'bitis' => date('Y-m-d H:i', $vardiyaBitis),
                            'tur' => $tur['etiket']
                        ]
                    ];
                }
            }
        }

        return ['cakisma_var' => false];
    }

    /**
     * Vardiya süresi kontrolü
     */
    public function suresiKontrol($vardiyaTuru, $personelId, $tarih)
    {
        $data = veriOku();
        $vardiyaTurleri = $this->turleriniGetir();
        $tur = $vardiyaTurleri[$vardiyaTuru];

        // Tarihi timestamp'e çevir
        if (!is_numeric($tarih)) {
            $tarih = strtotime($tarih);
        }

        $baslangicZamani = strtotime(date('Y-m-d', $tarih) . ' ' . $tur['baslangic']);
        $bitisZamani = strtotime(date('Y-m-d', $tarih) . ' ' . $tur['bitis']);

        if ($bitisZamani < $baslangicZamani) {
            $bitisZamani = strtotime('+1 day', $bitisZamani);
        }

        // Önceki ve sonraki vardiyalarla minimum süre kontrolü
        $minimumSure = 11 * 3600; // 11 saat (saniye cinsinden)

        foreach ($data['vardiyalar'] as $vardiya) {
            if ($vardiya['personel_id'] === $personelId) {
                $digerTur = $vardiyaTurleri[$vardiya['vardiya_turu']];
                $digerBaslangic = strtotime(date('Y-m-d', $vardiya['tarih']) . ' ' . $digerTur['baslangic']);
                $digerBitis = strtotime(date('Y-m-d', $vardiya['tarih']) . ' ' . $digerTur['bitis']);

                if ($digerBitis < $digerBaslangic) {
                    $digerBitis = strtotime('+1 day', $digerBitis);
                }

                // Önceki vardiya kontrolü
                if ($digerBitis <= $baslangicZamani) {
                    $araSure = $baslangicZamani - $digerBitis;
                    if ($araSure < $minimumSure) {
                        return [
                            'uygun' => false,
                            'mesaj' => 'İki vardiya arası en az 11 saat olmalıdır.',
                            'onceki_vardiya' => [
                                'id' => $vardiya['id'],
                                'bitis' => date('Y-m-d H:i', $digerBitis)
                            ]
                        ];
                    }
                }

                // Sonraki vardiya kontrolü
                if ($digerBaslangic >= $bitisZamani) {
                    $araSure = $digerBaslangic - $bitisZamani;
                    if ($araSure < $minimumSure) {
                        return [
                            'uygun' => false,
                            'mesaj' => 'İki vardiya arası en az 11 saat olmalıdır.',
                            'sonraki_vardiya' => [
                                'id' => $vardiya['id'],
                                'baslangic' => date('Y-m-d H:i', $digerBaslangic)
                            ]
                        ];
                    }
                }
            }
        }

        return ['uygun' => true];
    }

    /**
     * JSON dosyasına veri yazma
     */
    private function veriYaz($data)
    {
        file_put_contents('personel.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
