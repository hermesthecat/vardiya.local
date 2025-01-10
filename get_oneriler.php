<?php
require_once 'functions.php';

header('Content-Type: application/json');

if (isset($_GET['tarih']) && isset($_GET['vardiya_turu'])) {
    $oneriler = akilliVardiyaOnerisiOlustur($_GET['tarih'], $_GET['vardiya_turu']);
    echo json_encode($oneriler);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Tarih ve vardiya türü gerekli']);
} 