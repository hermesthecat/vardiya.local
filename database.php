<?php

class Database
{
    private static $instance = null;
    private $connection;
    private $host = 'localhost';
    private $username = 'root';
    private $password = '';
    private $database = 'vardiya';
    private $charset = 'utf8mb4_turkish_ci';
    private $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    private function __construct()
    {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";
            $this->connection = new PDO($dsn, $this->username, $this->password, $this->options);
        } catch (PDOException $e) {
            throw new Exception("Veritabanı bağlantı hatası: " . $e->getMessage());
        }
    }

    // Singleton pattern - tek instance oluşturma
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // PDO bağlantısını döndür
    public function getConnection()
    {
        return $this->connection;
    }

    // Sorgu çalıştırma
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Sorgu hatası: " . $e->getMessage());
        }
    }

    // Tek satır getirme
    public function fetch($sql, $params = [])
    {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception("Veri getirme hatası: " . $e->getMessage());
        }
    }

    // Tüm satırları getirme
    public function fetchAll($sql, $params = [])
    {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Veri getirme hatası: " . $e->getMessage());
        }
    }

    // Son eklenen ID'yi getirme
    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    // Transaction başlatma
    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    // Transaction onaylama
    public function commit()
    {
        return $this->connection->commit();
    }

    // Transaction geri alma
    public function rollBack()
    {
        return $this->connection->rollBack();
    }

    // Bağlantıyı kapatma
    public function closeConnection()
    {
        $this->connection = null;
        self::$instance = null;
    }

    // Timestamp'i tarihe çevirme
    public function timestampToDate($timestamp)
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

    // Tarihi timestamp'e çevirme
    public function dateToTimestamp($date)
    {
        return strtotime($date);
    }

    // Güvenli string oluşturma
    public function escape($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

// Kullanım örneği:
/*
try {
    // Veritabanı bağlantısı
    $db = Database::getInstance();
    
    // Örnek sorgu
    $sql = "SELECT * FROM personel WHERE id = ?";
    $result = $db->fetch($sql, ['64f1a2b3c4d5']);
    
    // Örnek insert
    $sql = "INSERT INTO personel (id, ad, soyad) VALUES (?, ?, ?)";
    $db->query($sql, ['yeni_id', 'Ad', 'Soyad']);
    
    // Transaction örneği
    $db->beginTransaction();
    try {
        $sql1 = "INSERT INTO vardiyalar ...";
        $sql2 = "UPDATE izin_haklari ...";
        
        $db->query($sql1, [...]);
        $db->query($sql2, [...]);
        
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    // Hata yönetimi
    echo $e->getMessage();
}
*/
