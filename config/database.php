<?php
// Gabia Database Configuration
class DatabaseConfig {
    private static $host;
    private static $dbname;
    private static $username;
    private static $password;
    
    private static function loadConfig() {
        // 가비아 호스팅 설정
        self::$host = 'localhost';
        self::$dbname = 'tansaeng_db';  // 실제 DB명 수정
        self::$username = 'root';  // 실제 사용자명
        self::$password = 'qjawns3445';  // MySQL 비밀번호
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