<?php
require_once 'functions.php';
session_start();

// Çıkış işlemini gerçekleştir
kullaniciCikis();

// Giriş sayfasına yönlendir
header('Location: giris.php');
exit;
