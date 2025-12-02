#!/usr/bin/php
<?php
/**
 * 일일 센서 데이터 엑셀 리포트 생성 및 이메일 전송
 * 매일 오전 9시 실행 예정
 */

require_once __DIR__ . '/../classes/Database.php';

// 날짜 설정 (어제 데이터)
$yesterday = date('Y-m-d', strtotime('-1 day'));
echo "=== 일일 센서 데이터 리포트 생성 ===\n";
echo "대상 날짜: {$yesterday}\n";

try {
    $db = Database::getInstance();

    // 어제 데이터 조회
    $sql = "SELECT
                controller_id,
                sensor_type,
                sensor_location,
                temperature,
                humidity,
                recorded_at
            FROM sensor_data
            WHERE DATE(recorded_at) = :date
            ORDER BY recorded_at ASC";

    $data = $db->select($sql, ['date' => $yesterday]);

    if (empty($data)) {
        echo "어제 데이터가 없습니다.\n";
        exit(0);
    }

    echo "데이터 " . count($data) . "개 조회됨\n";

    // CSV 파일 생성 (Excel에서 열 수 있음)
    $filename = "/tmp/sensor_data_{$yesterday}.csv";
    $fp = fopen($filename, 'w');

    // UTF-8 BOM 추가 (Excel에서 한글 깨짐 방지)
    fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

    // 헤더 작성
    fputcsv($fp, ['제어장치 ID', '센서 타입', '센서 위치', '온도(°C)', '습도(%)', '기록 시간']);

    // 데이터 작성
    foreach ($data as $row) {
        fputcsv($fp, [
            $row['controller_id'],
            $row['sensor_type'],
            $row['sensor_location'],
            $row['temperature'] ?? '-',
            $row['humidity'] ?? '-',
            $row['recorded_at']
        ]);
    }

    fclose($fp);
    echo "CSV 파일 생성 완료: {$filename}\n";

    // 통계 계산
    $stats = [];
    foreach (['front', 'back', 'top'] as $location) {
        $locationData = array_filter($data, function($row) use ($location) {
            return $row['sensor_location'] === $location;
        });

        if (!empty($locationData)) {
            $temps = array_filter(array_column($locationData, 'temperature'));
            $hums = array_filter(array_column($locationData, 'humidity'));

            $stats[$location] = [
                'temp_avg' => !empty($temps) ? round(array_sum($temps) / count($temps), 2) : null,
                'temp_min' => !empty($temps) ? min($temps) : null,
                'temp_max' => !empty($temps) ? max($temps) : null,
                'hum_avg' => !empty($hums) ? round(array_sum($hums) / count($hums), 2) : null,
                'hum_min' => !empty($hums) ? min($hums) : null,
                'hum_max' => !empty($hums) ? max($hums) : null,
                'count' => count($locationData)
            ];
        }
    }

    // 이메일 본문 작성
    $emailBody = "탄생 스마트팜 일일 센서 데이터 리포트\n";
    $emailBody .= "========================================\n\n";
    $emailBody .= "날짜: {$yesterday}\n";
    $emailBody .= "총 데이터 수: " . count($data) . "개\n\n";

    foreach ($stats as $location => $stat) {
        $locationName = [
            'front' => '내부팬 앞',
            'back' => '내부팬 뒤',
            'top' => '천장'
        ][$location];

        $emailBody .= "----------------------------------------\n";
        $emailBody .= "{$locationName} (데이터 {$stat['count']}개)\n";
        $emailBody .= "----------------------------------------\n";
        $emailBody .= "온도: 평균 {$stat['temp_avg']}°C (최소 {$stat['temp_min']}°C / 최대 {$stat['temp_max']}°C)\n";
        $emailBody .= "습도: 평균 {$stat['hum_avg']}% (최소 {$stat['hum_min']}% / 최대 {$stat['hum_max']}%)\n\n";
    }

    $emailBody .= "\n자세한 데이터는 첨부된 CSV 파일을 확인하세요.\n";
    $emailBody .= "\n이 메일은 자동으로 발송되었습니다.\n";

    // 이메일 전송 (mail 함수 사용)
    $to = 'korea_tansaeng@naver.com';
    $subject = "[탄생 스마트팜] 일일 센서 데이터 리포트 ({$yesterday})";

    // 첨부 파일을 위한 MIME 설정
    $boundary = md5(time());
    $headers = "From: noreply@tansaeng.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

    // 이메일 본문
    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $emailBody . "\r\n";

    // CSV 파일 첨부
    $fileContent = file_get_contents($filename);
    $fileContent = chunk_split(base64_encode($fileContent));

    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/csv; name=\"sensor_data_{$yesterday}.csv\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "Content-Disposition: attachment; filename=\"sensor_data_{$yesterday}.csv\"\r\n\r\n";
    $message .= $fileContent . "\r\n";
    $message .= "--{$boundary}--";

    // 이메일 전송
    if (mail($to, $subject, $message, $headers)) {
        echo "이메일 전송 성공: {$to}\n";
    } else {
        echo "이메일 전송 실패\n";
    }

    // 임시 파일 삭제
    unlink($filename);
    echo "임시 파일 삭제 완료\n";

} catch (Exception $e) {
    echo "오류 발생: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== 리포트 생성 완료 ===\n";
?>
