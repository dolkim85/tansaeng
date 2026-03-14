<?php
// 관리자 카테고리 CRUD API - 메인페이지와 실시간 연동
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';

$auth = Auth::getInstance();
if (!$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '관리자 권한이 필요합니다']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    switch ($method) {
        case 'GET':
            // 카테고리 목록 조회 (상품 개수 포함)
            $sql = "SELECT c.*, COUNT(p.id) as product_count 
                    FROM product_categories c 
                    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
                    WHERE c.status = 'active'
                    GROUP BY c.id 
                    ORDER BY c.name";
            
            $categories = $db->select($sql);
            
            echo json_encode([
                'success' => true,
                'categories' => $categories
            ]);
            break;
            
        case 'POST':
            // 새 카테고리 추가
            if (empty($input['name'])) {
                throw new Exception('카테고리명은 필수 항목입니다');
            }
            
            // 중복 카테고리명 확인
            $existing = $db->selectOne(
                "SELECT id FROM product_categories WHERE name = ? AND status = 'active'", 
                [$input['name']]
            );
            
            if ($existing) {
                throw new Exception('동일한 이름의 카테고리가 이미 존재합니다');
            }
            
            $data = [
                'name' => $input['name'],
                'description' => $input['description'] ?? '',
                'status' => 'active'
            ];
            
            $categoryId = $db->insert('product_categories', $data);
            
            echo json_encode([
                'success' => true,
                'message' => '카테고리가 성공적으로 등록되었습니다',
                'category_id' => $categoryId
            ]);
            break;
            
        case 'PUT':
            // 카테고리 수정
            if (empty($input['id'])) {
                throw new Exception('카테고리 ID가 필요합니다');
            }
            
            $categoryId = intval($input['id']);
            
            // 기존 카테고리 확인
            $existing = $db->selectOne(
                "SELECT * FROM product_categories WHERE id = ? AND status = 'active'", 
                [$categoryId]
            );
            
            if (!$existing) {
                throw new Exception('카테고리를 찾을 수 없습니다');
            }
            
            // 카테고리명 중복 확인 (자신 제외)
            if (!empty($input['name']) && $input['name'] !== $existing['name']) {
                $duplicate = $db->selectOne(
                    "SELECT id FROM product_categories WHERE name = ? AND id != ? AND status = 'active'",
                    [$input['name'], $categoryId]
                );
                
                if ($duplicate) {
                    throw new Exception('동일한 이름의 카테고리가 이미 존재합니다');
                }
            }
            
            $data = [
                'name' => $input['name'] ?? $existing['name'],
                'description' => $input['description'] ?? $existing['description']
            ];
            
            $db->update('product_categories', $data, 'id = ?', [$categoryId]);
            
            echo json_encode([
                'success' => true,
                'message' => '카테고리 정보가 성공적으로 수정되었습니다'
            ]);
            break;
            
        case 'DELETE':
            // 카테고리 삭제
            $categoryId = intval($_GET['id'] ?? 0);
            if ($categoryId <= 0) {
                throw new Exception('유효한 카테고리 ID가 필요합니다');
            }
            
            // 카테고리 존재 여부 확인
            $existing = $db->selectOne(
                "SELECT * FROM product_categories WHERE id = ? AND status = 'active'", 
                [$categoryId]
            );
            
            if (!$existing) {
                throw new Exception('카테고리를 찾을 수 없습니다');
            }
            
            // 해당 카테고리의 상품이 있는지 확인
            $productCount = $db->selectOne(
                "SELECT COUNT(*) as count FROM products WHERE category_id = ? AND status = 'active'",
                [$categoryId]
            )['count'];
            
            if ($productCount > 0) {
                throw new Exception('해당 카테고리에 등록된 상품이 있어 삭제할 수 없습니다. 먼저 상품을 이동하거나 삭제해주세요.');
            }
            
            // 소프트 삭제 (status를 inactive로 변경)
            $db->update('product_categories', ['status' => 'inactive'], 'id = ?', [$categoryId]);
            
            echo json_encode([
                'success' => true,
                'message' => '카테고리가 성공적으로 삭제되었습니다'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => '지원하지 않는 요청 방식입니다']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>