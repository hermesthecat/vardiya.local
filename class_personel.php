<?php

/**
 * Personel sınıfı - Personel işlemlerini yönetir
 * PHP 7.4+
 */
class Personel
{
    /**
     * Tüm personelleri getir
     */
    public function tumPersonelleriGetir()
    {
        $data = veriOku();
        return $data['personel'];
    }

    /**
     * Personel vardiya bilgilerini getir
     */
    public function vardiyaBilgisiGetir($personelId)
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

    /**
     * Personel silme
     */
    public function sil($personelId)
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

    /**
     * Yeni personel ekleme
     */
    public function ekle($ad, $soyad, $email, $telefon = '', $yetki = 'personel')
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

    /**
     * Personel listesini getirme (select için)
     */
    public function listesiGetir()
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

    /**
     * Personelin izinlerini getir
     */
    public function izinleriniGetir($personelId)
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

    /**
     * Personelin izin taleplerini getir
     */
    public function izinTalepleriniGetir($personelId)
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

    /**
     * Personel düzenleme
     */
    public function duzenle($personelId, $ad, $soyad, $notlar)
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

    /**
     * Personel bazlı vardiya dağılımı
     */
    public function vardiyaDagilimi($baslangicTarih, $bitisTarih)
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

    /**
     * Personel tercihlerini getir
     */
    public function tercihGetir($personelId)
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

    /**
     * Personelin ay içindeki vardiya sayılarını hesapla
     */
    public function aylikVardiyaSayisi($personelId, $ay, $yil)
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

        // Ay başı ve sonu
        $ayBasi = mktime(0, 0, 0, $ay, 1, $yil);
        $aySonu = mktime(23, 59, 59, $ay + 1, 0, $yil);

        $vardiyaSayilari = [];
        foreach ($data['vardiyalar'] as $vardiya) {
            $vardiyaTarihi = is_numeric($vardiya['tarih']) ? $vardiya['tarih'] : strtotime($vardiya['tarih']);
            if ($vardiya['personel_id'] === $personelId && $vardiyaTarihi >= $ayBasi && $vardiyaTarihi <= $aySonu) {
                $vardiyaSayilari[$vardiya['vardiya_turu']] = ($vardiyaSayilari[$vardiya['vardiya_turu']] ?? 0) + 1;
            }
        }

        return $vardiyaSayilari;
    }

    /**
     * Kullanıcı oluştur
     */
    public function kullaniciOlustur($ad, $soyad, $email, $sifre, $rol = 'personel', $telefon = '', $tercihler = null)
    {
        $data = veriOku();

        // E-posta kontrolü
        foreach ($data['personel'] as $personel) {
            if ($personel['email'] === $email) {
                throw new Exception('Bu e-posta adresi zaten kullanılıyor.');
            }
        }

        $yeniKullanici = [
            'id' => uniqid(),
            'ad' => $ad,
            'soyad' => $soyad,
            'email' => $email,
            'sifre' => $sifre,
            'rol' => $rol,
            'telefon' => $telefon,
            'tercihler' => $tercihler ?? [
                'bildirimler' => true,
                'tercih_edilen_vardiyalar' => [],
                'tercih_edilmeyen_gunler' => []
            ],
            'olusturma_tarihi' => time(),
            'guncelleme_tarihi' => time()
        ];

        $data['personel'][] = $yeniKullanici;
        veriYaz($data);

        islemLogKaydet('kullanici_olustur', "Yeni kullanıcı oluşturuldu: $ad $soyad ($email)");
        return $yeniKullanici['id'];
    }

    /**
     * Kullanıcı güncelle
     */
    public function kullaniciGuncelle($kullaniciId, $ad, $soyad, $email, $rol = null, $telefon = null, $tercihler = null)
    {
        $data = veriOku();
        $kullaniciBulundu = false;

        foreach ($data['personel'] as &$kullanici) {
            if ($kullanici['id'] === $kullaniciId) {
                $kullaniciBulundu = true;

                // E-posta değişmişse kontrol et
                if ($email !== $kullanici['email']) {
                    foreach ($data['personel'] as $digerKullanici) {
                        if ($digerKullanici['email'] === $email && $digerKullanici['id'] !== $kullaniciId) {
                            throw new Exception('Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.');
                        }
                    }
                }

                $kullanici['ad'] = $ad;
                $kullanici['soyad'] = $soyad;
                $kullanici['email'] = $email;
                if ($rol !== null) $kullanici['rol'] = $rol;
                if ($telefon !== null) $kullanici['telefon'] = $telefon;
                if ($tercihler !== null) $kullanici['tercihler'] = $tercihler;
                $kullanici['guncelleme_tarihi'] = time();
                break;
            }
        }

        if (!$kullaniciBulundu) {
            throw new Exception('Kullanıcı bulunamadı.');
        }

        veriYaz($data);
        islemLogKaydet('kullanici_guncelle', "Kullanıcı güncellendi: $kullaniciId");
        return true;
    }

    /**
     * Kullanıcı tercihlerini güncelle
     */
    public function tercihleriniGuncelle($kullaniciId, $tercihler)
    {
        $data = veriOku();
        $kullaniciBulundu = false;

        foreach ($data['personel'] as &$kullanici) {
            if ($kullanici['id'] === $kullaniciId) {
                $kullaniciBulundu = true;
                $kullanici['tercihler'] = array_merge($kullanici['tercihler'] ?? [], $tercihler);
                $kullanici['guncelleme_tarihi'] = time();
                break;
            }
        }

        if (!$kullaniciBulundu) {
            throw new Exception('Kullanıcı bulunamadı.');
        }

        veriYaz($data);
        islemLogKaydet('tercih_guncelle', "Kullanıcı tercihleri güncellendi: $kullaniciId");
        return true;
    }

    /**
     * Personel tercihlerini kaydet
     */
    public function tercihKaydet($personelId, $tercihler)
    {
        $data = veriOku();

        if (!isset($data['personel_tercihleri'])) {
            $data['personel_tercihleri'] = [];
        }

        $tercihBulundu = false;
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
        return true;
    }

    /**
     * Bildirim tercihini güncelle
     */
    public function bildirimTercihiGuncelle($kullaniciId, $bildirimTercihleri)
    {
        $data = veriOku();
        $kullaniciBulundu = false;

        foreach ($data['personel'] as &$kullanici) {
            if ($kullanici['id'] === $kullaniciId) {
                $kullaniciBulundu = true;
                if (!isset($kullanici['tercihler'])) {
                    $kullanici['tercihler'] = [];
                }
                $kullanici['tercihler']['bildirimler'] = $bildirimTercihleri;
                break;
            }
        }

        if (!$kullaniciBulundu) {
            throw new Exception('Kullanıcı bulunamadı.');
        }

        veriYaz($data);
        return true;
    }
}
