<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
$auth = Auth::getInstance();

require_once $base_path . '/classes/Database.php';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;
$search = $_GET['search'] ?? '';

$posts = [];
$total_posts = 0;
$total_pages = 0;
$error = '';

try {
    $pdo = Database::getInstance()->getConnection();

    // 카테고리 목록 조회
    $category_sql = "SELECT id, name, slug, description FROM board_categories WHERE status = 'active' ORDER BY sort_order, id";
    $stmt = $pdo->prepare($category_sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 선택된 카테고리 필터
    $category_filter = $_GET['category'] ?? '';

    $where_conditions = ["b.status = 'published'"];
    $params = [];

    if ($category_filter) {
        $where_conditions[] = "c.slug = ?";
        $params[] = $category_filter;
    }

    if ($search) {
        $where_conditions[] = "(b.title LIKE ? OR b.content LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%"]);
    }

    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

    // 총 게시글 수 조회
    $count_sql = "SELECT COUNT(*) FROM boards b
                  JOIN board_categories c ON b.category_id = c.id
                  $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_posts = $stmt->fetchColumn();

    $total_pages = ceil($total_posts / $per_page);

    // 게시글 목록 조회
    $sql = "SELECT b.id, b.title, b.summary, b.views, b.is_notice, b.is_featured, b.created_at,
                   c.name as category_name, c.slug as category_slug,
                   u.name as author_name
            FROM boards b
            JOIN board_categories c ON b.category_id = c.id
            LEFT JOIN users u ON b.user_id = u.id
            $where_clause
            ORDER BY b.is_notice DESC, b.is_featured DESC, b.created_at DESC
            LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "게시글을 불러오는데 실패했습니다: " . $e->getMessage();
    $posts = [];
    $total_posts = 0;
    $total_pages = 0;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>게시판 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .board-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f9f9f9;
            min-height: 100vh;
        }
        
        .board-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .board-title {
            font-size: 2rem;
            margin: 0;
            color: #333;
        }
        
        .board-stats {
            color: #666;
            font-size: 14px;
        }
        
        .board-actions {
            display: flex;
            gap: 10px;
        }
        
        .search-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            max-width: 400px;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
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
            background-color: #1e7e34;
        }
        
        .btn-outline {
            background-color: white;
            color: #007bff;
            border: 1px solid #007bff;
        }
        
        .btn-outline:hover {
            background-color: #007bff;
            color: white;
        }
        
        .board-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .board-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .board-table th,
        .board-table td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .board-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        
        .board-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .post-number {
            font-weight: 600;
            color: #666;
        }
        
        .post-title {
            color: #333;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .post-title:hover {
            color: #007bff;
        }
        
        .notice-badge {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .review-badge {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .post-author {
            color: #666;
            font-weight: 500;
        }
        
        .post-date {
            color: #888;
            font-size: 13px;
        }
        
        .post-views {
            color: #666;
            font-weight: 500;
        }
        
        .pagination-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            text-decoration: none;
            color: #007bff;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            background-color: #e9ecef;
        }
        
        .pagination .current {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .pagination .disabled {
            color: #6c757d;
            cursor: not-allowed;
        }

        /* 모바일 최적화 스타일 */
        @media (max-width: 768px) {
            body {
                padding-top: 60px; /* 고정 헤더를 위한 상단 패딩 */
            }

            .container {
                padding: 10px;
                margin-top: 10px; /* 최소 여백만 */
            }

            .board-header {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }

            .board-title {
                font-size: 1.5rem;
                text-align: center;
            }

            .board-actions {
                justify-content: center;
                flex-wrap: wrap;
            }

            .search-section {
                padding: 15px;
                margin-bottom: 15px;
            }

            .search-form {
                flex-direction: column;
                gap: 10px;
            }

            .search-input {
                max-width: 100%;
                width: 100%;
            }

            /* 모바일에서 테이블을 카드형으로 변경 */
            .board-table {
                display: none;
            }

            .mobile-board-list {
                display: block;
            }

            .mobile-post-item {
                background: white;
                border: none;
                border-radius: 12px;
                margin-bottom: 12px;
                padding: 18px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }

            .mobile-post-item:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            }

            .mobile-post-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 12px;
            }

            .mobile-post-number {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 6px 10px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 700;
                min-width: 40px;
                text-align: center;
            }

            .mobile-post-badges {
                display: flex;
                gap: 6px;
                align-items: center;
            }

            .mobile-post-category {
                background: #28a745;
                color: white;
                padding: 5px 10px;
                border-radius: 15px;
                font-size: 11px;
                font-weight: 600;
            }

            .mobile-post-title {
                font-size: 17px;
                font-weight: 700;
                color: #1a202c;
                text-decoration: none;
                line-height: 1.5;
                margin-bottom: 10px;
                display: block;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .mobile-post-title:hover {
                color: #667eea;
            }

            .mobile-post-summary {
                font-size: 14px;
                color: #718096;
                margin-bottom: 12px;
                line-height: 1.5;
                overflow: hidden;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
            }

            .mobile-post-meta {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 12px;
                color: #a0aec0;
                border-top: 1px solid #f7fafc;
                padding-top: 10px;
            }

            .mobile-post-author-date {
                display: flex;
                gap: 12px;
                align-items: center;
            }

            .mobile-post-author {
                font-weight: 600;
                color: #4a5568;
            }

            .mobile-post-date {
                color: #a0aec0;
            }

            .mobile-post-views {
                background: #edf2f7;
                color: #4a5568;
                padding: 4px 8px;
                border-radius: 10px;
                font-size: 11px;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 3px;
            }

            .pagination {
                flex-wrap: wrap;
                gap: 3px;
            }

            .pagination a,
            .pagination span {
                padding: 6px 10px;
                font-size: 13px;
            }

            .btn {
                padding: 10px 16px;
                font-size: 13px;
            }
        }

        /* 데스크톱에서는 모바일 리스트 숨기기 */
        @media (min-width: 769px) {
            .mobile-board-list {
                display: none;
            }
        }
        
        .no-posts {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .no-posts-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .no-posts-text {
            font-size: 1.2rem;
            margin-bottom: 20px;
        }
        
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 6px;
            border-left: 4px solid;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .board-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .search-form {
                flex-direction: column;
                width: 100%;
            }
            
            .search-input {
                max-width: none;
            }
            
            .board-table th:nth-child(4),
            .board-table td:nth-child(4),
            .board-table th:nth-child(5),
            .board-table td:nth-child(5) {
                display: none;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <main class="board-container">
        <div class="board-header">
            <div>
                <h1 class="board-title">📝 게시판</h1>
                <div class="board-stats">총 <?= number_format($total_posts) ?>개의 게시글</div>
            </div>
            <div class="board-actions">
                <a href="write.php" class="btn btn-success">✏️ 글쓰기</a>
            </div>
        </div>
        
        <div class="search-section">
            <!-- 카테고리 필터 -->
            <div style="margin-bottom: 15px;">
                <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    <span style="font-weight: 600; color: #333;">카테고리:</span>
                    <a href="?<?= http_build_query(array_filter(['search' => $search])) ?>"
                       class="btn <?= !$category_filter ? 'btn-primary' : 'btn-outline' ?>" style="padding: 8px 16px; font-size: 13px;">전체</a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="?<?= http_build_query(array_filter(['category' => $cat['slug'], 'search' => $search])) ?>"
                           class="btn <?= $category_filter === $cat['slug'] ? 'btn-primary' : 'btn-outline' ?>"
                           style="padding: 8px 16px; font-size: 13px;">
                            <?= htmlspecialchars($cat['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 검색 폼 -->
            <form class="search-form" method="get">
                <?php if ($category_filter): ?>
                    <input type="hidden" name="category" value="<?= htmlspecialchars($category_filter) ?>">
                <?php endif; ?>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                       placeholder="제목, 내용으로 검색하세요..." class="search-input">
                <button type="submit" class="btn btn-primary">🔍 검색</button>
                <?php if ($search || $category_filter): ?>
                    <a href="index.php" class="btn btn-outline">전체보기</a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <strong>오류:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <div class="board-content">
            <?php if (empty($posts)): ?>
                <div class="no-posts">
                    <div class="no-posts-icon">📝</div>
                    <div class="no-posts-text">
                        <?= $search ? '검색 결과가 없습니다.' : '등록된 게시글이 없습니다.' ?>
                    </div>
                    <?php if (!$search): ?>
                        <a href="write.php" class="btn btn-success">첫 번째 글 작성하기</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <table class="board-table">
                    <thead>
                        <tr>
                            <th width="80">번호</th>
                            <th width="100">카테고리</th>
                            <th>제목</th>
                            <th width="120">작성자</th>
                            <th width="120">작성일</th>
                            <th width="80">조회</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $index => $post): ?>
                            <tr>
                                <td>
                                    <div class="post-number">
                                        <?= $total_posts - ($page - 1) * $per_page - $index ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="post-category">
                                        <a href="?category=<?= urlencode($post['category_slug']) ?>"
                                           style="color: #2E7D32; text-decoration: none; font-size: 12px;">
                                            <?= htmlspecialchars($post['category_name']) ?>
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <a href="view.php?id=<?= $post['id'] ?>" class="post-title">
                                        <?php if ($post['is_notice']): ?>
                                            <span class="notice-badge">공지</span>
                                        <?php endif; ?>
                                        <?php if ($post['is_featured']): ?>
                                            <span class="featured-badge">추천</span>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($post['title']) ?></span>
                                    </a>
                                    <?php if ($post['summary']): ?>
                                        <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                            <?= htmlspecialchars(mb_substr($post['summary'], 0, 100)) ?>...
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="post-author">
                                        <?= htmlspecialchars($post['author_name'] ?: '익명') ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="post-date">
                                        <?= date('m-d H:i', strtotime($post['created_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="post-views"><?= number_format($post['views']) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- 모바일용 카드 리스트 -->
                <div class="mobile-board-list">
                    <?php foreach ($posts as $index => $post): ?>
                        <div class="mobile-post-item">
                            <div class="mobile-post-header">
                                <div class="mobile-post-number">
                                    <?= $total_posts - ($page - 1) * $per_page - $index ?>
                                </div>
                                <div class="mobile-post-badges">
                                    <span class="mobile-post-category">
                                        <?= htmlspecialchars($post['category_name']) ?>
                                    </span>
                                    <?php if ($post['is_notice']): ?>
                                        <span class="notice-badge">공지</span>
                                    <?php endif; ?>
                                    <?php if ($post['is_featured']): ?>
                                        <span class="featured-badge">추천</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <a href="view.php?id=<?= $post['id'] ?>" class="mobile-post-title">
                                <?= htmlspecialchars($post['title']) ?>
                            </a>

                            <?php if ($post['summary']): ?>
                                <div class="mobile-post-summary">
                                    <?= htmlspecialchars(mb_substr($post['summary'], 0, 100)) ?>...
                                </div>
                            <?php endif; ?>

                            <div class="mobile-post-meta">
                                <div class="mobile-post-author-date">
                                    <span class="mobile-post-author"><?= htmlspecialchars($post['author_name'] ?: '익명') ?></span>
                                    <span class="mobile-post-date"><?= date('m.d', strtotime($post['created_at'])) ?></span>
                                </div>
                                <div class="mobile-post-views">
                                    <span>👁</span>
                                    <span><?= number_format($post['views']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination-section">
                <div class="pagination">
                    <?php
                    // 페이지 그룹 계산
                    $page_group = ceil($page / 10);
                    $start_page = ($page_group - 1) * 10 + 1;
                    $end_page = min($start_page + 9, $total_pages);
                    ?>
                    
                    <!-- 이전 그룹 -->
                    <?php if ($start_page > 1): ?>
                        <a href="?page=1<?= $search ? '&search=' . urlencode($search) : '' ?>">처음</a>
                        <a href="?page=<?= $start_page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">이전</a>
                    <?php endif; ?>
                    
                    <!-- 페이지 번호 -->
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <!-- 다음 그룹 -->
                    <?php if ($end_page < $total_pages): ?>
                        <a href="?page=<?= $end_page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">다음</a>
                        <a href="?page=<?= $total_pages ?><?= $search ? '&search=' . urlencode($search) : '' ?>">마지막</a>
                    <?php endif; ?>
                </div>
                
                <div style="text-align: center; margin-top: 15px; color: #666; font-size: 14px;">
                    <?= $page ?>페이지 / 총 <?= $total_pages ?>페이지 
                    (<?= number_format($total_posts) ?>개 게시글 중 
                    <?= ($page - 1) * $per_page + 1 ?>-<?= min($page * $per_page, $total_posts) ?>번째)
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <?php include '../../includes/footer.php'; ?>
    <script src="/assets/js/main.js"></script>
</body>
</html>