#!/usr/bin/php
<?php
/**
 * 월간 센서 데이터 백업 스크립트
 * - 센서 데이터를 CSV(Excel 호환)로 내보내기
 * - 이메일로 전송
 * - 데이터 삭제
 *
 * Cron: 0 0 1 * * /usr/bin/php /var/www/html/scripts/monthly_sensor_backup.php
 */

date_default_timezone_set('Asia/Seoul');

// 설정
$config = [
    'email_to' => 'spinmoll@naver.com',
    'email_from' => 'noreply@tansaeng.com',
    'email_subject' => '[탄생농원] 센서 데이터 월간 백업',
    'backup_dir' => __DIR__ . '/../backups/sensor_data',
];

// 로그 함수
function logMessage($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
}

logMessage("=== 월간 센서 데이터 백업 시작 ===");

// 백업 디렉토리 생성
if (!is_dir($config['backup_dir'])) {
    mkdir($config['backup_dir'], 0755, true);
    logMessage("백업 디렉토리 생성: " . $config['backup_dir']);
}

// 데이터베이스 연결
require_once __DIR__ . '/../classes/Database.php';
$db = Database::getInstance();

// 현재 데이터 개수 확인
$countResult = $db->query("SELECT COUNT(*) as cnt FROM sensor_data");
$totalCount = $countResult[0]['cnt'] ?? 0;

if ($totalCount == 0) {
    logMessage("백업할 데이터가 없습니다.");
    exit(0);
}

logMessage("백업할 데이터: {$totalCount}건");

// 날짜 범위 확인
$dateResult = $db->query("SELECT MIN(recorded_at) as min_date, MAX(recorded_at) as max_date FROM sensor_data");
$minDate = $dateResult[0]['min_date'] ?? '';
$maxDate = $dateResult[0]['max_date'] ?? '';

// CSV 파일명 생성
$yearMonth = date('Y-m');
$filename = "sensor_data_{$yearMonth}_" . date('Ymd_His') . ".csv";
$filepath = $config['backup_dir'] . '/' . $filename;

logMessage("CSV 파일 생성: $filename");

// CSV 파일 생성
$fp = fopen($filepath, 'w');

// UTF-8 BOM 추가 (Excel에서 한글 깨짐 방지)
fwrite($fp, "\xEF\xBB\xBF");

// 헤더 작성
fputcsv($fp, [
    'ID',
    '컨트롤러 ID',
    '센서 타입',
    '센서 위치',
    '온도(°C)',
    '습도(%)',
    '조도',
    '토양 수분',
    'pH',
    '기록 시간'
]);

// 데이터 조회 및 작성 (배치 처리)
$batchSize = 10000;
$offset = 0;
$writtenCount = 0;

while (true) {
    $sql = "SELECT id, controller_id, sensor_type, sensor_location,
            temperature, humidity, light_intensity, soil_moisture, ph_level, recorded_at
            FROM sensor_data
            ORDER BY recorded_at
            LIMIT $batchSize OFFSET $offset";

    $rows = $db->query($sql);

    if (empty($rows)) {
        break;
    }

    foreach ($rows as $row) {
        fputcsv($fp, [
            $row['id'],
            $row['controller_id'],
            $row['sensor_type'],
            $row['sensor_location'],
            $row['temperature'],
            $row['humidity'],
            $row['light_intensity'],
            $row['soil_moisture'],
            $row['ph_level'],
            $row['recorded_at']
        ]);
        $writtenCount++;
    }

    $offset += $batchSize;
    logMessage("처리 중: {$writtenCount}건...");
}

fclose($fp);

$fileSize = filesize($filepath);
$fileSizeMB = round($fileSize / 1024 / 1024, 2);
logMessage("CSV 파일 생성 완료: {$writtenCount}건, {$fileSizeMB}MB");

// 이메일 전송
logMessage("이메일 전송 중...");

$boundary = md5(time());

$headers = "From: {$config['email_from']}\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

$body = "--{$boundary}\r\n";
$body .= "Content-Type: text/html; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";

$body .= "<html><body>";
$body .= "<h2>탄생농원 센서 데이터 월간 백업</h2>";
$body .= "<p><strong>백업 기간:</strong> {$minDate} ~ {$maxDate}</p>";
$body .= "<p><strong>데이터 건수:</strong> " . number_format($totalCount) . "건</p>";
$body .= "<p><strong>파일 크기:</strong> {$fileSizeMB}MB</p>";
$body .= "<p><strong>백업 일시:</strong> " . date('Y-m-d H:i:s') . "</p>";
$body .= "<hr>";
$body .= "<p>첨부된 CSV 파일은 Excel에서 열 수 있습니다.</p>";
$body .= "</body></html>";
$body .= "\r\n\r\n";

// 파일 첨부
$fileContent = file_get_contents($filepath);
$fileEncoded = chunk_split(base64_encode($fileContent));

$body .= "--{$boundary}\r\n";
$body .= "Content-Type: text/csv; name=\"{$filename}\"\r\n";
$body .= "Content-Transfer-Encoding: base64\r\n";
$body .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
$body .= $fileEncoded;
$body .= "\r\n--{$boundary}--";

$emailSent = mail($config['email_to'], $config['email_subject'], $body, $headers);

if ($emailSent) {
    logMessage("이메일 전송 성공: {$config['email_to']}");

    // 데이터 삭제
    logMessage("센서 데이터 삭제 중...");
    $db->query("DELETE FROM sensor_data");
    logMessage("센서 데이터 삭제 완료");

    // 로컬 CSV 파일 유지 (백업용)
    logMessage("백업 파일 보관: $filepath");
} else {
    logMessage("이메일 전송 실패! 데이터를 삭제하지 않습니다.");
}

logMessage("=== 월간 센서 데이터 백업 완료 ===");
?>
