<?php
// Gabia Database Configuration
class DatabaseConfig {
    private static $host;
    private static $dbname;
    private static $username;
    private static $password;
    
    private static function loadConfig() {
        // 가비아 호스팅 설정 (실제 값으로 변경 필요)
        self::$host = 'localhost';  // 가비아에서 제공하는 DB 호스트
        self::$dbname = 'tangsaeng_db';  // 생성한 DB명
        self::$username = 'your_db_user';  // DB 사용자명 (가비아에서 생성)
        self::$password = 'your_db_password';  // DB 비밀번호 (가비아에서 생성)
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