<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
$dbConnected = false;
$notice = null;

// GET 파라미터에서 ID 가져오기
$noticeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($noticeId <= 0) {
    header('Location: /pages/support/notice.php');
    exit;
}

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    require_once __DIR__ . '/../../classes/Database.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $dbConnected = true;
    
    // 공지사항/게시글 조회 (board_posts 테이블에서)
    $stmt = $pdo->prepare("
        SELECT id, title, content, author, created_at, views, is_notice 
        FROM board_posts 
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$noticeId]);
    $notice = $stmt->fetch();
    
    if (!$notice) {
        // notices 테이블에서도 확인
        $stmt = $pdo->prepare("
            SELECT id, title, content, created_at, views, is_important 
            FROM notices 
            WHERE id = ? AND status = 'published'
        ");
        $stmt->execute([$noticeId]);
        $notice = $stmt->fetch();
        
        if ($notice) {
            // notices 테이블 데이터를 board_posts 형식으로 조정
            $notice['author'] = '관리자';
            $notice['is_notice'] = $notice['is_important'] ?? 0;
        }
    }
    
    if (!$notice) {
        header('Location: /pages/support/notice.php');
        exit;
    }
    
    // 조회수 증가
    $updateStmt = $pdo->prepare("UPDATE board_posts SET views = views + 1 WHERE id = ?");
    $updateStmt->execute([$noticeId]);
    
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    header('Location: /pages/support/notice.php');
    exit;
}

// 이전글/다음글 조회
$prevNotice = null;
$nextNotice = null;

