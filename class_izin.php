<?php


// İzin talebi oluşturma
function izinTalebiOlustur($personelId, $baslangicTarihi, $bitisTarihi, $izinTuru, $aciklama)
{
    // Tarihleri timestamp'e çevir
    $baslangicTimestamp = is_numeric($baslangicTarihi) ? $baslangicTarihi : strtotime($baslangicTarihi);
    $bitisTimestamp = is_numeric($bitisTarihi) ? $bitisTarihi : strtotime($bitisTarihi);

    // Tarih kontrolü
    if ($baslangicTimestamp > $bitisTimestamp) {
        throw new Exception('Bitiş tarihi başlangıç tarihinden önce olamaz.');
    }

    // Geçmiş tarih kontrolü
    if ($baslangicTimestamp < strtotime('today')) {
        throw new Exception('Geçmiş tarihli izin talebi oluşturamazsınız.');
    }

    $data = veriOku();
    $gunSayisi = floor(($bitisTimestamp - $baslangicTimestamp) / (60 * 60 * 24)) + 1;

    // Personel kontrolü ve izin hakkı kontrolü
    $personel = null;
    foreach ($data['personel'] as $p) {
        if ($p['id'] === $personelId) {
            $personel = $p;
            break;
        }
    }

    if (!$personel) {
        throw new Exception('Personel bulunamadı.');
    }

    // İzin hakkı kontrolü
    if ($izinTuru === 'yillik') {
        $kalanIzin = yillikIzinHakkiHesapla($personelId);
        if ($gunSayisi > $kalanIzin) {
            throw new Exception("Yeterli yıllık izin hakkınız bulunmuyor. Kalan izin: $kalanIzin gün");
        }
    } elseif ($izinTuru === 'mazeret') {
        if (!isset($personel['izin_haklari']['mazeret'])) {
            $personel['izin_haklari']['mazeret'] = ['toplam' => 5, 'kullanilan' => 0, 'kalan' => 5];
        }
        $kalanMazeret = $personel['izin_haklari']['mazeret']['kalan'];
        if ($gunSayisi > $kalanMazeret) {
            throw new Exception("Yeterli mazeret izni hakkınız bulunmuyor. Kalan izin: $kalanMazeret gün");
        }
    }

    // Vardiya çakışması kontrolü
    for ($i = 0; $i < $gunSayisi; $i++) {
        $kontrolTarihi = strtotime("+$i day", $baslangicTimestamp);
        foreach ($data['vardiyalar'] as $vardiya) {
            $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);
            if (
                $vardiya['personel_id'] === $personelId &&
                date('Y-m-d', $vardiyaTarihi) === date('Y-m-d', $kontrolTarihi)
            ) {
                throw new Exception('Seçilen tarih aralığında vardiya bulunuyor. Önce vardiyaları düzenlemelisiniz.');
            }
        }
    }

    // Çakışan izin kontrolü
    foreach ($data['izinler'] as $izin) {
        if ($izin['personel_id'] === $personelId) {
            $izinBaslangic = is_numeric($izin['baslangic_tarihi']) ? $izin['baslangic_tarihi'] : strtotime($izin['baslangic_tarihi']);
            $izinBitis = is_numeric($izin['bitis_tarihi']) ? $izin['bitis_tarihi'] : strtotime($izin['bitis_tarihi']);

            if (($baslangicTimestamp >= $izinBaslangic && $baslangicTimestamp <= $izinBitis) ||
                ($bitisTimestamp >= $izinBaslangic && $bitisTimestamp <= $izinBitis)
            ) {
                throw new Exception('Seçilen tarih aralığında başka bir izin bulunuyor.');
            }
        }
    }

    $yeniTalep = [
        'id' => uniqid(),
        'personel_id' => $personelId,
        'baslangic_tarihi' => $baslangicTimestamp,
        'bitis_tarihi' => $bitisTimestamp,
        'izin_turu' => $izinTuru,
        'aciklama' => $aciklama,
        'durum' => 'beklemede',
        'gun_sayisi' => $gunSayisi,
        'olusturma_tarihi' => time(),
        'guncelleme_tarihi' => time(),
        'belgeler' => [],
        'yonetici_notu' => ''
    ];

    if (!isset($data['izin_talepleri'])) {
        $data['izin_talepleri'] = [];
    }

    $data['izin_talepleri'][] = $yeniTalep;
    veriYaz($data);

    islemLogKaydet('izin_talebi_olustur', "İzin talebi oluşturuldu: $personelId - $izinTuru ($gunSayisi gün)");
    return $yeniTalep['id'];
}

