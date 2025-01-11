<?php

// JSON dosyasından verileri okuma
function veriOku()
{
    if (!file_exists('personel.json')) {
        return [
            'personel' => [],
            'vardiyalar' => [],
            'vardiya_talepleri' => [],
            'izinler' => [],
            'izin_talepleri' => []
        ];
    }
    $json = file_get_contents('personel.json');
    return json_decode($json, true);
}

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

// Tüm personelleri getir
function tumPersonelleriGetir()
{
    $data = veriOku();
    return $data['personel'];
}

// Personel vardiya bilgilerini getir
function personelVardiyaBilgisiGetir($personelId)
{
    $data = veriOku();
    $toplam = 0;
    $sonVardiyaTimestamp = null;

    foreach ($data['vardiyalar'] as $vardiya) {
        if ($vardiya['personel_id'] === $personelId) {
            $toplam++;
            $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);
            if (!$sonVardiyaTimestamp || $vardiyaTarihi > $sonVardiyaTimestamp) {
                $sonVardiyaTimestamp = $vardiyaTarihi;
            }
        }
    }

    return [
        'toplam_vardiya' => $toplam,
        'son_vardiya' => $sonVardiyaTimestamp
    ];
}

// Personel düzenleme
function personelDuzenle($personelId, $ad, $soyad, $notlar)
{
    $data = veriOku();

    foreach ($data['personel'] as &$personel) {
        if ($personel['id'] === $personelId) {
            $personel['ad'] = $ad;
            $personel['soyad'] = $soyad;
            $personel['notlar'] = $notlar;
            break;
        }
    }

    veriYaz($data);
}

