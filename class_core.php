<?php

/**
 * Core sınıfı - Temel işlevleri yönetir
 * PHP 7.4+
 */
class Core
{
    private $db;
    private $islemLog;
    private static $instance = null;

    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->islemLog = IslemLog::getInstance();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Takvim oluşturma
     */
    public function takvimOlustur($ay, $yil)
    {
        $ilkGun = mktime(0, 0, 0, $ay, 1, $yil);
        $ayinGunSayisi = date('t', $ilkGun);
        $ilkGununHaftaninGunu = date('w', $ilkGun);
        $sonGun = mktime(0, 0, 0, $ay, $ayinGunSayisi, $yil);

        // Vardiyaları getir
        $sql = "SELECT v.*, p.ad, p.soyad, vt.etiket as vardiya_turu_etiket, vt.renk 
                FROM vardiyalar v 
                LEFT JOIN personel p ON v.personel_id = p.id 
                LEFT JOIN vardiya_turleri vt ON v.vardiya_turu = vt.id 
                WHERE MONTH(FROM_UNIXTIME(v.tarih)) = ? AND YEAR(FROM_UNIXTIME(v.tarih)) = ?";

        $vardiyalar = $this->db->fetchAll($sql, [$ay, $yil]);
        $vardiyaGruplari = [];

        foreach ($vardiyalar as $vardiya) {
            $gun = date('j', $vardiya['tarih']);
            if (!isset($vardiyaGruplari[$gun])) {
                $vardiyaGruplari[$gun] = [];
            }
            $vardiyaGruplari[$gun][] = $vardiya;
        }

        $takvim = '<table class="takvim">';
        $takvim .= '<tr><th>Pzt</th><th>Sal</th><th>Çar</th><th>Per</th><th>Cum</th><th>Cmt</th><th>Paz</th></tr>';
        $takvim .= '<tr>';

        // Ayın ilk gününe kadar boş hücreler ekle
        $ilkGununHaftaninGunu = ($ilkGununHaftaninGunu == 0) ? 7 : $ilkGununHaftaninGunu;
        for ($i = 1; $i < $ilkGununHaftaninGunu; $i++) {
            $takvim .= '<td class="bos"></td>';
        }

        // Günleri ekle
        for ($gun = 1; $gun <= $ayinGunSayisi; $gun++) {
            $gunTimestamp = mktime(0, 0, 0, $ay, $gun, $yil);
            $class = [];

            // Bugün kontrolü
            if (date('Y-m-d', $gunTimestamp) === date('Y-m-d')) {
                $class[] = 'bugun';
            }

            // Hafta sonu kontrolü
            if (date('N', $gunTimestamp) >= 6) {
                $class[] = 'haftasonu';
            }

            $takvim .= sprintf('<td class="%s">', implode(' ', $class));
            $takvim .= '<div class="gun">' . $gun . '</div>';

            // Vardiyaları ekle
            if (isset($vardiyaGruplari[$gun])) {
                $takvim .= '<div class="vardiyalar">';
                foreach ($vardiyaGruplari[$gun] as $vardiya) {
                    $takvim .= sprintf(
                        '<div class="vardiya" style="background-color: %s" title="%s %s - %s">%s</div>',
                        $vardiya['renk'],
                        $vardiya['ad'],
                        $vardiya['soyad'],
                        $vardiya['vardiya_turu_etiket'],
                        $vardiya['vardiya_turu_etiket'][0]
                    );
                }
                $takvim .= '</div>';
            }

            $takvim .= '</td>';

            // Haftanın son günü kontrolü
            if (date('N', $gunTimestamp) == 7) {
                $takvim .= '</tr><tr>';
            }
        }

        // Son haftanın kalan günlerini boş hücrelerle doldur
        $sonGununHaftaninGunu = date('N', $sonGun);
        if ($sonGununHaftaninGunu != 7) {
            for ($i = $sonGununHaftaninGunu; $i < 7; $i++) {
                $takvim .= '<td class="bos"></td>';
            }
        }

        $takvim .= '</tr></table>';
        return $takvim;
    }

    /**
     * Kullanıcı girişi
     */
    public function kullaniciGiris($email, $sifre)
    {
        // E-posta formatı kontrolü
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Geçersiz e-posta formatı.');
        }

        $sql = "SELECT * FROM personel WHERE email = ? LIMIT 1";
        $kullanici = $this->db->fetch($sql, [$email]);

        if (!$kullanici) {
            throw new Exception('Kullanıcı bulunamadı.');
        }

