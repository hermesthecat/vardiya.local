<?php
require_once 'functions.php';

header('Content-Type: application/json');

// POST verilerini al
$json = file_get_contents('php://input');
$subscription = json_decode($json, true);

if ($subscription) {
    $data = veriOku();
    
    // Push abonelikleri dizisini oluştur (yoksa)
    if (!isset($data['push_abonelikleri'])) {
        $data['push_abonelikleri'] = [];
    }
    
    // Aynı endpoint'e sahip abonelik var mı kontrol et
    $abonelikVar = false;
    foreach ($data['push_abonelikleri'] as &$abone) {
        if ($abone['endpoint'] === $subscription['endpoint']) {
            $abone = $subscription; // Güncelle
            $abonelikVar = true;
            break;
        }
    }
    
    // Yeni abonelik ekle
    if (!$abonelikVar) {
        $data['push_abonelikleri'][] = $subscription;
    }
    
    veriYaz($data);
    
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Geçersiz abonelik verisi']);
} 