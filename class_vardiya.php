<?php

// JSON dosyasına veri yazma
function veriYaz($data)
{
    file_put_contents('personel.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Vardiya silme
function vardiyaSil($vardiyaId)
{
    $data = veriOku();
    $data['vardiyalar'] = array_filter($data['vardiyalar'], function ($vardiya) use ($vardiyaId) {
        return $vardiya['id'] !== $vardiyaId;
    });
    veriYaz($data);
}

// Vardiya düzenleme
function vardiyaDuzenle($vardiyaId, $personelId, $tarih, $vardiyaTuru, $notlar = '')
{
    // Vardiya çakışması kontrolü
    if (vardiyaCakismasiVarMi($personelId, $tarih, $vardiyaTuru, $vardiyaId)) {
        throw new Exception('Bu personelin seçilen tarihte başka bir vardiyası bulunuyor.');
    }

    // Ardışık çalışma günlerini kontrol et
    if (!ardisikCalismaGunleriniKontrolEt($personelId, $tarih)) {
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

    veriYaz($data);
    islemLogKaydet('vardiya_duzenle', "Vardiya düzenlendi: ID: $vardiyaId, Personel: $personelId, Tarih: " . date('Y-m-d', $tarih));
}

// Vardiya çakışması kontrolü
function vardiyaCakismasiVarMi($personelId, $tarih, $vardiyaTuru, $haricVardiyaId = null)
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

// Vardiya değişim talebi oluşturma
function vardiyaDegisimTalebiOlustur($vardiyaId, $talepEdenPersonelId, $aciklama)
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
    veriYaz($data);

    islemLogKaydet('vardiya_talep', "Vardiya değişim talebi oluşturuldu: Vardiya ID: $vardiyaId");
    return $yeniTalep['id'];
}

// Vardiya değişim talebini onayla/reddet
function vardiyaTalebiGuncelle($talepId, $durum, $yoneticiNotu = '')
{
    $data = veriOku();
    $talep = null;

    foreach ($data['vardiya_talepleri'] as &$talep) {
        if ($talep['id'] === $talepId) {
            $talep['durum'] = $durum;
            $talep['yonetici_notu'] = $yoneticiNotu;
            $talep['guncelleme_tarihi'] = time();

            // Eğer onaylandıysa izinler listesine ekle
            if ($durum === 'onaylandi') {
                if (!isset($data['izinler'])) {
                    $data['izinler'] = [];
                }

                $yeniIzin = [
                    'id' => uniqid(),
                    'personel_id' => $talep['personel_id'],
                    'baslangic_tarihi' => $talep['baslangic_tarihi'],
                    'bitis_tarihi' => $talep['bitis_tarihi'],
                    'izin_turu' => $talep['izin_turu'],
                    'aciklama' => $talep['aciklama'],
                    'onaylayan_id' => $_SESSION['kullanici_id'] ?? null,
                    'onay_tarihi' => time(),
                    'olusturma_tarihi' => time(),
                    'guncelleme_tarihi' => time(),
                    'gun_sayisi' => $talep['gun_sayisi'] ?? null,
                    'belgeler' => $talep['belgeler'] ?? [],
                    'notlar' => $yoneticiNotu
                ];

                $data['izinler'][] = $yeniIzin;
            }
            break;
        }
    }

    if ($talep === null) {
        throw new Exception('İzin talebi bulunamadı.');
    }

    veriYaz($data);
    islemLogKaydet('izin_talebi_guncelle', "İzin talebi güncellendi: $talepId - Durum: $durum");
    return true;
}

// Personel detaylarını getir
function vardiyaDetayGetir($vardiyaId)
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

// Ardışık çalışma günlerini kontrol et
function ardisikCalismaGunleriniKontrolEt($personelId, $tarih)
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


// Vardiya ekleme
function vardiyaEkle($personelId, $tarih, $vardiyaTuru, $notlar = '')
{
    // Vardiya çakışması kontrolü
    if (vardiyaCakismasiVarMi($personelId, $tarih, $vardiyaTuru)) {
        throw new Exception('Bu personelin seçilen tarihte başka bir vardiyası bulunuyor.');
    }

    // Ardışık çalışma günü kontrolü
    if (!ardisikCalismaGunleriniKontrolEt($personelId, $tarih)) {
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
        'durum' => 'onaylandi'
    ];
    $data['vardiyalar'][] = $yeniVardiya;
    veriYaz($data);

    islemLogKaydet('vardiya_ekle', "Yeni vardiya eklendi: Personel ID: $personelId, Tarih: " . date('Y-m-d', $tarih));
    return $yeniVardiya['id'];
}

// Belirli bir tarihteki vardiyaları getir
function gunlukVardiyalariGetir($tarih)
{
    // Tarihi timestamp'e çevir
    if (!is_numeric($tarih)) {
        $tarih = strtotime($tarih);
    }

    $data = veriOku();
    $gunlukVardiyalar = [];

    foreach ($data['vardiyalar'] as $vardiya) {
        $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);
        if (date('Y-m-d', $vardiyaTarihi) === date('Y-m-d', $tarih)) {
            $personel = array_filter($data['personel'], function ($p) use ($vardiya) {
                return $p['id'] === $vardiya['personel_id'];
            });
            $personel = reset($personel);

            $vardiyaTurleri = vardiyaTurleriniGetir();
            $vardiyaBilgisi = $vardiyaTurleri[$vardiya['vardiya_turu']] ?? null;
            $vardiyaKisaltma = $vardiyaBilgisi ? $vardiyaBilgisi['etiket'] : '?';

            $gunlukVardiyalar[] = [
                'id' => $vardiya['id'],
                'personel' => $personel['ad'] . ' ' . $personel['soyad'],
                'vardiya' => $vardiyaKisaltma,
                'vardiya_turu' => $vardiya['vardiya_turu'],
                'notlar' => $vardiya['notlar'] ?? '',
                'durum' => $vardiya['durum'] ?? 'onaylandi'
            ];
        }
    }

    return $gunlukVardiyalar;
}

// Vardiya saatlerini hesapla
function vardiyaSaatleriHesapla($vardiyaTuru)
{
    $vardiyaTurleri = vardiyaTurleriniGetir();
    if (!isset($vardiyaTurleri[$vardiyaTuru])) {
        throw new Exception('Geçersiz vardiya türü: ' . $vardiyaTuru);
    }

    $vardiya = $vardiyaTurleri[$vardiyaTuru];

    // Saat formatı kontrolü
    if (!isset($vardiya['baslangic']) || !isset($vardiya['bitis'])) {
        throw new Exception('Vardiya başlangıç veya bitiş saati tanımlanmamış.');
    }

    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $vardiya['baslangic'])) {
        throw new Exception('Geçersiz başlangıç saati formatı: ' . $vardiya['baslangic']);
    }

    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $vardiya['bitis'])) {
        throw new Exception('Geçersiz bitiş saati formatı: ' . $vardiya['bitis']);
    }

    // Saatleri dakikaya çevir
    list($baslangicSaat, $baslangicDakika) = array_map('intval', explode(':', $vardiya['baslangic']));
    list($bitisSaat, $bitisDakika) = array_map('intval', explode(':', $vardiya['bitis']));

    $baslangicDakikaTotal = $baslangicSaat * 60 + $baslangicDakika;
    $bitisDakikaTotal = $bitisSaat * 60 + $bitisDakika;

    // Gece vardiyası kontrolü
    $geceVardiyasi = false;
    if ($bitisDakikaTotal <= $baslangicDakikaTotal) {
        $bitisDakikaTotal += 24 * 60; // 24 saat ekle
        $geceVardiyasi = true;
    }

    // Toplam çalışma süresini hesapla (saat cinsinden)
    $calismaSuresi = ($bitisDakikaTotal - $baslangicDakikaTotal) / 60;

    // Mola süresini hesapla ve düş
    $molaSuresi = $calismaSuresi >= 7.5 ? 0.5 : ($calismaSuresi >= 4 ? 0.25 : 0);
    $netCalismaSuresi = $calismaSuresi - $molaSuresi;

    return [
        'baslangic' => $vardiya['baslangic'],
        'bitis' => $vardiya['bitis'],
        'sure' => $netCalismaSuresi,
        'brut_sure' => $calismaSuresi,
        'mola_suresi' => $molaSuresi,
        'gece_vardiyasi' => $geceVardiyasi,
        'baslangic_dakika' => $baslangicDakikaTotal,
        'bitis_dakika' => $bitisDakikaTotal,
        'vardiya_bilgisi' => [
            'etiket' => $vardiya['etiket'],
            'renk' => $vardiya['renk'],
            'min_personel' => $vardiya['min_personel'],
            'max_personel' => $vardiya['max_personel']
        ]
    ];
}

