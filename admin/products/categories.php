<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$success = '';
$error = '';

// Handle category operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo = Database::getInstance()->getConnection();
        
        // 카테고리 테이블 생성 (없으면)
        $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            display_order INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // 기본 카테고리 삽입
        $pdo->exec("INSERT IGNORE INTO categories (id, name, description) VALUES 
                    (1, '배지', '코코피트, 펄라이트 등 재배용 배지'),
                    (2, '농업용품', '농업에 필요한 각종 도구 및 용품'),
                    (3, '양액', '식물 성장에 필요한 영양액'),
                    (4, '기타', '기타 상품')");
        
        switch ($action) {
            case 'add':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name)) {
                    $error = '카테고리명을 입력해주세요.';
                } else {
                    $sql = "INSERT INTO categories (name, description) VALUES (?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$name, $description]);
                    $success = '카테고리가 추가되었습니다.';
                }
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name)) {
                    $error = '카테고리명을 입력해주세요.';
                } else {
                    $sql = "UPDATE categories SET name = ?, description = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$name, $description, $id]);
                    $success = '카테고리가 수정되었습니다.';
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                
                // Check if category is used by products
                $sql = "SELECT COUNT(*) FROM products WHERE category_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                $product_count = $stmt->fetchColumn();
                
                if ($product_count > 0) {
                    $error = "이 카테고리를 사용하는 상품이 {$product_count}개 있어 삭제할 수 없습니다.";
                } else {
                    $sql = "DELETE FROM categories WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$id]);
                    $success = '카테고리가 삭제되었습니다.';
                }
                break;
        }
        
    } catch (Exception $e) {
        $error = '작업 처리에 실패했습니다: ' . $e->getMessage();
    }
}

// Get categories
$categories = [];
try {
    $pdo = Database::getInstance()->getConnection();
    $sql = "SELECT pc.*, COUNT(p.id) as product_count 
            FROM categories pc 
            LEFT JOIN products p ON pc.id = p.category_id 
            GROUP BY pc.id 
            ORDER BY pc.name";
    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = '카테고리 목록을 불러올 수 없습니다.';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>상품 카테고리 관리 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-content">
                <div class="page-header">
                    <div class="page-title">
                        <h1>📂 카테고리 관리</h1>
                        <p>상품 카테고리를 관리합니다</p>
                    </div>
                    <div class="page-actions">
                        <a href="index.php" class="btn btn-outline">상품 목록</a>
                        <button onclick="showAddModal()" class="btn btn-primary">새 카테고리 추가</button>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <strong>성공:</strong> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <strong>오류:</strong> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="admin-card">
                    <div class="card-header">
                        <h3>카테고리 목록</h3>
                        <div class="table-info">
                            <span>총 <?= count($categories) ?>개 카테고리</span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if (!empty($categories)): ?>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th width="60">ID</th>
                                            <th>카테고리명</th>
                                            <th>설명</th>
                                            <th width="100">상품 수</th>
                                            <th width="120">등록일</th>
                                            <th width="120">관리</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td><?= $category['id'] ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($category['name']) ?></strong>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($category['description'] ?? '-') ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge badge-info"><?= $category['product_count'] ?></span>
                                                </td>
                                                <td><?= date('m-d H:i', strtotime($category['created_at'])) ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button onclick="editCategory(<?= $category['id'] ?>, '<?= htmlspecialchars($category['name']) ?>', '<?= htmlspecialchars($category['description'] ?? '') ?>')"
                                                                class="btn btn-sm btn-secondary" title="수정">✏️</button>
                                                        <?php if ($category['product_count'] == 0): ?>
                                                            <button onclick="deleteCategory(<?= $category['id'] ?>, '<?= htmlspecialchars($category['name']) ?>')"
                                                                    class="btn btn-sm btn-danger" title="삭제">🗑️</button>
                                                        <?php else: ?>
                                                            <span class="btn btn-sm btn-disabled" title="사용 중인 카테고리는 삭제할 수 없습니다">🔒</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <div class="no-data-icon">📂</div>
                                <div class="no-data-text">등록된 카테고리가 없습니다.</div>
                                <div class="no-data-action">
                                    <button onclick="showAddModal()" class="btn btn-primary">첫 카테고리 추가하기</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- 카테고리 추가 모달 -->
    <div id="addModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>새 카테고리 추가</h3>
                <button type="button" class="modal-close" onclick="closeModal('addModal')">&times;</button>
            </div>
            <form method="post" id="addForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="add_name" class="form-label">카테고리명 <span class="required">*</span></label>
                        <input type="text" id="add_name" name="name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="add_description" class="form-label">설명</label>
                        <textarea id="add_description" name="description" class="form-input" rows="3" placeholder="카테고리에 대한 설명을 입력하세요"></textarea>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeModal('addModal')" class="btn btn-outline">취소</button>
                    <button type="submit" class="btn btn-primary">추가</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 카테고리 수정 모달 -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>카테고리 수정</h3>
                <button type="button" class="modal-close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="post" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_name" class="form-label">카테고리명 <span class="required">*</span></label>
                        <input type="text" id="edit_name" name="name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_description" class="form-label">설명</label>
                        <textarea id="edit_description" name="description" class="form-input" rows="3" placeholder="카테고리에 대한 설명을 입력하세요"></textarea>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeModal('editModal')" class="btn btn-outline">취소</button>
                    <button type="submit" class="btn btn-primary">수정</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 카테고리 삭제 모달 -->
    <div id="deleteModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>카테고리 삭제</h3>
                <button type="button" class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <form method="post" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-body">
                    <p><strong id="delete_name"></strong> 카테고리를 정말 삭제하시겠습니까?</p>
                    <p class="text-warning"><strong>이 작업은 되돌릴 수 없습니다.</strong></p>
                </div>
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
        function showAddModal() {
            document.getElementById('addForm').reset();
            showModal('addModal');
        }
        
        function editCategory(id, name, description) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
            showModal('editModal');
        }
        
        function deleteCategory(id, name) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_name').textContent = name;
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
        .badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .btn-disabled {
            background: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-warning {
            color: #856404;
        }
        
        .required {
            color: #dc3545;
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
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px 30px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #666;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            color: #333;
        }
        
        .modal-body {
            padding: 20px 30px;
        }
        
        .modal-actions {
            padding: 20px 30px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
    </style>
</body>
</html>