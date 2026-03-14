<?php
/**
 * 분무수경 가동 로그 조회 API
 * GET ?date=YYYY-MM-DD&zone_id=zone1&page=1&per_page=20
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

date_default_timezone_set('Asia/Seoul');

require_once __DIR__ . '/../../classes/Database.php';

$date    = $_GET['date']     ?? date('Y-m-d');
$zoneId  = $_GET['zone_id']  ?? '';
$page    = max(1, (int)($_GET['page']     ?? 1));
$perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

try {
    $db = Database::getInstance();

    $params    = [$date];
    $zoneWhere = '';
    if ($zoneId !== '') {
        $zoneWhere = ' AND zone_id = ?';
        $params[]  = $zoneId;
    }

    // 전체 건수 (페이징용)
    $countRow   = $db->select(
        "SELECT COUNT(*) AS cnt FROM mist_logs WHERE DATE(created_at) = ?{$zoneWhere}",
        $params
    );
    $totalCount = (int)($countRow[0]['cnt'] ?? 0);
    $totalPages = max(1, (int)ceil($totalCount / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    // 페이지 데이터 (최신순) - LIMIT/OFFSET은 정수 직접 삽입 (PDO 바인딩 오류 방지)
    $logs = $db->select(
        "SELECT id, zone_id, zone_name, event_type, mode, created_at
         FROM mist_logs
         WHERE DATE(created_at) = ?{$zoneWhere}
         ORDER BY created_at DESC
         LIMIT {$perPage} OFFSET {$offset}",
        $params
    );

    // 요약 (전체 날짜 기준 - 페이지 무관)
    $allLogs = $db->select(
        "SELECT zone_id, event_type, created_at
         FROM mist_logs
         WHERE DATE(created_at) = ?{$zoneWhere}
         ORDER BY zone_id, created_at ASC",
        $params
    );
    $startCount   = 0;
    $totalSeconds = 0;
    $zoneStarts   = [];
    foreach ($allLogs as $log) {
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
        'success'     => true,
        'date'        => $date,
        'logs'        => array_values($logs),
        'total_count' => $totalCount,
        'total_pages' => $totalPages,
        'page'        => $page,
        'per_page'    => $perPage,
        'summary'     => [
            'start_count'   => $startCount,
            'total_minutes' => (int)round($totalSeconds / 60),
        ],
        'zones'       => array_values($zones),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
