<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 관리자 인증 확인
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
          (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 9);

if (!$isAdmin) {
    echo json_encode(['success' => false, 'error' => '권한이 없습니다.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST 요청만 허용됩니다.']);
    exit;
}

$pageKey = $_POST['page'] ?? '';
$content = $_POST['content'] ?? '';

if (empty($pageKey)) {
    echo json_encode(['success' => false, 'error' => '페이지 키가 필요합니다.']);
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
    echo json_encode(['success' => false, 'error' => '잘못된 페이지 키입니다.']);
    exit;
}

// 임시 파일 저장 디렉토리
$tempDir = __DIR__ . '/../../temp/preview';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// 임시 파일명 생성 (세션 ID 기반)
$tempFile = $tempDir . '/' . $pageKey . '_' . session_id() . '.php';

// 임시 파일에 저장
try {
    if (file_put_contents($tempFile, $content) !== false) {
        echo json_encode([
            'success' => true,
            'message' => '임시 파일이 저장되었습니다.',
            'tempFile' => basename($tempFile)
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => '임시 파일 저장에 실패했습니다.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
