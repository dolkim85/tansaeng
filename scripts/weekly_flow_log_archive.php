#!/usr/bin/php
<?php
/**
 * 분무수경 유량 로그 주간 압축 아카이브 스크립트
 * - mist_flow_logs 테이블에서 7일 지난 행을 ISO 주차별로 묶어 gzip 압축 저장
 * - 저장 성공 후에만 DB에서 삭제 (재실행해도 안전 — 기존 아카이브와 병합)
 *
 * Cron: 10 0 * * 1 /usr/bin/php /var/www/html/scripts/weekly_flow_log_archive.php
 */

date_default_timezone_set('Asia/Seoul');

function logMessage($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
}

logMessage("=== 유량 로그 주간 압축 아카이브 시작 ===");

require_once __DIR__ . '/../classes/Database.php';
$db = Database::getInstance();

$archiveDir = __DIR__ . '/../backups/flow_logs';
if (!is_dir($archiveDir)) {
    mkdir($archiveDir, 0755, true);
    logMessage("아카이브 디렉토리 생성: $archiveDir");
}

// 7일 지난 행 조회 (created_at 기준)
$rows = $db->select(
    "SELECT * FROM mist_flow_logs WHERE created_at < (NOW() - INTERVAL 7 DAY) ORDER BY log_at ASC"
);

if (empty($rows)) {
    logMessage("압축할 데이터가 없습니다.");
    logMessage("=== 완료 ===");
    exit(0);
}

logMessage("대상 행: " . count($rows) . "건");

// ISO 주차별로 그룹핑 (log_at 기준)
$byWeek = [];
foreach ($rows as $row) {
    $weekKey = date('o-\WW', strtotime($row['log_at']));
    $byWeek[$weekKey][] = $row;
}

$archivedIds = [];

foreach ($byWeek as $weekKey => $weekRows) {
    $file = $archiveDir . "/flow_logs_{$weekKey}.json.gz";

    // 기존 아카이브가 있으면 병합 (재실행 안전성)
    $existing = [];
    if (is_file($file)) {
        $raw = file_get_contents($file);
        $json = @gzdecode($raw);
        if ($json !== false) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) $existing = $decoded;
        }
    }

    // id 기준 중복 제거 후 병합
    $merged = $existing;
    $existingIds = array_column($existing, 'id');
    foreach ($weekRows as $row) {
        if (!in_array($row['id'], $existingIds, true)) {
            $merged[] = $row;
        }
    }
    usort($merged, fn($a, $b) => strcmp($a['log_at'], $b['log_at']));

    $gz = gzencode(json_encode($merged, JSON_UNESCAPED_UNICODE), 9);
    $written = file_put_contents($file, $gz);

    if ($written === false) {
        logMessage("⚠️ 압축 저장 실패: $weekKey — 이 주차 데이터는 DB에서 삭제하지 않습니다.");
        continue;
    }

    logMessage("압축 저장 완료: $weekKey (" . count($merged) . "건, " . round($written / 1024, 1) . "KB)");

    foreach ($weekRows as $row) {
        $archivedIds[] = $row['id'];
    }
}

if (!empty($archivedIds)) {
    $placeholders = implode(',', array_fill(0, count($archivedIds), '?'));
    $db->delete('mist_flow_logs', "id IN ($placeholders)", $archivedIds);
    logMessage("DB에서 삭제 완료: " . count($archivedIds) . "건");
}

logMessage("=== 유량 로그 주간 압축 아카이브 완료 ===");