        if (!password_verify($sifre, $kullanici['sifre'])) {
            $this->islemLog->logKaydet('giris_basarisiz', "Başarısız giriş denemesi: $email");
            throw new Exception('Hatalı şifre.');
        }

        // Session'a kullanıcı bilgilerini kaydet
        $_SESSION['kullanici_id'] = $kullanici['id'];
        $_SESSION['ad_soyad'] = $kullanici['ad'] . ' ' . $kullanici['soyad'];
        $_SESSION['email'] = $kullanici['email'];
        $_SESSION['rol'] = $kullanici['yetki'];
        $_SESSION['giris_zamani'] = time();

        // Son giriş zamanını güncelle
        $sql = "UPDATE personel SET son_giris = ? WHERE id = ?";
        $this->db->query($sql, [time(), $kullanici['id']]);

        $this->islemLog->logKaydet('giris_basarili', "Başarılı giriş: {$kullanici['email']}");
        return true;
    }

    /**
     * Kullanıcı çıkışı
     */
    public function kullaniciCikis()
    {
        if (isset($_SESSION['kullanici_id'])) {
            $this->islemLog->logKaydet('cikis', "Kullanıcı çıkış yaptı: {$_SESSION['email']}");
        }

        // Session'ı temizle
        session_unset();
        session_destroy();

        // Yeni session başlat
        session_start();
        session_regenerate_id(true);

        return true;
    }

    /**
     * Şifre değiştirme
     */
    public function sifreDegistir($kullaniciId, $eskiSifre, $yeniSifre)
    {
        $sql = "SELECT sifre FROM personel WHERE id = ? LIMIT 1";
        $kullanici = $this->db->fetch($sql, [$kullaniciId]);

        if (!$kullanici) {
            throw new Exception('Kullanıcı bulunamadı.');
        }

        if (!password_verify($eskiSifre, $kullanici['sifre'])) {
            throw new Exception('Mevcut şifre hatalı.');
        }

        // Şifre karmaşıklık kontrolü
        if (strlen($yeniSifre) < 8) {
            throw new Exception('Şifre en az 8 karakter olmalıdır.');
        }

        if (!preg_match('/[A-Z]/', $yeniSifre)) {
            throw new Exception('Şifre en az bir büyük harf içermelidir.');
        }

        if (!preg_match('/[a-z]/', $yeniSifre)) {
            throw new Exception('Şifre en az bir küçük harf içermelidir.');
        }

        if (!preg_match('/[0-9]/', $yeniSifre)) {
            throw new Exception('Şifre en az bir rakam içermelidir.');
        }

        // Şifreyi güncelle
        $yeniSifreHash = password_hash($yeniSifre, PASSWORD_DEFAULT);
        $sql = "UPDATE personel SET sifre = ?, sifre_degistirme_tarihi = ? WHERE id = ?";
        $this->db->query($sql, [$yeniSifreHash, time(), $kullaniciId]);

        $this->islemLog->logKaydet('sifre_degistir', "Şifre değiştirildi: Kullanıcı ID: $kullaniciId");
        return true;
    }

    /**
     * Yetki kontrolü
     */
    public function yetkiKontrol($gerekliRoller = ['admin'])
    {
        if (!isset($_SESSION['kullanici_id']) || !isset($_SESSION['rol'])) {
            return false;
        }

        if (in_array($_SESSION['rol'], $gerekliRoller)) {
            return true;
        }

        return false;
    }

    /**
     * Tarih formatlama
     */
    public function tarihFormatla($timestamp, $format = 'kisa')
    {
        if (!is_numeric($timestamp)) {
            $timestamp = strtotime($timestamp);
        }

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
            'Pazartesi',
            'Salı',
            'Çarşamba',
            'Perşembe',
            'Cuma',
            'Cumartesi',
            'Pazar'
        ];

        $gun = date('j', $timestamp);
        $ay = $aylar[date('n', $timestamp) - 1];
        $yil = date('Y', $timestamp);
        $haftaninGunu = $gunler[date('N', $timestamp) - 1];

        switch ($format) {
            case 'kisa':
                return sprintf('%02d.%02d.%04d', $gun, date('m', $timestamp), $yil);

            case 'uzun':
                return "$gun $ay $yil";

            case 'tam':
                return "$gun $ay $yil, $haftaninGunu";

            case 'saat':
                return date('H:i', $timestamp);

            case 'tam_saat':
                return sprintf(
                    '%02d.%02d.%04d %02d:%02d',
                    $gun,
                    date('m', $timestamp),
                    $yil,
                    date('H', $timestamp),
                    date('i', $timestamp)
                );

            default:
                return date($format, $timestamp);
        }
    }
}
