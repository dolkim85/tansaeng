<?php
/**
 * Environment Detection and Configuration
 * 환경별 자동 감지 및 설정 시스템
 */

class Environment {
    private static $environment = null;

    public static function detect() {
        if (self::$environment !== null) {
            return self::$environment;
        }

        // 환경 감지
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];

            if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
                self::$environment = 'local';
            } elseif (strpos($host, '1.201.17.34') !== false) {
                self::$environment = 'production';
            } else {
                self::$environment = 'production'; // 기본값
            }
        } else {
            // CLI 환경 - 더 정확한 판단
            $hostname = gethostname();
            $currentPath = getcwd();

            if (strpos($currentPath, '/var/www/html') !== false) {
                self::$environment = 'production'; // 클라우드 서버
            } elseif (strpos($currentPath, '/home/spinmoll') !== false) {
                self::$environment = 'local'; // 로컬 개발 환경
            } else {
                self::$environment = 'production'; // 기본값
            }
        }

        return self::$environment;
    }

    public static function isLocal() {
        return self::detect() === 'local';
    }

    public static function isProduction() {
        return self::detect() === 'production';
    }

    public static function getDatabaseConfig() {
        $env = self::detect();

        switch ($env) {
            case 'local':
                return [
                    'host' => 'localhost',
                    'dbname' => 'tansaeng_db',
                    'username' => 'root',
                    'password' => '', // 로컬 MySQL 비밀번호 없음
                ];

            case 'production':
                return [
                    'host' => 'localhost',
                    'dbname' => 'tansaeng_db',
                    'username' => 'root',
                    'password' => 'qjawns3445', // 클라우드 MySQL 비밀번호
                ];

            default:
                throw new Exception('Unknown environment: ' . $env);
        }
    }
}
?>