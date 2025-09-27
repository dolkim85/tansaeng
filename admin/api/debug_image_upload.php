<?php
// PHP 업로드 설정 조정 (런타임)
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '12M');
ini_set('max_file_uploads', '20');
ini_set('max_execution_time', '120');
ini_set('memory_limit', '256M');

// 디버그용 이미지 업로드 API (인증 간소화)
header('Content-Type: application/json');

// CORS 헤더
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 모든 요청 정보를 로그에 기록
error_log("=== DEBUG IMAGE UPLOAD START ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Content Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
error_log("Content Length: " . ($_SERVER['CONTENT_LENGTH'] ?? 'not set'));
error_log("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'not set'));
error_log("Referer: " . ($_SERVER['HTTP_REFERER'] ?? 'not set'));
error_log("Files received: " . print_r($_FILES, true));
error_log("POST data: " . print_r($_POST, true));
error_log("GET data: " . print_r($_GET, true));

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    error_log("OPTIONS request - exiting");
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'error' => 'POST 메소드만 허용됩니다.',
        'method' => $_SERVER['REQUEST_METHOD']
    ]);
    exit;
}

// 간소화된 인증 (세션 에러 방지)
$base_path = dirname(dirname(__DIR__));
$authenticated = false;

try {
    // 세션 시작 전에 출력 버퍼링
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 간단한 인증 체크 (관리자 세션이 있는지만 확인)
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_level']) && $_SESSION['user_level'] >= 9) {
        $authenticated = true;
        error_log("User authenticated: ID=" . $_SESSION['user_id'] . ", Level=" . $_SESSION['user_level']);
    } else {
        error_log("User not authenticated. Session: " . print_r($_SESSION ?? [], true));
    }
} catch (Exception $e) {
    error_log("Authentication error: " . $e->getMessage());
}

// 인증 실패시에도 계속 진행 (디버그 목적)
if (!$authenticated) {
    error_log("WARNING: Proceeding without authentication for debug purposes");
}

// 파일 필드 확인
$fileField = null;
error_log("Checking file fields...");

if (isset($_FILES['upload'])) {
    $fileField = 'upload';
    error_log("Found 'upload' field");
} elseif (isset($_FILES['file'])) {
    $fileField = 'file';
    error_log("Found 'file' field");
} elseif (isset($_FILES['image'])) {
    $fileField = 'image';
    error_log("Found 'image' field");
} else {
    error_log("No file field found. Available fields: " . implode(', ', array_keys($_FILES)));
    http_response_code(400);
    echo json_encode([
        'error' => '업로드할 파일이 없습니다.',
        'message' => '업로드할 파일이 없습니다.',
        'debug' => [
            'availableFields' => array_keys($_FILES),
            'expectedFields' => ['upload', 'file', 'image'],
            'postData' => $_POST,
            'contentType' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
            'requestMethod' => $_SERVER['REQUEST_METHOD'],
            'authenticated' => $authenticated
        ]
    ]);
    exit;
}

$file = $_FILES[$fileField];
error_log("Selected file field: $fileField");
error_log("File details: " . print_r($file, true));

// 파일 검증
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => '파일이 너무 큽니다 (php.ini 설정)',
        UPLOAD_ERR_FORM_SIZE => '파일이 너무 큽니다 (폼 설정)',
        UPLOAD_ERR_PARTIAL => '파일이 부분적으로만 업로드됨',
        UPLOAD_ERR_NO_FILE => '파일이 업로드되지 않음',
        UPLOAD_ERR_NO_TMP_DIR => '임시 디렉토리 없음',
        UPLOAD_ERR_CANT_WRITE => '디스크 쓰기 실패',
        UPLOAD_ERR_EXTENSION => 'PHP 확장에 의해 중단됨'
    ];

    $errorMsg = $errorMessages[$file['error']] ?? '알 수 없는 업로드 오류';
    error_log("File upload error: " . $file['error'] . " - " . $errorMsg);

    http_response_code(400);
    echo json_encode([
        'error' => $errorMsg,
        'message' => $errorMsg,
        'debug' => [
            'errorCode' => $file['error'],
            'fileSize' => $file['size'] ?? 'unknown',
            'fileName' => $file['name'] ?? 'unknown',
            'authenticated' => $authenticated,
            'phpSettings' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_file_uploads' => ini_get('max_file_uploads'),
                'file_uploads' => ini_get('file_uploads'),
                'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: 'default'
            ]
        ]
    ]);
    exit;
}

// 파일 크기 확인 (10MB 제한)
if ($file['size'] > 10 * 1024 * 1024) {
    error_log("File too large: " . $file['size'] . " bytes");
    http_response_code(400);
    echo json_encode([
        'error' => '파일 크기가 너무 큽니다. (최대 10MB)',
        'message' => '파일 크기가 너무 큽니다. (최대 10MB)',
        'debug' => [
            'fileSize' => $file['size'],
            'maxSize' => 10 * 1024 * 1024,
            'authenticated' => $authenticated
        ]
    ]);
    exit;
}

// 파일 타입 확인
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    error_log("Invalid file extension: " . $file_extension);
    http_response_code(400);
    echo json_encode([
        'error' => '지원하지 않는 파일 형식입니다.',
        'message' => '지원하지 않는 파일 형식입니다.',
        'debug' => [
            'extension' => $file_extension,
            'allowed' => $allowed_extensions,
            'fileName' => $file['name'],
            'authenticated' => $authenticated
        ]
    ]);
    exit;
}

// 업로드 디렉토리 생성
$upload_dir = $base_path . '/uploads/editor/debug/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        error_log("Failed to create upload directory: " . $upload_dir);
        http_response_code(500);
        echo json_encode([
            'error' => '업로드 디렉토리 생성 실패',
            'debug' => [
                'directory' => $upload_dir,
                'authenticated' => $authenticated
            ]
        ]);
        exit;
    }
}

// 파일명 생성
$new_filename = 'debug_' . uniqid() . '.' . $file_extension;
$upload_path = $upload_dir . $new_filename;

error_log("Attempting to move file from " . $file['tmp_name'] . " to " . $upload_path);

// 파일 업로드
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    $file_url = '/uploads/editor/debug/' . $new_filename;
    error_log("File uploaded successfully: " . $file_url);

    echo json_encode([
        'success' => true,
        'location' => $file_url,
        'url' => $file_url,
        'uploaded' => 1,
        'fileName' => $new_filename,
        'fileType' => 'image',
        'fileExtension' => $file_extension,
        'fileSize' => $file['size'],
        'message' => '디버그 이미지가 성공적으로 업로드되었습니다.',
        'debug' => [
            'originalName' => $file['name'],
            'uploadPath' => $upload_path,
            'fileField' => $fileField,
            'authenticated' => $authenticated,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    error_log("File upload failed. Source: " . $file['tmp_name'] . ", Destination: " . $upload_path);
    http_response_code(500);
    echo json_encode([
        'error' => '파일 업로드에 실패했습니다.',
        'debug' => [
            'sourcePath' => $file['tmp_name'],
            'destinationPath' => $upload_path,
            'sourceExists' => file_exists($file['tmp_name']),
            'dirExists' => is_dir($upload_dir),
            'dirWritable' => is_writable($upload_dir),
            'authenticated' => $authenticated
        ]
    ]);
}

error_log("=== DEBUG IMAGE UPLOAD END ===");
?>