// Personel silme
function personelSil($personelId)
{
    $data = veriOku();

    // Personelin vardiyalarını kontrol et
    foreach ($data['vardiyalar'] as $vardiya) {
        if ($vardiya['personel_id'] === $personelId) {
            throw new Exception('Bu personele ait vardiyalar bulunduğu için silinemez. Önce vardiyaları silmelisiniz.');
        }
    }

    // Personeli sil
    $data['personel'] = array_filter($data['personel'], function ($personel) use ($personelId) {
        return $personel['id'] !== $personelId;
    });

    veriYaz($data);
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

// Yeni personel ekleme
function personelEkle($ad, $soyad, $email, $telefon = '', $yetki = 'personel')
{
    $data = veriOku();

    // E-posta kontrolü
    foreach ($data['personel'] as $personel) {
        if ($personel['email'] === $email) {
            throw new Exception('Bu e-posta adresi zaten kullanılıyor.');
        }
    }

    $yeniPersonel = [
        'id' => uniqid(),
        'ad' => $ad,
        'soyad' => $soyad,
        'email' => $email,
        'telefon' => $telefon,
        'yetki' => $yetki,
        'sifre' => '123456', // Varsayılan şifre
        'tercihler' => [
            'bildirimler' => true,
            'tercih_edilen_vardiyalar' => [],
            'tercih_edilmeyen_gunler' => []
        ],
        'izin_haklari' => [
            'yillik' => [
                'toplam' => 14,
                'kullanilan' => 0,
                'kalan' => 14,
                'son_guncelleme' => time()
            ],
            'mazeret' => [
                'toplam' => 5,
                'kullanilan' => 0,
                'kalan' => 5
            ],
            'hastalik' => [
                'kullanilan' => 0
            ]
        ]
    ];

    $data['personel'][] = $yeniPersonel;
    veriYaz($data);

    islemLogKaydet('personel_ekle', "Yeni personel eklendi: $ad $soyad ($email)");
    return $yeniPersonel['id'];
}

// Vardiya ekleme
function vardiyaEkle($personelId, $tarih, $vardiyaTuru, $notlar = '')
{
    $data = veriOku();
    
    // Personel bilgilerini al
    $personel = array_filter($data['personel'], function($p) use ($personelId) {
        return $p['id'] === $personelId;
    });
    $personel = reset($personel);
    $personelAdSoyad = $personel['ad'] . ' ' . $personel['soyad'];

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

    islemLogKaydet('vardiya_ekle', "Yeni vardiya eklendi: $personelAdSoyad, Tarih: " . date('d.m.Y', $tarih));
    return $yeniVardiya['id'];
}

// Personel listesini getirme (select için)
function personelListesiGetir()
{
    $data = veriOku();
    $output = '';
    foreach ($data['personel'] as $personel) {
        $output .= sprintf(
            '<option value="%s">%s %s</option>',
            $personel['id'],
            htmlspecialchars($personel['ad']),
            htmlspecialchars($personel['soyad'])
        );
    }
    return $output;
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

// Takvim oluşturma
function takvimOlustur($ay, $yil)
{
    $ilkGun = mktime(0, 0, 0, $ay, 1, $yil);
    $ayinIlkGunu = date('w', $ilkGun);
    $aydakiGunSayisi = date('t', $ilkGun);

    $output = '<table class="takvim-tablo">';
    $output .= '<tr>
        <th>Pzr</th>
        <th>Pzt</th>
        <th>Sal</th>
        <th>Çar</th>
        <th>Per</th>
        <th>Cum</th>
        <th>Cmt</th>
    </tr>';

    // Boş günleri ekle
    $output .= '<tr>';
    for ($i = 0; $i < $ayinIlkGunu; $i++) {
        $output .= '<td class="bos"></td>';
    }

    // Günleri ekle
    $gunSayaci = $ayinIlkGunu;
    for ($gun = 1; $gun <= $aydakiGunSayisi; $gun++) {
        if ($gunSayaci % 7 === 0 && $gun !== 1) {
            $output .= '</tr><tr>';
        }

        $gunTimestamp = mktime(0, 0, 0, $ay, $gun, $yil);
        $vardiyalar = gunlukVardiyalariGetir($gunTimestamp);

        $output .= sprintf('<td class="gun" data-tarih="%s">', date('Y-m-d', $gunTimestamp));
        $output .= '<div class="gun-baslik">' . $gun . '</div>';

        if (!empty($vardiyalar)) {
            $output .= '<div class="vardiyalar">';
            foreach ($vardiyalar as $vardiya) {
                $output .= sprintf(
                    '<a href="vardiya.php?vardiya_id=%s" class="vardiya-item %s" title="%s - %s">%s</a>',
                    $vardiya['id'],
                    $vardiya['durum'],
                    htmlspecialchars($vardiya['personel']),
                    htmlspecialchars($vardiya['vardiya_turu']),
                    htmlspecialchars($vardiya['vardiya'])
                );
            }
            $output .= '</div>';
        }

        $output .= '</td>';
        $gunSayaci++;
    }

    // Kalan boş günleri ekle
    while ($gunSayaci % 7 !== 0) {
        $output .= '<td class="bos"></td>';
        $gunSayaci++;
    }

    $output .= '</tr></table>';
    return $output;
}

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

// Personelin izinlerini getir
function personelIzinleriniGetir($personelId)
{
    $data = veriOku();
    $izinler = [];

    foreach ($data['izinler'] as $izin) {
        if ($izin['personel_id'] === $personelId) {
            $izinler[] = $izin;
        }
    }

    return $izinler;
}

// Personelin izin taleplerini getir
function personelIzinTalepleriniGetir($personelId)
{
    $data = veriOku();
    $talepler = [];

    foreach ($data['izin_talepleri'] as $talep) {
        if ($talep['personel_id'] === $personelId) {
            $talepler[] = $talep;
        }
    }

    return $talepler;
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

// Aylık çalışma saatleri raporu
function aylikCalismaRaporu($ay, $yil)
{
    $data = veriOku();
    $rapor = [];
    $vardiyaTurleri = vardiyaTurleriniGetir();

    foreach ($data['personel'] as $personel) {
        $vardiyalar = [];
        foreach ($vardiyaTurleri as $id => $vardiya) {
            $vardiyalar[$id] = 0;
        }

        $rapor[$personel['id']] = [
            'personel' => $personel['ad'] . ' ' . $personel['soyad'],
            'toplam_saat' => 0,
            'vardiyalar' => $vardiyalar
        ];
    }

    $baslangicTimestamp = strtotime(sprintf('%04d-%02d-01', $yil, $ay));
    $bitisTimestamp = strtotime(date('Y-m-t', $baslangicTimestamp));

    foreach ($data['vardiyalar'] as $vardiya) {
        $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);

        if ($vardiyaTarihi >= $baslangicTimestamp && $vardiyaTarihi <= $bitisTimestamp) {
            $saatler = vardiyaSaatleriHesapla($vardiya['vardiya_turu']);
            $rapor[$vardiya['personel_id']]['toplam_saat'] += $saatler['sure'];
            $rapor[$vardiya['personel_id']]['vardiyalar'][$vardiya['vardiya_turu']]++;
        }
    }

    return $rapor;
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

// Personel bazlı vardiya dağılımı
function personelVardiyaDagilimi($baslangicTarih, $bitisTarih)
{
    // Tarihleri timestamp'e çevir
    $baslangicTimestamp = is_numeric($baslangicTarih) ? $baslangicTarih : strtotime($baslangicTarih);
    $bitisTimestamp = is_numeric($bitisTarih) ? $bitisTarih : strtotime($bitisTarih);

    $data = veriOku();
    $vardiyaTurleri = vardiyaTurleriniGetir();
    $dagilim = [];

    foreach ($data['personel'] as $personel) {
        $vardiyalar = [];
        foreach ($vardiyaTurleri as $id => $vardiya) {
            $vardiyalar[$id] = [
                'adet' => 0,
                'etiket' => $vardiya['etiket'],
                'renk' => $vardiya['renk']
            ];
        }

        $dagilim[$personel['id']] = [
            'personel' => $personel['ad'] . ' ' . $personel['soyad'],
            'vardiyalar' => $vardiyalar,
            'toplam' => 0
        ];
    }

    foreach ($data['vardiyalar'] as $vardiya) {
        $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);
        if ($vardiyaTarihi >= $baslangicTimestamp && $vardiyaTarihi <= $bitisTimestamp) {
            $dagilim[$vardiya['personel_id']]['vardiyalar'][$vardiya['vardiya_turu']]['adet']++;
            $dagilim[$vardiya['personel_id']]['toplam']++;
        }
    }

    return $dagilim;
}

// Excel raporu oluştur
function excelRaporuOlustur($ay, $yil)
{
    $rapor = aylikCalismaRaporu($ay, $yil);
    $vardiyaTurleri = vardiyaTurleriniGetir();

    // Başlık satırı
    $basliklar = ['Personel', 'Toplam Saat'];
    foreach ($vardiyaTurleri as $id => $vardiya) {
        $basliklar[] = $vardiya['etiket'] . ' Vardiyası';
    }
    $csv = implode(',', array_map('str_putcsv', $basliklar)) . "\n";

    // Veri satırları
    foreach ($rapor as $personelRapor) {
        $satir = [
            str_putcsv($personelRapor['personel']),
            number_format($personelRapor['toplam_saat'], 2)
        ];
        foreach ($vardiyaTurleri as $id => $vardiya) {
            $satir[] = $personelRapor['vardiyalar'][$id];
        }
        $csv .= implode(',', $satir) . "\n";
    }

    return $csv;
}

// CSV için özel karakter düzenleme
function str_putcsv($str)
{
    $str = str_replace('"', '""', $str);
    if (strpbrk($str, ",\"\r\n") !== false) {
        $str = '"' . $str . '"';
    }
    return $str;
}

// PDF raporu için HTML oluştur
function pdfRaporuIcinHtmlOlustur($ay, $yil)
{
    $rapor = aylikCalismaRaporu($ay, $yil);
    $vardiyaTurleri = vardiyaTurleriniGetir();
    $ayAdi = tarihFormatla(mktime(0, 0, 0, $ay, 1, $yil), 'uzun');

    $html = '<h1>Aylık Çalışma Raporu - ' . $ayAdi . '</h1>';
    $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';

    // Başlık satırı
    $html .= '<tr><th>Personel</th><th>Toplam Saat</th>';
    foreach ($vardiyaTurleri as $vardiya) {
        $html .= sprintf(
            '<th style="background-color: %s">%s</th>',
            $vardiya['renk'],
            htmlspecialchars($vardiya['etiket']) . ' Vardiyası'
        );
    }
    $html .= '</tr>';

    // Veri satırları
    foreach ($rapor as $personelRapor) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($personelRapor['personel']) . '</td>';
        $html .= '<td>' . number_format($personelRapor['toplam_saat'], 2) . ' Saat</td>';

        foreach ($vardiyaTurleri as $id => $vardiya) {
            $html .= sprintf(
                '<td style="text-align: center; background-color: %s">%d</td>',
                adjustBrightness($vardiya['renk'], 0.9),
                $personelRapor['vardiyalar'][$id]
            );
        }

        $html .= '</tr>';
    }

    $html .= '</table>';
    return $html;
}

// Renk parlaklığını ayarla
function adjustBrightness($hex, $factor)
{
    $hex = ltrim($hex, '#');

    if (strlen($hex) == 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    $r = hexdec(substr($hex, 0, 2)) * $factor;
    $g = hexdec(substr($hex, 2, 2)) * $factor;
    $b = hexdec(substr($hex, 4, 2)) * $factor;

    $r = min(255, max(0, $r));
    $g = min(255, max(0, $g));
    $b = min(255, max(0, $b));

    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

// Personel tercihlerini kaydet
function personelTercihKaydet($personelId, $tercihler)
{
    $data = veriOku();
    $tercihBulundu = false;

    foreach ($data['personel'] as &$personel) {
        if ($personel['id'] === $personelId) {
            // Mevcut tercihleri koru ve yenileriyle birleştir
            $personel['tercihler'] = array_merge(
                $personel['tercihler'] ?? [],
                $tercihler
            );
            $tercihBulundu = true;
            break;
        }
    }

    if (!$tercihBulundu) {
        throw new Exception('Personel bulunamadı.');
    }

    veriYaz($data);
    islemLogKaydet('tercih_guncelle', "Personel tercihleri güncellendi: $personelId");
    return true;
}

// Personel tercihlerini getir
function personelTercihGetir($personelId)
{
    $data = veriOku();

    if (!isset($data['personel_tercihleri'])) {
        return [
            'tercih_edilen_vardiyalar' => [],
            'tercih_edilmeyen_gunler' => [],
            'max_ardisik_vardiya' => 5
        ];
    }

    foreach ($data['personel_tercihleri'] as $tercih) {
        if ($tercih['personel_id'] === $personelId) {
            return $tercih['tercihler'];
        }
    }

    return [
        'tercih_edilen_vardiyalar' => [],
        'tercih_edilmeyen_gunler' => [],
        'max_ardisik_vardiya' => 5
    ];
}

// Personelin ay içindeki vardiya sayılarını hesapla
function personelAylikVardiyaSayisi($personelId, $ay, $yil)
{
    $data = veriOku();

    // Personel kontrolü
    $personelBulundu = false;
    $personelBilgisi = null;
    foreach ($data['personel'] as $personel) {
        if ($personel['id'] === $personelId) {
            $personelBulundu = true;
            $personelBilgisi = $personel;
            break;
        }
    }

    if (!$personelBulundu) {
        throw new Exception('Personel bulunamadı.');
    }

    // Ay ve yıl kontrolü
    if (!is_numeric($ay) || $ay < 1 || $ay > 12) {
        throw new Exception('Geçersiz ay değeri.');
    }

    if (!is_numeric($yil) || $yil < 2000 || $yil > 2100) {
        throw new Exception('Geçersiz yıl değeri.');
    }

    $vardiyaTurleri = vardiyaTurleriniGetir();

    // Ay başlangıç ve bitiş tarihlerini timestamp olarak al
    $baslangicTimestamp = mktime(0, 0, 0, $ay, 1, $yil);
    $bitisTimestamp = mktime(23, 59, 59, $ay + 1, 0, $yil);

    $vardiyaSayilari = [
        'toplam' => 0,
        'toplam_saat' => 0,
        'toplam_gece_vardiyasi' => 0,
        'toplam_hafta_sonu' => 0,
        'vardiyalar' => [],
        'personel_bilgisi' => [
            'ad_soyad' => $personelBilgisi['ad'] . ' ' . $personelBilgisi['soyad'],
            'email' => $personelBilgisi['email']
        ],
        'donem' => [
            'ay' => $ay,
            'yil' => $yil,
            'baslangic' => date('Y-m-d', $baslangicTimestamp),
            'bitis' => date('Y-m-d', $bitisTimestamp)
        ]
    ];

    foreach ($vardiyaTurleri as $id => $vardiya) {
        $vardiyaSayilari['vardiyalar'][$id] = [
            'adet' => 0,
            'saat' => 0,
            'etiket' => $vardiya['etiket'] ?? $id,
            'toplam_sure' => 0,
            'hafta_ici_adet' => 0,
            'hafta_sonu_adet' => 0
        ];
    }

    // İzinli günleri kontrol et
    $izinliGunler = [];
    if (isset($data['izinler'])) {
        foreach ($data['izinler'] as $izin) {
            if ($izin['personel_id'] === $personelId) {
                $izinBaslangic = is_numeric($izin['baslangic_tarihi']) ? $izin['baslangic_tarihi'] : strtotime($izin['baslangic_tarihi']);
                $izinBitis = is_numeric($izin['bitis_tarihi']) ? $izin['bitis_tarihi'] : strtotime($izin['bitis_tarihi']);

                if ($izinBaslangic <= $bitisTimestamp && $izinBitis >= $baslangicTimestamp) {
                    for ($t = max($izinBaslangic, $baslangicTimestamp); $t <= min($izinBitis, $bitisTimestamp); $t = strtotime('+1 day', $t)) {
                        $izinliGunler[date('Y-m-d', $t)] = true;
                    }
                }
            }
        }
    }

    $vardiyaSayilari['izinli_gun_sayisi'] = count($izinliGunler);

    foreach ($data['vardiyalar'] as $vardiya) {
        if ($vardiya['personel_id'] === $personelId) {
            $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);

            if ($vardiyaTarihi >= $baslangicTimestamp && $vardiyaTarihi <= $bitisTimestamp) {
                // Vardiya türü kontrolü
                if (!isset($vardiyaTurleri[$vardiya['vardiya_turu']])) {
                    continue; // Geçersiz vardiya türünü atla
                }

                $vardiyaGunu = date('Y-m-d', $vardiyaTarihi);
                $haftaSonu = (date('N', $vardiyaTarihi) >= 6); // 6=Cumartesi, 7=Pazar

                if ($haftaSonu) {
                    $vardiyaSayilari['toplam_hafta_sonu']++;
                    $vardiyaSayilari['vardiyalar'][$vardiya['vardiya_turu']]['hafta_sonu_adet']++;
                } else {
                    $vardiyaSayilari['vardiyalar'][$vardiya['vardiya_turu']]['hafta_ici_adet']++;
                }

                $vardiyaSayilari['vardiyalar'][$vardiya['vardiya_turu']]['adet']++;

                // Vardiya süresini hesapla
                try {
                    $vardiyaSaatleri = vardiyaSaatleriHesapla($vardiya['vardiya_turu']);
                    $vardiyaSayilari['vardiyalar'][$vardiya['vardiya_turu']]['saat'] += $vardiyaSaatleri['sure'];
                    $vardiyaSayilari['toplam_saat'] += $vardiyaSaatleri['sure'];

                    if ($vardiyaSaatleri['gece_vardiyasi']) {
                        $vardiyaSayilari['toplam_gece_vardiyasi']++;
                    }
                } catch (Exception $e) {
                    // Vardiya süresi hesaplanamadıysa atla
                    continue;
                }

                $vardiyaSayilari['toplam']++;
            }
        }
    }

    // Ek istatistikler
    $vardiyaSayilari['ortalama_gunluk_saat'] = $vardiyaSayilari['toplam'] > 0
        ? round($vardiyaSayilari['toplam_saat'] / $vardiyaSayilari['toplam'], 2)
        : 0;

    $vardiyaSayilari['calisilan_gun_sayisi'] = $vardiyaSayilari['toplam'];
    $vardiyaSayilari['izinli_gun_sayisi'] = count($izinliGunler);

    $aydakiGunSayisi = date('t', $baslangicTimestamp);
    $vardiyaSayilari['bos_gun_sayisi'] = $aydakiGunSayisi - $vardiyaSayilari['calisilan_gun_sayisi'] - $vardiyaSayilari['izinli_gun_sayisi'];

    return $vardiyaSayilari;
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

// Kullanıcı girişi
function kullaniciGiris($email, $sifre)
{
    try {
        // E-posta formatı kontrolü
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Geçersiz e-posta formatı.');
        }

        $data = veriOku();

        // Personel dizisi kontrolü
        if (!isset($data['personel']) || empty($data['personel'])) {
            throw new Exception('Sistemde kayıtlı personel bulunmuyor.');
        }

        // Kullanıcı arama ve doğrulama
        foreach ($data['personel'] as $personel) {
            if ($personel['email'] === $email) {
                // Şifre kontrolü
                if (!isset($personel['sifre'])) {
                    throw new Exception('Kullanıcı şifresi tanımlanmamış.');
                }

                if ($sifre === $personel['sifre']) {
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

                    // Tercihler varsa kaydet
                    if (isset($personel['tercihler'])) {
                        $_SESSION['tercihler'] = $personel['tercihler'];
                    }

                    // İzin hakları varsa kaydet
                    if (isset($personel['izin_haklari'])) {
                        $_SESSION['izin_haklari'] = $personel['izin_haklari'];
                    }

                    // Giriş logunu kaydet
                    islemLogKaydet('giris', 'Başarılı giriş: ' . $personel['email']);

                    return true;
                } else {
                    throw new Exception('Hatalı şifre.');
                }
            }
        }

        throw new Exception('Bu e-posta adresi ile kayıtlı personel bulunamadı.');
    } catch (Exception $e) {
        // Başarısız giriş logunu kaydet
        if (isset($email)) {
            islemLogKaydet('giris_hata', 'Başarısız giriş denemesi: ' . $email . ' - ' . $e->getMessage());
        }
        throw $e;
    }
}

// Kullanıcı çıkışı
function kullaniciCikis()
{
    islemLogKaydet('cikis', 'Kullanıcı çıkışı yapıldı');
    session_destroy();
}

// Yeni kullanıcı oluşturma
function kullaniciOlustur($ad, $soyad, $email, $sifre, $rol = 'personel', $telefon = '', $tercihler = null)
{
    $data = veriOku();

    // E-posta kontrolü
    foreach ($data['personel'] as $personel) {
        if ($personel['email'] === $email) {
            throw new Exception('Bu e-posta adresi zaten kullanılıyor.');
        }
    }

    // Varsayılan tercihler
    if ($tercihler === null) {
        $tercihler = [
            'bildirimler' => true,
            'tercih_edilen_vardiyalar' => [],
            'tercih_edilmeyen_gunler' => []
        ];
    }

    // Yeni kullanıcı
    $yeniKullanici = [
        'id' => uniqid(),
        'ad' => $ad,
        'soyad' => $soyad,
        'yetki' => $rol,
        'email' => $email,
        'telefon' => $telefon,
        'sifre' => $sifre,
        'tercihler' => $tercihler,
        'izin_haklari' => [
            'yillik' => [
                'toplam' => 14,
                'kullanilan' => 0,
                'kalan' => 14,
                'son_guncelleme' => time()
            ],
            'mazeret' => [
                'toplam' => 5,
                'kullanilan' => 0,
                'kalan' => 5
            ],
            'hastalik' => [
                'kullanilan' => 0
            ]
        ]
    ];

    $data['personel'][] = $yeniKullanici;
    veriYaz($data);

    islemLogKaydet('kullanici_olustur', "Yeni kullanıcı oluşturuldu: $email");
    return $yeniKullanici['id'];
}

// Kullanıcı güncelleme
function kullaniciGuncelle($kullaniciId, $ad, $soyad, $email, $rol = null, $telefon = null, $tercihler = null)
{
    $data = veriOku();

    // E-posta kontrolü (kendi e-postası hariç)
    foreach ($data['personel'] as $personel) {
        if ($personel['email'] === $email && $personel['id'] !== $kullaniciId) {
            throw new Exception('Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.');
        }
    }

    foreach ($data['personel'] as &$kullanici) {
        if ($kullanici['id'] === $kullaniciId) {
            $kullanici['ad'] = $ad;
            $kullanici['soyad'] = $soyad;
            $kullanici['email'] = $email;

            if ($rol !== null) {
                $kullanici['yetki'] = $rol;
            }

            if ($telefon !== null) {
                $kullanici['telefon'] = $telefon;
            }

            if ($tercihler !== null) {
                $kullanici['tercihler'] = array_merge($kullanici['tercihler'] ?? [], $tercihler);
            }

            // İzin haklarını kontrol et ve yoksa ekle
            if (!isset($kullanici['izin_haklari'])) {
                $kullanici['izin_haklari'] = [
                    'yillik' => [
                        'toplam' => 14,
                        'kullanilan' => 0,
                        'kalan' => 14,
                        'son_guncelleme' => time()
                    ],
                    'mazeret' => [
                        'toplam' => 5,
                        'kullanilan' => 0,
                        'kalan' => 5
                    ],
                    'hastalik' => [
                        'kullanilan' => 0
                    ]
                ];
            }

            veriYaz($data);
            islemLogKaydet('kullanici_guncelle', "Kullanıcı güncellendi: $email");
            return true;
        }
    }

    throw new Exception('Kullanıcı bulunamadı.');
}

// Şifre değiştirme
function sifreDegistir($kullaniciId, $eskiSifre, $yeniSifre)
{
    $data = veriOku();

    foreach ($data['personel'] as &$kullanici) {
        if ($kullanici['id'] === $kullaniciId) {
            if ($kullanici['sifre'] !== $eskiSifre) {
                throw new Exception('Mevcut şifre hatalı.');
            }

            $kullanici['sifre'] = $yeniSifre;
            veriYaz($data);

            islemLogKaydet('sifre_degistir', 'Kullanıcı şifresi değiştirildi');
            return true;
        }
    }

    throw new Exception('Kullanıcı bulunamadı.');
}

// Yetki kontrolü
function yetkiKontrol($gerekliRoller = ['admin'])
{
    if (!isset($_SESSION['kullanici_id']) || !isset($_SESSION['rol'])) {
        header('Location: giris.php');
        exit;
    }

    if (!in_array($_SESSION['rol'], $gerekliRoller)) {
        throw new Exception('Bu işlem için yetkiniz bulunmuyor.');
    }

    return true;
}

// İşlem logu kaydetme
function islemLogKaydet($islemTuru, $aciklama)
{
    $data = veriOku();

    if (!isset($data['islem_loglari'])) {
        $data['islem_loglari'] = [];
    }

    $yeniLog = [
        'id' => uniqid(),
        'kullanici_id' => $_SESSION['kullanici_id'] ?? null,
        'kullanici_rol' => $_SESSION['rol'] ?? null,
        'islem_turu' => $islemTuru,
        'aciklama' => $aciklama,
        'ip_adresi' => $_SERVER['REMOTE_ADDR'] ?? null,
        'tarih' => time(),
        'tarayici' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];

    // Log sayısını kontrol et ve gerekirse eski logları temizle
    if (count($data['islem_loglari']) > 1000) {
        // En eski 200 logu sil
        $data['islem_loglari'] = array_slice($data['islem_loglari'], -800);
    }

    $data['islem_loglari'][] = $yeniLog;
    veriYaz($data);
    return $yeniLog['id'];
}

// İşlem loglarını getir
function islemLoglariGetir($baslangicTarih = null, $bitisTarih = null, $islemTuru = null, $kullaniciId = null)
{
    $data = veriOku();
    $loglar = [];

    if (!isset($data['islem_loglari'])) {
        return $loglar;
    }

    foreach ($data['islem_loglari'] as $log) {
        $ekle = true;

        if ($baslangicTarih && $log['tarih'] < $baslangicTarih) {
            $ekle = false;
        }

        if ($bitisTarih && $log['tarih'] > $bitisTarih) {
            $ekle = false;
        }

        if ($islemTuru && $log['islem_turu'] !== $islemTuru) {
            $ekle = false;
        }

        if ($kullaniciId && $log['kullanici_id'] !== $kullaniciId) {
            $ekle = false;
        }

        if ($ekle) {
            $loglar[] = $log;
        }
    }

    return $loglar;
}

// Kullanıcı tercihlerini güncelleme
function kullaniciTercihleriniGuncelle($kullaniciId, $tercihler)
{
    $data = veriOku();
    
    // Personel bilgilerini al
    $personel = array_filter($data['personel'], function($p) use ($kullaniciId) {
        return $p['id'] === $kullaniciId;
    });
    $personel = reset($personel);
    $personelAdSoyad = $personel['ad'] . ' ' . $personel['soyad'];

    $varsayilanTercihler = [
        'bildirimler' => true,
        'tercih_edilen_vardiyalar' => [],
        'tercih_edilmeyen_gunler' => [],
        'max_ardisik_vardiya' => 5,
        'min_gunluk_dinlenme' => 11,
        'haftalik_izin_tercihi' => 'pazar',
        'vardiya_degisim_bildirimi' => true,
        'izin_talep_bildirimi' => true,
        'sistem_bildirimleri' => true
    ];

    foreach ($data['personel'] as &$kullanici) {
        if ($kullanici['id'] === $kullaniciId) {
            // Mevcut tercihleri koru
            $kullanici['tercihler'] = array_merge(
                $kullanici['tercihler'] ?? $varsayilanTercihler,
                $tercihler
            );

            veriYaz($data);
            islemLogKaydet('tercih_guncelle', "Kullanıcı tercihleri güncellendi: $personelAdSoyad");
            return true;
        }
    }

    throw new Exception('Kullanıcı bulunamadı.');
}

// Kullanıcı bildirim tercihini güncelleme
function bildirimTercihiGuncelle($kullaniciId, $bildirimTercihleri)
{
    $data = veriOku();
    $varsayilanBildirimler = [
        'bildirimler' => true,
        'vardiya_degisim_bildirimi' => true,
        'izin_talep_bildirimi' => true,
        'sistem_bildirimleri' => true,
        'email_bildirimi' => true,
        'tarayici_bildirimi' => true
    ];

    foreach ($data['personel'] as &$kullanici) {
        if ($kullanici['id'] === $kullaniciId) {
            // Mevcut bildirimleri al veya varsayılan değerleri kullan
            if (!isset($kullanici['tercihler']['bildirimler'])) {
                $kullanici['tercihler']['bildirimler'] = $varsayilanBildirimler;
            }

            // Yeni bildirimleri mevcut bildirimlerle birleştir
            foreach ($bildirimTercihleri as $key => $value) {
                if (array_key_exists($key, $varsayilanBildirimler)) {
                    $kullanici['tercihler']['bildirimler'][$key] = (bool)$value;
                }
            }

            veriYaz($data);
            islemLogKaydet('bildirim_tercih', sprintf(
                'Bildirim tercihleri güncellendi: %s - %s',
                $kullaniciId,
                json_encode($bildirimTercihleri, JSON_UNESCAPED_UNICODE)
            ));
            return true;
        }
    }

    throw new Exception('Kullanıcı bulunamadı.');
}

// Tarih formatlama fonksiyonu
function tarihFormatla($timestamp, $format = 'kisa')
{
    if (!is_numeric($timestamp)) {
        $timestamp = strtotime($timestamp);
    }

    $tarih = date_create(date('Y-m-d H:i:s', $timestamp));

    $gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
    $aylar = [
        'Ocak',
        'Şubat',
        'Mart',
        'Nisan',
        'Mayıs',
        'Haziran',
        'Temmuz',
        'Ağustos',
        'Eylül',
        'Ekim',
        'Kasım',
        'Aralık'
    ];

    switch ($format) {
        case 'kisa':
            return date_format($tarih, 'd.m.Y');

        case 'uzun':
            return date_format($tarih, 'j') . ' ' . $aylar[date_format($tarih, 'n') - 1] . ' ' . date_format($tarih, 'Y');

        case 'tam':
            return $gunler[date_format($tarih, 'w')] . ', ' . date_format($tarih, 'j') . ' ' . $aylar[date_format($tarih, 'n') - 1] . ' ' . date_format($tarih, 'Y');

        case 'saat':
            return date_format($tarih, 'H:i');

        case 'tam_saat':
            return date_format($tarih, 'j') . ' ' . $aylar[date_format($tarih, 'n') - 1] . ' ' . date_format($tarih, 'Y') . ' ' . date_format($tarih, 'H:i');

        case 'veritabani':
            return date_format($tarih, 'Y-m-d H:i:s');

        case 'gun':
            return $gunler[date_format($tarih, 'w')];

        case 'ay':
            return $aylar[date_format($tarih, 'n') - 1];

        default:
            return date_format($tarih, 'd.m.Y');
    }
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

/**
 * Belirtilen tarih aralığı için vardiya raporunu getirir
 * 
 * @param string $baslangic_tarihi Y-m-d formatında başlangıç tarihi
 * @param string $bitis_tarihi Y-m-d formatında bitiş tarihi
 * @param string|null $personel_id Belirli bir personel için filtreleme (opsiyonel)
 * @param string|null $vardiya_turu Belirli bir vardiya türü için filtreleme (opsiyonel)
 * @return array Rapor verileri
 * @throws Exception Hata durumunda
 */
function vardiyaRaporuGetir($baslangic_tarihi, $bitis_tarihi, $personel_id = null, $vardiya_turu = null)
{
    $data = veriOku();
    $vardiyalar = $data['vardiyalar'] ?? [];
    $personeller = $data['personel'] ?? [];
    $vardiyaTurleri = vardiyaTurleriniGetir();

    // Filtreleme
    $filtrelenmisVardiyalar = array_filter($vardiyalar, function ($vardiya) use ($baslangic_tarihi, $bitis_tarihi, $personel_id, $vardiya_turu) {
        if ($vardiya['tarih'] < $baslangic_tarihi || $vardiya['tarih'] > $bitis_tarihi) {
            return false;
        }
        if ($personel_id !== null && $vardiya['personel_id'] !== $personel_id) {
            return false;
        }
        if ($vardiya_turu !== null && $vardiya['vardiya_turu'] !== $vardiya_turu) {
            return false;
        }
        return true;
    });

    // Vardiya dağılımını hesapla
    $vardiyaDagilimi = [];
    foreach ($filtrelenmisVardiyalar as $vardiya) {
        $tur = $vardiya['vardiya_turu'];
        if (!isset($vardiyaDagilimi[$tur])) {
            $vardiyaDagilimi[$tur] = [
                'etiket' => $vardiyaTurleri[$tur]['etiket'],
                'sayi' => 0
            ];
        }
        $vardiyaDagilimi[$tur]['sayi']++;
    }

    // Günlük dağılımı hesapla
    $gunlukDagilim = [];
    $current = strtotime($baslangic_tarihi);
    $end = strtotime($bitis_tarihi);
    while ($current <= $end) {
        $tarih = date('Y-m-d', $current);
        $gunlukDagilim[$tarih] = [
            'tarih' => date('d.m.Y', $current),
            'sayi' => 0
        ];
        $current = strtotime('+1 day', $current);
    }

    foreach ($filtrelenmisVardiyalar as $vardiya) {
        $gunlukDagilim[$vardiya['tarih']]['sayi']++;
    }

    // Toplam saatleri hesapla
    $toplamSaat = 0;
    foreach ($filtrelenmisVardiyalar as $vardiya) {
        $tur = $vardiyaTurleri[$vardiya['vardiya_turu']];
        $baslangic = strtotime($tur['baslangic']);
        $bitis = strtotime($tur['bitis']);
        if ($bitis < $baslangic) {
            $bitis = strtotime('+1 day', $bitis);
        }
        $toplamSaat += ($bitis - $baslangic) / 3600;
    }

    // Detaylı vardiya listesi
    $detayliVardiyalar = [];
    foreach ($filtrelenmisVardiyalar as $vardiya) {
        $personel = array_filter($personeller, function ($p) use ($vardiya) {
            return $p['id'] === $vardiya['personel_id'];
        });
        $personel = reset($personel);
        $tur = $vardiyaTurleri[$vardiya['vardiya_turu']];

        $detayliVardiyalar[] = [
            'tarih' => $vardiya['tarih'],
            'personel_adi' => $personel['ad'] . ' ' . $personel['soyad'],
            'vardiya_turu' => $tur['etiket'],
            'baslangic' => $tur['baslangic'],
            'bitis' => $tur['bitis'],
            'durum' => $vardiya['durum'] ?? 'Normal'
        ];
    }

    // Sonuçları döndür
    return [
        'toplam_vardiya' => count($filtrelenmisVardiyalar),
        'toplam_personel' => count(array_unique(array_column($filtrelenmisVardiyalar, 'personel_id'))),
        'toplam_saat' => round($toplamSaat),
        'vardiya_dagilimi' => array_values($vardiyaDagilimi),
        'gunluk_dagilim' => array_values($gunlukDagilim),
        'vardiyalar' => $detayliVardiyalar
    ];
}
