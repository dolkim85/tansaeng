<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
$auth = Auth::getInstance();

require_once $base_path . '/classes/Database.php';

$id = $_GET['id'] ?? 0;
$error = '';
$success = '';
$post = null;
$comments = [];
$attachments = [];

if (!$id) {
    header('Location: index.php');
    exit;
}

// 답글 수정 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_reply_submit'])) {
    $reply_id = $_POST['reply_id'] ?? 0;
    $edit_content = trim($_POST['edit_content'] ?? '');
    $edit_password = $_POST['edit_password'] ?? '';
    
    if (empty($edit_content)) {
        $error = '답글 내용을 입력해주세요.';
    } elseif (empty($edit_password)) {
        $error = '비밀번호를 입력해주세요.';
    } else {
        try {
            $pdo = Database::getInstance()->getConnection();
            
            // 비밀번호 확인
            $sql = "SELECT password FROM board_replies WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$reply_id]);
            $reply = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reply && password_verify($edit_password, $reply['password'])) {
                $sql = "UPDATE board_replies SET content = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$edit_content, $reply_id]);
                
                $success = '답글이 수정되었습니다.';
            } else {
                $error = '비밀번호가 일치하지 않습니다.';
            }
            
        } catch (Exception $e) {
            $error = '답글 수정에 실패했습니다.';
        }
    }
}

// 답글 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reply_submit'])) {
    $reply_id = $_POST['reply_id'] ?? 0;
    $delete_password = $_POST['delete_password'] ?? '';
    
    if (empty($delete_password)) {
        $error = '비밀번호를 입력해주세요.';
    } else {
        try {
            $pdo = Database::getInstance()->getConnection();
            
            // 비밀번호 확인
            $sql = "SELECT password FROM board_replies WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$reply_id]);
            $reply = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reply && password_verify($delete_password, $reply['password'])) {
                $sql = "DELETE FROM board_replies WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$reply_id]);
                
                $success = '답글이 삭제되었습니다.';
            } else {
                $error = '비밀번호가 일치하지 않습니다.';
            }
            
        } catch (Exception $e) {
            $error = '답글 삭제에 실패했습니다.';
        }
    }
}

// 댓글 수정 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_comment_submit'])) {
    $comment_id = $_POST['comment_id'] ?? 0;
    $edit_content = trim($_POST['edit_content'] ?? '');

    try {
        $auth = Auth::getInstance();
        $currentUser = $auth->getCurrentUser();

        if (!$currentUser) {
            $error = '댓글 수정을 위해 로그인해주세요.';
        } elseif (empty($edit_content)) {
            $error = '댓글 내용을 입력해주세요.';
        } else {
            $pdo = Database::getInstance()->getConnection();

            // 권한 확인 (작성자 또는 관리자만 수정 가능)
            $sql = "SELECT user_id FROM board_comments WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$comment_id]);
            $comment = $stmt->fetch();

            if ($comment && ($comment['user_id'] == $currentUser['id'] || $currentUser['role'] === 'admin')) {
                $sql = "UPDATE board_comments SET content = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$edit_content, $comment_id]);

                $success = '댓글이 수정되었습니다.';
            } else {
                $error = '댓글을 수정할 권한이 없습니다.';
            }
        }
    } catch (Exception $e) {
        $error = '댓글 수정에 실패했습니다: ' . $e->getMessage();
    }
}

// 댓글 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment_submit'])) {
    $comment_id = $_POST['comment_id'] ?? 0;

    try {
        $auth = Auth::getInstance();
        $currentUser = $auth->getCurrentUser();

        if (!$currentUser) {
            $error = '댓글 삭제를 위해 로그인해주세요.';
        } else {
            $pdo = Database::getInstance()->getConnection();

            // 권한 확인 (작성자 또는 관리자만 삭제 가능)
            $sql = "SELECT user_id FROM board_comments WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$comment_id]);
            $comment = $stmt->fetch();

            if ($comment && ($comment['user_id'] == $currentUser['id'] || $currentUser['role'] === 'admin')) {
                $sql = "DELETE FROM board_comments WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$comment_id]);

                $success = '댓글이 삭제되었습니다.';
            } else {
                $error = '댓글을 삭제할 권한이 없습니다.';
            }
        }
    } catch (Exception $e) {
        $error = '댓글 삭제에 실패했습니다: ' . $e->getMessage();
    }
}

