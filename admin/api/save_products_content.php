<?php
// 배지설명 페이지 콘텐츠 저장 API
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../classes/Auth.php';

$auth = Auth::getInstance();
if (!$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '관리자 권한이 필요합니다']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 현재 콘텐츠 반환
    $configFile = __DIR__ . '/../../config/products_page_content.json';
    if (file_exists($configFile)) {
        $content = file_get_contents($configFile);
        echo $content;
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '설정 파일이 없습니다']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '허용되지 않는 요청 방식입니다']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '유효하지 않은 데이터입니다']);
    exit;
}

// 필수 구조 검증
if (!isset($input['header']) || !isset($input['products']) || !isset($input['comparison']) || !isset($input['cta'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '필수 섹션이 누락되었습니다 (header, products, comparison, cta)']);
    exit;
}

$configFile = __DIR__ . '/../../config/products_page_content.json';

// 백업
$backupDir = __DIR__ . '/../../backup/pages';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}
if (file_exists($configFile)) {
    copy($configFile, $backupDir . '/products_page_content_' . date('Ymd_His') . '.json.bak');
}

// 저장
$json = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$result = file_put_contents($configFile, $json);

if ($result !== false) {
    echo json_encode(['success' => true, 'message' => '배지설명 페이지가 저장되었습니다.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '파일 저장에 실패했습니다.']);
}
