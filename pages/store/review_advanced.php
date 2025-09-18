<?php
// 고급 후기 관리 페이지 (수정/삭제/답글 기능 포함)
ob_start();

$currentUser = null;
$product = null;
$reviews = [];
$product_id = intval($_GET['product_id'] ?? 0);
$success = '';
$error = '';

if ($product_id <= 0) {
    header('Location: /pages/store/products.php');
    exit;
}

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    require_once __DIR__ . '/../../classes/Database.php';
    
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // 상품 정보 조회
    $stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('Location: /pages/store/products.php');
        exit;
    }
    
    // POST 요청 처리
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        // 후기 작성
        if ($action === 'add_review') {
            $rating = intval($_POST['rating'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            $author = trim($_POST['author'] ?? '');
            $password = trim($_POST['password'] ?? '');
            
            if ($rating < 1 || $rating > 5) {
                $error = '평점은 1~5점 사이로 선택해주세요.';
            } elseif (empty($content)) {
                $error = '후기 내용을 입력해주세요.';
            } elseif (!$currentUser && empty($author)) {
                $error = '비로그인 사용자는 작성자명을 입력해주세요.';
            } elseif (!$currentUser && empty($password)) {
                $error = '비로그인 사용자는 비밀번호를 입력해주세요.';
            } else {
                $rating_text = ['', '별로예요', '그냥그래요', '괜찮아요', '좋아요', '최고예요'];
                $title = $rating_text[$rating] ?? '후기';
                
                if ($currentUser) {
                    // 로그인된 사용자
                    $user_id = $currentUser['id'];
                    $user_name = $currentUser['name'];
                    $hashed_password = null; // 로그인 사용자는 비밀번호 불필요
                } else {
                    // 비로그인 사용자
                    $user_id = null;
                    $user_name = $author;
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                }
                
                $sql = "INSERT INTO product_reviews (product_id, user_id, user_name, rating, title, content, password) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $product_id, 
                    $user_id, 
                    $user_name, 
                    $rating, 
                    $title, 
                    $content, 
                    $hashed_password
                ]);
                
                $success = '후기가 성공적으로 등록되었습니다.';
            }
        }
        // 후기 수정
        elseif ($action === 'edit_review') {
            $review_id = intval($_POST['review_id'] ?? 0);
            $rating = intval($_POST['rating'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            $password = trim($_POST['password'] ?? '');
            
            // 관리자 권한 체크
            $isAdmin = $currentUser && $currentUser['user_level'] >= 9;
            
            if ($isAdmin) {
                // 관리자는 모든 후기 수정 가능
                $rating_text = ['', '별로예요', '그냥그래요', '괜찮아요', '좋아요', '최고예요'];
                $title = $rating_text[$rating] ?? '후기';
                
                $sql = "UPDATE product_reviews SET rating = ?, title = ?, content = ?, updated_at = NOW() WHERE id = ? AND product_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$rating, $title, $content, $review_id, $product_id]);
                
                if ($stmt->rowCount() > 0) {
                    $success = '후기가 수정되었습니다. (관리자 권한)';
                } else {
                    $error = '후기를 찾을 수 없습니다.';
                }
            } elseif ($currentUser) {
                // 로그인된 사용자 - 자신의 후기만 수정 가능
                $stmt = $pdo->prepare("SELECT user_id FROM product_reviews WHERE id = ? AND product_id = ?");
                $stmt->execute([$review_id, $product_id]);
                $review = $stmt->fetch();
                
                if ($review && $review['user_id'] == $currentUser['id']) {
                    $rating_text = ['', '별로예요', '그냥그래요', '괜찮아요', '좋아요', '최고예요'];
                    $title = $rating_text[$rating] ?? '후기';
                    
                    $sql = "UPDATE product_reviews SET rating = ?, title = ?, content = ?, updated_at = NOW() WHERE id = ? AND product_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$rating, $title, $content, $review_id, $product_id]);
                    
                    $success = '후기가 수정되었습니다.';
                } else {
                    $error = '본인이 작성한 후기만 수정할 수 있습니다.';
                }
            } else {
                // 비로그인 사용자 - 비밀번호 확인 필요
                if (empty($password)) {
                    $error = '수정하려면 비밀번호를 입력해주세요.';
                } else {
                    $stmt = $pdo->prepare("SELECT password FROM product_reviews WHERE id = ? AND product_id = ? AND user_id IS NULL");
                    $stmt->execute([$review_id, $product_id]);
                    $review = $stmt->fetch();
                    
                    if ($review && password_verify($password, $review['password'])) {
                        $rating_text = ['', '별로예요', '그냥그래요', '괜찮아요', '좋아요', '최고예요'];
                        $title = $rating_text[$rating] ?? '후기';
                        
                        $sql = "UPDATE product_reviews SET rating = ?, title = ?, content = ?, updated_at = NOW() WHERE id = ? AND product_id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$rating, $title, $content, $review_id, $product_id]);
                        
                        $success = '후기가 수정되었습니다.';
                    } else {
                        $error = '비밀번호가 일치하지 않습니다.';
                    }
                }
            }
        }
        // 후기 삭제
        elseif ($action === 'delete_review') {
            $review_id = intval($_POST['review_id'] ?? 0);
            $password = trim($_POST['password'] ?? '');
            
            // 관리자 권한 체크
            $isAdmin = $currentUser && $currentUser['user_level'] >= 9;
            
            if ($isAdmin) {
                // 관리자는 모든 후기 삭제 가능
                $sql = "DELETE FROM product_reviews WHERE id = ? AND product_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$review_id, $product_id]);
                
                if ($stmt->rowCount() > 0) {
                    $success = '후기가 삭제되었습니다. (관리자 권한)';
                } else {
                    $error = '후기를 찾을 수 없습니다.';
                }
            } elseif ($currentUser) {
                // 로그인된 사용자 - 자신의 후기만 삭제 가능
                $stmt = $pdo->prepare("SELECT user_id FROM product_reviews WHERE id = ? AND product_id = ?");
                $stmt->execute([$review_id, $product_id]);
                $review = $stmt->fetch();
                
                if ($review && $review['user_id'] == $currentUser['id']) {
                    $sql = "DELETE FROM product_reviews WHERE id = ? AND product_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$review_id, $product_id]);
                    
                    $success = '후기가 삭제되었습니다.';
                } else {
                    $error = '본인이 작성한 후기만 삭제할 수 있습니다.';
                }
            } else {
                // 비로그인 사용자 - 비밀번호 확인 필요
                if (empty($password)) {
                    $error = '삭제하려면 비밀번호를 입력해주세요.';
                } else {
                    $stmt = $pdo->prepare("SELECT password FROM product_reviews WHERE id = ? AND product_id = ? AND user_id IS NULL");
                    $stmt->execute([$review_id, $product_id]);
                    $review = $stmt->fetch();
                    
                    if ($review && password_verify($password, $review['password'])) {
                        $sql = "DELETE FROM product_reviews WHERE id = ? AND product_id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$review_id, $product_id]);
                        
                        $success = '후기가 삭제되었습니다.';
                    } else {
                        $error = '비밀번호가 일치하지 않습니다.';
                    }
                }
            }
        }
        // 답글 작성
        elseif ($action === 'add_reply') {
            $review_id = intval($_POST['review_id'] ?? 0);
            $author = trim($_POST['reply_author'] ?? '');
            $content = trim($_POST['reply_content'] ?? '');
            $password = trim($_POST['reply_password'] ?? '');
            
            if (empty($author) || empty($content) || empty($password)) {
                $error = '답글 작성시 모든 필드를 입력해주세요.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $sql = "INSERT INTO review_replies (review_id, author, content, password) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$review_id, $author, $content, $hashed_password]);
                
                $success = '답글이 등록되었습니다.';
            }
        }
        // 답글 수정
        elseif ($action === 'edit_reply') {
            $reply_id = intval($_POST['reply_id'] ?? 0);
            $content = trim($_POST['reply_content'] ?? '');
            $password = trim($_POST['reply_password'] ?? '');
            
            if (empty($password)) {
                $error = '수정하려면 비밀번호를 입력해주세요.';
            } else {
                $isAdmin = $currentUser && $currentUser['user_level'] >= 9;
                
                if ($isAdmin) {
                    $sql = "UPDATE review_replies SET content = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$content, $reply_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success = '답글이 수정되었습니다. (관리자 권한)';
                    } else {
                        $error = '답글을 찾을 수 없습니다.';
                    }
                } else {
                    $stmt = $pdo->prepare("SELECT password FROM review_replies WHERE id = ?");
                    $stmt->execute([$reply_id]);
                    $reply = $stmt->fetch();
                    
                    if ($reply && password_verify($password, $reply['password'])) {
                        $sql = "UPDATE review_replies SET content = ?, updated_at = NOW() WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$content, $reply_id]);
                        
                        $success = '답글이 수정되었습니다.';
                    } else {
                        $error = '비밀번호가 일치하지 않습니다.';
                    }
                }
            }
        }
        // 답글 삭제
        elseif ($action === 'delete_reply') {
            $reply_id = intval($_POST['reply_id'] ?? 0);
            $password = trim($_POST['reply_password'] ?? '');
            
            if (empty($password)) {
                $error = '삭제하려면 비밀번호를 입력해주세요.';
            } else {
                $isAdmin = $currentUser && $currentUser['user_level'] >= 9;
                
                if ($isAdmin) {
                    $sql = "DELETE FROM review_replies WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$reply_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success = '답글이 삭제되었습니다. (관리자 권한)';
                    } else {
                        $error = '답글을 찾을 수 없습니다.';
                    }
                } else {
                    $stmt = $pdo->prepare("SELECT password FROM review_replies WHERE id = ?");
                    $stmt->execute([$reply_id]);
                    $reply = $stmt->fetch();
                    
                    if ($reply && password_verify($password, $reply['password'])) {
                        $sql = "DELETE FROM review_replies WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$reply_id]);
                        
                        $success = '답글이 삭제되었습니다.';
                    } else {
                        $error = '비밀번호가 일치하지 않습니다.';
                    }
                }
            }
        }
    }
    
    // 후기 목록 조회 (답글 포함)
    $sql = "SELECT r.*, 
            (SELECT COUNT(*) FROM review_replies rr WHERE rr.review_id = r.id) as reply_count
            FROM product_reviews r 
            WHERE r.product_id = ? AND r.status = 'active' 
            ORDER BY r.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id]);
    $reviews = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = '오류가 발생했습니다: ' . $e->getMessage();
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name'] ?? '') ?> 후기 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .review-main { padding: 2rem 0; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .page-header { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .breadcrumb { color: #666; margin-bottom: 1rem; }
        .breadcrumb a { color: #007bff; text-decoration: none; }
        
        .alert { padding: 1rem; margin-bottom: 1rem; border-radius: 6px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .review-form, .review-item { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
        .form-input { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; }
        .form-textarea { min-height: 120px; resize: vertical; }
        
        .rating-input { display: flex; gap: 0.25rem; margin-top: 0.5rem; }
        .star { font-size: 1.5rem; color: #ddd; cursor: pointer; transition: color 0.2s; }
        .star:hover, .star.selected { color: #ffc107; }
        
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 0.25rem; transition: all 0.3s; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #1e7e34; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-outline { background: white; color: #007bff; border: 1px solid #007bff; }
        .btn-outline:hover { background: #007bff; color: white; }
        
        .review-meta { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; font-size: 0.9rem; color: #666; }
        .reviewer-name { font-weight: 600; color: #333; }
        .review-rating { color: #ffc107; }
        .review-date { margin-left: auto; }
        
        .review-content { margin: 1rem 0; line-height: 1.6; }
        .review-actions { margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
        
        .replies-section { margin-top: 2rem; border-top: 1px solid #eee; padding-top: 1rem; }
        .reply-item { background: #f8f9fa; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; border-left: 3px solid #007bff; }
        .reply-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; font-size: 0.85rem; color: #666; }
        .reply-author { font-weight: 600; color: #333; }
        .reply-content { line-height: 1.5; }
        .reply-actions { margin-top: 0.5rem; display: flex; gap: 0.5rem; }
        
        .reply-form { 
            margin-top: 1rem; 
            padding: 1.5rem; 
            background: #ffffff; 
            border: 2px dashed #007bff; 
            border-radius: 8px; 
            box-shadow: 0 2px 8px rgba(0,123,255,0.1);
        }
        .reply-write-form .form-group { margin-bottom: 1rem; }
        .reply-form-actions { 
            display: flex; 
            gap: 0.5rem; 
            justify-content: flex-end; 
            margin-top: 1rem; 
        }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { background: white; width: 90%; max-width: 600px; margin: 5% auto; padding: 2rem; border-radius: 8px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .modal-close { font-size: 1.5rem; cursor: pointer; }
        
        .password-input { width: 200px; }
        .admin-badge { background: #dc3545; color: white; padding: 0.2rem 0.5rem; border-radius: 3px; font-size: 0.7rem; }
        
        @media (max-width: 768px) {
            .review-main { padding: 1rem 0; }
            .container { padding: 0 15px; }
            .review-form, .review-item { padding: 1rem; }
            .review-actions, .reply-actions { flex-direction: column; }
            .btn { margin: 0.25rem 0; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main class="review-main">
        <div class="container">
            <div class="page-header">
                <div class="breadcrumb">
                    <a href="/">홈</a> > 
                    <a href="/pages/store/">스토어</a> > 
                    <a href="/pages/store/product_detail.php?id=<?= $product_id ?>"><?= htmlspecialchars($product['name']) ?></a> > 
                    <span>후기</span>
                </div>
                <h1>🌟 상품 후기</h1>
                <p><?= htmlspecialchars($product['name']) ?></p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    ✅ <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- 후기 작성 폼 -->
            <div class="review-form">
                <h3>📝 후기 작성하기</h3>
                <form method="post">
                    <input type="hidden" name="action" value="add_review">
                    
                    <?php if ($currentUser): ?>
                        <!-- 로그인된 사용자 -->
                        <div class="form-group">
                            <label class="form-label">작성자</label>
                            <div style="padding: 0.75rem; background: #f8f9fa; border-radius: 4px; border: 1px solid #ddd;">
                                <?= htmlspecialchars($currentUser['name']) ?> (로그인됨)
                                <input type="hidden" name="author" value="<?= htmlspecialchars($currentUser['name']) ?>">
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- 비로그인 사용자 -->
                        <div class="form-group">
                            <label class="form-label" for="author">작성자명 *</label>
                            <input type="text" id="author" name="author" class="form-input" required 
                                   placeholder="작성자명을 입력하세요">
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label">별점 *</label>
                        <div class="rating-input">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star" data-rating="<?= $i ?>" onclick="setRating(<?= $i ?>)">☆</span>
                            <?php endfor; ?>
                            <input type="hidden" name="rating" id="rating-value" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="content">후기 내용 *</label>
                        <textarea name="content" id="content" class="form-input form-textarea" required 
                                  placeholder="상품에 대한 솔직한 후기를 남겨주세요."></textarea>
                    </div>
                    
                    <?php if (!$currentUser): ?>
                        <!-- 비로그인 사용자만 비밀번호 입력 -->
                        <div class="form-group">
                            <label class="form-label" for="password">비밀번호 *</label>
                            <input type="password" id="password" name="password" class="form-input password-input" required 
                                   placeholder="수정/삭제시 사용될 비밀번호">
                            <small style="color: #666; display: block; margin-top: 0.5rem;">
                                수정 및 삭제시 필요한 비밀번호입니다. 기억해두세요!
                            </small>
                        </div>
                    <?php else: ?>
                        <div style="background: #d4edda; color: #155724; padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem;">
                            ✅ 로그인된 상태에서는 비밀번호 없이 후기 작성이 가능하며, 본인 후기만 수정/삭제할 수 있습니다.
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-success">후기 등록</button>
                </form>
            </div>

            <!-- 후기 목록 -->
            <div class="reviews-list">
                <h3>💬 등록된 후기 (<?= count($reviews) ?>개)</h3>
                
                <?php if (empty($reviews)): ?>
                    <div style="text-align: center; padding: 3rem; color: #666;">
                        <p>아직 등록된 후기가 없습니다.</p>
                        <p>첫 번째 후기를 작성해보세요!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                    <div class="review-item">
                        <div class="review-meta">
                            <span class="reviewer-name">
                                <?= htmlspecialchars($review['user_name']) ?>
                                <?php if ($currentUser && $currentUser['user_level'] >= 9): ?>
                                    <span class="admin-badge">관리자 보기</span>
                                <?php endif; ?>
                            </span>
                            <span class="review-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?= $i <= $review['rating'] ? '⭐' : '☆' ?>
                                <?php endfor; ?>
                            </span>
                            <span class="review-date"><?= date('Y-m-d H:i', strtotime($review['created_at'])) ?></span>
                        </div>
                        
                        <div class="review-content">
                            <p><?= nl2br(htmlspecialchars($review['content'])) ?></p>
                        </div>
                        
                        <div class="review-actions">
                            <?php 
                            $isAdmin = $currentUser && $currentUser['user_level'] >= 9;
                            $isOwner = $currentUser && $review['user_id'] == $currentUser['id'];
                            $isAnonymous = !$review['user_id']; // 비로그인으로 작성된 후기
                            ?>
                            
                            <?php if ($isAdmin || $isOwner || $isAnonymous): ?>
                                <button onclick="showEditForm(<?= $review['id'] ?>, <?= $review['rating'] ?>, '<?= addslashes($review['content']) ?>')" 
                                        class="btn btn-warning">수정</button>
                                <button onclick="showDeleteForm(<?= $review['id'] ?>)" class="btn btn-danger">삭제</button>
                            <?php endif; ?>
                            
                            <button onclick="showReplyForm(<?= $review['id'] ?>)" class="btn btn-outline">답글 (<?= $review['reply_count'] ?>)</button>
                            
                            <?php if ($isAdmin): ?>
                                <span class="admin-badge" style="margin-left: 0.5rem;">관리자 권한</span>
                            <?php elseif ($isOwner): ?>
                                <span style="color: #007bff; font-size: 0.8rem; margin-left: 0.5rem;">내 후기</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- 답글 섹션 -->
                        <div class="replies-section" id="replies-<?= $review['id'] ?>">
                            <?php
                            // 답글 조회
                            $reply_stmt = $pdo->prepare("SELECT * FROM review_replies WHERE review_id = ? ORDER BY created_at ASC");
                            $reply_stmt->execute([$review['id']]);
                            $replies = $reply_stmt->fetchAll();
                            ?>
                            
                            <?php foreach ($replies as $reply): ?>
                            <div class="reply-item">
                                <div class="reply-meta">
                                    <span class="reply-author">
                                        <?= htmlspecialchars($reply['author']) ?>
                                        <?php if ($currentUser && $currentUser['user_level'] >= 9): ?>
                                            <span class="admin-badge">관리자 보기</span>
                                        <?php endif; ?>
                                    </span>
                                    <span><?= date('Y-m-d H:i', strtotime($reply['created_at'])) ?></span>
                                </div>
                                <div class="reply-content">
                                    <?= nl2br(htmlspecialchars($reply['content'])) ?>
                                </div>
                                <div class="reply-actions">
                                    <button onclick="showEditReplyForm(<?= $reply['id'] ?>, '<?= addslashes($reply['content']) ?>')" 
                                            class="btn btn-warning" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">수정</button>
                                    <button onclick="showDeleteReplyForm(<?= $reply['id'] ?>)" 
                                            class="btn btn-danger" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">삭제</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <!-- 답글 작성 폼 -->
                            <div class="reply-form" id="reply-form-<?= $review['id'] ?>" style="display: none;">
                                <form method="POST" class="reply-write-form">
                                    <input type="hidden" name="action" value="add_reply">
                                    <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                    
                                    <div class="form-group">
                                        <label class="form-label">답글 작성자 *</label>
                                        <input type="text" name="reply_author" class="form-input" required 
                                               placeholder="답글 작성자 이름">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">답글 내용 *</label>
                                        <textarea name="reply_content" class="form-input form-textarea" required 
                                                  placeholder="답글을 작성해주세요." style="min-height: 80px;"></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">비밀번호 *</label>
                                        <input type="password" name="reply_password" class="form-input" required 
                                               placeholder="수정/삭제시 사용할 비밀번호">
                                        <small style="color: #666; display: block; margin-top: 0.5rem;">
                                            답글 수정 및 삭제시 필요한 비밀번호입니다.
                                        </small>
                                    </div>
                                    
                                    <div class="reply-form-actions">
                                        <button type="submit" class="btn btn-primary">답글 등록</button>
                                        <button type="button" onclick="hideReplyForm(<?= $review['id'] ?>)" class="btn btn-outline">취소</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- 수정 모달 -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>후기 수정</h4>
                <span class="modal-close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="edit_review">
                <input type="hidden" name="review_id" id="edit-review-id">
                
                <div class="form-group">
                    <label class="form-label">별점</label>
                    <div class="rating-input">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star edit-star" data-rating="<?= $i ?>" onclick="setEditRating(<?= $i ?>)">☆</span>
                        <?php endfor; ?>
                        <input type="hidden" name="rating" id="edit-rating-value">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit-content">내용</label>
                    <textarea name="content" id="edit-content" class="form-input form-textarea"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit-password">비밀번호</label>
                    <input type="password" name="password" id="edit-password" class="form-input password-input" required>
                </div>
                
                <div style="text-align: right;">
                    <button type="button" onclick="closeModal('editModal')" class="btn btn-outline">취소</button>
                    <button type="submit" class="btn btn-success">수정</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 삭제 모달 -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>후기 삭제</h4>
                <span class="modal-close" onclick="closeModal('deleteModal')">&times;</span>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="delete_review">
                <input type="hidden" name="review_id" id="delete-review-id">
                
                <p>정말로 이 후기를 삭제하시겠습니까?</p>
                
                <div class="form-group">
                    <label class="form-label" for="delete-password">비밀번호</label>
                    <input type="password" name="password" id="delete-password" class="form-input password-input" required>
                </div>
                
                <div style="text-align: right;">
                    <button type="button" onclick="closeModal('deleteModal')" class="btn btn-outline">취소</button>
                    <button type="submit" class="btn btn-danger">삭제</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 답글 작성 모달 -->
    <div id="replyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>답글 작성</h4>
                <span class="modal-close" onclick="closeModal('replyModal')">&times;</span>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="add_reply">
                <input type="hidden" name="review_id" id="reply-review-id">
                
                <div class="form-group">
                    <label class="form-label" for="reply-author">작성자</label>
                    <input type="text" name="reply_author" id="reply-author" class="form-input" required 
                           value="<?= $currentUser ? htmlspecialchars($currentUser['name']) : '' ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="reply-content">답글 내용</label>
                    <textarea name="reply_content" id="reply-content" class="form-input form-textarea" required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="reply-password">비밀번호</label>
                    <input type="password" name="reply_password" id="reply-password" class="form-input password-input" required>
                </div>
                
                <div style="text-align: right;">
                    <button type="button" onclick="closeModal('replyModal')" class="btn btn-outline">취소</button>
                    <button type="submit" class="btn btn-success">답글 등록</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 답글 수정 모달 -->
    <div id="editReplyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>답글 수정</h4>
                <span class="modal-close" onclick="closeModal('editReplyModal')">&times;</span>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="edit_reply">
                <input type="hidden" name="reply_id" id="edit-reply-id">
                
                <div class="form-group">
                    <label class="form-label" for="edit-reply-content">답글 내용</label>
                    <textarea name="reply_content" id="edit-reply-content" class="form-input form-textarea"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit-reply-password">비밀번호</label>
                    <input type="password" name="reply_password" id="edit-reply-password" class="form-input password-input" required>
                </div>
                
                <div style="text-align: right;">
                    <button type="button" onclick="closeModal('editReplyModal')" class="btn btn-outline">취소</button>
                    <button type="submit" class="btn btn-success">수정</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 답글 삭제 모달 -->
    <div id="deleteReplyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>답글 삭제</h4>
                <span class="modal-close" onclick="closeModal('deleteReplyModal')">&times;</span>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="delete_reply">
                <input type="hidden" name="reply_id" id="delete-reply-id">
                
                <p>정말로 이 답글을 삭제하시겠습니까?</p>
                
                <div class="form-group">
                    <label class="form-label" for="delete-reply-password">비밀번호</label>
                    <input type="password" name="reply_password" id="delete-reply-password" class="form-input password-input" required>
                </div>
                
                <div style="text-align: right;">
                    <button type="button" onclick="closeModal('deleteReplyModal')" class="btn btn-outline">취소</button>
                    <button type="submit" class="btn btn-danger">삭제</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script>
        // 별점 설정
        function setRating(rating) {
            document.getElementById('rating-value').value = rating;
            updateStars('.star', rating);
        }
        
        function setEditRating(rating) {
            document.getElementById('edit-rating-value').value = rating;
            updateStars('.edit-star', rating);
        }
        
        function updateStars(selector, rating) {
            const stars = document.querySelectorAll(selector);
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.textContent = '★';
                    star.classList.add('selected');
                } else {
                    star.textContent = '☆';
                    star.classList.remove('selected');
                }
            });
        }
        
        // 모달 관리
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // 후기 수정
        function showEditForm(reviewId, rating, content) {
            document.getElementById('edit-review-id').value = reviewId;
            document.getElementById('edit-content').value = content;
            setEditRating(rating);
            showModal('editModal');
        }
        
        // 후기 삭제
        function showDeleteForm(reviewId) {
            document.getElementById('delete-review-id').value = reviewId;
            showModal('deleteModal');
        }
        
        // 답글 작성 (인라인 폼)
        function showReplyForm(reviewId) {
            // 기존 열린 답글 폼들 숨기기
            const openForms = document.querySelectorAll('.reply-form');
            openForms.forEach(form => form.style.display = 'none');
            
            // 해당 답글 폼 보여주기
            const replyForm = document.getElementById('reply-form-' + reviewId);
            if (replyForm) {
                replyForm.style.display = 'block';
            }
        }
        
        // 답글 폼 숨기기
        function hideReplyForm(reviewId) {
            const replyForm = document.getElementById('reply-form-' + reviewId);
            if (replyForm) {
                replyForm.style.display = 'none';
                // 폼 초기화
                const form = replyForm.querySelector('form');
                if (form) form.reset();
            }
        }
        
        // 답글 수정
        function showEditReplyForm(replyId, content) {
            document.getElementById('edit-reply-id').value = replyId;
            document.getElementById('edit-reply-content').value = content;
            showModal('editReplyModal');
        }
        
        // 답글 삭제
        function showDeleteReplyForm(replyId) {
            document.getElementById('delete-reply-id').value = replyId;
            showModal('deleteReplyModal');
        }
        
        // 모달 외부 클릭시 닫기
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        };
        
        // 페이지 로드시 기본 별점 설정
        window.onload = function() {
            setRating(5);
        };
    </script>
</body>
</html>