try {
    // 이전글 조회
    $stmt = $pdo->prepare("
        SELECT id, title 
        FROM board_posts 
        WHERE id < ? AND status = 'active' 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$noticeId]);
    $prevNotice = $stmt->fetch();
    
    // 다음글 조회
    $stmt = $pdo->prepare("
        SELECT id, title 
        FROM board_posts 
        WHERE id > ? AND status = 'active' 
        ORDER BY id ASC 
        LIMIT 1
    ");
    $stmt->execute([$noticeId]);
    $nextNotice = $stmt->fetch();
    
} catch (Exception $e) {
    error_log("Error fetching prev/next notices: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($notice['title']) ?> - 탄생</title>
    <meta name="description" content="<?= htmlspecialchars(mb_substr(strip_tags($notice['content']), 0, 150)) ?>">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main >
        <div class="container">
            <div class="breadcrumb">
                <a href="/">홈</a> > 
                <a href="/pages/support/notice.php">공지사항</a> > 
                <span><?= htmlspecialchars($notice['title']) ?></span>
            </div>

            <article class="notice-detail">
                <header class="notice-header">
                    <?php if ($notice['is_notice']): ?>
                    <div class="notice-badge important">중요 공지</div>
                    <?php endif; ?>
                    <h1><?= htmlspecialchars($notice['title']) ?></h1>
                    
                    <div class="notice-meta">
                        <div class="meta-item">
                            <span class="label">작성자:</span>
                            <span class="value"><?= htmlspecialchars($notice['author'] ?? '관리자') ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="label">작성일:</span>
                            <span class="value"><?= date('Y년 m월 d일 H:i', strtotime($notice['created_at'])) ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="label">조회수:</span>
                            <span class="value"><?= number_format($notice['views'] + 1) ?></span>
                        </div>
                    </div>
                </header>

                <div class="notice-content">
                    <?= nl2br(htmlspecialchars($notice['content'])) ?>
                </div>

                <div class="notice-actions">
                    <button onclick="history.back()" class="btn btn-outline">목록으로</button>
                    <a href="/pages/support/notice.php" class="btn btn-primary">전체 목록</a>
                </div>
            </article>

            <!-- 이전글/다음글 네비게이션 -->
            <nav class="notice-navigation">
                <?php if ($nextNotice): ?>
                <div class="nav-item next">
                    <div class="nav-label">다음글</div>
                    <a href="/pages/support/notice_detail.php?id=<?= $nextNotice['id'] ?>" class="nav-title">
                        <?= htmlspecialchars(mb_substr($nextNotice['title'], 0, 50)) ?><?= mb_strlen($nextNotice['title']) > 50 ? '...' : '' ?>
                    </a>
                </div>
                <?php endif; ?>

                <?php if ($prevNotice): ?>
                <div class="nav-item prev">
                    <div class="nav-label">이전글</div>
                    <a href="/pages/support/notice_detail.php?id=<?= $prevNotice['id'] ?>" class="nav-title">
                        <?= htmlspecialchars(mb_substr($prevNotice['title'], 0, 50)) ?><?= mb_strlen($prevNotice['title']) > 50 ? '...' : '' ?>
                    </a>
                </div>
                <?php endif; ?>
            </nav>

            <!-- 관련 링크 -->
            <section class="related-links">
                <h2>관련 페이지</h2>
                <div class="links-grid">
                    <a href="/pages/support/faq.php" class="related-link">
                        <span class="link-icon">❓</span>
                        <span>자주 묻는 질문</span>
                    </a>
                    <a href="/pages/support/contact.php" class="related-link">
                        <span class="link-icon">✉️</span>
                        <span>문의하기</span>
                    </a>
                    <a href="/pages/products/media.php" class="related-link">
                        <span class="link-icon">📦</span>
                        <span>제품 정보</span>
                    </a>
                    <a href="/pages/company/about.php" class="related-link">
                        <span class="link-icon">🏢</span>
                        <span>회사 소개</span>
                    </a>
                </div>
            </section>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
</body>
</html>

<style>
.notice-detail-main {
    padding: 2rem 0;
    min-height: 60vh;
}

.breadcrumb {
    margin-bottom: 2rem;
    color: #666;
    font-size: 0.9rem;
}

.breadcrumb a {
    color: #4CAF50;
    text-decoration: none;
}

.breadcrumb a:hover {
    text-decoration: underline;
}

.notice-detail {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 2rem;
}

.notice-header {
    padding: 2rem;
    background: linear-gradient(135deg, #E8F5E8 0%, #C8E6C9 100%);
    border-bottom: 1px solid #e0e0e0;
}

.notice-badge {
    display: inline-block;
    background: #ff5722;
    color: white;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.notice-header h1 {
    color: #2E7D32;
    font-size: 1.8rem;
    margin-bottom: 1.5rem;
    line-height: 1.4;
}

.notice-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
    color: #666;
    font-size: 0.9rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.meta-item .label {
    font-weight: 600;
}

.notice-content {
    padding: 2rem;
    color: #333;
    line-height: 1.8;
    font-size: 1rem;
}

.notice-content p {
    margin-bottom: 1rem;
}

.notice-actions {
    padding: 1rem 2rem 2rem;
    display: flex;
    justify-content: center;
    gap: 1rem;
}

.btn {
    padding: 0.8rem 2rem;
    border: none;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-block;
    text-align: center;
}

.btn-outline {
    background: white;
    color: #4CAF50;
    border: 2px solid #4CAF50;
}

.btn-outline:hover {
    background: #4CAF50;
    color: white;
}

.btn-primary {
    background: #4CAF50;
    color: white;
}

.btn-primary:hover {
    background: #45a049;
}

.notice-navigation {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 2rem;
}

.nav-item {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #f0f0f0;
}

.nav-item:last-child {
    border-bottom: none;
}

.nav-label {
    font-size: 0.8rem;
    color: #666;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.nav-title {
    color: #333;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease;
}

.nav-title:hover {
    color: #4CAF50;
}

.related-links {
    background: #f8f9fa;
    padding: 2rem;
    border-radius: 12px;
}

.related-links h2 {
    color: #2E7D32;
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    text-align: center;
}

.links-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.related-link {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: white;
    border-radius: 8px;
    text-decoration: none;
    color: #333;
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.related-link:hover {
    background: #4CAF50;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
}

.link-icon {
    margin-right: 0.8rem;
    font-size: 1.2rem;
}

@media (max-width: 768px) {
    .notice-meta {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .notice-header h1 {
        font-size: 1.5rem;
    }
    
    .notice-content,
    .notice-header {
        padding: 1.5rem;
    }
    
    .notice-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .btn {
        width: 200px;
    }
    
    .links-grid {
        grid-template-columns: 1fr;
    }
}
</style>