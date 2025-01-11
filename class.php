<?php
/**
 * Sınıf Otomatik Yükleme Dosyası
 * PHP 7.4+ uyumlu
 */

// Hata raporlamayı ayarla
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Zaman dilimini ayarla
date_default_timezone_set('Europe/Istanbul');

// Karakter setini ayarla
mb_internal_encoding('UTF-8');
ini_set('default_charset', 'UTF-8');

// Session güvenlik ayarları
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600); // 1 saat
session_start();

// Autoloader sınıfı
class Autoloader
{
    private static $instance = null;
    private $baseDir;
    private $classMap = [];

    private function __construct()
    {
        $this->baseDir = __DIR__;
        $this->scanClassFiles();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function scanClassFiles()
    {
        $files = glob($this->baseDir . DIRECTORY_SEPARATOR . 'class_*.php');
        foreach ($files as $file) {
            $className = strtolower(basename($file, '.php'));
            $this->classMap[$className] = $file;
        }
    }

    public function loadClass($className)
    {
        // Sınıf adını küçük harfe çevir ve başındaki namespace'i temizle
        $className = strtolower(basename(str_replace('\\', '/', $className)));
        
        // Eğer sınıf adı "class_" ile başlamıyorsa, başına ekle
        if (strpos($className, 'class_') !== 0) {
            $className = 'class_' . $className;
        }

        // Sınıf haritasında varsa yükle
        if (isset($this->classMap[$className])) {
            require_once $this->classMap[$className];
            return true;
        }
        
        // Haritada yoksa dosya sisteminde ara
        $filePath = $this->baseDir . DIRECTORY_SEPARATOR . $className . '.php';
        if (is_readable($filePath)) {
            require_once $filePath;
            $this->classMap[$className] = $filePath;
            return true;
        }

        return false;
    }

    public function getLoadedClasses()
    {
        return array_keys($this->classMap);
    }
}

// Autoloader'ı başlat
$autoloader = Autoloader::getInstance();
spl_autoload_register([$autoloader, 'loadClass']);

// Gerekli fonksiyonları yükle
require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

// Veritabanı bağlantısını başlat
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    error_log('Veritabanı bağlantı hatası: ' . $e->getMessage());
}

// Güvenlik kontrolleri
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['csrf_token']) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        die('CSRF token doğrulaması başarısız.');
    }
}

// XSS koruması
foreach ($_POST as $key => $value) {
    if (is_string($value)) {
        $_POST[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

foreach ($_GET as $key => $value) {
    if (is_string($value)) {
        $_GET[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// Hata yakalama
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $errorType = match ($errno) {
        E_ERROR => 'Hata',
        E_WARNING => 'Uyarı',
        E_PARSE => 'Ayrıştırma Hatası',
        E_NOTICE => 'Bildirim',
        default => 'Bilinmeyen Hata'
    };
    
    $message = "$errorType: $errstr in $errfile on line $errline";
    error_log($message);
    
    if ($errno == E_ERROR) {
        http_response_code(500);
        die('Bir sistem hatası oluştu. Lütfen daha sonra tekrar deneyiniz.');
    }
    
    return true;
});

// Kritik hataları yakala
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && $error['type'] === E_ERROR) {
        $message = "Kritik Hata: {$error['message']} in {$error['file']} on line {$error['line']}";
        error_log($message);
        
        if (!headers_sent()) {
            http_response_code(500);
            echo 'Bir sistem hatası oluştu. Lütfen daha sonra tekrar deneyiniz.';
        }
    }
}); 