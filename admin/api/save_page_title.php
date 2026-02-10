<?php
// 관리자 페이지 제목 저장 API
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

$input = json_decode(file_get_contents('php://input'), true);
$pageKey = $input['page'] ?? '';
$newTitle = trim($input['title'] ?? '');

// 허용되는 페이지 키 목록
$allowedKeys = [
    'product_index', 'product_coco', 'product_perlite',
    'product_mixed', 'product_compare',
    'support_technical', 'support_faq'
];

if (empty($pageKey) || !in_array($pageKey, $allowedKeys)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '유효하지 않은 페이지입니다']);
    exit;
}

if (empty($newTitle) || mb_strlen($newTitle) > 50) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '제목은 1~50자 이내로 입력해주세요']);
    exit;
}

$configFile = __DIR__ . '/../../config/page_titles.json';

// 기존 설정 불러오기
$titles = [];
if (file_exists($configFile)) {
    $titles = json_decode(file_get_contents($configFile), true) ?: [];
}

// 제목 업데이트
$titles[$pageKey] = $newTitle;

// 저장
$result = file_put_contents($configFile, json_encode($titles, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

if ($result !== false) {
    echo json_encode([
        'success' => true,
        'message' => '제목이 저장되었습니다.',
        'title' => $newTitle
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '제목 저장에 실패했습니다.']);
}
