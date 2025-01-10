<?php
require_once 'functions.php';
session_start();

// Yetki kontrolü
try {
    yetkiKontrol(['yonetici', 'admin']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['hata' => 'Yetkiniz bulunmuyor.']);
    exit;
}

// Parametreleri al
$tarih = $_GET['tarih'] ?? null;
$vardiyaTuru = $_GET['vardiya_turu'] ?? null;

if (!$tarih || !$vardiyaTuru) {
    http_response_code(400);
    echo json_encode(['hata' => 'Geçersiz parametreler.']);
    exit;
}

// Akıllı vardiya önerilerini getir
try {
    $oneriler = akilliVardiyaOnerisiOlustur($tarih, $vardiyaTuru);
    echo json_encode($oneriler);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['hata' => $e->getMessage()]);
} 