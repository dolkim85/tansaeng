<?php
/**
 * 스마트팜 센서 데이터 삭제 API
 * 개별 삭제, 선택 삭제, 날짜 범위 삭제 지원
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../classes/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $db = Database::getInstance();

    // JSON 입력 파싱
    $input = json_decode(file_get_contents('php://input'), true);

    $deleteType = $input['type'] ?? 'selected'; // 'selected', 'date_range', 'all'
    $deletedCount = 0;

    switch ($deleteType) {
        case 'selected':
            // 선택된 레코드 삭제 (recorded_at 기준)
            $records = $input['records'] ?? [];

            if (empty($records)) {
                echo json_encode(['success' => false, 'error' => '삭제할 데이터를 선택해주세요.']);
                exit;
            }

            // 각 레코드를 개별 삭제 (recorded_at + sensor_location 조합으로 식별)
            foreach ($records as $record) {
                $sql = "DELETE FROM sensor_data
                        WHERE recorded_at = :recorded_at
                        AND sensor_location = :sensor_location
                        LIMIT 1";

                $result = $db->query($sql, [
                    'recorded_at' => $record['recorded_at'],
                    'sensor_location' => $record['sensor_location']
                ]);

                if ($result) {
                    $deletedCount++;
                }
            }
            break;

        case 'date_range':
            // 날짜 범위 삭제
            $startDate = $input['start_date'] ?? null;
            $endDate = $input['end_date'] ?? null;

            if (!$startDate || !$endDate) {
                echo json_encode(['success' => false, 'error' => '시작 날짜와 종료 날짜를 입력해주세요.']);
                exit;
            }

            // 삭제 전 개수 확인
            $countSql = "SELECT COUNT(*) as cnt FROM sensor_data WHERE DATE(recorded_at) BETWEEN :start_date AND :end_date";
            $countResult = $db->selectOne($countSql, ['start_date' => $startDate, 'end_date' => $endDate]);
            $deletedCount = $countResult['cnt'] ?? 0;

            // 삭제 실행
            $sql = "DELETE FROM sensor_data WHERE DATE(recorded_at) BETWEEN :start_date AND :end_date";
            $db->query($sql, ['start_date' => $startDate, 'end_date' => $endDate]);
            break;

        case 'all':
            // 전체 삭제 (주의: 확인 필요)
            $confirm = $input['confirm'] ?? false;

            if (!$confirm) {
                echo json_encode(['success' => false, 'error' => '전체 삭제를 확인해주세요.']);
                exit;
            }

            // 삭제 전 개수 확인
            $countSql = "SELECT COUNT(*) as cnt FROM sensor_data";
            $countResult = $db->selectOne($countSql);
            $deletedCount = $countResult['cnt'] ?? 0;

            // 전체 삭제
            $sql = "TRUNCATE TABLE sensor_data";
            $db->query($sql);
            break;

        default:
            echo json_encode(['success' => false, 'error' => '알 수 없는 삭제 유형입니다.']);
            exit;
    }

    echo json_encode([
        'success' => true,
        'message' => "{$deletedCount}개의 데이터가 삭제되었습니다.",
        'deleted_count' => $deletedCount
    ]);

} catch (Exception $e) {
    error_log("Delete sensor data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
