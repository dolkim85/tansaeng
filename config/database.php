<?php
// Database Configuration
class DatabaseConfig {
    private static $host = 'localhost';
    private static $dbname = 'tansaeng_db';
    private static $username = 'root';
    private static $password = 'qjawns3445';

    public static function getConnection() {
        try {
            $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$dbname . ";charset=utf8mb4";
            $pdo = new PDO($dsn, self::$username, self::$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        } catch (PDOException $e) {
            throw new Exception("데이터베이스 연결 실패: " . $e->getMessage());
        }
    }
}

// Helper function for convenience
function getDBConnection() {
    return DatabaseConfig::getConnection();
}
?>