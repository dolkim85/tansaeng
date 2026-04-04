#!/usr/bin/php
<?php
/**
 * mist_logs 테이블 생성 마이그레이션 스크립트
 * 실행 후 삭제하거나 웹 접근 차단 필요
 * Usage: php scripts/create_mist_logs_table.php
 */

date_default_timezone_set('Asia/Seoul');

require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

$sql = "CREATE TABLE IF NOT EXISTS mist_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  zone_id VARCHAR(50) NOT NULL,
  zone_name VARCHAR(100) NOT NULL,
  event_type ENUM('start', 'stop') NOT NULL,
  mode VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created_at (created_at),
  INDEX idx_zone_id (zone_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $pdo->exec($sql);
    echo "[OK] mist_logs 테이블 생성 완료 (" . date('Y-m-d H:i:s') . ")\n";
} catch (Exception $e) {
    echo "[ERROR] 테이블 생성 실패: " . $e->getMessage() . "\n";
    exit(1);
}