// Vardiya türlerine göre dağılım
function vardiyaTuruDagilimi($baslangicTarih, $bitisTarih)
{
    // Tarihleri timestamp'e çevir
    $baslangicTimestamp = is_numeric($baslangicTarih) ? $baslangicTarih : strtotime($baslangicTarih);
    $bitisTimestamp = is_numeric($bitisTarih) ? $bitisTarih : strtotime($bitisTarih);

    $data = veriOku();
    $vardiyaTurleri = vardiyaTurleriniGetir();

    $dagilim = [];
    foreach ($vardiyaTurleri as $id => $vardiya) {
        $dagilim[$id] = [
            'adet' => 0,
            'etiket' => $vardiya['etiket'],
            'renk' => $vardiya['renk']
        ];
    }

    foreach ($data['vardiyalar'] as $vardiya) {
        $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);
        if ($vardiyaTarihi >= $baslangicTimestamp && $vardiyaTarihi <= $bitisTimestamp) {
            $dagilim[$vardiya['vardiya_turu']]['adet']++;
        }
    }

    return $dagilim;
}

// Akıllı vardiya önerisi oluştur
function akilliVardiyaOnerisiOlustur($tarih, $vardiyaTuru)
{
    $data = veriOku();
    $puanlar = [];

    // Tarihi timestamp'e çevir
    if (!is_numeric($tarih)) {
        $tarih = strtotime($tarih);
    }

    foreach ($data['personel'] as $personel) {
        $puan = 100; // Başlangıç puanı

        // Personelin tercihleri
        if (isset($personel['tercihler'])) {
            // Tercih edilen vardiyalar kontrolü
            if (isset($personel['tercihler']['tercih_edilen_vardiyalar'])) {
                if (in_array($vardiyaTuru, $personel['tercihler']['tercih_edilen_vardiyalar'])) {
                    $puan += 20;
                }
            }

            // Tercih edilmeyen günler kontrolü
            if (isset($personel['tercihler']['tercih_edilmeyen_gunler'])) {
                $gun = date('w', $tarih);
                if (in_array($gun, $personel['tercihler']['tercih_edilmeyen_gunler'])) {
                    $puan -= 30;
                }
            }
        }

        // Son 7 gündeki vardiya sayısı kontrolü
        $sonYediGunVardiyaSayisi = 0;
        foreach ($data['vardiyalar'] as $vardiya) {
            $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);
            if (
                $vardiya['personel_id'] === $personel['id'] &&
                $vardiyaTarihi >= strtotime('-7 days', $tarih) &&
                $vardiyaTarihi < $tarih
            ) {
                $sonYediGunVardiyaSayisi++;
            }
        }
        $puan -= ($sonYediGunVardiyaSayisi * 10); // Her vardiya için -10 puan

        // Ardışık çalışma günü kontrolü
        if (!ardisikCalismaGunleriniKontrolEt($personel['id'], $tarih)) {
            $puan -= 50;
        }

        // Vardiya çakışması kontrolü
        if (vardiyaCakismasiVarMi($personel['id'], $tarih, $vardiyaTuru)) {
            $puan = 0; // Çakışma varsa sıfır puan
        }

        // İzin kontrolü
        foreach ($data['izinler'] as $izin) {
            if ($izin['personel_id'] === $personel['id']) {
                $izinBaslangic = is_numeric($izin['baslangic_tarihi']) ? $izin['baslangic_tarihi'] : strtotime($izin['baslangic_tarihi']);
                $izinBitis = is_numeric($izin['bitis_tarihi']) ? $izin['bitis_tarihi'] : strtotime($izin['bitis_tarihi']);

                if ($tarih >= $izinBaslangic && $tarih <= $izinBitis) {
                    $puan = 0; // İzindeyse sıfır puan
                }
            }
        }

        // Aylık vardiya dağılımı kontrolü
        $aylikVardiyalar = personelAylikVardiyaSayisi(
            $personel['id'],
            date('m', $tarih),
            date('Y', $tarih)
        );
        if ($aylikVardiyalar['toplam'] > 20) { // Aylık maksimum vardiya sayısı
            $puan -= 40;
        }

        $puanlar[$personel['id']] = [
            'personel' => $personel['ad'] . ' ' . $personel['soyad'],
            'puan' => max(0, $puan) // Puan 0'ın altına düşmesin
        ];
    }

    // Puanlara göre sırala
    uasort($puanlar, function ($a, $b) {
        return $b['puan'] - $a['puan'];
    });

    return $puanlar;
}

