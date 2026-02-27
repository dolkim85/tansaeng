<?php
/**
 * 분무수경 가동 로그 조회 API
 * GET ?date=YYYY-MM-DD&zone_id=zone1 (선택)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

date_default_timezone_set('Asia/Seoul');

require_once __DIR__ . '/../../classes/Database.php';

$date   = $_GET['date']    ?? date('Y-m-d');
$zoneId = $_GET['zone_id'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

try {
    $db = Database::getInstance();

    // 로그 조회
    $params  = [$date];
    $zoneWhere = '';
    if ($zoneId !== '') {
        $zoneWhere = ' AND zone_id = ?';
        $params[] = $zoneId;
    }

    $logs = $db->select(
        "SELECT id, zone_id, zone_name, event_type, mode, created_at
         FROM mist_logs
         WHERE DATE(created_at) = ?{$zoneWhere}
         ORDER BY created_at ASC",
        $params
    );

    // 요약 (총 가동 횟수 + 총 시간)
    $startCount  = 0;
    $totalSeconds = 0;
    $zoneStarts  = [];

    foreach ($logs as $log) {
        $zId = $log['zone_id'];
        if ($log['event_type'] === 'start') {
            $startCount++;
            $zoneStarts[$zId] = strtotime($log['created_at']);
        } elseif ($log['event_type'] === 'stop' && isset($zoneStarts[$zId])) {
            $totalSeconds += strtotime($log['created_at']) - $zoneStarts[$zId];
            unset($zoneStarts[$zId]);
        }
    }

    // 구역 목록 (필터 드롭다운용)
    $zones = $db->select(
        "SELECT DISTINCT zone_id, zone_name FROM mist_logs ORDER BY zone_id"
    );

    echo json_encode([
        'success' => true,
        'date'    => $date,
        'logs'    => array_values($logs),
        'summary' => [
            'start_count'   => $startCount,
            'total_minutes' => (int) round($totalSeconds / 60),
        ],
        'zones'   => array_values($zones),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
