<?php

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
