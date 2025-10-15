<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;
$search = $_GET['search'] ?? '';

try {
    $pdo = Database::getInstance()->getConnection();
    
    // categories 테이블을 먼저 생성
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        status VARCHAR(20) DEFAULT 'active',
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // products 테이블 생성 (외래키 제약조건 제거)
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        detailed_description TEXT,
        price DECIMAL(10,2) NOT NULL,
        category_id INT,
        stock_quantity INT DEFAULT 0,
        weight VARCHAR(50),
        dimensions VARCHAR(100),
        image_url VARCHAR(500),
        status VARCHAR(20) DEFAULT 'active',
        is_featured BOOLEAN DEFAULT FALSE,
        discount_percentage INT DEFAULT 0,
        rating_score DECIMAL(2,1) DEFAULT 4.5,
        review_count INT DEFAULT 0,
        delivery_info VARCHAR(100) DEFAULT '무료배송',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // 기존 products 테이블에 필요한 컬럼들 추가 (기존 테이블이 있는 경우)
    // stock_quantity 컬럼과 stock 컬럼 동기화
    try {
        $result = $pdo->query("DESCRIBE products");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $column_names = array_column($columns, 'Field');

        // stock과 stock_quantity 컬럼 모두 존재하는 경우 동기화
        if (in_array('stock', $column_names) && in_array('stock_quantity', $column_names)) {
            $pdo->exec("UPDATE products SET stock_quantity = stock WHERE stock_quantity = 0 OR stock_quantity IS NULL");
        }
        // stock_quantity만 없는 경우 추가
        elseif (in_array('stock', $column_names) && !in_array('stock_quantity', $column_names)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN stock_quantity INT DEFAULT 0");
            $pdo->exec("UPDATE products SET stock_quantity = stock");
        }
        // stock만 없는 경우 추가
        elseif (!in_array('stock', $column_names) && in_array('stock_quantity', $column_names)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN stock INT DEFAULT 0");
            $pdo->exec("UPDATE products SET stock = stock_quantity");
        }
    } catch (Exception $e) {
        // 오류 무시
    }

    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN weight VARCHAR(50)");
    } catch (Exception $e) {
        // 이미 존재하는 경우 무시
    }
    
    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN dimensions VARCHAR(100)");
    } catch (Exception $e) {
        // 이미 존재하는 경우 무시
    }
    
    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN image_url VARCHAR(500)");
    } catch (Exception $e) {
        // 이미 존재하는 경우 무시
    }
    
    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN is_featured BOOLEAN DEFAULT FALSE");
    } catch (Exception $e) {
        // 이미 존재하는 경우 무시
    }

    // 상품카드에 필요한 추가 컬럼들
    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN discount_percentage INT DEFAULT 0");
    } catch (Exception $e) {
        // 이미 존재하는 경우 무시
    }

    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN rating_score DECIMAL(2,1) DEFAULT 4.5");
    } catch (Exception $e) {
        // 이미 존재하는 경우 무시
    }

    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN review_count INT DEFAULT 0");
    } catch (Exception $e) {
        // 이미 존재하는 경우 무시
    }

    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN delivery_info VARCHAR(100) DEFAULT '무료배송'");
    } catch (Exception $e) {
        // 이미 존재하는 경우 무시
    }

    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN detailed_description TEXT");
    } catch (Exception $e) {
        // 이미 존재하는 경우 무시
    }

    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    } catch (Exception $e) {
        // 이미 존재하는 경우 무시
    }

    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    } catch (Exception $e) {
        // 이미 존재하는 경우 무시
    }
    
    // 기존 categories 테이블 사용 (categories 대신)

    // 기본 카테고리 삽입 (없으면)
    $pdo->exec("INSERT IGNORE INTO categories (id, name, description, status, sort_order) VALUES
                (1, '배지', '코코피트, 펄라이트 등 재배용 배지', 'active', 1),
                (2, '농업용품', '농업에 필요한 각종 도구 및 용품', 'active', 2),
                (3, '양액/비료', '식물 성장에 필요한 영양액', 'active', 3),
                (4, '씨앗/종자', '고품질 씨앗과 종자', 'active', 4)");

    // 샘플 상품 데이터는 삭제됨 - 실제 상품은 관리자가 직접 추가해야 함
    
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }
    
    $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
    
    $count_sql = "SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_products = $stmt->fetchColumn();

    $total_pages = ceil($total_products / $per_page);

    // Fix LIMIT/OFFSET binding issue by using direct integer values
    $per_page = (int) $per_page;
    $offset = (int) $offset;
    $sql = "SELECT p.*, c.name as category_name FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            $where_clause ORDER BY p.id DESC LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // 카테고리 정보 가져오기 (edit.php에서 사용)
    $categories_stmt = $pdo->query("SELECT * FROM categories ORDER BY sort_order, name");
    $categories = $categories_stmt->fetchAll();

    // 디버깅 로그
    error_log("Products query executed successfully. Found " . count($products) . " products");
    error_log("Categories found: " . count($categories));
    if (empty($products)) {
        error_log("No products found. SQL: $sql");
        error_log("Params: " . print_r($params, true));

        // 테이블 존재 확인
        $tables_check = $pdo->query("SHOW TABLES LIKE 'products'")->fetchColumn();
        error_log("Products table exists: " . ($tables_check ? 'YES' : 'NO'));

        // 데이터 개수 확인
        $count_check = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        error_log("Total products in table: " . $count_check);
    }
    
} catch (Exception $e) {
    $error = "상품 정보를 불러오는데 실패했습니다: " . $e->getMessage();
    $products = [];
    $total_products = 0;
    $total_pages = 0;
    error_log("Products index error: " . $e->getMessage());
}

