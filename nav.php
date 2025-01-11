<?php
// Oturum kontrolü
if (!isset($_SESSION['kullanici_id'])) {
    header('Location: giris.php');
    exit;
}
?>

<nav>
    <div class="user-info">
        <div>
            <i class="fas fa-user"></i>
            Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['ad_soyad']); ?>
            (<?php echo htmlspecialchars($_SESSION['rol']); ?>)
        </div>
        <a href="cikis.php"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a>
    </div>

    <div class="menu">
        <a href="index.php" <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-calendar-week"></i> Vardiya Takvimi
        </a>
        
        <?php if (in_array($_SESSION['rol'], ['yonetici', 'admin'])): ?>
            <a href="personel.php" <?php echo basename($_SERVER['PHP_SELF']) == 'personel.php' ? 'class="active"' : ''; ?>>
                <i class="fas fa-users"></i> Personel Yönetimi
            </a>
        <?php endif; ?>
        
        <a href="izin.php" <?php echo basename($_SERVER['PHP_SELF']) == 'izin.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-calendar-alt"></i> İzin İşlemleri
        </a>
        
        <a href="profil.php" <?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-user-circle"></i> Profil
        </a>
    </div>
</nav> 