<?php
// 에디터 미디어(비디오/오디오) 업로드 API
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
    echo json_encode(['error' => 'POST 메소드만 허용됩니다.']);
    exit;
}

// 파일 및 타입 확인
if (!isset($_FILES['media']) || !isset($_POST['type'])) {
    http_response_code(400);
    echo json_encode(['error' => '업로드할 미디어 파일과 타입이 필요합니다.']);
    exit;
}

$file = $_FILES['media'];
$type = $_POST['type']; // 'video' 또는 'audio'

// 파일 검증
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => '파일 업로드 오류가 발생했습니다.']);
    exit;
}

// 파일 크기 확인 (100MB 제한)
$maxSize = 100 * 1024 * 1024; // 100MB
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => '파일 크기가 너무 큽니다. (최대 100MB)']);
    exit;
}

// 파일 타입 및 확장자 확인
$video_extensions = ['mp4', 'webm', 'ogg', 'avi', 'mov', 'mkv'];
$audio_extensions = ['mp3', 'wav', 'ogg', 'aac', 'm4a', 'flac'];

$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if ($type === 'video' && !in_array($file_extension, $video_extensions)) {
    http_response_code(400);
    echo json_encode(['error' => '지원하지 않는 비디오 형식입니다. (MP4, WebM, OGG, AVI, MOV, MKV)']);
    exit;
}

if ($type === 'audio' && !in_array($file_extension, $audio_extensions)) {
    http_response_code(400);
    echo json_encode(['error' => '지원하지 않는 오디오 형식입니다. (MP3, WAV, OGG, AAC, M4A, FLAC)']);
    exit;
}

// 업로드 디렉토리 생성
$upload_dir = $base_path . '/uploads/editor/media/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// 파일명 생성 (중복 방지)
$new_filename = uniqid($type . '_') . '.' . $file_extension;
$upload_path = $upload_dir . $new_filename;

// 파일 업로드
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    $file_url = '/uploads/editor/media/' . $new_filename;

    // 파일 정보 가져오기
    $fileInfo = [
        'size' => $file['size'],
        'originalName' => $file['name'],
        'extension' => $file_extension,
        'type' => $type
    ];

    // 비디오 파일인 경우 썸네일 생성 시도 (ffmpeg가 설치된 경우)
    $thumbnail_url = null;
    if ($type === 'video' && function_exists('exec')) {
        $thumbnail_filename = pathinfo($new_filename, PATHINFO_FILENAME) . '_thumb.jpg';
        $thumbnail_path = $upload_dir . $thumbnail_filename;

        // ffmpeg로 썸네일 생성 시도
        $cmd = "ffmpeg -i " . escapeshellarg($upload_path) . " -ss 00:00:01 -vframes 1 -y " . escapeshellarg($thumbnail_path) . " 2>/dev/null";
        exec($cmd, $output, $return_var);

        if ($return_var === 0 && file_exists($thumbnail_path)) {
            $thumbnail_url = '/uploads/editor/media/' . $thumbnail_filename;
        }
    }

    echo json_encode([
        'success' => true,
        'url' => $file_url,
        'fileName' => $new_filename,
        'fileType' => $type,
        'fileExtension' => $file_extension,
        'fileSize' => $file['size'],
        'thumbnailUrl' => $thumbnail_url,
        'message' => $type === 'video' ? '비디오가 성공적으로 업로드되었습니다.' : '오디오가 성공적으로 업로드되었습니다.'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => '파일 업로드에 실패했습니다.']);
}
?>