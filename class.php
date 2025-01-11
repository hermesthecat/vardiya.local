<?php
/**
 * Sınıf Otomatik Yükleme Dosyası
 * PHP 7.4+ uyumlu
 */

spl_autoload_register(function ($className) {
    // Sınıf adını küçük harfe çevir ve başındaki namespace'i temizle
    $className = strtolower(basename(str_replace('\\', '/', $className)));
    
    // Eğer sınıf adı "class_" ile başlamıyorsa, başına ekle
    if (strpos($className, 'class_') !== 0) {
        $className = 'class_' . $className;
    }
    
    // Dosya yolunu oluştur
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . $className . '.php';
    
    // Dosya varsa ve okunabiliyorsa yükle
    if (is_readable($filePath)) {
        require_once $filePath;
        return true;
    }
    
    return false;
});

// Hata raporlamayı ayarla
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Zaman dilimini ayarla
date_default_timezone_set('Europe/Istanbul');

// Karakter setini ayarla
mb_internal_encoding('UTF-8'); 