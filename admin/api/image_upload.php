<?php
// 에디터 이미지 업로드 API (CKEditor & TinyMCE 호환)
header('Content-Type: application/json');

// CORS 헤더
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 관리자 권한 확인
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';

try {
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    
    if (!$currentUser || $currentUser['user_level'] < 9) {
        http_response_code(403);
        echo json_encode(['error' => '관리자 권한이 필요합니다.']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['error' => '인증 실패: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => ['message' => 'POST 메소드만 허용됩니다.']]);
    exit;
}

// 파일 필드 확인 (CKEditor: 'upload', TinyMCE: 'file', 커스텀: 'image')
$fileField = null;
if (isset($_FILES['upload'])) {
    $fileField = 'upload';
} elseif (isset($_FILES['file'])) {
    $fileField = 'file';
} elseif (isset($_FILES['image'])) {
    $fileField = 'image';
} else {
    http_response_code(400);
    echo json_encode(['error' => '업로드할 파일이 없습니다.']);
    exit;
}

$file = $_FILES[$fileField];

// 파일 검증
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => '파일 업로드 오류가 발생했습니다.']]);
    exit;
}

// 파일 크기 확인 (10MB 제한)
if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => '파일 크기가 너무 큽니다. (최대 10MB)']]);
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