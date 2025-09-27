<?php
// 테스트용 이미지 업로드 API (인증 없음)
header('Content-Type: application/json');

// CORS 헤더
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST 메소드만 허용됩니다.']);
    exit;
}

// 업로드 로그 생성
error_log("TEST: Image upload attempt at " . date('Y-m-d H:i:s'));
error_log("TEST: Files received: " . print_r($_FILES, true));

// 파일 필드 확인
$fileField = null;
if (isset($_FILES['upload'])) {
    $fileField = 'upload';
} elseif (isset($_FILES['file'])) {
    $fileField = 'file';
} elseif (isset($_FILES['image'])) {
    $fileField = 'image';
} else {
    http_response_code(400);
    echo json_encode(['error' => '업로드할 파일이 없습니다.', 'debug' => array_keys($_FILES)]);
    exit;
}

$file = $_FILES[$fileField];

// 파일 검증
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => '파일이 너무 큽니다 (서버 설정)',
        UPLOAD_ERR_FORM_SIZE => '파일이 너무 큽니다 (폼 설정)',
        UPLOAD_ERR_PARTIAL => '파일이 부분적으로만 업로드됨',
        UPLOAD_ERR_NO_FILE => '파일이 업로드되지 않음',
        UPLOAD_ERR_NO_TMP_DIR => '임시 디렉토리 없음',
        UPLOAD_ERR_CANT_WRITE => '디스크 쓰기 실패',
        UPLOAD_ERR_EXTENSION => 'PHP 확장에 의해 중단됨'
    ];
    $errorMsg = $errorMessages[$file['error']] ?? '알 수 없는 업로드 오류';
    echo json_encode(['error' => $errorMsg, 'errorCode' => $file['error']]);
    exit;
}

// 파일 크기 확인 (10MB 제한)
if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => '파일 크기가 너무 큽니다. (최대 10MB)', 'size' => $file['size']]);
    exit;
}

// 파일 타입 및 확장자 확인
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    http_response_code(400);
    echo json_encode([
        'error' => '지원하지 않는 파일 형식입니다.',
        'allowed' => $allowed_extensions,
        'received' => $file_extension
    ]);
    exit;
}

// 업로드 디렉토리 생성
$base_path = dirname(dirname(__DIR__));
$upload_dir = $base_path . '/uploads/editor/test/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        http_response_code(500);
        echo json_encode(['error' => '업로드 디렉토리 생성 실패']);
        exit;
    }
}

// 파일명 생성 (중복 방지)
$new_filename = 'test_' . uniqid() . '.' . $file_extension;
$upload_path = $upload_dir . $new_filename;

// 파일 업로드
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    $file_url = '/uploads/editor/test/' . $new_filename;

    error_log("TEST: Image uploaded successfully: " . $file_url);

    // 다양한 에디터 호환 형식으로 응답
    echo json_encode([
        'success' => true,
        'location' => $file_url,
        'url' => $file_url,
        'uploaded' => 1,
        'fileName' => $new_filename,
        'fileType' => 'image',
        'fileExtension' => $file_extension,
        'fileSize' => $file['size'],
        'message' => '테스트 이미지가 성공적으로 업로드되었습니다.',
        'debug' => [
            'originalName' => $file['name'],
            'uploadPath' => $upload_path,
            'fileField' => $fileField
        ]
    ]);
} else {
    error_log("TEST: File upload failed. Source: " . $file['tmp_name'] . ", Destination: " . $upload_path);
    http_response_code(500);
    echo json_encode([
        'error' => '파일 업로드에 실패했습니다.',
        'debug' => [
            'sourcePath' => $file['tmp_name'],
            'destinationPath' => $upload_path,
            'dirExists' => is_dir($upload_dir),
            'dirWritable' => is_writable($upload_dir)
        ]
    ]);
}
?>