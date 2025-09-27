<?php
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';

$id = $_GET['id'] ?? 0;
$error = '';
$success = '';
$post = null;
$attachments = [];

// Get board categories
$categories = [];
try {
    $pdo = Database::getInstance()->getConnection();
    $sql = "SELECT * FROM board_categories WHERE status = 'active' ORDER BY sort_order, name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Categories will remain empty array
}

if (!$id) {
    header('Location: index.php');
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $isAdmin = $currentUser && $currentUser['user_level'] == 9;
    
    $sql = "SELECT b.*, c.name as category_name
            FROM boards b
            JOIN board_categories c ON b.category_id = c.id
            WHERE b.id = ? AND b.status = 'published'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $post = $stmt->fetch();

    if (!$post) {
        header('Location: index.php');
        exit;
    }

    // Check edit permission
    if (!$currentUser || ($currentUser['id'] !== $post['user_id'] && $currentUser['role'] !== 'admin')) {
        header('Location: view.php?id=' . $id);
        exit;
    }

    // Get attachments from attached_files JSON field
    $attachments = [];
    if ($post['attached_files']) {
        $attached_files = json_decode($post['attached_files'], true);
        if (is_array($attached_files)) {
            foreach ($attached_files as $index => $file) {
                $attachments[] = [
                    'id' => $index,
                    'file_path' => $file['path'] ?? '',
                    'original_filename' => $file['name'] ?? '',
                    'file_type' => $file['type'] ?? 'application/octet-stream'
                ];
            }
        }
    }
    
} catch (Exception $e) {
    $error = '게시글을 불러오는데 실패했습니다.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    
    // Only check permission, no password needed for logged-in users
    if ($currentUser && ($currentUser['id'] === $post['user_id'] || $currentUser['role'] === 'admin')) {
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $category_id = $_POST['category_id'] ?? $post['category_id'];
        $summary = trim($_POST['summary'] ?? '');
        $remove_files = $_POST['remove_files'] ?? [];
        
        if (empty($title)) {
            $error = '제목을 입력해주세요.';
        } elseif (empty($content)) {
            $error = '내용을 입력해주세요.';
        } elseif (empty($category_id)) {
            $error = '카테고리를 선택해주세요.';
        } else {
            try {
                $pdo->beginTransaction();
                
                $password_hash = $new_password ? password_hash($new_password, PASSWORD_DEFAULT) : $post['password'];
                
                $sql = "UPDATE board_posts SET title = ?, content = ?, author = ?, password = ?, email = ?, post_type = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$title, $content, $author, $password_hash, $email, $post_type, $id]);
                
                if (!empty($remove_files)) {
                    foreach ($remove_files as $file_id) {
                        $sql = "SELECT file_path FROM board_attachments WHERE id = ? AND post_id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$file_id, $id]);
                        $file_path = $stmt->fetchColumn();
                        
                        if ($file_path && file_exists(__DIR__ . '/../../' . $file_path)) {
                            unlink(__DIR__ . '/../../' . $file_path);
                        }
                        
                        $sql = "DELETE FROM board_attachments WHERE id = ? AND post_id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$file_id, $id]);
                    }
                }
                
                if (!empty($_FILES['attachments']['name'][0])) {
                    $upload_dir = __DIR__ . '/../../uploads/board/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    foreach ($_FILES['attachments']['name'] as $key => $filename) {
                        if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                            $original_filename = $filename;
                            $file_extension = pathinfo($filename, PATHINFO_EXTENSION);
                            $new_filename = uniqid() . '.' . $file_extension;
                            $file_path = $upload_dir . $new_filename;
                            
                            if (move_uploaded_file($_FILES['attachments']['tmp_name'][$key], $file_path)) {
                                $sql = "INSERT INTO board_attachments (post_id, filename, original_filename, file_path, file_size, file_type) VALUES (?, ?, ?, ?, ?, ?)";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute([
                                    $id,
                                    $new_filename,
                                    $original_filename,
                                    '/uploads/board/' . $new_filename,
                                    $_FILES['attachments']['size'][$key],
                                    $_FILES['attachments']['type'][$key]
                                ]);
                            }
                        }
                    }
                }
                
                $pdo->commit();
                header("Location: view.php?id=$id");
                exit;
                
            } catch (Exception $e) {
                $pdo->rollback();
                $error = '게시글 수정에 실패했습니다.';
            }
        }
    } else {
        $error = '비밀번호가 일치하지 않습니다.';
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>게시글 수정 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .edit-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .edit-header {
            margin-bottom: 30px;
        }
        
        .edit-title {
            font-size: 2rem;
            margin: 0;
        }
        
        .password-form,
        .edit-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
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
            border-color: #007bff;
            outline: none;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .content-editor {
            min-height: 300px;
            resize: vertical;
        }
        
        .existing-files {
            margin-bottom: 20px;
        }
        
        .existing-files h4 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 5px;
        }
        
        .file-preview {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .file-info {
            flex: 1;
            font-size: 14px;
            color: #666;
        }
        
        .file-remove-checkbox {
            margin-left: auto;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 30px;
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
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <main >
        <div class="edit-header">
            <h1 class="edit-title">게시글 수정</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!$isAdmin && (!isset($_POST['password']) || !password_verify($_POST['password'] ?? '', $post['password']))): ?>
            <!-- Password verification form -->
            <form class="password-form" method="post">
                <div class="form-group">
                    <label class="form-label">비밀번호를 입력하세요</label>
                    <input type="password" name="password" class="form-input" required 
                           placeholder="게시글 작성시 입력한 비밀번호">
                </div>
                <button type="submit" class="btn btn-primary">확인</button>
                <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">취소</a>
            </form>
        <?php else: ?>
            <?php if ($isAdmin): ?>
                <div class="admin-notice">
                    관리자 권한으로 수정 중입니다.
                </div>
            <?php endif; ?>
            
            <!-- Edit form -->
            <form class="edit-form" method="post" enctype="multipart/form-data">
                <?php if (!$isAdmin): ?>
                    <input type="hidden" name="password" value="<?= htmlspecialchars($_POST['password']) ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">게시글 유형</label>
                    <select name="post_type" class="form-input" style="width: 200px;">
                        <option value="general" <?= $post['post_type'] === 'general' ? 'selected' : '' ?>>일반글</option>
                        <option value="review" <?= $post['post_type'] === 'review' ? 'selected' : '' ?>>상품리뷰</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">작성자 *</label>
                        <input type="text" name="author" class="form-input" required 
                               value="<?= htmlspecialchars($post['author']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">새 비밀번호</label>
                        <input type="password" name="new_password" class="form-input" 
                               placeholder="변경하지 않으려면 비워두세요">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">이메일</label>
                    <input type="email" name="email" class="form-input" 
                           value="<?= htmlspecialchars($post['email'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">제목 *</label>
                    <input type="text" name="title" class="form-input" required 
                           value="<?= htmlspecialchars($post['title']) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">내용 *</label>
                    <textarea name="content" class="form-input content-editor" required><?= htmlspecialchars($post['content']) ?></textarea>
                </div>
                
                <?php if (!empty($attachments)): ?>
                    <div class="existing-files">
                        <h4>기존 첨부파일</h4>
                        <?php foreach ($attachments as $attachment): ?>
                            <div class="file-item">
                                <?php if (strpos($attachment['file_type'], 'image/') === 0): ?>
                                    <img src="<?= $attachment['file_path'] ?>" alt="" class="file-preview">
                                <?php else: ?>
                                    <div class="file-preview" style="background: #333; color: white; display: flex; align-items: center; justify-content: center;">📄</div>
                                <?php endif; ?>
                                <div class="file-info">
                                    <?= htmlspecialchars($attachment['original_filename']) ?>
                                </div>
                                <div class="file-remove-checkbox">
                                    <label>
                                        <input type="checkbox" name="remove_files[]" value="<?= $attachment['id'] ?>">
                                        삭제
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">새 첨부파일</label>
                    <input type="file" name="attachments[]" multiple accept="image/*,video/*" class="form-input">
                    <small style="color: #666;">이미지 및 동영상 파일만 업로드 가능합니다 (최대 10MB)</small>
                </div>
                
                <div class="form-actions">
                    <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">취소</a>
                    <button type="submit" class="btn btn-primary">수정하기</button>
                </div>
            </form>
        <?php endif; ?>
    </main>
    
    <?php include '../../includes/footer.php'; ?>
    <script src="/assets/js/main.js"></script>
</body>
</html>