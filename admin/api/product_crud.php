<?php
// 관리자 상품 CRUD API - 메인페이지와 실시간 연동
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
            // 상품 목록 조회
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = 20;
            $offset = ($page - 1) * $limit;
            $search = trim($_GET['search'] ?? '');
            $categoryId = intval($_GET['category'] ?? 0);
            
            $whereConditions = [];
            $params = [];
            
            if (!empty($search)) {
                $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            if ($categoryId > 0) {
                $whereConditions[] = "p.category_id = ?";
                $params[] = $categoryId;
            }
            
            $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);
            
            $sql = "SELECT p.*, c.name as category_name 
                    FROM products p 
                    LEFT JOIN product_categories c ON p.category_id = c.id 
                    $whereClause 
                    ORDER BY p.created_at DESC 
                    LIMIT $limit OFFSET $offset";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll();
            
            // 총 개수 조회
            $countSql = "SELECT COUNT(*) FROM products p $whereClause";
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'products' => $products,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($total / $limit),
                    'total_items' => $total,
                    'per_page' => $limit
                ]
            ]);
            break;
            
        case 'POST':
            // 새 상품 추가
            $requiredFields = ['name', 'description', 'price', 'category_id'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    throw new Exception("$field 는 필수 항목입니다");
                }
            }
            
            $data = [
                'name' => $input['name'],
                'description' => $input['description'],
                'price' => floatval($input['price']),
                'category_id' => intval($input['category_id']),
                'stock' => intval($input['stock'] ?? 0),
                'weight' => $input['weight'] ?? null,
                'dimensions' => $input['dimensions'] ?? null,
                'image_url' => $input['image_url'] ?? null,
                'status' => $input['status'] ?? 'active',
                'is_featured' => intval($input['is_featured'] ?? 0)
            ];
            
            $productId = $db->insert('products', $data);
            
            echo json_encode([
                'success' => true, 
                'message' => '상품이 성공적으로 등록되었습니다',
                'product_id' => $productId
            ]);
            break;
            
        case 'PUT':
            // 상품 수정
            if (empty($input['id'])) {
                throw new Exception('상품 ID가 필요합니다');
            }
            
            $productId = intval($input['id']);
            
            // 기존 상품 확인
            $existing = $db->selectOne("SELECT * FROM products WHERE id = ?", [$productId]);
            if (!$existing) {
                throw new Exception('상품을 찾을 수 없습니다');
            }
            
            $data = [
                'name' => $input['name'] ?? $existing['name'],
                'description' => $input['description'] ?? $existing['description'],
                'price' => isset($input['price']) ? floatval($input['price']) : $existing['price'],
                'category_id' => isset($input['category_id']) ? intval($input['category_id']) : $existing['category_id'],
                'stock' => isset($input['stock']) ? intval($input['stock']) : $existing['stock'],
                'weight' => $input['weight'] ?? $existing['weight'],
                'dimensions' => $input['dimensions'] ?? $existing['dimensions'],
                'image_url' => $input['image_url'] ?? $existing['image_url'],
                'status' => $input['status'] ?? $existing['status'],
                'is_featured' => isset($input['is_featured']) ? intval($input['is_featured']) : $existing['is_featured']
            ];
            
            $db->update('products', $data, 'id = ?', [$productId]);
            
            echo json_encode([
                'success' => true,
                'message' => '상품 정보가 성공적으로 수정되었습니다'
            ]);
            break;
            
        case 'DELETE':
            // 상품 삭제 (소프트 삭제)
            $productId = intval($_GET['id'] ?? 0);
            if ($productId <= 0) {
                throw new Exception('유효한 상품 ID가 필요합니다');
            }
            
            // 상품 존재 여부 확인
            $existing = $db->selectOne("SELECT id FROM products WHERE id = ?", [$productId]);
            if (!$existing) {
                throw new Exception('상품을 찾을 수 없습니다');
            }
            
            // 소프트 삭제 (status를 deleted로 변경)
            $db->update('products', ['status' => 'deleted'], 'id = ?', [$productId]);
            
            echo json_encode([
                'success' => true,
                'message' => '상품이 성공적으로 삭제되었습니다'
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