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
    if (!ardisikCalismaGunleriniKontrolEt($personelId, $tarih, $vardiyaId)) {
        throw new Exception('Bu personel 6 gün üst üste çalıştığı için bu tarihe vardiya eklenemez. En az 1 gün izin kullanması gerekiyor.');
    }

    $data = veriOku();
    foreach ($data['vardiyalar'] as &$vardiya) {
        if ($vardiya['id'] === $vardiyaId) {
            $vardiya['personel_id'] = $personelId;
            $vardiya['tarih'] = $tarih;
            $vardiya['vardiya_turu'] = $vardiyaTuru;
            $vardiya['notlar'] = $notlar;
            break;
        }
    }
    veriYaz($data);
}

// Vardiya çakışması kontrolü
function vardiyaCakismasiVarMi($personelId, $tarih, $vardiyaTuru, $haricVardiyaId = null)
{
    $data = veriOku();
    foreach ($data['vardiyalar'] as $vardiya) {
        if (
            $vardiya['personel_id'] === $personelId &&
            $vardiya['tarih'] === $tarih &&
            $vardiya['id'] !== $haricVardiyaId
        ) {
            return true;
        }
    }
    return false;
}

// Vardiya değişim talebi oluşturma
function vardiyaDegisimTalebiOlustur($vardiyaId, $talepEdenPersonelId, $aciklama)
{
    $data = veriOku();
    $yeniTalep = [
        'id' => uniqid(),
        'vardiya_id' => $vardiyaId,
        'talep_eden_personel_id' => $talepEdenPersonelId,
        'durum' => 'beklemede', // beklemede, onaylandi, reddedildi
        'aciklama' => $aciklama,
        'olusturma_tarihi' => date('Y-m-d H:i:s')
    ];
    $data['vardiya_talepleri'][] = $yeniTalep;
    veriYaz($data);
}

// Vardiya değişim talebini onayla/reddet
function vardiyaTalebiGuncelle($talepId, $durum)
{
    $data = veriOku();
    foreach ($data['vardiya_talepleri'] as &$talep) {
        if ($talep['id'] === $talepId) {
            $talep['durum'] = $durum;
            $talep['guncelleme_tarihi'] = date('Y-m-d H:i:s');
            break;
        }
    }
    veriYaz($data);
}

// Vardiya detaylarını getir
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
    $sonVardiya = null;

    foreach ($data['vardiyalar'] as $vardiya) {
        if ($vardiya['personel_id'] === $personelId) {
            $toplam++;
            if (!$sonVardiya || strtotime($vardiya['tarih']) > strtotime($sonVardiya)) {
                $sonVardiya = $vardiya['tarih'];
            }
        }
    }

    return [
        'toplam_vardiya' => $toplam,
        'son_vardiya' => $sonVardiya
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
    $kontrolTarihi = strtotime($tarih);
    $ardisikGunler = 0;

    // Seçilen tarihten geriye doğru 6 günü kontrol et
    for ($i = 0; $i < 6; $i++) {
        $kontrolEdilecekTarih = date('Y-m-d', strtotime("-$i day", $kontrolTarihi));

        foreach ($data['vardiyalar'] as $vardiya) {
            if ($vardiya['personel_id'] === $personelId && $vardiya['tarih'] === $kontrolEdilecekTarih) {
                $ardisikGunler++;
                break;
            }
        }
    }

    // Eğer 6 gün üst üste çalışmışsa, 7. gün çalışamaz
    if ($ardisikGunler >= 6) {
        return false;
    }

    // Seçilen tarihten sonraki günleri de kontrol et
    for ($i = 1; $i < 6; $i++) {
        $kontrolEdilecekTarih = date('Y-m-d', strtotime("+$i day", $kontrolTarihi));

        foreach ($data['vardiyalar'] as $vardiya) {
            if ($vardiya['personel_id'] === $personelId && $vardiya['tarih'] === $kontrolEdilecekTarih) {
                $ardisikGunler++;
                if ($ardisikGunler >= 6) {
                    return false;
                }
                break;
            }
        }
    }

    return true;
}

