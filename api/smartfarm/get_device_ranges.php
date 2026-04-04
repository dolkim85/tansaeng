<?php
/**
 * 장치별 온도 범위 설정 조회 API
 * GET /api/smartfarm/get_device_ranges.php
 */
date_default_timezone_set('Asia/Seoul');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../classes/Database.php';

try {
    $db = Database::getInstance();

    // 테이블 없으면 자동 생성
    $db->query("CREATE TABLE IF NOT EXISTS device_ranges (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        device_key  VARCHAR(50)  NOT NULL,
        device_name VARCHAR(100) NOT NULL,
        range_low   FLOAT        NOT NULL DEFAULT 15,
        range_high  FLOAT        NOT NULL DEFAULT 22,
        updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_device_key (device_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $rows = $db->select("SELECT device_key, device_name, range_low, range_high,
                                DATE_FORMAT(updated_at, '%m/%d %H:%i') AS updated_fmt
                         FROM device_ranges ORDER BY id ASC");

    $result = [];
    foreach ($rows as $r) {
        $result[$r['device_key']] = [
            'device_name' => $r['device_name'],
            'low'         => (float)$r['range_low'],
            'high'        => (float)$r['range_high'],
            'updated_at'  => $r['updated_fmt'],
        ];
    }

    echo json_encode(['success' => true, 'ranges' => $result], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
