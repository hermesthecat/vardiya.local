<?php
require_once 'functions.php';
session_start();

// Oturum kontrolü
if (!isset($_SESSION['kullanici_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Oturum geçersiz']));
}

// Yönetici ve admin kontrolü
if (!in_array($_SESSION['rol'], ['yonetici', 'admin'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Yetkiniz yok']));
}

// Parametreleri al
$tarih = $_GET['tarih'] ?? null;
$vardiyaTuru = $_GET['vardiya_turu'] ?? null;

if (!$tarih || !$vardiyaTuru) {
    http_response_code(400);
    exit(json_encode(['error' => 'Geçersiz parametreler']));
}

try {
    $data = veriOku();
    $oneriler = [];
    
    // Tüm personeli döngüye al
    foreach ($data['personel'] as $personel) {
        $puan = 100; // Başlangıç puanı
        
        // Seçilen tarihte vardiyası var mı kontrol et
        if (vardiyaCakismasiVarMi($personel['id'], $tarih, $vardiyaTuru)) {
            continue; // Bu personeli atla
        }
        
        // Ardışık çalışma günü kontrolü
        if (!ardisikCalismaGunleriniKontrolEt($personel['id'], $tarih)) {
            continue; // Bu personeli atla
        }
        
        // Personelin tercihlerini kontrol et
        if (isset($personel['tercihler'])) {
            // Tercih edilen vardiyalar kontrolü
            if (!empty($personel['tercihler']['tercih_edilen_vardiyalar'])) {
                if (in_array($vardiyaTuru, $personel['tercihler']['tercih_edilen_vardiyalar'])) {
                    $puan += 20;
                } else {
                    $puan -= 10;
                }
            }
            
            // Tercih edilmeyen günler kontrolü
            if (!empty($personel['tercihler']['tercih_edilmeyen_gunler'])) {
                $gun = date('w', strtotime($tarih));
                if (in_array($gun, $personel['tercihler']['tercih_edilmeyen_gunler'])) {
                    $puan -= 30;
                }
            }
        }
        
        // Son 30 gündeki vardiya sayısını kontrol et
        $sonVardiyalar = personelVardiyaBilgisiGetir($personel['id']);
        if ($sonVardiyalar['toplam_vardiya'] > 20) {
            $puan -= 15; // Çok vardiyası varsa puanı düşür
        }
        
        // Puanı 0-100 arasında tut
        $puan = max(0, min(100, $puan));
        
        // Öneri listesine ekle
        if ($puan >= 50) { // Sadece 50 puan üstü personeli öner
            $oneriler[] = [
                'personel_id' => $personel['id'],
                'ad_soyad' => $personel['ad'] . ' ' . $personel['soyad'],
                'puan' => $puan
            ];
        }
    }
    
    // Puana göre sırala (yüksekten düşüğe)
    usort($oneriler, function($a, $b) {
        return $b['puan'] - $a['puan'];
    });
    
    // En iyi 5 öneriyi döndür
    $oneriler = array_slice($oneriler, 0, 5);
    
    header('Content-Type: application/json');
    echo json_encode($oneriler);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
