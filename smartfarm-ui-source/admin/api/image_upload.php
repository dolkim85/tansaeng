<?php
// PHP 업로드 설정 조정 (런타임)
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '12M');
ini_set('max_file_uploads', '20');
ini_set('max_execution_time', '120');
ini_set('memory_limit', '256M');

// 에디터 이미지 업로드 API (CKEditor & TinyMCE 호환)
header('Content-Type: application/json');

// CORS 헤더
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 디버그 로그 시작
error_log("=== IMAGE UPLOAD DEBUG START ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
error_log("Content Length: " . ($_SERVER['CONTENT_LENGTH'] ?? 'not set'));
error_log("Files received: " . print_r($_FILES, true));
error_log("POST data: " . print_r($_POST, true));

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    error_log("OPTIONS request - exiting");
    exit(0);
}

// 관리자 권한 확인
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';

try {
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();

    // 디버그 로그
    error_log("Image upload attempt - User: " . ($currentUser ? json_encode($currentUser) : 'null'));

    if (!$currentUser || $currentUser['user_level'] < 9) {
        http_response_code(403);
        echo json_encode([
            'error' => '관리자 권한이 필요합니다.',
            'message' => '관리자 권한이 필요합니다.',
            'debug' => [
                'userLevel' => $currentUser ? $currentUser['user_level'] : null,
                'required' => 9
            ]
        ]);
        exit;
    }
} catch (Exception $e) {
    error_log("Image upload auth error: " . $e->getMessage());
    http_response_code(403);
    echo json_encode([
        'error' => '인증 실패: ' . $e->getMessage(),
        'message' => '인증 실패: ' . $e->getMessage()
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => ['message' => 'POST 메소드만 허용됩니다.']]);
    exit;
}

// 파일 필드 확인 (CKEditor: 'upload', TinyMCE: 'file', 커스텀: 'image')
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
            'contentType' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
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
            'phpSettings' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_file_uploads' => ini_get('max_file_uploads')
            ]
        ]
    ]);
    exit;
}

// 파일 크기 확인 (PHP 설정에 따라 동적 조정)
function return_bytes($val) {
    if (empty($val)) return 0;
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int) $val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

$maxUploadSize = return_bytes(ini_get('upload_max_filesize'));
$maxPostSize = return_bytes(ini_get('post_max_size'));
$maxSize = min($maxUploadSize, $maxPostSize, 10 * 1024 * 1024); // 최대 10MB

error_log("Max allowed size: " . $maxSize . " bytes (upload: " . $maxUploadSize . ", post: " . $maxPostSize . ")");

if ($file['size'] > $maxSize) {
    error_log("File too large: " . $file['size'] . " bytes (max: " . $maxSize . ")");
    http_response_code(400);
    echo json_encode([
        'error' => '파일 크기가 너무 큽니다. (최대 ' . round($maxSize/1024/1024, 1) . 'MB)',
        'message' => '파일 크기가 너무 큽니다. (최대 ' . round($maxSize/1024/1024, 1) . 'MB)',
        'debug' => [
            'fileSize' => $file['size'],
            'maxSize' => $maxSize,
            'maxUploadSize' => $maxUploadSize,
            'maxPostSize' => $maxPostSize,
            'phpSettings' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_file_uploads' => ini_get('max_file_uploads')
            ]
        ]
    ]);
    exit;
}

// 파일 타입 및 확장자 확인
$image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$video_extensions = ['mp4', 'webm', 'ogg', 'avi', 'mov'];
$audio_extensions = ['mp3', 'wav', 'ogg', 'aac', 'm4a'];
$allowed_extensions = array_merge($image_extensions, $video_extensions, $audio_extensions);
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => '지원하지 않는 파일 형식입니다. (이미지: JPG, PNG, GIF, WebP / 비디오: MP4, WebM, OGG, AVI, MOV / 오디오: MP3, WAV, OGG, AAC, M4A)']]);
    exit;
}

// 업로드 디렉토리 생성
$upload_dir = $base_path . '/uploads/editor/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// 파일명 생성 (중복 방지)
$new_filename = uniqid('editor_') . '.' . $file_extension;
$upload_path = $upload_dir . $new_filename;

// 파일 업로드
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    $file_url = '/uploads/editor/' . $new_filename;
    
    // 파일 타입 감지
    $file_type = 'image';
    if (in_array($file_extension, $video_extensions)) {
        $file_type = 'video';
    } elseif (in_array($file_extension, $audio_extensions)) {
        $file_type = 'audio';
    }
    
    // 다양한 에디터 호환 형식으로 응답
    echo json_encode([
        'success' => true,        // 커스텀 에디터용
        'location' => $file_url,  // TinyMCE용
        'url' => $file_url,       // CKEditor용
        'uploaded' => 1,          // CKEditor용
        'fileName' => $new_filename,
        'fileType' => $file_type, // 미디어 타입 정보
        'fileExtension' => $file_extension,
        'message' => $file_type === 'image' ? '이미지가 성공적으로 업로드되었습니다.' : 
                    ($file_type === 'video' ? '비디오가 성공적으로 업로드되었습니다.' : '오디오가 성공적으로 업로드되었습니다.')
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => '파일 업로드에 실패했습니다.']);
}
?>