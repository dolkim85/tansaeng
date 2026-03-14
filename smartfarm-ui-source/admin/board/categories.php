<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$success = '';
$error = '';

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        $pdo = Database::getInstance()->getConnection();

        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $status = $_POST['status'] ?? 'active';

            if (empty($name)) {
                throw new Exception('카테고리 이름을 입력해주세요.');
            }

            $sql = "INSERT INTO board_categories (name, description, sort_order, status) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $description, $sort_order, $status]);

            $success = '카테고리가 추가되었습니다.';

        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $status = $_POST['status'] ?? 'active';

            if (empty($name)) {
                throw new Exception('카테고리 이름을 입력해주세요.');
            }

            $sql = "UPDATE board_categories SET name = ?, description = ?, sort_order = ?, status = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $description, $sort_order, $status, $id]);

            $success = '카테고리가 수정되었습니다.';

        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            // Check if category has posts
            $sql = "SELECT COUNT(*) FROM boards WHERE category_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $post_count = $stmt->fetchColumn();

            if ($post_count > 0) {
                throw new Exception("이 카테고리에 {$post_count}개의 게시글이 있어 삭제할 수 없습니다.");
            }

            $sql = "DELETE FROM board_categories WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            $success = '카테고리가 삭제되었습니다.';
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all categories
try {
    $pdo = Database::getInstance()->getConnection();

    $sql = "SELECT c.*,
            (SELECT COUNT(*) FROM boards WHERE category_id = c.id) as post_count
            FROM board_categories c
            ORDER BY c.sort_order ASC, c.name ASC";
    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = '카테고리를 불러오는데 실패했습니다.';
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>게시판 카테고리 관리 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .categories-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .categories-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .categories-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .categories-table th {
            background-color: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }

        .categories-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .categories-table tr:hover {
            background-color: #f8f9fa;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }

        .close:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: #007bff;
            outline: none;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .post-count {
            background-color: #e9ecef;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            color: #495057;
        }

        @media (max-width: 768px) {
            .categories-container {
                padding: 10px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .categories-table {
                overflow-x: auto;
            }

            .modal-content {
                margin: 20px;
                width: calc(100% - 40px);
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-layout">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="categories-container">
                <div class="page-header">
                    <h1 class="page-title">게시판 카테고리 관리</h1>
                    <button class="btn btn-primary" onclick="openAddModal()">
                        + 새 카테고리 추가
                    </button>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="categories-table">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 60px;">순서</th>
                                <th>카테고리명</th>
                                <th>설명</th>
                                <th style="width: 100px;">게시글 수</th>
                                <th style="width: 100px;">상태</th>
                                <th style="width: 150px;">관리</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px;">
                                        등록된 카테고리가 없습니다.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($category['sort_order']) ?></td>
                                        <td><strong><?= htmlspecialchars($category['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($category['description'] ?? '') ?></td>
                                        <td>
                                            <span class="post-count"><?= number_format($category['post_count']) ?>개</span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $category['status'] ?>">
                                                <?= $category['status'] === 'active' ? '활성' : '비활성' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-success"
                                                    onclick='openEditModal(<?= json_encode($category) ?>)'>
                                                수정
                                            </button>
                                            <?php if ($category['post_count'] == 0): ?>
                                                <button class="btn btn-sm btn-danger"
                                                        onclick="deleteCategory(<?= $category['id'] ?>, '<?= htmlspecialchars($category['name']) ?>')">
                                                    삭제
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add/Edit Category Modal -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">새 카테고리 추가</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" id="categoryForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="categoryId">

                <div class="form-group">
                    <label class="form-label">카테고리명 *</label>
                    <input type="text" name="name" id="categoryName" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">설명</label>
                    <textarea name="description" id="categoryDescription" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">정렬 순서</label>
                    <input type="number" name="sort_order" id="categorySortOrder" class="form-control" value="0" min="0">
                    <small style="color: #666;">숫자가 작을수록 먼저 표시됩니다.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">상태</label>
                    <select name="status" id="categoryStatus" class="form-control">
                        <option value="active">활성</option>
                        <option value="inactive">비활성</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">취소</button>
                    <button type="submit" class="btn btn-primary">저장</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Form -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = '새 카테고리 추가';
            document.getElementById('formAction').value = 'add';
            document.getElementById('categoryForm').reset();
            document.getElementById('categoryId').value = '';
            document.getElementById('categoryModal').style.display = 'block';
        }

        function openEditModal(category) {
            document.getElementById('modalTitle').textContent = '카테고리 수정';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('categoryId').value = category.id;
            document.getElementById('categoryName').value = category.name;
            document.getElementById('categoryDescription').value = category.description || '';
            document.getElementById('categorySortOrder').value = category.sort_order;
            document.getElementById('categoryStatus').value = category.status;
            document.getElementById('categoryModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }

        function deleteCategory(id, name) {
            if (confirm(`'${name}' 카테고리를 삭제하시겠습니까?`)) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('categoryModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
