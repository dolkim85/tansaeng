<?php
/**
 * 스마트팜 관리자 대시보드 데이터 API
 * 장치 상태, 분무 통계, 24시간 센서 차트 데이터 반환
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

date_default_timezone_set('Asia/Seoul');
set_time_limit(10);        // 최대 10초 실행
ini_set('memory_limit', '64M');

require_once __DIR__ . '/../../classes/Database.php';

try {
    $db    = Database::getInstance();
    $today = date('Y-m-d');

    // ── 1. 장치 온라인/오프라인 현황 + 컨트롤러별 목록 ──
    $deviceList = $db->select(
        "SELECT controller_id, status,
                TIMESTAMPDIFF(MINUTE, last_seen, NOW()) AS minutes_ago,
                DATE_FORMAT(last_seen, '%m/%d %H:%i') AS last_seen_fmt
         FROM device_status
         ORDER BY controller_id ASC"
    );

    $onlineCount  = 0;
    $offlineCount = 0;
    foreach ($deviceList as $d) {
        if ($d['status'] === 'online') $onlineCount++;
        else $offlineCount++;
    }

    // ── 2. 오늘 분무수경 통계 + 구역별 분무 횟수 ──
    $mistStats = ['count' => 0, 'total_minutes' => 0];
    $zoneMist  = [];
    $recentMist = [];

    try {
        $mistLogs = $db->select(
            "SELECT zone_id, event_type, created_at
             FROM mist_logs
             WHERE DATE(created_at) = ?
             ORDER BY zone_id, created_at ASC",
            [$today]
        );

        $startCount   = 0;
        $totalSeconds = 0;
        $zoneStarts   = [];

        foreach ($mistLogs as $log) {
            $zId = $log['zone_id'];
            if ($log['event_type'] === 'start') {
                $startCount++;
                $zoneStarts[$zId] = strtotime($log['created_at']);
            } elseif ($log['event_type'] === 'stop' && isset($zoneStarts[$zId])) {
                $totalSeconds += strtotime($log['created_at']) - $zoneStarts[$zId];
                unset($zoneStarts[$zId]);
            }
        }
        $mistStats = [
            'count'         => $startCount,
            'total_minutes' => (int)round($totalSeconds / 60),
        ];

        // 구역별 오늘 분무 횟수
        $zoneMistRows = $db->select(
            "SELECT zone_id, zone_name, COUNT(*) AS start_count
             FROM mist_logs
             WHERE DATE(created_at) = ? AND event_type = 'start'
             GROUP BY zone_id, zone_name
             ORDER BY zone_id",
            [$today]
        );
        $zoneMist = array_values($zoneMistRows);

        // 최근 분무 이벤트 (10건)
        $recentMist = $db->select(
            "SELECT zone_name, event_type, mode,
                    DATE_FORMAT(created_at, '%H:%i:%s') AS time_str,
                    DATE_FORMAT(created_at, '%m/%d') AS date_str
             FROM mist_logs
             ORDER BY created_at DESC
             LIMIT 10"
        );
    } catch (Exception $e) {
        // mist_logs 테이블 없음 → 기본값 유지
    }

    // ── 3. 24시간 온도/습도 차트 데이터 ──
    $chartRows = [];
    try {
        // sensor_data 테이블 존재 여부 먼저 확인
        $tableCheck = $db->select("SHOW TABLES LIKE 'sensor_data'");
        if (!empty($tableCheck)) {
            $chartRows = $db->select(
                "SELECT
                    DATE_FORMAT(MIN(recorded_at), '%m/%d %H시') AS hour_label,
                    ROUND(AVG(temperature), 1)                  AS avg_temp,
                    ROUND(AVG(humidity), 1)                     AS avg_humidity
                 FROM sensor_data
                 WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                   AND (temperature IS NOT NULL OR humidity IS NOT NULL)
                 GROUP BY DATE(recorded_at), HOUR(recorded_at)
                 ORDER BY MIN(recorded_at) ASC
                 LIMIT 24"
            );
        }
    } catch (Exception $e) {
        // 차트 데이터 실패해도 나머지 응답은 정상 반환
        $chartRows = [];
    }

    echo json_encode([
        'success'     => true,
        'devices'     => [
            'total'   => count($deviceList),
            'online'  => $onlineCount,
            'offline' => $offlineCount,
            'list'    => array_values($deviceList),
        ],
        'mist_today'  => $mistStats,
        'zone_mist'   => $zoneMist,
        'recent_mist' => array_values($recentMist),
        'chart_24h'   => array_values($chartRows),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