// Vardiya türlerini getir
function vardiyaTurleriniGetir()
{
    $data = veriOku();

    if (!isset($data['sistem_ayarlari']['vardiya_turleri'])) {
        // Varsayılan vardiya türleri
        return [
            'sabah' => [
                'id' => 'sabah',
                'baslangic' => '08:00',
                'bitis' => '16:00',
                'etiket' => 'Sabah',
                'renk' => '#4CAF50',
                'sure' => 8,
                'gece_vardiyasi' => false,
                'min_personel' => 2,
                'max_personel' => 5,
                'ozel_gunler' => [
                    'hafta_sonu_aktif' => true,
                    'resmi_tatil_aktif' => true
                ],
                'yetkinlikler' => []
            ],
            'aksam' => [
                'id' => 'aksam',
                'baslangic' => '16:00',
                'bitis' => '24:00',
                'etiket' => 'Akşam',
                'renk' => '#2196F3',
                'sure' => 8,
                'gece_vardiyasi' => false,
                'min_personel' => 2,
                'max_personel' => 4,
                'ozel_gunler' => [
                    'hafta_sonu_aktif' => true,
                    'resmi_tatil_aktif' => true
                ],
                'yetkinlikler' => []
            ],
            'gece' => [
                'id' => 'gece',
                'baslangic' => '24:00',
                'bitis' => '08:00',
                'etiket' => 'Gece',
                'renk' => '#9C27B0',
                'sure' => 8,
                'gece_vardiyasi' => true,
                'min_personel' => 1,
                'max_personel' => 3,
                'ozel_gunler' => [
                    'hafta_sonu_aktif' => true,
                    'resmi_tatil_aktif' => true
                ],
                'yetkinlikler' => []
            ]
        ];
    }

    $vardiyalar = [];
    foreach ($data['sistem_ayarlari']['vardiya_turleri'] as $vardiya) {
        // Varsayılan değerler
        $varsayilanDegerler = [
            'etiket' => ucfirst($vardiya['id']),
            'renk' => '#808080',
            'sure' => 8,
            'gece_vardiyasi' => false,
            'min_personel' => 1,
            'max_personel' => 5,
            'ozel_gunler' => [
                'hafta_sonu_aktif' => true,
                'resmi_tatil_aktif' => true
            ],
            'yetkinlikler' => []
        ];

        // Saat formatı kontrolü
        if (isset($vardiya['baslangic'])) {
            if ($vardiya['baslangic'] === '24:00') {
                $vardiya['baslangic'] = '00:00';
            } elseif (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $vardiya['baslangic'])) {
                throw new Exception('Geçersiz başlangıç saati formatı: ' . $vardiya['baslangic']);
            }
        }
        if (isset($vardiya['bitis'])) {
            if ($vardiya['bitis'] === '24:00') {
                $vardiya['bitis'] = '00:00';
            } elseif (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $vardiya['bitis'])) {
                throw new Exception('Geçersiz bitiş saati formatı: ' . $vardiya['bitis']);
            }
        }

        // Gece vardiyası kontrolü
        if (isset($vardiya['baslangic']) && isset($vardiya['bitis'])) {
            $baslangicSaat = strtotime('1970-01-01 ' . $vardiya['baslangic']);
            $bitisSaat = strtotime('1970-01-01 ' . $vardiya['bitis']);
            $varsayilanDegerler['gece_vardiyasi'] = ($bitisSaat < $baslangicSaat);
        }

        // Personel sayısı kontrolü
        if (isset($vardiya['min_personel']) && isset($vardiya['max_personel'])) {
            if ($vardiya['min_personel'] > $vardiya['max_personel']) {
                throw new Exception('Minimum personel sayısı, maksimum personel sayısından büyük olamaz.');
            }
        }

        $vardiyalar[$vardiya['id']] = array_merge($varsayilanDegerler, $vardiya);
    }

    return $vardiyalar;
}

