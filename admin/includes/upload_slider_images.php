<?php
session_start();
header('Content-Type: application/json');

// 관리자 권한 확인
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
          (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 9);

if (!$isAdmin) {
    echo json_encode(['success' => false, 'error' => '관리자 권한이 필요합니다.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST 방식만 허용됩니다.']);
    exit;
}

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

try {
    // post_max_size 초과 감지
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    $postMaxSize = ini_get('post_max_size');
    $postMaxBytes = return_bytes($postMaxSize);

    if ($contentLength > 0 && $contentLength > $postMaxBytes) {
        echo json_encode([
            'success' => false,
            'error' => "전체 파일 크기({$contentLength}바이트)가 서버 제한({$postMaxSize})을 초과했습니다. 파일 수를 줄여서 업로드해 주세요."
        ]);
        exit;
    }

    // $_FILES가 비어있고 CONTENT_LENGTH가 있으면 post_max_size 초과로 판단
    if (empty($_FILES) && $contentLength > 0) {
        echo json_encode([
            'success' => false,
            'error' => "업로드 데이터가 서버 제한을 초과하여 처리할 수 없습니다. 파일 수를 줄여서 다시 시도해 주세요."
        ]);
        exit;
    }

    $uploadDir = __DIR__ . '/../../uploads/media/';

    // 디렉토리가 없으면 생성
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $uploadedUrls = [];
    $errors = [];

    if (isset($_FILES['slider_images']) && is_array($_FILES['slider_images']['name'])) {
        $fileCount = count($_FILES['slider_images']['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            $fileName = $_FILES['slider_images']['name'][$i];
            $fileTmpName = $_FILES['slider_images']['tmp_name'][$i];
            $fileError = $_FILES['slider_images']['error'][$i];
            $fileSize = $_FILES['slider_images']['size'][$i];

            // 파일 업로드 에러 체크
            if ($fileError !== UPLOAD_ERR_OK) {
                $errors[] = "파일 '{$fileName}' 업로드 실패: 에러 코드 {$fileError}";
                continue;
            }

            // 파일 크기 체크 (50MB 제한)
            if ($fileSize > 50 * 1024 * 1024) {
                $errors[] = "파일 '{$fileName}'이 너무 큽니다. (최대 50MB)";
                continue;
            }

            // 파일 확장자 체크
            $fileInfo = pathinfo($fileName);
            $extension = strtolower($fileInfo['extension'] ?? '');
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($extension, $allowedExtensions)) {
                $errors[] = "파일 '{$fileName}'의 확장자가 지원되지 않습니다. (지원: jpg, png, gif, webp)";
                continue;
            }

            // 이미지 파일인지 확인
            $imageInfo = getimagesize($fileTmpName);
            if ($imageInfo === false) {
                $errors[] = "파일 '{$fileName}'이 유효한 이미지가 아닙니다.";
                continue;
            }

            // 새 파일명 생성 (타임스탬프와 랜덤 숫자 추가로 중복 방지)
            $uniqueId = time() . '_' . mt_rand(1000, 9999) . '_' . $i;
            $newFileName = 'media_' . $uniqueId . '.' . $extension;
            $uploadPath = $uploadDir . $newFileName;

            // 파일 이동
            if (move_uploaded_file($fileTmpName, $uploadPath)) {
                $uploadedUrls[] = '/uploads/media/' . $newFileName;
            } else {
                $errors[] = "파일 '{$fileName}' 저장 실패";
            }
        }
    } else {
        echo json_encode(['success' => false, 'error' => '업로드할 파일이 없습니다.']);
        exit;
    }

    if (empty($uploadedUrls)) {
        echo json_encode([
            'success' => false,
            'error' => '업로드된 파일이 없습니다.',
            'details' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'urls' => $uploadedUrls,
            'message' => count($uploadedUrls) . '개의 이미지가 성공적으로 업로드되었습니다.',
            'errors' => $errors // 일부 파일만 실패한 경우를 위해
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => '업로드 중 오류가 발생했습니다: ' . $e->getMessage()
    ]);
}
?>