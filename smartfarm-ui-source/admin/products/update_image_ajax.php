<?php
// AJAX 이미지 업데이트 처리
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST 방식만 허용됩니다.']);
    exit;
}

try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../config/database.php';

    $product_id = intval($_POST['product_id'] ?? 0);
    $cropped_image_data = $_POST['cropped_image_data'] ?? '';

    if (!$product_id || empty($cropped_image_data)) {
        throw new Exception('필수 데이터가 누락되었습니다.');
    }

    $pdo = DatabaseConfig::getConnection();

    // 상품 존재 확인
    $stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        throw new Exception('상품을 찾을 수 없습니다.');
    }

    // Base64 이미지 데이터 처리
    if (preg_match('/^data:image\/(png|jpg|jpeg);base64,/', $cropped_image_data, $matches)) {
        $extension = $matches[1] === 'jpg' ? 'jpeg' : $matches[1];
        $base64_data = preg_replace('/^data:image\/(png|jpg|jpeg);base64,/', '', $cropped_image_data);
        $image_data = base64_decode($base64_data);

        if ($image_data === false) {
            throw new Exception('이미지 데이터가 올바르지 않습니다.');
        }

        // 이미지 파일 저장
        $upload_dir = realpath(__DIR__ . '/../../uploads/products/') . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $new_filename = uniqid('product_cropped_') . '.jpg';
        $upload_path = $upload_dir . $new_filename;

        if (file_put_contents($upload_path, $image_data)) {
            $image_url = '/uploads/products/' . $new_filename;

            // 데이터베이스 업데이트
            $stmt = $pdo->prepare("UPDATE products SET image_url = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateResult = $stmt->execute([$image_url, $product_id]);
            $affectedRows = $stmt->rowCount();

            // 업데이트된 상품 정보 가져오기
            $stmt = $pdo->prepare("
                SELECT p.*, c.name as category_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.id = ?
            ");
            $stmt->execute([$product_id]);
            $updated_product = $stmt->fetch();

            // 파일 크기 확인
            $fileSize = strlen($image_data);
            $fileSizeMB = round($fileSize / 1024 / 1024, 2);

            echo json_encode([
                'success' => true,
                'message' => '이미지가 성공적으로 업데이트되었습니다!',
                'image_url' => $image_url,
                'product' => $updated_product,
                'timestamp' => time(),
                'debug' => [
                    'file_saved' => file_exists($upload_path),
                    'file_size' => $fileSizeMB . 'MB',
                    'file_path' => $upload_path,
                    'db_update_result' => $updateResult,
                    'affected_rows' => $affectedRows,
                    'old_image_url' => $product['image_url'] ?? 'none',
                    'new_image_url' => $image_url,
                    'update_time' => date('Y-m-d H:i:s')
                ]
            ]);

        } else {
            throw new Exception('이미지 파일 저장에 실패했습니다.');
        }
    } else {
        throw new Exception('올바르지 않은 이미지 형식입니다.');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>