// Vardiya türü etiketini getir
function vardiyaTuruEtiketGetir($vardiyaTuru)
{
    try {
        $vardiyaTurleri = vardiyaTurleriniGetir();
        if (!isset($vardiyaTurleri[$vardiyaTuru])) {
            throw new Exception('Geçersiz vardiya türü: ' . $vardiyaTuru);
        }
        return $vardiyaTurleri[$vardiyaTuru]['etiket'] ?? ucfirst($vardiyaTuru);
    } catch (Exception $e) {
        islemLogKaydet('hata', 'Vardiya türü etiketi alınamadı: ' . $e->getMessage());
        return '?';
    }
}

// Vardiya detaylarını getir
function vardiyaDetaylariGetir($vardiyaTuru)
{
    try {
        $vardiyaTurleri = vardiyaTurleriniGetir();
        if (!isset($vardiyaTurleri[$vardiyaTuru])) {
            throw new Exception('Geçersiz vardiya türü: ' . $vardiyaTuru);
        }

        $vardiya = $vardiyaTurleri[$vardiyaTuru];
        $saatler = vardiyaSaatleriHesapla($vardiyaTuru);

        return [
            'id' => $vardiya['id'],
            'etiket' => $vardiya['etiket'],
            'baslangic' => $vardiya['baslangic'],
            'bitis' => $vardiya['bitis'],
            'sure' => $saatler['sure'],
            'gece_vardiyasi' => $vardiya['gece_vardiyasi'],
            'renk' => $vardiya['renk'],
            'min_personel' => $vardiya['min_personel'],
            'max_personel' => $vardiya['max_personel'],
            'ozel_gunler' => $vardiya['ozel_gunler'],
            'yetkinlikler' => $vardiya['yetkinlikler']
        ];
    } catch (Exception $e) {
        islemLogKaydet('hata', 'Vardiya detayları alınamadı: ' . $e->getMessage());
        throw $e;
    }
}

