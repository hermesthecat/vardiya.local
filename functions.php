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

            $vardiyaTurleri = vardiyaTurleriniGetir();
            $vardiyaKisaltma = isset($vardiyaTurleri[$vardiya['vardiya_turu']]) 
                ? mb_substr($vardiyaTurleri[$vardiya['vardiya_turu']]['etiket'], 0, 1, 'UTF-8')
                : '?';

            $gunlukVardiyalar[] = [
                'id' => $vardiya['id'],
                'personel' => $personel['ad'] . ' ' . $personel['soyad'],
                'vardiya' => $vardiyaKisaltma
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
function vardiyaSaatleriHesapla($vardiyaTuru)
{
    $vardiyaTurleri = vardiyaTurleriniGetir();
    if (!isset($vardiyaTurleri[$vardiyaTuru])) {
        throw new Exception('Geçersiz vardiya türü');
    }

    $vardiya = $vardiyaTurleri[$vardiyaTuru];
    $baslangicSaat = strtotime($vardiya['baslangic']);
    $bitisSaat = strtotime($vardiya['bitis']);
    
    // Eğer bitiş saati başlangıç saatinden küçükse (gece vardiyası), 24 saat ekle
    if ($bitisSaat < $baslangicSaat) {
        $bitisSaat += 86400; // 24 saat
    }
    
    $calismaSuresi = ($bitisSaat - $baslangicSaat) / 3600; // Saat cinsinden süre

    return [
        'baslangic' => $vardiya['baslangic'],
        'bitis' => $vardiya['bitis'],
        'sure' => $calismaSuresi
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
function vardiyaTuruDagilimi($baslangicTarih, $bitisTarih)
{
    $data = veriOku();
    $vardiyaTurleri = vardiyaTurleriniGetir();
    
    $dagilim = [];
    foreach ($vardiyaTurleri as $id => $vardiya) {
        $dagilim[$id] = 0;
    }

    foreach ($data['vardiyalar'] as $vardiya) {
        if ($vardiya['tarih'] >= $baslangicTarih && $vardiya['tarih'] <= $bitisTarih) {
            $dagilim[$vardiya['vardiya_turu']]++;
        }
    }

    return $dagilim;
}

// Personel bazlı vardiya dağılımı
function personelVardiyaDagilimi($baslangicTarih, $bitisTarih)
{
    $data = veriOku();
    $vardiyaTurleri = vardiyaTurleriniGetir();
    $dagilim = [];

    foreach ($data['personel'] as $personel) {
        $vardiyalar = [];
        foreach ($vardiyaTurleri as $id => $vardiya) {
            $vardiyalar[$id] = 0;
        }

        $dagilim[$personel['id']] = [
            'personel' => $personel['ad'] . ' ' . $personel['soyad'],
            'vardiyalar' => $vardiyalar,
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
function excelRaporuOlustur($ay, $yil)
{
    $rapor = aylikCalismaRaporu($ay, $yil);
    $vardiyaTurleri = vardiyaTurleriniGetir();

    // Başlık satırı
    $basliklar = ['Personel', 'Toplam Saat'];
    foreach ($vardiyaTurleri as $vardiya) {
        $basliklar[] = $vardiya['etiket'] . ' Vardiyası';
    }
    $csv = implode(',', $basliklar) . "\n";

    // Veri satırları
    foreach ($rapor as $personelRapor) {
        $satir = [$personelRapor['personel'], $personelRapor['toplam_saat']];
        foreach ($vardiyaTurleri as $id => $vardiya) {
            $satir[] = $personelRapor['vardiyalar'][$id];
        }
        $csv .= implode(',', $satir) . "\n";
    }

    return $csv;
}

// PDF raporu için HTML oluştur
function pdfRaporuIcinHtmlOlustur($ay, $yil)
{
    $rapor = aylikCalismaRaporu($ay, $yil);
    $vardiyaTurleri = vardiyaTurleriniGetir();

    $html = '<h1>Aylık Çalışma Raporu - ' . date('F Y', mktime(0, 0, 0, $ay, 1, $yil)) . '</h1>';
    $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
    
    // Başlık satırı
    $html .= '<tr><th>Personel</th><th>Toplam Saat</th>';
    foreach ($vardiyaTurleri as $vardiya) {
        $html .= '<th>' . htmlspecialchars($vardiya['etiket']) . ' Vardiyası</th>';
    }
    $html .= '</tr>';

    // Veri satırları
    foreach ($rapor as $personelRapor) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($personelRapor['personel']) . '</td>';
        $html .= '<td>' . $personelRapor['toplam_saat'] . '</td>';
        foreach ($vardiyaTurleri as $id => $vardiya) {
            $html .= '<td>' . $personelRapor['vardiyalar'][$id] . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</table>';
    return $html;
}

// Personel tercihlerini kaydet
function personelTercihKaydet($personelId, $tercihler)
{
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
    $vardiyaTurleri = vardiyaTurleriniGetir();
    $baslangic = sprintf('%04d-%02d-01', $yil, $ay);
    $bitis = date('Y-m-t', strtotime($baslangic));

    $vardiyaSayilari = ['toplam' => 0];
    foreach ($vardiyaTurleri as $id => $vardiya) {
        $vardiyaSayilari[$id] = 0;
    }

    foreach ($data['vardiyalar'] as $vardiya) {
        if (
            $vardiya['personel_id'] === $personelId &&
            $vardiya['tarih'] >= $baslangic &&
            $vardiya['tarih'] <= $bitis
        ) {
            $vardiyaSayilari[$vardiya['vardiya_turu']]++;
            $vardiyaSayilari['toplam']++;
        }
    }

    return $vardiyaSayilari;
}

// Akıllı vardiya önerisi oluştur
function akilliVardiyaOnerisiOlustur($tarih, $vardiyaTuru)
{
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

// Kullanıcı girişi
function kullaniciGiris($email, $sifre)
{
    $data = veriOku();

    if (!isset($data['kullanicilar'])) {
        throw new Exception('Kullanıcı bulunamadı.');
    }

    foreach ($data['kullanicilar'] as $kullanici) {
        if ($kullanici['email'] === $email && password_verify($sifre, $kullanici['sifre'])) {
            // Oturum başlat
            session_start();
            $_SESSION['kullanici_id'] = $kullanici['id'];
            $_SESSION['rol'] = $kullanici['rol'];
            $_SESSION['ad_soyad'] = $kullanici['ad'] . ' ' . $kullanici['soyad'];

            // Giriş logunu kaydet
            islemLogKaydet('giris', 'Kullanıcı girişi yapıldı');

            return true;
        }
    }

    throw new Exception('E-posta veya şifre hatalı.');
}

// Kullanıcı çıkışı
function kullaniciCikis()
{
    islemLogKaydet('cikis', 'Kullanıcı çıkışı yapıldı');
    session_destroy();
}

// Yeni kullanıcı oluşturma
function kullaniciOlustur($ad, $soyad, $email, $sifre, $rol = 'personel')
{
    $data = veriOku();

    // E-posta kontrolü
    if (isset($data['kullanicilar'])) {
        foreach ($data['kullanicilar'] as $kullanici) {
            if ($kullanici['email'] === $email) {
                throw new Exception('Bu e-posta adresi zaten kullanılıyor.');
            }
        }
    } else {
        $data['kullanicilar'] = [];
    }

    // Yeni kullanıcı
    $yeniKullanici = [
        'id' => uniqid(),
        'ad' => $ad,
        'soyad' => $soyad,
        'email' => $email,
        'sifre' => password_hash($sifre, PASSWORD_DEFAULT),
        'rol' => $rol,
        'olusturma_tarihi' => date('Y-m-d H:i:s')
    ];

    $data['kullanicilar'][] = $yeniKullanici;
    veriYaz($data);

    islemLogKaydet('kullanici_olustur', "Yeni kullanıcı oluşturuldu: $email");

    return $yeniKullanici['id'];
}

// Kullanıcı güncelleme
function kullaniciGuncelle($kullaniciId, $ad, $soyad, $email, $rol = null)
{
    $data = veriOku();

    foreach ($data['kullanicilar'] as &$kullanici) {
        if ($kullanici['id'] === $kullaniciId) {
            $kullanici['ad'] = $ad;
            $kullanici['soyad'] = $soyad;
            $kullanici['email'] = $email;
            if ($rol !== null) {
                $kullanici['rol'] = $rol;
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

    foreach ($data['kullanicilar'] as &$kullanici) {
        if ($kullanici['id'] === $kullaniciId) {
            if (!password_verify($eskiSifre, $kullanici['sifre'])) {
                throw new Exception('Mevcut şifre hatalı.');
            }

            $kullanici['sifre'] = password_hash($yeniSifre, PASSWORD_DEFAULT);
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
        'ip_adresi' => $_SERVER['REMOTE_ADDR'],
        'tarih' => date('Y-m-d H:i:s')
    ];

    $data['islem_loglari'][] = $yeniLog;
    veriYaz($data);
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
    foreach ($data['personel'] as &$kullanici) {
        if ($kullanici['id'] === $kullaniciId) {
            $kullanici['tercihler'] = $tercihler;
            veriYaz($data);

            islemLogKaydet('tercih_guncelle', 'Kullanıcı tercihleri güncellendi');
            return true;
        }
    }
    throw new Exception('Kullanıcı bulunamadı.');
}

// Kullanıcı bildirim tercihini güncelleme
function bildirimTercihiGuncelle($kullaniciId, $bildirimDurumu)
{
    $data = veriOku();
    foreach ($data['personel'] as &$kullanici) {
        if ($kullanici['id'] === $kullaniciId) {
            $kullanici['tercihler']['bildirimler'] = $bildirimDurumu;
            veriYaz($data);

            islemLogKaydet('bildirim_tercih', 'Bildirim tercihi güncellendi: ' . ($bildirimDurumu ? 'Aktif' : 'Pasif'));
            return true;
        }
    }
    throw new Exception('Kullanıcı bulunamadı.');
}

// Tarih formatlama fonksiyonu
function tarihFormatla($timestamp, $format = 'kisa')
{
    if (!$timestamp) return '';

    $tarih = date_create('@' . $timestamp);
    if (!$tarih) return '';

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

    $gunler = [
        'Pazar',
        'Pazartesi',
        'Salı',
        'Çarşamba',
        'Perşembe',
        'Cuma',
        'Cumartesi'
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

        default:
            return date_format($tarih, 'd.m.Y');
    }
}

// Vardiya türlerini getir
function vardiyaTurleriniGetir()
{
    $data = veriOku();
    $vardiyaTurleri = [];

    if (isset($data['sistem_ayarlari']['vardiya_turleri'])) {
        foreach ($data['sistem_ayarlari']['vardiya_turleri'] as $vardiya) {
            $vardiyaTurleri[$vardiya['id']] = [
                'baslangic' => $vardiya['baslangic'],
                'bitis' => $vardiya['bitis'],
                'etiket' => $vardiya['etiket'] ?? ucfirst($vardiya['id']),
                'renk' => $vardiya['renk'] ?? null
            ];
        }
    } else {
        // Varsayılan vardiya türleri
        $vardiyaTurleri = [
            'sabah' => [
                'baslangic' => '08:00',
                'bitis' => '16:00',
                'etiket' => 'Sabah',
                'renk' => '#4CAF50'
            ],
            'aksam' => [
                'baslangic' => '16:00',
                'bitis' => '24:00',
                'etiket' => 'Akşam',
                'renk' => '#2196F3'
            ],
            'gece' => [
                'baslangic' => '00:00',
                'bitis' => '08:00',
                'etiket' => 'Gece',
                'renk' => '#9C27B0'
            ]
        ];
    }

    return $vardiyaTurleri;
}

// Vardiya türü etiketini getir
function vardiyaTuruEtiketGetir($vardiyaTuru)
{
    $vardiyaTurleri = vardiyaTurleriniGetir();
    return isset($vardiyaTurleri[$vardiyaTuru]) ? $vardiyaTurleri[$vardiyaTuru]['etiket'] : '';
}