// 댓글 작성 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_submit'])) {
    try {
        $auth = Auth::getInstance();
        $currentUser = $auth->getCurrentUser();

        if (!$currentUser) {
            $error = '댓글 작성을 위해 로그인해주세요.';
        } else {
            $comment_content = trim($_POST['comment_content'] ?? '');

            if (empty($comment_content)) {
                $error = '댓글 내용을 입력해주세요.';
            } else {
                $pdo = Database::getInstance()->getConnection();

                $sql = "INSERT INTO board_comments (board_id, user_id, author_name, author_email, content)
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $id,
                    $currentUser['id'],
                    $currentUser['name'],
                    $currentUser['email'],
                    $comment_content
                ]);

                $success = '댓글이 등록되었습니다.';
                $_POST = []; // 폼 초기화
            }
        }
    } catch (Exception $e) {
        $error = '댓글 등록에 실패했습니다: ' . $e->getMessage();
    }
}

try {
    $pdo = Database::getInstance()->getConnection();
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $isAdmin = $currentUser && $currentUser['role'] === 'admin';
    
    $sql = "UPDATE boards SET views = views + 1 WHERE id = ? AND status = 'published'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

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
    
    // Get attachments from attached_files JSON field
    $attachments = [];
    if ($post && $post['attached_files']) {
        $attached_files = json_decode($post['attached_files'], true);
        if (is_array($attached_files)) {
            foreach ($attached_files as $file) {
                $attachments[] = [
                    'file_path' => $file['path'] ?? '',
                    'original_filename' => $file['name'] ?? '',
                    'file_type' => $file['type'] ?? 'application/octet-stream'
                ];
            }
        }
    }
    
    // 댓글 가져오기
    $sql = "SELECT c.*, u.name as author_name
            FROM board_comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.board_id = ? AND c.status = 'published'
            ORDER BY c.created_at ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $comments = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = '게시글을 불러오는데 실패했습니다: ' . $e->getMessage();
    $post = null;
    $comments = [];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title'] ?? '게시글') ?> - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .view-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .post-header {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .post-title {
            font-size: 2rem;
            margin: 0 0 20px 0;
            color: #333;
        }
        
        .post-badges {
            margin-bottom: 15px;
        }
        
        .notice-badge {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            margin-right: 8px;
        }
        
        .review-badge {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            margin-right: 8px;
        }
        
        .post-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
            color: #666;
            font-size: 14px;
        }
        
        .meta-left {
            display: flex;
            gap: 20px;
        }
        
        .meta-right {
            display: flex;
            gap: 10px;
        }
        
        .post-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            line-height: 1.8;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .post-attachments {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .attachments-title {
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        
        .attachment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .attachment-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: #f8f9fa;
        }
        
        .attachment-preview {
            width: 100%;
            height: 150px;
            object-fit: cover;
            cursor: pointer;
        }
        
        .attachment-info {
            padding: 10px;
            text-align: center;
        }
        
        .attachment-name {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            word-break: break-all;
        }
        
        .attachment-download {
            font-size: 12px;
            color: #007bff;
            text-decoration: none;
        }
        
        .attachment-download:hover {
            text-decoration: underline;
        }
        
        .video-placeholder {
            width: 100%;
            height: 150px;
            background: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }
        
        .post-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        
        .action-left {
            display: flex;
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
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-outline {
            background-color: white;
            color: #007bff;
            border: 1px solid #007bff;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
        }
        
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 90%;
            max-height: 90%;
        }
        
        .modal-image {
            width: 100%;
            height: auto;
            border-radius: 8px;
        }
        
        .modal-video {
            width: 100%;
            height: auto;
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .modal-close:hover {
            color: #ccc;
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
        
        /* 댓글 스타일 */
        .comments-section {
            margin-top: 40px;
        }

        .comments-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .comments-header h3 {
            margin: 0;
            color: #333;
            font-size: 1.2rem;
        }

        .comment-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .comment-item {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .comment-item:last-child {
            border-bottom: none;
        }
        
        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .comment-author {
            font-weight: 600;
            color: #333;
        }

        .comment-date {
            color: #666;
            font-size: 14px;
        }

        .comment-content {
            line-height: 1.6;
            color: #555;
        }
        
        .reply-actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-small {
            padding: 4px 8px;
            font-size: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-edit:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .btn-delete:hover {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        
        .reply-edit-form,
        .reply-delete-form {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        
        .form-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
            border: 1px solid #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        
        .reply-content {
            line-height: 1.6;
            color: #555;
        }
        
        .private-content {
            font-style: italic;
            color: #888;
        }
        
        .private-badge {
            background: #ffc107;
            color: #212529;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .reply-form-section {
            background: white;
            padding: 30px;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 1px;
        }
        
        .reply-form-section h4 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 1.1rem;
        }
        
        .reply-form {
            max-width: none;
        }
        
        .reply-textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .form-checkbox input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .checkbox-text {
            color: #555;
            font-size: 14px;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .comment-form-section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .comment-form-section h4 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 1.1rem;
        }

        .comment-textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .comment-textarea:focus {
            border-color: #007bff;
            outline: none;
        }

        .comment-actions {
            display: flex;
            gap: 5px;
            margin-left: 10px;
        }

        .comment-edit-form,
        .comment-delete-form {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }

        .edit-badge {
            background: #6c757d;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 8px;
        }
        
        @media (max-width: 768px) {
            .post-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .post-actions {
                flex-direction: column;
            }
            
            .attachment-grid {
                grid-template-columns: 1fr;
            }
            
            .replies-header,
            .replies-list .reply-item,
            .reply-form-section {
                padding: 15px 20px;
            }
            
            .reply-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <main class="view-container">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php elseif (!$post): ?>
            <div class="alert alert-danger">게시글을 찾을 수 없습니다.</div>
        <?php else: ?>
            <div class="post-header">
                <div class="post-badges">
                    <?php if ($post['is_notice'] ?? false): ?>
                        <span class="notice-badge">공지</span>
                    <?php endif; ?>
                    <?php if (($post['category_name'] ?? '') === '고객후기'): ?>
                        <span class="review-badge">리뷰</span>
                    <?php endif; ?>
                </div>
                
                <h1 class="post-title"><?= htmlspecialchars($post['title']) ?></h1>
                
                <div class="post-meta">
                    <div class="meta-left">
                        <span>작성자: <?= htmlspecialchars($post['author_name'] ?? '익명') ?></span>
                        <span>작성일: <?= date('Y-m-d H:i', strtotime($post['created_at'])) ?></span>
                    </div>
                    <div class="meta-right">
                        <span>조회수: <?= number_format($post['views']) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="post-content">
                <?= nl2br(htmlspecialchars($post['content'])) ?>
            </div>
            
            <?php if (!empty($attachments)): ?>
                <div class="post-attachments">
                    <div class="attachments-title">첨부파일 (<?= count($attachments) ?>개)</div>
                    <div class="attachment-grid">
                        <?php foreach ($attachments as $attachment): ?>
                            <div class="attachment-item">
                                <?php if (strpos($attachment['file_type'], 'image/') === 0): ?>
                                    <img src="<?= $attachment['file_path'] ?>" 
                                         alt="<?= htmlspecialchars($attachment['original_filename']) ?>"
                                         class="attachment-preview"
                                         onclick="openModal('<?= $attachment['file_path'] ?>', 'image')">
                                <?php elseif (strpos($attachment['file_type'], 'video/') === 0): ?>
                                    <div class="video-placeholder" 
                                         onclick="openModal('<?= $attachment['file_path'] ?>', 'video')">
                                        ▶
                                    </div>
                                <?php endif; ?>
                                
                                <div class="attachment-info">
                                    <div class="attachment-name"><?= htmlspecialchars($attachment['original_filename']) ?></div>
                                    <a href="<?= $attachment['file_path'] ?>" download="<?= htmlspecialchars($attachment['original_filename']) ?>" 
                                       class="attachment-download">다운로드</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="post-actions">
                <div class="action-left">
                    <a href="index.php" class="btn btn-outline">목록으로</a>
                </div>
                <div class="action-right">
                    <a href="edit.php?id=<?= $post['id'] ?>" class="btn btn-secondary">수정</a>
                    <?php if ($isAdmin): ?>
                        <a href="delete.php?id=<?= $post['id'] ?>&admin=1" class="btn btn-danger" 
                           onclick="return confirm('정말 삭제하시겠습니까?')">삭제</a>
                    <?php else: ?>
                        <a href="delete.php?id=<?= $post['id'] ?>" class="btn btn-danger" 
                           onclick="return confirm('정말 삭제하시겠습니까?')">삭제</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 댓글 섹션 -->
            <div class="comments-section">
                <div class="comments-header">
                    <h3>댓글 (<?= count($comments) ?>개)</h3>
                </div>
                
                <!-- 댓글 목록 -->
                <?php if (!empty($comments)): ?>
                    <div class="comment-list">
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment-item" id="comment-<?= $comment['id'] ?>">
                                <div class="comment-header">
                                    <div class="comment-author">
                                        <?= htmlspecialchars($comment['author_name'] ?? '익명') ?>
                                        <?php if ($comment['updated_at'] && $comment['updated_at'] != $comment['created_at']): ?>
                                            <span class="edit-badge">수정됨</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="comment-date">
                                        <?= date('Y-m-d H:i', strtotime($comment['created_at'])) ?>
                                        <?php if ($currentUser && ($comment['user_id'] == $currentUser['id'] || $currentUser['role'] === 'admin')): ?>
                                            <div class="comment-actions">
                                                <button onclick="toggleEditForm(<?= $comment['id'] ?>)" class="btn-small btn-edit">수정</button>
                                                <button onclick="toggleDeleteForm(<?= $comment['id'] ?>)" class="btn-small btn-delete">삭제</button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="comment-content" id="comment-content-<?= $comment['id'] ?>">
                                    <?= nl2br(htmlspecialchars($comment['content'])) ?>
                                </div>

                                <!-- 댓글 수정 폼 -->
                                <div class="comment-edit-form" id="edit-form-<?= $comment['id'] ?>" style="display: none;">
                                    <form method="post">
                                        <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                        <textarea name="edit_content" class="comment-textarea" required><?= htmlspecialchars($comment['content']) ?></textarea>
                                        <div class="form-buttons">
                                            <button type="submit" name="edit_comment_submit" class="btn btn-primary">수정 완료</button>
                                            <button type="button" onclick="toggleEditForm(<?= $comment['id'] ?>)" class="btn btn-outline">취소</button>
                                        </div>
                                    </form>
                                </div>

                                <!-- 댓글 삭제 확인 폼 -->
                                <div class="comment-delete-form" id="delete-form-<?= $comment['id'] ?>" style="display: none;">
                                    <p style="color: #dc3545; margin-bottom: 15px;">정말로 이 댓글을 삭제하시겠습니까?</p>
                                    <form method="post">
                                        <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                        <div class="form-buttons">
                                            <button type="submit" name="delete_comment_submit" class="btn btn-danger">삭제</button>
                                            <button type="button" onclick="toggleDeleteForm(<?= $comment['id'] ?>)" class="btn btn-outline">취소</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- 댓글 작성 폼 -->
                <div class="comment-form-section">
                    <h4>댓글 작성</h4>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <?php if ($currentUser): ?>
                        <form method="post">
                            <textarea name="comment_content" class="comment-textarea"
                                      placeholder="댓글을 입력하세요..." required><?= htmlspecialchars($_POST['comment_content'] ?? '') ?></textarea>
                            <button type="submit" name="comment_submit" class="btn btn-primary">댓글 등록</button>
                        </form>
                    <?php else: ?>
                        <p>댓글을 작성하려면 <a href="/pages/auth/login.php">로그인</a>해주세요.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Modal for images and videos -->
    <div id="mediaModal" class="modal" onclick="closeModal()">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <div class="modal-content" onclick="event.stopPropagation()">
            <img id="modalImage" class="modal-image" style="display: none;">
            <video id="modalVideo" class="modal-video" controls style="display: none;">
                <source id="modalVideoSource" src="" type="">
                브라우저가 비디오를 지원하지 않습니다.
            </video>
        </div>
    </div>
    
    <?php include '../../includes/footer.php'; ?>
    <script src="/assets/js/main.js"></script>
    <script>
        function openModal(src, type) {
            const modal = document.getElementById('mediaModal');
            const modalImage = document.getElementById('modalImage');
            const modalVideo = document.getElementById('modalVideo');
            const modalVideoSource = document.getElementById('modalVideoSource');
            
            if (type === 'image') {
                modalImage.src = src;
                modalImage.style.display = 'block';
                modalVideo.style.display = 'none';
            } else if (type === 'video') {
                modalVideoSource.src = src;
                modalVideo.load();
                modalVideo.style.display = 'block';
                modalImage.style.display = 'none';
            }
            
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            const modal = document.getElementById('mediaModal');
            const modalVideo = document.getElementById('modalVideo');
            
            modal.style.display = 'none';
            modalVideo.pause();
            document.body.style.overflow = '';
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
        // 댓글 수정/삭제 폼 토글 함수
        function toggleEditForm(commentId) {
            const editForm = document.getElementById(`edit-form-${commentId}`);
            const content = document.getElementById(`comment-content-${commentId}`);

            if (editForm.style.display === 'none') {
                editForm.style.display = 'block';
                content.style.display = 'none';
            } else {
                editForm.style.display = 'none';
                content.style.display = 'block';
            }
        }

        function toggleDeleteForm(commentId) {
            const deleteForm = document.getElementById(`delete-form-${commentId}`);

            if (deleteForm.style.display === 'none') {
                deleteForm.style.display = 'block';
            } else {
                deleteForm.style.display = 'none';
            }
        }

        // 페이지 로드시 자동 스크롤 (댓글 등록 후)
        if (window.location.hash === '#comments') {
            document.querySelector('.comments-section').scrollIntoView();
        }
    </script>
</body>
</html>