// İzin talebini onayla/reddet
function izinTalebiGuncelle($talepId, $durum, $yoneticiNotu = '')
{
    $data = veriOku();
    $talep = null;

    foreach ($data['izin_talepleri'] as &$talep) {
        if ($talep['id'] === $talepId) {
            // Eğer talep zaten onaylanmış veya reddedilmişse işlem yapma
            if ($talep['durum'] !== 'beklemede') {
                throw new Exception('Bu talep zaten ' . $talep['durum'] . ' durumunda.');
            }

            $talep['durum'] = $durum;
            $talep['yonetici_notu'] = $yoneticiNotu;
            $talep['guncelleme_tarihi'] = time();

            // Eğer onaylandıysa izinler listesine ekle ve izin haklarını güncelle
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

                // İzin haklarını güncelle
                foreach ($data['personel'] as &$personel) {
                    if ($personel['id'] === $talep['personel_id']) {
                        if ($talep['izin_turu'] === 'yillik') {
                            if (!isset($personel['izin_haklari']['yillik'])) {
                                $personel['izin_haklari']['yillik'] = [
                                    'toplam' => 14,
                                    'kullanilan' => 0,
                                    'kalan' => 14,
                                    'son_guncelleme' => time()
                                ];
                            }
                            $personel['izin_haklari']['yillik']['kullanilan'] += $talep['gun_sayisi'];
                            $personel['izin_haklari']['yillik']['kalan'] =
                                $personel['izin_haklari']['yillik']['toplam'] -
                                $personel['izin_haklari']['yillik']['kullanilan'];
                        } elseif ($talep['izin_turu'] === 'mazeret') {
                            if (!isset($personel['izin_haklari']['mazeret'])) {
                                $personel['izin_haklari']['mazeret'] = [
                                    'toplam' => 5,
                                    'kullanilan' => 0,
                                    'kalan' => 5
                                ];
                            }
                            $personel['izin_haklari']['mazeret']['kullanilan'] += $talep['gun_sayisi'];
                            $personel['izin_haklari']['mazeret']['kalan'] =
                                $personel['izin_haklari']['mazeret']['toplam'] -
                                $personel['izin_haklari']['mazeret']['kullanilan'];
                        } elseif ($talep['izin_turu'] === 'hastalik') {
                            if (!isset($personel['izin_haklari']['hastalik'])) {
                                $personel['izin_haklari']['hastalik'] = ['kullanilan' => 0];
                            }
                            $personel['izin_haklari']['hastalik']['kullanilan'] += $talep['gun_sayisi'];
                        }
                        break;
                    }
                }
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

// Yıllık izin hakkı hesapla
function yillikIzinHakkiHesapla($personelId)
{
    $data = veriOku();
    $personel = null;

    // Önce personeli bul ve izin haklarını kontrol et
    foreach ($data['personel'] as $p) {
        if ($p['id'] === $personelId) {
            $personel = $p;
            break;
        }
    }

    if (!$personel || !isset($personel['izin_haklari']['yillik'])) {
        return 14; // Varsayılan yıllık izin hakkı
    }

    $izinHakki = $personel['izin_haklari']['yillik'];
    $kullanilanIzin = 0;

    // Onaylanmış izinleri hesapla
    foreach ($data['izinler'] as $izin) {
        if ($izin['personel_id'] === $personelId && $izin['izin_turu'] === 'yillik') {
            $baslangicTimestamp = is_numeric($izin['baslangic_tarihi']) ? $izin['baslangic_tarihi'] : strtotime($izin['baslangic_tarihi']);
            $bitisTimestamp = is_numeric($izin['bitis_tarihi']) ? $izin['bitis_tarihi'] : strtotime($izin['bitis_tarihi']);
            $kullanilanIzin += floor(($bitisTimestamp - $baslangicTimestamp) / (60 * 60 * 24)) + 1;
        }
    }

    // Bekleyen izin taleplerini hesapla
    foreach ($data['izin_talepleri'] as $talep) {
        if (
            $talep['personel_id'] === $personelId &&
            $talep['izin_turu'] === 'yillik' &&
            $talep['durum'] === 'beklemede'
        ) {
            $baslangicTimestamp = is_numeric($talep['baslangic_tarihi']) ? $talep['baslangic_tarihi'] : strtotime($talep['baslangic_tarihi']);
            $bitisTimestamp = is_numeric($talep['bitis_tarihi']) ? $talep['bitis_tarihi'] : strtotime($talep['bitis_tarihi']);
            $kullanilanIzin += floor(($bitisTimestamp - $baslangicTimestamp) / (60 * 60 * 24)) + 1;
        }
    }

    return $izinHakki['toplam'] - $kullanilanIzin;
}

// İzin türlerini getir
function izinTurleriniGetir()
{
    $data = veriOku();

    if (!isset($data['sistem_ayarlari']['izin_turleri'])) {
        // Varsayılan izin türleri
        return [
            'yillik' => 'Yıllık İzin',
            'hastalik' => 'Hastalık İzni',
            'idari' => 'İdari İzin'
        ];
    }

    $izinTurleri = [];
    foreach ($data['sistem_ayarlari']['izin_turleri'] as $kod => $izinTuru) {
        $izinTurleri[$kod] = $izinTuru['ad'];
    }

    return $izinTurleri;
}

// Yıllık izin hakkı hesapla
function yillikIzinHakkiHesapla($personelId)
{
    $data = veriOku();
    $personel = null;

    // Önce personeli bul ve izin haklarını kontrol et
    foreach ($data['personel'] as $p) {
        if ($p['id'] === $personelId) {
            $personel = $p;
            break;
        }
    }

    if (!$personel || !isset($personel['izin_haklari']['yillik'])) {
        return 14; // Varsayılan yıllık izin hakkı
    }

    $izinHakki = $personel['izin_haklari']['yillik'];
    $kullanilanIzin = 0;

    // Onaylanmış izinleri hesapla
    foreach ($data['izinler'] as $izin) {
        if ($izin['personel_id'] === $personelId && $izin['izin_turu'] === 'yillik') {
            $baslangicTimestamp = is_numeric($izin['baslangic_tarihi']) ? $izin['baslangic_tarihi'] : strtotime($izin['baslangic_tarihi']);
            $bitisTimestamp = is_numeric($izin['bitis_tarihi']) ? $izin['bitis_tarihi'] : strtotime($izin['bitis_tarihi']);
            $kullanilanIzin += floor(($bitisTimestamp - $baslangicTimestamp) / (60 * 60 * 24)) + 1;
        }
    }

    // Bekleyen izin taleplerini hesapla
    foreach ($data['izin_talepleri'] as $talep) {
        if (
            $talep['personel_id'] === $personelId &&
            $talep['izin_turu'] === 'yillik' &&
            $talep['durum'] === 'beklemede'
        ) {
            $baslangicTimestamp = is_numeric($talep['baslangic_tarihi']) ? $talep['baslangic_tarihi'] : strtotime($talep['baslangic_tarihi']);
            $bitisTimestamp = is_numeric($talep['bitis_tarihi']) ? $talep['bitis_tarihi'] : strtotime($talep['bitis_tarihi']);
            $kullanilanIzin += floor(($bitisTimestamp - $baslangicTimestamp) / (60 * 60 * 24)) + 1;
        }
    }

    return $izinHakki['toplam'] - $kullanilanIzin;
}

// İzin türlerini getir
function izinTurleriniGetir()
{
    $data = veriOku();

    if (!isset($data['sistem_ayarlari']['izin_turleri'])) {
        // Varsayılan izin türleri
        return [
            'yillik' => 'Yıllık İzin',
            'hastalik' => 'Hastalık İzni',
            'idari' => 'İdari İzin'
        ];
    }

    $izinTurleri = [];
    foreach ($data['sistem_ayarlari']['izin_turleri'] as $kod => $izinTuru) {
        $izinTurleri[$kod] = $izinTuru['ad'];
    }

    return $izinTurleri;
}