// Yeni personel ekleme
function personelEkle($ad, $soyad)
{
    $data = veriOku();
    $yeniPersonel = [
        'id' => uniqid(),
        'ad' => $ad,
        'soyad' => $soyad,
        'notlar' => ''
    ];
    $data['personel'][] = $yeniPersonel;
    veriYaz($data);
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

    $data = veriOku();
    $yeniVardiya = [
        'id' => uniqid(),
        'personel_id' => $personelId,
        'tarih' => $tarih,
        'vardiya_turu' => $vardiyaTuru,
        'notlar' => $notlar
    ];
    $data['vardiyalar'][] = $yeniVardiya;
    veriYaz($data);

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
    $data = veriOku();
    $gunlukVardiyalar = [];

    foreach ($data['vardiyalar'] as $vardiya) {
        if ($vardiya['tarih'] === $tarih) {
            $personel = array_filter($data['personel'], function ($p) use ($vardiya) {
                return $p['id'] === $vardiya['personel_id'];
            });
            $personel = reset($personel);

            $vardiyaTurleri = [
                'sabah' => 'S',
                'aksam' => 'A',
                'gece' => 'G'
            ];

            $gunlukVardiyalar[] = [
                'id' => $vardiya['id'],
                'personel' => $personel['ad'] . ' ' . $personel['soyad'],
                'vardiya' => $vardiyaTurleri[$vardiya['vardiya_turu']]
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

        $tarih = sprintf('%04d-%02d-%02d', $yil, $ay, $gun);
        $vardiyalar = gunlukVardiyalariGetir($tarih);

        $output .= sprintf('<td class="gun" data-tarih="%s">', $tarih);
        $output .= '<div class="gun-baslik">' . $gun . '</div>';

        if (!empty($vardiyalar)) {
            $output .= '<div class="vardiyalar">';
            foreach ($vardiyalar as $vardiya) {
                $output .= sprintf(
                    '<a href="vardiya.php?vardiya_id=%s" class="vardiya-item" title="%s">%s</a>',
                    $vardiya['id'],
                    htmlspecialchars($vardiya['personel']),
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
    // Tarih kontrolü
    if (strtotime($baslangicTarihi) > strtotime($bitisTarihi)) {
        throw new Exception('Bitiş tarihi başlangıç tarihinden önce olamaz.');
    }

    // Vardiya çakışması kontrolü
    $data = veriOku();
    $baslangic = new DateTime($baslangicTarihi);
    $bitis = new DateTime($bitisTarihi);
    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($baslangic, $interval, $bitis->modify('+1 day'));

    foreach ($daterange as $date) {
        foreach ($data['vardiyalar'] as $vardiya) {
            if ($vardiya['personel_id'] === $personelId && $vardiya['tarih'] === $date->format('Y-m-d')) {
                throw new Exception('Seçilen tarih aralığında vardiya bulunuyor. Önce vardiyaları düzenlemelisiniz.');
            }
        }
    }

    // Çakışan izin kontrolü
    foreach ($data['izinler'] as $izin) {
        if ($izin['personel_id'] === $personelId) {
            $izinBaslangic = strtotime($izin['baslangic_tarihi']);
            $izinBitis = strtotime($izin['bitis_tarihi']);
            $yeniBaslangic = strtotime($baslangicTarihi);
            $yeniBitis = strtotime($bitisTarihi);

            if (($yeniBaslangic >= $izinBaslangic && $yeniBaslangic <= $izinBitis) ||
                ($yeniBitis >= $izinBaslangic && $yeniBitis <= $izinBitis)
            ) {
                throw new Exception('Seçilen tarih aralığında başka bir izin bulunuyor.');
            }
        }
    }

    // Yıllık izin hakkı kontrolü
    if ($izinTuru === 'yillik') {
        $kalanIzin = yillikIzinHakkiHesapla($personelId);
        $izinGunSayisi = (strtotime($bitisTarihi) - strtotime($baslangicTarihi)) / (60 * 60 * 24) + 1;
        if ($izinGunSayisi > $kalanIzin) {
            throw new Exception('Yeterli yıllık izin hakkınız bulunmuyor. Kalan izin: ' . $kalanIzin . ' gün');
        }
    }

    $yeniTalep = [
        'id' => uniqid(),
        'personel_id' => $personelId,
        'baslangic_tarihi' => $baslangicTarihi,
        'bitis_tarihi' => $bitisTarihi,
        'izin_turu' => $izinTuru, // yillik, hastalik, idari
        'aciklama' => $aciklama,
        'durum' => 'beklemede', // beklemede, onaylandi, reddedildi
        'olusturma_tarihi' => date('Y-m-d H:i:s')
    ];

    $data['izin_talepleri'][] = $yeniTalep;
    veriYaz($data);

    return $yeniTalep['id'];
}

// İzin talebini onayla/reddet
function izinTalebiGuncelle($talepId, $durum, $yoneticiNotu = '')
{
    $data = veriOku();
    foreach ($data['izin_talepleri'] as $key => $talep) {
        if ($talep['id'] === $talepId) {
            $data['izin_talepleri'][$key]['durum'] = $durum;
            $data['izin_talepleri'][$key]['yonetici_notu'] = $yoneticiNotu;
            $data['izin_talepleri'][$key]['guncelleme_tarihi'] = date('Y-m-d H:i:s');

            // Eğer onaylandıysa izinler listesine ekle
            if ($durum === 'onaylandi') {
                $yeniIzin = [
                    'id' => uniqid(),
                    'personel_id' => $talep['personel_id'],
                    'baslangic_tarihi' => $talep['baslangic_tarihi'],
                    'bitis_tarihi' => $talep['bitis_tarihi'],
                    'izin_turu' => $talep['izin_turu'],
                    'aciklama' => $talep['aciklama']
                ];
                $data['izinler'][] = $yeniIzin;
            }
            break;
        }
    }
    veriYaz($data);
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
    $yillikIzinHakki = 14; // Varsayılan yıllık izin hakkı

    // Kullanılan izinleri hesapla
    $data = veriOku();
    $kullanilanIzin = 0;

    foreach ($data['izinler'] as $izin) {
        if ($izin['personel_id'] === $personelId && $izin['izin_turu'] === 'yillik') {
            $baslangic = new DateTime($izin['baslangic_tarihi']);
            $bitis = new DateTime($izin['bitis_tarihi']);
            $fark = $bitis->diff($baslangic);
            $kullanilanIzin += $fark->days + 1;
        }
    }

    // Bekleyen izin taleplerini de hesaba kat
    foreach ($data['izin_talepleri'] as $talep) {
        if (
            $talep['personel_id'] === $personelId &&
            $talep['izin_turu'] === 'yillik' &&
            $talep['durum'] === 'beklemede'
        ) {
            $baslangic = new DateTime($talep['baslangic_tarihi']);
            $bitis = new DateTime($talep['bitis_tarihi']);
            $fark = $bitis->diff($baslangic);
            $kullanilanIzin += $fark->days + 1;
        }
    }

    return $yillikIzinHakki - $kullanilanIzin;
}

// İzin türlerini getir
function izinTurleriniGetir()
{
    return [
        'yillik' => 'Yıllık İzin',
        'hastalik' => 'Hastalık İzni',
        'idari' => 'İdari İzin'
    ];
}

// Vardiya saatlerini hesapla
function vardiyaSaatleriHesapla($vardiyaTuru) {
    $saatler = [
        'sabah' => ['baslangic' => '08:00', 'bitis' => '16:00', 'sure' => 8],
        'aksam' => ['baslangic' => '16:00', 'bitis' => '24:00', 'sure' => 8],
        'gece' => ['baslangic' => '00:00', 'bitis' => '08:00', 'sure' => 8]
    ];
    return $saatler[$vardiyaTuru];
}

// Aylık çalışma saatleri raporu
function aylikCalismaRaporu($ay, $yil) {
    $data = veriOku();
    $rapor = [];
    
    foreach ($data['personel'] as $personel) {
        $rapor[$personel['id']] = [
            'personel' => $personel['ad'] . ' ' . $personel['soyad'],
            'toplam_saat' => 0,
            'vardiyalar' => [
                'sabah' => 0,
                'aksam' => 0,
                'gece' => 0
            ]
        ];
    }
    
    $baslangic = sprintf('%04d-%02d-01', $yil, $ay);
    $bitis = date('Y-m-t', strtotime($baslangic));
    
    foreach ($data['vardiyalar'] as $vardiya) {
        if ($vardiya['tarih'] >= $baslangic && $vardiya['tarih'] <= $bitis) {
            $saatler = vardiyaSaatleriHesapla($vardiya['vardiya_turu']);
            $rapor[$vardiya['personel_id']]['toplam_saat'] += $saatler['sure'];
            $rapor[$vardiya['personel_id']]['vardiyalar'][$vardiya['vardiya_turu']]++;
        }
    }
    
    return $rapor;
}

// Vardiya türlerine göre dağılım
function vardiyaTuruDagilimi($baslangicTarih, $bitisTarih) {
    $data = veriOku();
    $dagilim = [
        'sabah' => 0,
        'aksam' => 0,
        'gece' => 0
    ];
    
    foreach ($data['vardiyalar'] as $vardiya) {
        if ($vardiya['tarih'] >= $baslangicTarih && $vardiya['tarih'] <= $bitisTarih) {
            $dagilim[$vardiya['vardiya_turu']]++;
        }
    }
    
    return $dagilim;
}

// Personel bazlı vardiya dağılımı
function personelVardiyaDagilimi($baslangicTarih, $bitisTarih) {
    $data = veriOku();
    $dagilim = [];
    
    foreach ($data['personel'] as $personel) {
        $dagilim[$personel['id']] = [
            'personel' => $personel['ad'] . ' ' . $personel['soyad'],
            'vardiyalar' => [
                'sabah' => 0,
                'aksam' => 0,
                'gece' => 0
            ],
            'toplam' => 0
        ];
    }
    
    foreach ($data['vardiyalar'] as $vardiya) {
        if ($vardiya['tarih'] >= $baslangicTarih && $vardiya['tarih'] <= $bitisTarih) {
            $dagilim[$vardiya['personel_id']]['vardiyalar'][$vardiya['vardiya_turu']]++;
            $dagilim[$vardiya['personel_id']]['toplam']++;
        }
    }
    
    return $dagilim;
}

// Excel raporu oluştur
function excelRaporuOlustur($ay, $yil) {
    $rapor = aylikCalismaRaporu($ay, $yil);
    $vardiyaTurleri = ['sabah' => 'Sabah', 'aksam' => 'Akşam', 'gece' => 'Gece'];
    
    $csv = "Personel,Toplam Saat,Sabah Vardiyası,Akşam Vardiyası,Gece Vardiyası\n";
    
    foreach ($rapor as $personelRapor) {
        $csv .= sprintf(
            "%s,%d,%d,%d,%d\n",
            $personelRapor['personel'],
            $personelRapor['toplam_saat'],
            $personelRapor['vardiyalar']['sabah'],
            $personelRapor['vardiyalar']['aksam'],
            $personelRapor['vardiyalar']['gece']
        );
    }
    
    return $csv;
}

// PDF raporu için HTML oluştur
function pdfRaporuIcinHtmlOlustur($ay, $yil) {
    $rapor = aylikCalismaRaporu($ay, $yil);
    $vardiyaTurleri = ['sabah' => 'Sabah', 'aksam' => 'Akşam', 'gece' => 'Gece'];
    
    $html = '<h1>Aylık Çalışma Raporu - ' . date('F Y', mktime(0, 0, 0, $ay, 1, $yil)) . '</h1>';
    $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
    $html .= '<tr>
        <th>Personel</th>
        <th>Toplam Saat</th>
        <th>Sabah Vardiyası</th>
        <th>Akşam Vardiyası</th>
        <th>Gece Vardiyası</th>
    </tr>';
    
    foreach ($rapor as $personelRapor) {
        $html .= sprintf(
            '<tr>
                <td>%s</td>
                <td>%d</td>
                <td>%d</td>
                <td>%d</td>
                <td>%d</td>
            </tr>',
            $personelRapor['personel'],
            $personelRapor['toplam_saat'],
            $personelRapor['vardiyalar']['sabah'],
            $personelRapor['vardiyalar']['aksam'],
            $personelRapor['vardiyalar']['gece']
        );
    }
    
    $html .= '</table>';
    return $html;
}

// Personel tercihlerini kaydet
function personelTercihKaydet($personelId, $tercihler) {
    $data = veriOku();
    
    // Tercihleri güncelle veya ekle
    $tercihBulundu = false;
    if (!isset($data['personel_tercihleri'])) {
        $data['personel_tercihleri'] = [];
    }
    
    foreach ($data['personel_tercihleri'] as &$tercih) {
        if ($tercih['personel_id'] === $personelId) {
            $tercih['tercihler'] = $tercihler;
            $tercihBulundu = true;
            break;
        }
    }
    
    if (!$tercihBulundu) {
        $data['personel_tercihleri'][] = [
            'personel_id' => $personelId,
            'tercihler' => $tercihler
        ];
    }
    
    veriYaz($data);
}

// Personel tercihlerini getir
function personelTercihGetir($personelId) {
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
function personelAylikVardiyaSayisi($personelId, $ay, $yil) {
    $data = veriOku();
    $baslangic = sprintf('%04d-%02d-01', $yil, $ay);
    $bitis = date('Y-m-t', strtotime($baslangic));
    
    $vardiyaSayilari = [
        'sabah' => 0,
        'aksam' => 0,
        'gece' => 0,
        'toplam' => 0
    ];
    
    foreach ($data['vardiyalar'] as $vardiya) {
        if ($vardiya['personel_id'] === $personelId && 
            $vardiya['tarih'] >= $baslangic && 
            $vardiya['tarih'] <= $bitis) {
            $vardiyaSayilari[$vardiya['vardiya_turu']]++;
            $vardiyaSayilari['toplam']++;
        }
    }
    
    return $vardiyaSayilari;
}

// Akıllı vardiya önerisi oluştur
function akilliVardiyaOnerisiOlustur($tarih, $vardiyaTuru) {
    $data = veriOku();
    $puanlar = [];
    
    // Her personel için puan hesapla
    foreach ($data['personel'] as $personel) {
        $puan = 100;
        $personelId = $personel['id'];
        
        // Personel tercihleri kontrol
        $tercihler = personelTercihGetir($personelId);
        
        // Tercih edilen vardiya kontrolü
        if (in_array($vardiyaTuru, $tercihler['tercih_edilen_vardiyalar'])) {
            $puan += 20;
        }
        
        // Tercih edilmeyen gün kontrolü
        $gun = date('w', strtotime($tarih));
        if (in_array($gun, $tercihler['tercih_edilmeyen_gunler'])) {
            $puan -= 30;
        }
        
        // Ardışık çalışma günü kontrolü
        if (!ardisikCalismaGunleriniKontrolEt($personelId, $tarih)) {
            $puan = 0; // Çalışamaz
            continue;
        }
        
        // Vardiya çakışması kontrolü
        if (vardiyaCakismasiVarMi($personelId, $tarih, $vardiyaTuru)) {
            $puan = 0; // Çalışamaz
            continue;
        }
        
        // Ay içi vardiya dağılımı kontrolü
        $ay = date('m', strtotime($tarih));
        $yil = date('Y', strtotime($tarih));
        $vardiyaSayilari = personelAylikVardiyaSayisi($personelId, $ay, $yil);
        
        // Vardiya sayısı dengesizliği kontrolü
        $ortalamaVardiya = array_sum($vardiyaSayilari) / 3;
        if ($vardiyaSayilari[$vardiyaTuru] > $ortalamaVardiya) {
            $puan -= 15;
        }
        
        // Son vardiyadan bu yana geçen süre kontrolü
        $sonVardiyaBilgisi = personelVardiyaBilgisiGetir($personelId);
        if ($sonVardiyaBilgisi['son_vardiya']) {
            $gunFarki = (strtotime($tarih) - strtotime($sonVardiyaBilgisi['son_vardiya'])) / (60 * 60 * 24);
            if ($gunFarki < 2) {
                $puan -= 10;
            }
        }
        
        $puanlar[$personelId] = $puan;
    }
    
    // En yüksek puanlı personelleri sırala
    arsort($puanlar);
    
    // İlk 3 öneriyi döndür
    $oneriler = [];
    $sayac = 0;
    foreach ($puanlar as $personelId => $puan) {
        if ($puan > 0) {
            foreach ($data['personel'] as $personel) {
                if ($personel['id'] === $personelId) {
                    $oneriler[] = [
                        'personel_id' => $personelId,
                        'ad_soyad' => $personel['ad'] . ' ' . $personel['soyad'],
                        'puan' => $puan
                    ];
                    break;
                }
            }
            $sayac++;
            if ($sayac >= 3) break;
        }
    }
    
    return $oneriler;
}
