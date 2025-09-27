<?php
// 이미지 업로드 테스트 스크립트

// 테스트 이미지 생성 (1x1 픽셀 투명 PNG)
$imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAHmp3CM+AAAAABJRU5ErkJggg==');
$testImagePath = './test_image.png';
file_put_contents($testImagePath, $imageData);

echo "=== 이미지 업로드 테스트 ===\n";
echo "테스트 이미지 생성: $testImagePath (" . filesize($testImagePath) . " bytes)\n\n";

// 가상 $_FILES 배열 생성
$_FILES = [
    'image' => [
        'name' => 'test_image.png',
        'type' => 'image/png',
        'size' => filesize($testImagePath),
        'tmp_name' => $testImagePath,
        'error' => UPLOAD_ERR_OK
    ]
];

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];

echo "가상 파일 데이터:\n";
print_r($_FILES);
echo "\n";

// 업로드 API 실행
ob_start();
try {
    // 인증 우회를 위해 임시로 수정
    $originalContent = file_get_contents('./admin/api/image_upload.php');

    // 인증 부분 주석 처리
    $modifiedContent = str_replace(
        'if (!$currentUser || $currentUser[\'user_level\'] < 9) {',
        'if (false && !$currentUser || $currentUser[\'user_level\'] < 9) {',
        $originalContent
    );

    file_put_contents('./admin/api/temp_image_upload.php', $modifiedContent);

    include './admin/api/temp_image_upload.php';

} catch (Exception $e) {
    echo "오류: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();
echo "API 응답:\n$output\n";

// 정리
if (file_exists($testImagePath)) {
    unlink($testImagePath);
}
if (file_exists('./admin/api/temp_image_upload.php')) {
    unlink('./admin/api/temp_image_upload.php');
}

echo "\n=== 테스트 완료 ===\n";
?>