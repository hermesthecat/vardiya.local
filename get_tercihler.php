<?php
require_once 'functions.php';

header('Content-Type: application/json');

if (isset($_GET['personel_id'])) {
    $tercihler = personelTercihGetir($_GET['personel_id']);
    echo json_encode($tercihler);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Personel ID gerekli']);
} 