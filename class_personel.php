<?php

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

// Kullanıcı tercihlerini güncelleme
function kullaniciTercihleriniGuncelle($kullaniciId, $tercihler)
{
    $data = veriOku();
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
            // Mevcut tercihleri al veya varsayılan değerleri kullan
            $mevcutTercihler = $kullanici['tercihler'] ?? $varsayilanTercihler;

            // Yeni tercihleri mevcut tercihlerle birleştir
            $kullanici['tercihler'] = array_merge($mevcutTercihler, $tercihler);

            // Tercih değerlerini doğrula ve düzelt
            $kullanici['tercihler']['max_ardisik_vardiya'] =
                min(7, max(1, intval($kullanici['tercihler']['max_ardisik_vardiya'])));

            $kullanici['tercihler']['min_gunluk_dinlenme'] =
                min(24, max(8, intval($kullanici['tercihler']['min_gunluk_dinlenme'])));

            // Geçersiz gün indekslerini temizle
            $kullanici['tercihler']['tercih_edilmeyen_gunler'] = array_filter(
                $kullanici['tercihler']['tercih_edilmeyen_gunler'],
                function ($gun) {
                    return is_numeric($gun) && $gun >= 0 && $gun <= 6;
                }
            );

            // Geçersiz vardiya türlerini temizle
            $vardiyaTurleri = array_keys(vardiyaTurleriniGetir());
            $kullanici['tercihler']['tercih_edilen_vardiyalar'] = array_filter(
                $kullanici['tercihler']['tercih_edilen_vardiyalar'],
                function ($vardiya) use ($vardiyaTurleri) {
                    return in_array($vardiya, $vardiyaTurleri);
                }
            );

            veriYaz($data);
            islemLogKaydet('tercih_guncelle', "Kullanıcı tercihleri güncellendi: $kullaniciId");
            return true;
        }
    }

    throw new Exception('Kullanıcı bulunamadı.');
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
