<?php
require_once 'functions.php';
require __DIR__ . '/vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// VAPID anahtarları
$auth = [
    'VAPID' => [
        'subject' => 'mailto:your@email.com', // E-posta adresiniz
        'publicKey' => 'YOUR_PUBLIC_VAPID_KEY', // Public VAPID anahtarı
        'privateKey' => 'YOUR_PRIVATE_VAPID_KEY', // Private VAPID anahtarı
    ],
];

// WebPush nesnesi oluştur
$webPush = new WebPush($auth);

// Tüm abonelikleri al
$data = veriOku();
$abonelikler = $data['push_abonelikleri'] ?? [];

// Bildirim mesajı
$mesaj = isset($_POST['mesaj']) ? $_POST['mesaj'] : 'Yeni bir bildiriminiz var!';

// Her aboneliğe bildirim gönder
$basarisizAbonelikler = [];
foreach ($abonelikler as $index => $abone) {
    $subscription = Subscription::create([
        'endpoint' => $abone['endpoint'],
        'publicKey' => $abone['keys']['p256dh'],
        'authToken' => $abone['keys']['auth'],
    ]);

    $webPush->queueNotification(
        $subscription,
        $mesaj
    );
}

// Bildirimleri gönder ve sonuçları kontrol et
foreach ($webPush->flush() as $report) {
    $endpoint = $report->getRequest()->getUri()->__toString();

    if (!$report->isSuccess()) {
        // Başarısız gönderimler için endpoint'i kaydet
        $basarisizAbonelikler[] = $endpoint;
    }
}

// Başarısız abonelikleri temizle
if (!empty($basarisizAbonelikler)) {
    $data['push_abonelikleri'] = array_filter(
        $data['push_abonelikleri'],
        function ($abone) use ($basarisizAbonelikler) {
            return !in_array($abone['endpoint'], $basarisizAbonelikler);
        }
    );
    veriYaz($data);
}

// Yanıt döndür
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Bildirimler gönderildi',
    'failed_endpoints' => $basarisizAbonelikler
]);
