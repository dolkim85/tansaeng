<?php
// 관리자 페이지 저장 API
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../classes/Auth.php';

$auth = Auth::getInstance();
if (!$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '관리자 권한이 필요합니다']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '허용되지 않는 요청 방식입니다']);
    exit;
}

// JSON 또는 form data 모두 지원
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    $pageKey = $input['page'] ?? '';
    $content = $input['content'] ?? '';
} else {
    $pageKey = $_POST['page'] ?? '';
    $content = $_POST['content'] ?? '';
}

if (empty($pageKey) || $content === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '페이지 키와 콘텐츠가 필요합니다']);
    exit;
}

// 페이지 정보 매핑
$pageMap = [
    'product_coco' => ['title' => '코코피트 배지', 'file' => '/pages/products/coco.php'],
    'product_perlite' => ['title' => '펄라이트 배지', 'file' => '/pages/products/perlite.php'],
    'product_mixed' => ['title' => '혼합 배지', 'file' => '/pages/products/mixed.php'],
    'product_compare' => ['title' => '제품 비교', 'file' => '/pages/products/compare.php'],
    'support_technical' => ['title' => '기술지원', 'file' => '/pages/support/technical.php'],
    'support_faq' => ['title' => 'FAQ', 'file' => '/pages/support/faq.php']
];

if (!isset($pageMap[$pageKey])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '유효하지 않은 페이지입니다']);
    exit;
}

$pageInfo = $pageMap[$pageKey];
$filePath = realpath(__DIR__ . '/../../') . '/' . ltrim($pageInfo['file'], '/');

// 파일 존재 여부 확인
if (!file_exists($filePath)) {
    // 디렉토리가 존재하는지 확인
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '페이지 디렉토리가 존재하지 않습니다: ' . basename($dir)]);
        exit;
    }
}

// 백업 생성
$backupDir = __DIR__ . '/../../backup/pages';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

if (file_exists($filePath)) {
    $backupFile = $backupDir . '/' . $pageKey . '_' . date('Ymd_His') . '.php.bak';
    copy($filePath, $backupFile);

    // 오래된 백업 정리 (페이지당 최대 10개 유지)
    $backups = glob($backupDir . '/' . $pageKey . '_*.php.bak');
    if (count($backups) > 10) {
        sort($backups);
        $toDelete = array_slice($backups, 0, count($backups) - 10);
        foreach ($toDelete as $old) {
            unlink($old);
        }
    }
}

// 파일 저장
$result = file_put_contents($filePath, $content);

if ($result !== false) {
    echo json_encode([
        'success' => true,
        'message' => '페이지가 성공적으로 저장되었습니다.',
        'file' => $pageInfo['file'],
        'size' => $result
    ]);
} else {
    // 권한 문제 진단
    $parentDir = dirname($filePath);
    $dirWritable = is_writable($parentDir);
    $fileWritable = file_exists($filePath) ? is_writable($filePath) : $dirWritable;

    $errorDetail = '파일 저장에 실패했습니다.';
    if (!$fileWritable) {
        $errorDetail .= ' (파일 쓰기 권한이 없습니다. 서버 관리자에게 문의하세요.)';
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $errorDetail]);
}
