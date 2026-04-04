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
        body {
            overflow-y: auto;
            height: auto;
            min-height: 100vh;
        }

        .board-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background: #f9f9f9;
            min-height: calc(100vh - 200px);
            padding-bottom: 100px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 3rem 0;
            background: linear-gradient(135deg, #E8F5E8 0%, #C8E6C9 100%);
            border-radius: 12px;
        }
        .page-header h1 {
            font-size: 2.5rem;
            color: #2E7D32;
            margin-bottom: 1rem;
        }
        .page-header p {
            font-size: 1.1rem;
            color: #555;
        }

        .board-actions-bar {
            text-align: right;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .board-container {
                padding: 0 15px;
            }

            .page-header {
                padding: 1.5rem 1rem !important;
            }
            .page-header h1 {
                font-size: 1.5rem !important;
            }
            .page-header p {
                font-size: 0.9rem !important;
            }

            .btn {
                background: none !important;
                border: none !important;
                padding: 0 !important;
                color: #4CAF50 !important;
                text-decoration: underline !important;
                cursor: pointer;
                font-size: 0.8rem;
            }

            .search-section {
                padding: 15px;
            }
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
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .board-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
            font-size: 14px;
        }
        
        .board-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .post-number {
            font-weight: 600;
            color: #666;
            font-size: 12px;
        }
        
        .post-title {
            color: #333;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
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
            margin-bottom: 40px;
            position: relative;
            z-index: 10;
            display: block !important;
            visibility: visible !important;
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
        
        /* 모바일 카드 스타일 */
        .board-list-mobile {
            display: none;
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

            /* 테이블 숨기고 카드 보이기 */
            .board-table {
                display: none;
            }

            .board-list-mobile {
                display: block;
            }

            .board-card {
                background: white;
                border-radius: 8px;
                padding: 16px;
                margin-bottom: 12px;
                border-left: 4px solid #2E7D32;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                transition: all 0.3s ease;
            }

            .board-card:active {
                transform: scale(0.98);
                box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            }

            .board-card-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 10px;
                gap: 8px;
            }

            .board-card-title {
                flex: 1;
                color: #333;
                text-decoration: none;
                font-weight: 600;
                font-size: 15px;
                line-height: 1.4;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .board-card-number {
                color: #999;
                font-size: 12px;
                font-weight: 500;
                white-space: nowrap;
                flex-shrink: 0;
            }

            .board-card-meta {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
                font-size: 12px;
                color: #666;
                margin-top: 8px;
            }

            .board-card-category {
                background: #E8F5E8;
                color: #2E7D32;
                padding: 3px 8px;
                border-radius: 4px;
                font-weight: 600;
                font-size: 11px;
            }

            .board-card-author {
                display: flex;
                align-items: center;
                gap: 4px;
            }

            .board-card-date {
                display: flex;
                align-items: center;
                gap: 4px;
            }

            .board-card-views {
                display: flex;
                align-items: center;
                gap: 4px;
                margin-left: auto;
            }

            .board-card-badges {
                display: flex;
                gap: 4px;
                margin-bottom: 8px;
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
        <div class="page-header">
            <h1>게시판</h1>
            <p>총 <?= number_format($total_posts) ?>개의 게시글이 있습니다</p>
        </div>

        <div class="board-actions-bar">
            <a href="write.php" class="btn btn-success">글쓰기</a>
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
                <!-- 모바일 카드 리스트 -->
                <div class="board-list-mobile">
                    <?php foreach ($posts as $index => $post): ?>
                        <div class="board-card">
                            <?php if ($post['is_notice'] || $post['is_featured']): ?>
                                <div class="board-card-badges">
                                    <?php if ($post['is_notice']): ?>
                                        <span class="notice-badge">공지</span>
                                    <?php endif; ?>
                                    <?php if ($post['is_featured']): ?>
                                        <span class="review-badge">추천</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="board-card-header">
                                <a href="view.php?id=<?= $post['id'] ?>" class="board-card-title">
                                    <?= htmlspecialchars($post['title']) ?>
                                </a>
                                <span class="board-card-number">#<?= $total_posts - ($page - 1) * $per_page - $index ?></span>
                            </div>

                            <div class="board-card-meta">
                                <span class="board-card-category"><?= htmlspecialchars($post['category_name']) ?></span>
                                <span class="board-card-author">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                    </svg>
                                    <?= htmlspecialchars(mb_substr($post['author_name'] ?: '익명', 0, 6)) ?>
                                </span>
                                <span class="board-card-date">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                                    </svg>
                                    <?= date('m/d', strtotime($post['created_at'])) ?>
                                </span>
                                <span class="board-card-views">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                    </svg>
                                    <?= $post['views'] > 999 ? round($post['views']/1000, 1).'k' : $post['views'] ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- 데스크톱 테이블 -->
                <table class="board-table">
                    <thead>
                        <tr>
                            <th width="60">번호</th>
                            <th width="80">분류</th>
                            <th>제목</th>
                            <th width="100">글쓴이</th>
                            <th width="60">날짜</th>
                            <th width="60">조회</th>
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
                                        <span style="color: #2E7D32; font-size: 12px;">
                                            <?= htmlspecialchars(mb_substr($post['category_name'], 0, 4)) ?>
                                        </span>
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
                                </td>
                                <td>
                                    <div class="post-author" style="font-size: 12px;">
                                        <?= htmlspecialchars(mb_substr($post['author_name'] ?: '익명', 0, 10)) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="post-date" style="font-size: 12px;">
                                        <?= date('m/d', strtotime($post['created_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="post-views" style="font-size: 12px;">
                                        <?= $post['views'] > 999 ? round($post['views']/1000, 1).'k' : $post['views'] ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php
                        // 빈 행으로 15개 맞추기
                        $empty_rows = 15 - count($posts);
                        for ($i = 0; $i < $empty_rows; $i++): ?>
                            <tr>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
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