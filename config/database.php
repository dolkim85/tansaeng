<?php
// Environment-aware Database Configuration
require_once __DIR__ . '/environment.php';

class DatabaseConfig {
    private static $host;
    private static $dbname;
    private static $username;
    private static $password;

    private static function loadConfig() {
        // 환경별 자동 설정
        $config = Environment::getDatabaseConfig();

        self::$host = $config['host'];
        self::$dbname = $config['dbname'];
        self::$username = $config['username'];
        self::$password = $config['password'];
    }
    
    public static function getConnection() {
        self::loadConfig();
        
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
?>