// Vardiya çakışması detaylı kontrolü
function vardiyaCakismasiDetayliKontrol($personelId, $baslangicZamani, $bitisZamani, $haricVardiyaId = null)
{
    $data = veriOku();
    $cakismalar = [];

    foreach ($data['vardiyalar'] as $vardiya) {
        if ($vardiya['personel_id'] === $personelId && $vardiya['id'] !== $haricVardiyaId) {
            try {
                $vardiyaSaatleri = vardiyaSaatleriHesapla($vardiya['vardiya_turu']);
                $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);

                // Vardiya başlangıç ve bitiş zamanlarını hesapla
                $vardiyaBaslangic = strtotime(date('Y-m-d ', $vardiyaTarihi) . $vardiyaSaatleri['baslangic']);
                $vardiyaBitis = $vardiyaSaatleri['gece_vardiyasi']
                    ? strtotime(date('Y-m-d ', $vardiyaTarihi) . $vardiyaSaatleri['bitis']) + 86400
                    : strtotime(date('Y-m-d ', $vardiyaTarihi) . $vardiyaSaatleri['bitis']);

                // Çakışma kontrolü
                if (
                    ($baslangicZamani >= $vardiyaBaslangic && $baslangicZamani < $vardiyaBitis) ||
                    ($bitisZamani > $vardiyaBaslangic && $bitisZamani <= $vardiyaBitis) ||
                    ($baslangicZamani <= $vardiyaBaslangic && $bitisZamani >= $vardiyaBitis)
                ) {
                    $cakismalar[] = [
                        'vardiya_id' => $vardiya['id'],
                        'vardiya_turu' => $vardiya['vardiya_turu'],
                        'tarih' => date('Y-m-d', $vardiyaTarihi),
                        'baslangic' => date('H:i', $vardiyaBaslangic),
                        'bitis' => date('H:i', $vardiyaBitis),
                        'sure' => $vardiyaSaatleri['sure']
                    ];
                }
            } catch (Exception $e) {
                // Vardiya saatleri hesaplanamadıysa bu vardiyayı atla
                continue;
            }
        }
    }

    return $cakismalar;
}

// Vardiya süresi kontrolü
function vardiyaSuresiKontrol($vardiyaTuru, $personelId, $tarih)
{
    try {
        $vardiyaSaatleri = vardiyaSaatleriHesapla($vardiyaTuru);
        $gunlukCalismaSuresi = $vardiyaSaatleri['sure'];

        // Aynı gün içindeki diğer vardiyaları kontrol et
        $data = veriOku();
        $gunBaslangic = strtotime(date('Y-m-d 00:00:00', $tarih));
        $gunBitis = strtotime(date('Y-m-d 23:59:59', $tarih));

        foreach ($data['vardiyalar'] as $vardiya) {
            if ($vardiya['personel_id'] === $personelId) {
                $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);

                if ($vardiyaTarihi >= $gunBaslangic && $vardiyaTarihi <= $gunBitis) {
                    $mevcutVardiyaSaatleri = vardiyaSaatleriHesapla($vardiya['vardiya_turu']);
                    $gunlukCalismaSuresi += $mevcutVardiyaSaatleri['sure'];
                }
            }
        }

        return [
            'toplam_sure' => $gunlukCalismaSuresi,
            'izin_verilen_sure' => 11, // Günlük maksimum çalışma süresi
            'uygun' => $gunlukCalismaSuresi <= 11,
            'kalan_sure' => max(0, 11 - $gunlukCalismaSuresi)
        ];
    } catch (Exception $e) {
        islemLogKaydet('hata', 'Vardiya süresi kontrolü yapılamadı: ' . $e->getMessage());
        throw $e;
    }
}
