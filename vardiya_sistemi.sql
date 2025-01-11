-- Personel tablosu
CREATE TABLE personel (
    id VARCHAR(12) PRIMARY KEY,
    ad VARCHAR(50) NOT NULL,
    soyad VARCHAR(50) NOT NULL,
    yetki ENUM('admin', 'personel') NOT NULL DEFAULT 'personel',
    email VARCHAR(100) UNIQUE NOT NULL,
    telefon VARCHAR(15) NOT NULL,
    sifre VARCHAR(255) NOT NULL,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Personel tercihleri tablosu
CREATE TABLE personel_tercihler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personel_id VARCHAR(12),
    bildirimler BOOLEAN DEFAULT TRUE,
    max_ardisik_vardiya INT DEFAULT 1,
    min_gunluk_dinlenme INT DEFAULT 8,
    FOREIGN KEY (personel_id) REFERENCES personel(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tercih edilen vardiyalar tablosu
CREATE TABLE personel_tercih_vardiyalar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personel_id VARCHAR(12),
    vardiya_turu VARCHAR(20),
    FOREIGN KEY (personel_id) REFERENCES personel(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tercih edilmeyen günler tablosu
CREATE TABLE personel_tercih_edilmeyen_gunler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personel_id VARCHAR(12),
    gun VARCHAR(20),
    FOREIGN KEY (personel_id) REFERENCES personel(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- İzin hakları tablosu
CREATE TABLE izin_haklari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personel_id VARCHAR(12),
    izin_turu ENUM('yillik', 'mazeret', 'hastalik') NOT NULL,
    toplam INT NOT NULL DEFAULT 0,
    kullanilan INT NOT NULL DEFAULT 0,
    kalan INT NOT NULL DEFAULT 0,
    son_guncelleme TIMESTAMP,
    FOREIGN KEY (personel_id) REFERENCES personel(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Vardiyalar tablosu
CREATE TABLE vardiyalar (
    id VARCHAR(12) PRIMARY KEY,
    personel_id VARCHAR(12),
    tarih TIMESTAMP NOT NULL,
    vardiya_turu VARCHAR(20) NOT NULL,
    notlar TEXT,
    durum ENUM('beklemede', 'onaylandi', 'reddedildi') NOT NULL DEFAULT 'beklemede',
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (personel_id) REFERENCES personel(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- İzinler tablosu
CREATE TABLE izinler (
    id VARCHAR(12) PRIMARY KEY,
    personel_id VARCHAR(12),
    baslangic_tarihi TIMESTAMP NOT NULL,
    bitis_tarihi TIMESTAMP NOT NULL,
    izin_turu VARCHAR(20) NOT NULL,
    aciklama TEXT,
    durum ENUM('beklemede', 'onaylandi', 'reddedildi') NOT NULL DEFAULT 'beklemede',
    onaylayan_id VARCHAR(12),
    onay_tarihi TIMESTAMP NULL,
    gun_sayisi INT NOT NULL,
    notlar TEXT,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (personel_id) REFERENCES personel(id) ON DELETE SET NULL,
    FOREIGN KEY (onaylayan_id) REFERENCES personel(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- İzin belgeleri tablosu
CREATE TABLE izin_belgeler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    izin_id VARCHAR(12),
    dosya_yolu VARCHAR(255) NOT NULL,
    yukleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (izin_id) REFERENCES izinler(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Vardiya talepleri tablosu
CREATE TABLE vardiya_talepleri (
    id VARCHAR(12) PRIMARY KEY,
    vardiya_id VARCHAR(12),
    talep_eden_personel_id VARCHAR(12),
    aciklama TEXT,
    durum ENUM('beklemede', 'onaylandi', 'reddedildi') NOT NULL DEFAULT 'beklemede',
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vardiya_id) REFERENCES vardiyalar(id) ON DELETE CASCADE,
    FOREIGN KEY (talep_eden_personel_id) REFERENCES personel(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- İzin talepleri tablosu
CREATE TABLE izin_talepleri (
    id VARCHAR(12) PRIMARY KEY,
    personel_id VARCHAR(12),
    baslangic_tarihi TIMESTAMP NOT NULL,
    bitis_tarihi TIMESTAMP NOT NULL,
    izin_turu VARCHAR(20) NOT NULL,
    aciklama TEXT,
    durum ENUM('beklemede', 'onaylandi', 'reddedildi') NOT NULL DEFAULT 'beklemede',
    talep_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    gun_sayisi INT NOT NULL,
    yonetici_notu TEXT,
    red_sebebi TEXT,
    hatirlatma_gonderildi BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (personel_id) REFERENCES personel(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Bildirim abonelikleri tablosu
CREATE TABLE bildirim_abonelikleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personel_id VARCHAR(12),
    endpoint VARCHAR(255) NOT NULL,
    auth VARCHAR(100) NOT NULL,
    p256dh VARCHAR(255) NOT NULL,
    kayit_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    son_guncelleme TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (personel_id) REFERENCES personel(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Vardiya türleri tablosu
CREATE TABLE vardiya_turleri (
    id VARCHAR(20) PRIMARY KEY,
    baslangic TIME NOT NULL,
    bitis TIME NOT NULL
) ENGINE=InnoDB;

-- İzin türleri tablosu
CREATE TABLE izin_turleri (
    id VARCHAR(20) PRIMARY KEY,
    ad VARCHAR(50) NOT NULL,
    varsayilan_hak INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- İndeksler
CREATE INDEX idx_personel_email ON personel(email);
CREATE INDEX idx_vardiyalar_tarih ON vardiyalar(tarih);
CREATE INDEX idx_izinler_tarih ON izinler(baslangic_tarihi, bitis_tarihi);
CREATE INDEX idx_vardiya_talepleri_durum ON vardiya_talepleri(durum);
CREATE INDEX idx_izin_talepleri_durum ON izin_talepleri(durum);

-- Varsayılan vardiya türlerini ekle
INSERT INTO vardiya_turleri (id, baslangic, bitis) VALUES
('sabah', '08:00:00', '16:00:00'),
('aksam', '16:00:00', '00:00:00'),
('gece', '00:00:00', '08:00:00');

-- Varsayılan izin türlerini ekle
INSERT INTO izin_turleri (id, ad, varsayilan_hak) VALUES
('yillik', 'Yıllık İzin', 14),
('mazeret', 'Mazeret İzni', 5),
('hastalik', 'Hastalık İzni', 0);

-- Örnek personel verileri
INSERT INTO personel (id, ad, soyad, yetki, email, telefon, sifre) VALUES
('64f1a2b3c4d5', 'Ahmet', 'Yılmaz', 'admin', 'ahmet@firma.com', '5551234567', '123456'),
('64f1a2b3c4d6', 'Ayşe', 'Demir', 'personel', 'ayse@firma.com', '5551234568', '123456'),
('64f1a2b3c4d7', 'Mehmet', 'Kaya', 'personel', 'mehmet@firma.com', '5551234569', '123456'),
('64f1a2b3c4d8', 'Fatma', 'Çelik', 'personel', 'fatma@firma.com', '5551234570', '123456');

-- Personel tercihleri
INSERT INTO personel_tercihler (personel_id, bildirimler, max_ardisik_vardiya, min_gunluk_dinlenme) VALUES
('64f1a2b3c4d5', true, 1, 8),
('64f1a2b3c4d6', true, 1, 8),
('64f1a2b3c4d7', true, 1, 8),
('64f1a2b3c4d8', true, 1, 8);

-- Tercih edilen vardiyalar
INSERT INTO personel_tercih_vardiyalar (personel_id, vardiya_turu) VALUES
('64f1a2b3c4d5', 'sabah'),
('64f1a2b3c4d5', 'aksam'),
('64f1a2b3c4d6', 'sabah'),
('64f1a2b3c4d7', 'gece'),
('64f1a2b3c4d8', 'sabah'),
('64f1a2b3c4d8', 'aksam');

-- Tercih edilmeyen günler
INSERT INTO personel_tercih_edilmeyen_gunler (personel_id, gun) VALUES
('64f1a2b3c4d6', 'cumartesi'),
('64f1a2b3c4d6', 'pazar'),
('64f1a2b3c4d7', 'cumartesi'),
('64f1a2b3c4d8', 'pazar');

-- İzin hakları
INSERT INTO izin_haklari (personel_id, izin_turu, toplam, kullanilan, kalan, son_guncelleme) VALUES
('64f1a2b3c4d5', 'yillik', 14, 4, 10, 1704844800),
('64f1a2b3c4d5', 'mazeret', 5, 1, 4, 1704844800),
('64f1a2b3c4d5', 'hastalik', 0, 2, 0, 1704844800);

-- Vardiyalar
INSERT INTO vardiyalar (id, personel_id, tarih, vardiya_turu, notlar, durum) VALUES
('64f1a2b3c4e1', '64f1a2b3c4d5', 1705276800, 'sabah', 'Özel proje toplantısı var', 'onaylandi'),
('64f1a2b3c4e2', '64f1a2b3c4d6', 1705276800, 'aksam', '', 'onaylandi'),
('64f1a2b3c4e3', '64f1a2b3c4d7', 1705276800, 'gece', '', 'onaylandi'),
('64f1a2b3c4e4', '64f1a2b3c4d8', 1705363200, 'sabah', '', 'onaylandi'),
('64f1a2b3c4e5', '64f1a2b3c4d5', 1705363200, 'aksam', '', 'onaylandi'),
('67824dc0bbf0a', '64f1a2b3c4d5', 1736888400, 'sabah', '', 'onaylandi'),
('678250cb53ded', '64f1a2b3c4d5', 1736974800, 'sabah', '', 'onaylandi'),
('678250d0a52bf', '64f1a2b3c4d5', 1737061200, 'sabah', '', 'onaylandi'),
('678250d4f1825', '64f1a2b3c4d5', 1737147600, 'sabah', '', 'onaylandi'),
('678250da2a7c3', '64f1a2b3c4d5', 1737234000, 'sabah', '', 'onaylandi'),
('678250df0ba85', '64f1a2b3c4d5', 1737320400, 'sabah', '', 'onaylandi'),
('678250e75e892', '64f1a2b3c4d5', 1737406800, 'sabah', '', 'onaylandi');

-- İzinler
INSERT INTO izinler (id, personel_id, baslangic_tarihi, bitis_tarihi, izin_turu, aciklama, durum, onaylayan_id, onay_tarihi, gun_sayisi, notlar) VALUES
('64f1a2b3c4f1', '64f1a2b3c4d5', 1706745600, 1707091200, 'yillik', 'Yıllık izin', 'onaylandi', '64f1a2b3c4d6', 1704844800, 4, '');

-- Vardiya talepleri
INSERT INTO vardiya_talepleri (id, vardiya_id, talep_eden_personel_id, aciklama, durum, olusturma_tarihi) VALUES
('64f1a2b3c4g1', '64f1a2b3c4e1', '64f1a2b3c4d5', 'Özel durum nedeniyle değişim talebi', 'beklemede', 1704672000);

-- İzin talepleri
INSERT INTO izin_talepleri (id, personel_id, baslangic_tarihi, bitis_tarihi, izin_turu, aciklama, durum, talep_tarihi, gun_sayisi, yonetici_notu, red_sebebi, hatirlatma_gonderildi) VALUES
('64f1a2b3c4h1', '64f1a2b3c4d6', 1709251200, 1709424000, 'yillik', 'Aile ziyareti', 'beklemede', 1704844800, 3, '', '', false);

-- Bildirim abonelikleri
INSERT INTO bildirim_abonelikleri (personel_id, endpoint, auth, p256dh, kayit_tarihi) VALUES
('64f1a2b3c4d5', 'https://fcm.googleapis.com/fcm/send/...', 'auth_key_1', 'p256dh_key_1', 1704844800); 