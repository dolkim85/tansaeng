<?php
/**
 * 스마트팜 관리자 대시보드 데이터 API
 * 장치 상태, 분무 통계, 24시간 센서 차트 데이터 반환
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

date_default_timezone_set('Asia/Seoul');

require_once __DIR__ . '/../../classes/Database.php';

try {
    $db = Database::getInstance();

    // ── 1. 장치 온라인/오프라인 현황 ──
    $deviceRows = $db->select(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) AS online_count,
            SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) AS offline_count
         FROM device_status"
    );
    $deviceStats = $deviceRows[0] ?? ['total' => 0, 'online_count' => 0, 'offline_count' => 0];

    // ── 2. 오늘 분무수경 가동 통계 ──
    $today = date('Y-m-d');

    // mist_logs 테이블이 없을 경우 대비
    $mistStats = ['count' => 0, 'total_minutes' => 0];
    try {
        $mistLogs = $db->select(
            "SELECT zone_id, event_type, created_at
             FROM mist_logs
             WHERE DATE(created_at) = ?
             ORDER BY zone_id, created_at ASC",
            [$today]
        );

        $startCount  = 0;
        $totalSeconds = 0;
        $zoneStarts  = [];

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
            'total_minutes' => (int) round($totalSeconds / 60),
        ];
    } catch (Exception $e) {
        // mist_logs 테이블 없음 → 기본값 유지
    }

    // ── 3. 24시간 온도/습도 차트 데이터 ──
    $chartRows = $db->select(
        "SELECT
            DATE_FORMAT(recorded_at, '%H:00') AS hour_label,
            ROUND(AVG(temperature), 1)        AS avg_temp,
            ROUND(AVG(humidity), 1)           AS avg_humidity
         FROM sensor_data
         WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         GROUP BY DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00:00')
         ORDER BY MIN(recorded_at) ASC
         LIMIT 24"
    );

    echo json_encode([
        'success'    => true,
        'devices'    => [
            'total'   => (int) $deviceStats['total'],
            'online'  => (int) $deviceStats['online_count'],
            'offline' => (int) $deviceStats['offline_count'],
        ],
        'mist_today' => $mistStats,
        'chart_24h'  => array_values($chartRows),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
