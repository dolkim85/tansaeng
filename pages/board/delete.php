<?php
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';

$id = $_GET['id'] ?? 0;
$isAdminDelete = $_GET['admin'] ?? 0;
$error = '';
$post = null;

if (!$id) {
    header('Location: index.php');
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $isAdmin = $currentUser && $currentUser['user_level'] == 9;
    
    $sql = "SELECT b.*, c.name as category_name, u.name as author_name
            FROM boards b
            JOIN board_categories c ON b.category_id = c.id
            LEFT JOIN users u ON b.user_id = u.id
            WHERE b.id = ? AND b.status = 'published'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $post = $stmt->fetch();

    if (!$post) {
        header('Location: index.php');
        exit;
    }

    // Check delete permission
    if (!$currentUser || ($currentUser['id'] !== $post['user_id'] && $currentUser['role'] !== 'admin')) {
        header('Location: view.php?id=' . $id);
        exit;
    }
    
} catch (Exception $e) {
    $error = '게시글을 불러오는데 실패했습니다.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    
    // Only check permission, no password needed for logged-in users
    if ($currentUser && ($currentUser['id'] === $post['user_id'] || $currentUser['role'] === 'admin')) {
        try {
            $pdo->beginTransaction();
            
            // Delete attached files
            if ($post['attached_files']) {
                $attached_files = json_decode($post['attached_files'], true);
                if (is_array($attached_files)) {
                    foreach ($attached_files as $file) {
                        $file_path = __DIR__ . '/../../' . $file['path'];
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }
                }
            }

            // Delete comments first
            $sql = "DELETE FROM board_comments WHERE board_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);

            // Update board status to deleted
            $sql = "UPDATE boards SET status = 'deleted' WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            
            $pdo->commit();
            header('Location: index.php?deleted=1');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error = '게시글 삭제에 실패했습니다.';
        }
    } else {
        $error = '삭제 권한이 없습니다.';
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>게시글 삭제 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .delete-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .delete-header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .delete-title {
            font-size: 2rem;
            margin: 0 0 10px 0;
            color: #dc3545;
        }
        
        .delete-subtitle {
            color: #666;
            margin: 0;
        }
        
        .post-info {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .post-title {
            font-size: 1.2rem;
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .post-meta {
            color: #666;
            font-size: 14px;
        }
        
        .delete-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-input:focus {
            border-color: #dc3545;
            outline: none;
        }
        
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .warning-box strong {
            color: #dc3545;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .admin-notice {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <main class="delete-container">
        <div class="delete-header">
            <h1 class="delete-title">게시글 삭제</h1>
            <p class="delete-subtitle">삭제된 게시글은 복구할 수 없습니다</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="post-info">
            <h3 class="post-title"><?= htmlspecialchars($post['title']) ?></h3>
            <div class="post-meta">
                작성자: <?= htmlspecialchars($post['author_name'] ?? '익명') ?> | 
                작성일: <?= date('Y-m-d H:i', strtotime($post['created_at'])) ?>
            </div>
        </div>
        
        <?php if ($currentUser['role'] === 'admin'): ?>
            <div class="admin-notice">
                관리자 권한으로 삭제합니다. 비밀번호 확인 없이 삭제됩니다.
            </div>
            
            <form class="delete-form" method="post">
                <div class="warning-box">
                    <strong>주의:</strong> 이 게시글과 모든 첨부파일이 영구적으로 삭제됩니다. 
                    이 작업은 되돌릴 수 없습니다.
                </div>
                
                <div class="form-actions">
                    <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">취소</a>
                    <button type="submit" class="btn btn-danger">삭제하기</button>
                </div>
            </form>
        <?php else: ?>
            <form class="delete-form" method="post">
                <div class="warning-box">
                    <strong>주의:</strong> 이 게시글과 모든 첨부파일이 영구적으로 삭제됩니다.
                    이 작업은 되돌릴 수 없습니다.
                </div>

                <div class="form-actions">
                    <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">취소</a>
                    <button type="submit" class="btn btn-danger">삭제하기</button>
                </div>
            </form>
        <?php endif; ?>
    </main>
    
    <?php include '../../includes/footer.php'; ?>
    <script src="/assets/js/main.js"></script>
</body>
</html>