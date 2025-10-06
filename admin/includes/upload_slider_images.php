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

try {
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

            // 파일 크기 체크 (20MB 제한)
            if ($fileSize > 20 * 1024 * 1024) {
                $errors[] = "파일 '{$fileName}'이 너무 큽니다. (최대 20MB)";
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

            // 새 파일명 생성
            $newFileName = 'slider_' . time() . '_' . $i . '.' . $extension;
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