// 상품 상태 변경 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $product_id = $_POST['product_id'] ?? 0;
    
    try {
        switch ($action) {
            case 'toggle_status':
                $new_status = $_POST['new_status'];
                $sql = "UPDATE products SET status = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$new_status, $product_id]);
                $success = "상품 상태가 변경되었습니다.";
                break;
                
            case 'delete':
                $sql = "DELETE FROM products WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$product_id]);
                $success = "상품이 삭제되었습니다.";
                break;
        }
        
        header("Location: ?success=1");
        exit;
        
    } catch (Exception $e) {
        $error = "작업 처리에 실패했습니다.";
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>상품 관리 - 탄생 관리자</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-content">
                <div class="page-header">
                    <div class="page-title">
                        <h1>상품 관리</h1>
                        <p>등록된 상품을 관리하고 재고를 확인할 수 있습니다</p>
                    </div>
                    <div class="page-actions">
                        <a href="add.php" class="btn btn-primary">새 상품 추가</a>
                        <a href="categories.php" class="btn btn-secondary">카테고리 관리</a>
                    </div>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">작업이 성공적으로 완료되었습니다.</div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="admin-card">
                    <div class="card-header">
                        <div class="search-form">
                            <form method="get" class="admin-search">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="상품명, 설명, 카테고리로 검색" class="form-input">
                                <button type="submit" class="btn btn-primary">검색</button>
                                <a href="?" class="btn btn-outline">전체</a>
                            </form>
                        </div>
                        <div class="table-info">
                            <span>총 <?= number_format($total_products) ?>개 상품</span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if (!empty($products)): ?>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th width="60">ID</th>
                                            <th width="80">이미지</th>
                                            <th>상품명</th>
                                            <th width="100">카테고리</th>
                                            <th width="100">가격</th>
                                            <th width="80">재고</th>
                                            <th width="80">상태</th>
                                            <th width="120">등록일</th>
                                            <th width="120">관리</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td><?= $product['id'] ?></td>
                                                <td>
                                                    <?php if ($product['image_url']): ?>
                                                        <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                                             alt="<?= htmlspecialchars($product['name']) ?>"
                                                             class="product-thumb">
                                                    <?php else: ?>
                                                        <div class="no-image">📦</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="product-info">
                                                        <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                                                        <?php if ($product['description']): ?>
                                                            <div class="product-desc"><?= htmlspecialchars(mb_strimwidth($product['description'], 0, 60, '...')) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="category-badge"><?= htmlspecialchars($product['category_name'] ?? '미분류') ?></span>
                                                </td>
                                                <td class="price-cell">
                                                    <?= number_format($product['price']) ?>원
                                                </td>
                                                <td class="stock-cell">
                                                    <span class="stock-badge <?= ($product['stock_quantity'] ?? 0) <= 5 ? 'low-stock' : '' ?>">
                                                        <?= number_format($product['stock_quantity'] ?? 0) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?= $product['status'] ?>">
                                                        <?php
                                                        switch($product['status']) {
                                                            case 'active': echo '판매중'; break;
                                                            case 'inactive': echo '미판매'; break;
                                                            case 'out_of_stock': echo '품절'; break;
                                                            default: echo $product['status'];
                                                        }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    if (!empty($product['created_at'])) {
                                                        echo date('m-d H:i', strtotime($product['created_at']));
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="edit.php?id=<?= $product['id'] ?>" 
                                                           class="btn btn-sm btn-secondary" title="수정">✏️</a>
                                                        <button onclick="toggleStatus(<?= $product['id'] ?>, '<?= $product['status'] ?>')"
                                                                class="btn btn-sm btn-warning" title="상태변경">🔄</button>
                                                        <button onclick="deleteProduct(<?= $product['id'] ?>)"
                                                                class="btn btn-sm btn-danger" title="삭제">🗑️</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if ($total_pages > 1): ?>
                                <div class="pagination-wrapper">
                                    <div class="pagination">
                                        <?php if ($page > 1): ?>
                                            <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>" 
                                               class="pagination-link">이전</a>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page-5); $i <= min($total_pages, $page+5); $i++): ?>
                                            <?php if ($i == $page): ?>
                                                <span class="pagination-current"><?= $i ?></span>
                                            <?php else: ?>
                                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" 
                                                   class="pagination-link"><?= $i ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>" 
                                               class="pagination-link">다음</a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pagination-info">
                                        총 <?= number_format($total_products) ?>건 중 <?= ($page-1)*$per_page+1 ?>-<?= min($page*$per_page, $total_products) ?>건
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <div class="no-data-icon">📦</div>
                                <div class="no-data-text">
                                    <?php if ($search): ?>
                                        '<?= htmlspecialchars($search) ?>'에 대한 검색 결과가 없습니다.
                                    <?php else: ?>
                                        등록된 상품이 없습니다.
                                    <?php endif; ?>
                                </div>
                                <?php if (!$search): ?>
                                    <div class="no-data-action">
                                        <a href="add.php" class="btn btn-primary">첫 상품 추가하기</a>
                                    </div>
                                <?php else: ?>
                                    <div class="no-data-action">
                                        <a href="?" class="btn btn-secondary">전체 상품 보기</a>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($error)): ?>
                                    <div class="debug-info" style="margin-top: 20px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 12px; color: #666;">
                                        오류 정보: <?= htmlspecialchars($error) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- 상태 변경 모달 -->
    <div id="statusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>상품 상태 변경</h3>
            <p>상품 상태를 변경하시겠습니까?</p>
            <form method="post" id="statusForm">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="product_id" id="statusProductId">
                <div class="form-group">
                    <label>새 상태:</label>
                    <select name="new_status" id="newStatus" class="form-input">
                        <option value="active">판매중</option>
                        <option value="inactive">미판매</option>
                        <option value="out_of_stock">품절</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeModal('statusModal')" class="btn btn-outline">취소</button>
                    <button type="submit" class="btn btn-primary">변경</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 삭제 확인 모달 -->
    <div id="deleteModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>상품 삭제</h3>
            <p>이 상품을 정말 삭제하시겠습니까?<br><strong>이 작업은 되돌릴 수 없습니다.</strong></p>
            <form method="post" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="product_id" id="deleteProductId">
                <div class="modal-actions">
                    <button type="button" onclick="closeModal('deleteModal')" class="btn btn-outline">취소</button>
                    <button type="submit" class="btn btn-danger">삭제</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/admin.js"></script>
    <script>
        function toggleStatus(productId, currentStatus) {
            document.getElementById('statusProductId').value = productId;
            document.getElementById('newStatus').value = currentStatus === 'active' ? 'inactive' : 'active';
            showModal('statusModal');
        }
        
        function deleteProduct(productId) {
            document.getElementById('deleteProductId').value = productId;
            showModal('deleteModal');
        }
        
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = '';
        }
        
        // 모달 외부 클릭시 닫기
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal(modal.id);
                }
            });
        });
    </script>
    
    <style>
        .product-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .no-image {
            width: 50px;
            height: 50px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            color: #666;
            font-size: 20px;
        }
        
        .product-info {
            min-width: 200px;
        }
        
        .product-name {
            font-weight: 500;
            color: #333;
            margin-bottom: 4px;
        }
        
        .product-desc {
            font-size: 12px;
            color: #666;
            line-height: 1.4;
        }
        
        .category-badge {
            background: #e9ecef;
            color: #495057;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
        }
        
        .price-cell {
            font-weight: bold;
            color: #28a745;
        }
        
        .stock-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .stock-badge.low-stock {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-out_of_stock {
            background: #fff3cd;
            color: #856404;
        }
        
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 400px;
            width: 90%;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .product-thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
            display: block;
        }
        
        .no-image {
            width: 60px;
            height: 60px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            border: 1px solid #ddd;
            color: #666;
            font-size: 24px;
        }
        
        .product-info {
            min-width: 200px;
        }
        
        .product-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .product-desc {
            font-size: 12px;
            color: #666;
            line-height: 1.3;
        }
        
        .category-badge {
            background: #e3f2fd;
            color: #1565c0;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .price-cell {
            font-weight: bold;
            color: #2e7d32;
            text-align: right;
        }
        
        .stock-cell {
            text-align: center;
        }
        
        .product-thumb {
            transition: opacity 0.3s ease;
        }
        
        .product-thumb.error {
            opacity: 0.5;
            filter: grayscale(100%);
        }
    </style>
    
    <script>
        // 이미지 로드 오류 처리
        document.addEventListener('DOMContentLoaded', function() {
            const productImages = document.querySelectorAll('.product-thumb');
            
            productImages.forEach(function(img) {
                img.addEventListener('error', function() {
                    // 이미지 로드 실패시 대체 이미지로 변경
                    this.src = 'https://via.placeholder.com/60x60/f8f9fa/666?text=NO+IMG';
                    this.classList.add('error');
                });
                
                img.addEventListener('load', function() {
                    this.classList.remove('error');
                });
            });
        });
    </script>
</body>
</html>