<?php

/**
 * Core sınıfı - Temel sistem fonksiyonlarını içerir
 * PHP 7.4+
 */
class Core
{
    /**
     * Takvim oluşturma
     */
    public function takvimOlustur($ay, $yil)
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

    /**
     * Kullanıcı girişi
     */
    public function kullaniciGiris($email, $sifre)
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

    /**
     * Kullanıcı çıkışı
     */
    public function kullaniciCikis()
    {
        islemLogKaydet('cikis', 'Kullanıcı çıkışı yapıldı');
        session_destroy();
    }

    /**
     * Şifre değiştirme
     */
    public function sifreDegistir($kullaniciId, $eskiSifre, $yeniSifre)
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

    /**
     * Tarih formatlama
     */
    public function tarihFormatla($timestamp, $format = 'kisa')
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

    /**
     * Yetki kontrolü
     */
    public function yetkiKontrol($gerekliRoller = ['admin'])
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
}
