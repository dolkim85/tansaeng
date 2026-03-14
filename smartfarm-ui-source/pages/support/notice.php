<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
$dbConnected = false;
$notices = [];
$totalNotices = 0;
$currentPage = 1;
$itemsPerPage = 10;

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $dbConnected = true;
    
    // 페이징 처리
    $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    // 총 공지사항 수 조회
    $countResult = $db->selectOne("SELECT COUNT(*) as total FROM notices WHERE status = 'published'");
    $totalNotices = $countResult ? $countResult['total'] : 0;
    
    // 공지사항 목록 조회
    $notices = $db->select(
        "SELECT id, title, content, created_at, is_important, views 
         FROM notices 
         WHERE status = 'published' 
         ORDER BY is_important DESC, created_at DESC 
         LIMIT :limit OFFSET :offset",
        [
            'limit' => $itemsPerPage,
            'offset' => $offset
        ]
    );
    
} catch (Exception $e) {
    // 데이터베이스 연결 실패시 샘플 데이터 사용
    error_log("Database connection failed: " . $e->getMessage());
    
    // 샘플 공지사항 데이터
    $notices = [
        [
            'id' => 1,
            'title' => '2024년 신제품 출시 안내',
            'content' => '새로운 AI 기반 스마트팜 배지가 출시되었습니다.',
            'created_at' => '2024-01-15 10:00:00',
            'is_important' => 1,
            'views' => 1250
        ],
        [
            'id' => 2,
            'title' => '설날 연휴 배송 일정 안내',
            'content' => '설날 연휴 기간 중 배송 일정을 안내드립니다.',
            'created_at' => '2024-01-10 14:30:00',
            'is_important' => 1,
            'views' => 850
        ],
        [
            'id' => 3,
            'title' => '고객센터 운영시간 변경 안내',
            'content' => '고객센터 운영시간이 일부 변경되었습니다.',
            'created_at' => '2024-01-05 09:15:00',
            'is_important' => 0,
            'views' => 420
        ],
        [
            'id' => 4,
            'title' => '식물분석 서비스 정기점검 안내',
            'content' => '매월 셋째주 일요일 정기점검을 실시합니다.',
            'created_at' => '2023-12-28 16:45:00',
            'is_important' => 0,
            'views' => 380
        ],
        [
            'id' => 5,
            'title' => '대량구매 할인 혜택 안내',
            'content' => '대량구매 고객을 위한 특별 할인 혜택을 제공합니다.',
            'created_at' => '2023-12-20 11:20:00',
            'is_important' => 0,
            'views' => 650
        ]
    ];
    $totalNotices = count($notices);
}

// 페이징 계산
$totalPages = ceil($totalNotices / $itemsPerPage);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>공지사항 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main >
        <div class="container">
            <div class="page-header">
                <h1>공지사항</h1>
                <p>탄생의 새로운 소식과 중요한 안내사항을 확인하세요</p>
            </div>

            <!-- Important Notices -->
            <?php 
            $importantNotices = array_filter($notices, function($notice) {
                return $notice['is_important'] == 1;
            });
            ?>
            
            <?php if (!empty($importantNotices)): ?>
            <section class="important-notices">
                <h2>🔔 중요 공지</h2>
                <div class="important-list">
                    <?php foreach ($importantNotices as $notice): ?>
                    <div class="important-notice">
                        <div class="notice-badge">중요</div>
                        <div class="notice-content">
                            <h3><?= htmlspecialchars($notice['title']) ?></h3>
                            <p><?= htmlspecialchars(substr($notice['content'], 0, 100)) ?>...</p>
                            <div class="notice-meta">
                                <span>📅 <?= date('Y.m.d', strtotime($notice['created_at'])) ?></span>
                                <span>👁️ <?= number_format($notice['views']) ?></span>
                            </div>
                        </div>
                        <button class="read-more" onclick="showNoticeDetail(<?= $notice['id'] ?>, '<?= htmlspecialchars($notice['title']) ?>', '<?= htmlspecialchars($notice['content']) ?>', '<?= $notice['created_at'] ?>')">
                            자세히 보기
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Notice List -->
            <section class="notice-list-section">
                <div class="list-header">
                    <h2>전체 공지사항</h2>
                    <div class="list-info">
                        <span>총 <?= number_format($totalNotices) ?>건</span>
                        <span>페이지 <?= $currentPage ?> / <?= $totalPages ?></span>
                    </div>
                </div>

                <div class="notice-table">
                    <div class="table-header">
                        <div class="col-no">번호</div>
                        <div class="col-title">제목</div>
                        <div class="col-date">작성일</div>
                        <div class="col-views">조회수</div>
                    </div>
                    
                    <?php if (empty($notices)): ?>
                    <div class="no-notices">
                        <p>등록된 공지사항이 없습니다.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($notices as $index => $notice): ?>
                    <div class="table-row" onclick="showNoticeDetail(<?= $notice['id'] ?>, '<?= htmlspecialchars($notice['title']) ?>', '<?= htmlspecialchars($notice['content']) ?>', '<?= $notice['created_at'] ?>')">
                        <div class="col-no"><?= $totalNotices - ($offset + $index) ?></div>
                        <div class="col-title">
                            <?php if ($notice['is_important']): ?>
                                <span class="important-badge">중요</span>
                            <?php endif; ?>
                            <span class="notice-title"><?= htmlspecialchars($notice['title']) ?></span>
                        </div>
                        <div class="col-date"><?= date('Y.m.d', strtotime($notice['created_at'])) ?></div>
                        <div class="col-views"><?= number_format($notice['views']) ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=1" class="page-btn">처음</a>
                        <a href="?page=<?= $currentPage - 1 ?>" class="page-btn">이전</a>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <a href="?page=<?= $i ?>" class="page-btn <?= $i == $currentPage ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?= $currentPage + 1 ?>" class="page-btn">다음</a>
                        <a href="?page=<?= $totalPages ?>" class="page-btn">마지막</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </section>

            <!-- Quick Links -->
            <section class="quick-links">
                <h2>바로가기</h2>
                <div class="links-grid">
                    <a href="/pages/support/faq.php" class="quick-link">
                        <span class="link-icon">❓</span>
                        <span>자주 묻는 질문</span>
                    </a>
                    <a href="/pages/support/contact.php" class="quick-link">
                        <span class="link-icon">✉️</span>
                        <span>문의하기</span>
                    </a>
                    <a href="/pages/products/media.php" class="quick-link">
                        <span class="link-icon">📦</span>
                        <span>제품 정보</span>
                    </a>
                    <a href="/pages/company/about.php" class="quick-link">
                        <span class="link-icon">🏢</span>
                        <span>회사 소개</span>
                    </a>
                </div>
            </section>
        </div>
    </main>

    <!-- Notice Detail Modal -->
    <div id="noticeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle"></h3>
                <span class="close-modal" onclick="closeNoticeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="modal-meta">
                    <span id="modalDate"></span>
                </div>
                <div class="modal-text" id="modalContent"></div>
            </div>
            <div class="modal-footer">
                <button onclick="closeNoticeModal()">닫기</button>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
    <script>
        function showNoticeDetail(id, title, content, date) {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalContent').textContent = content;
            document.getElementById('modalDate').textContent = '작성일: ' + new Date(date).toLocaleDateString('ko-KR');
            document.getElementById('noticeModal').style.display = 'block';
            
            // 조회수 증가 (실제 구현시 AJAX로 처리)
            // incrementViews(id);
        }
        
        function closeNoticeModal() {
            document.getElementById('noticeModal').style.display = 'none';
        }
        
        // 모달 외부 클릭시 닫기
        window.onclick = function(event) {
            const modal = document.getElementById('noticeModal');
            if (event.target == modal) {
                closeNoticeModal();
            }
        }
    </script>
</body>
</html>

<style>
.notice-main {
    padding: 2rem 0;
}

.page-header {
    text-align: center;
    margin-bottom: 3rem;
    padding: 3rem 0;
    background: linear-gradient(135deg, #E8F5E8 0%, #C8E6C9 100%);
    border-radius: 12px;
}

.page-header h1 {
    font-size: 2.5rem;
    color: #2E7D32;
    margin-bottom: 1rem;
}

section {
    margin-bottom: 3rem;
}

section h2 {
    color: #2E7D32;
    font-size: 1.8rem;
    margin-bottom: 1.5rem;
}

.important-notices {
    background: #fff8e1;
    padding: 2rem;
    border-radius: 12px;
    border-left: 5px solid #ff9800;
}

.important-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.important-notice {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.notice-badge {
    background: #ff5722;
    color: white;
    padding: 0.3rem 0.8rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
}

.notice-content {
    flex: 1;
}

.notice-content h3 {
    color: #2E7D32;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

.notice-content p {
    color: #666;
    margin-bottom: 0.5rem;
    line-height: 1.4;
}

.notice-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.9rem;
    color: #999;
}

.read-more {
    background: #4CAF50;
    color: white;
    border: none;
    padding: 0.8rem 1.5rem;
    border-radius: 6px;
    cursor: pointer;
    white-space: nowrap;
    transition: background-color 0.3s ease;
}

.read-more:hover {
    background: #45a049;
}

.list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.list-info {
    display: flex;
    gap: 1rem;
    color: #666;
    font-size: 0.9rem;
}

.notice-table {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.table-header {
    display: grid;
    grid-template-columns: 80px 1fr 120px 100px;
    background: #f8f9fa;
    padding: 1rem;
    font-weight: 600;
    color: #2E7D32;
    border-bottom: 2px solid #e0e0e0;
}

.table-row {
    display: grid;
    grid-template-columns: 80px 1fr 120px 100px;
    padding: 1rem;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.table-row:hover {
    background: #f8f9fa;
}

.table-row:last-child {
    border-bottom: none;
}

.col-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.important-badge {
    background: #ff5722;
    color: white;
    padding: 0.2rem 0.5rem;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 600;
}

.notice-title {
    color: #333;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.col-date,
.col-views {
    color: #666;
    font-size: 0.9rem;
}

.no-notices {
    padding: 3rem;
    text-align: center;
    color: #666;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.page-btn {
    padding: 0.8rem 1rem;
    border: 1px solid #e0e0e0;
    background: white;
    color: #333;
    text-decoration: none;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.page-btn:hover {
    background: #f8f9fa;
    border-color: #4CAF50;
}

.page-btn.active {
    background: #4CAF50;
    color: white;
    border-color: #4CAF50;
}

.quick-links {
    background: #f8f9fa;
    padding: 3rem;
    border-radius: 12px;
}

.links-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.quick-link {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: white;
    border-radius: 8px;
    text-decoration: none;
    color: #333;
    transition: all 0.3s ease;
}

.quick-link:hover {
    background: #4CAF50;
    color: white;
    transform: translateY(-2px);
}

.link-icon {
    margin-right: 0.5rem;
    font-size: 1.2rem;
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
    background: white;
    margin: 5% auto;
    padding: 0;
    width: 90%;
    max-width: 600px;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #2E7D32;
}

.close-modal {
    font-size: 2rem;
    cursor: pointer;
    color: #999;
}

.close-modal:hover {
    color: #333;
}

.modal-body {
    padding: 1.5rem;
}

.modal-meta {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #f0f0f0;
}

.modal-text {
    color: #333;
    line-height: 1.6;
    white-space: pre-wrap;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #e0e0e0;
    text-align: right;
}

.modal-footer button {
    background: #4CAF50;
    color: white;
    border: none;
    padding: 0.8rem 1.5rem;
    border-radius: 4px;
    cursor: pointer;
}

@media (max-width: 768px) {
    .page-header {
        height: 100px !important;
        padding: 1.5rem 1rem !important;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .table-header,
    .table-row {
        grid-template-columns: 60px 1fr 80px;
    }

    .col-views {
        display: none;
    }

    .important-notice {
        flex-direction: column;
        align-items: stretch;
    }

    .list-header {
        flex-direction: column;
        gap: 1rem;
    }

    .modal-content {
        width: 95%;
        margin: 10% auto;
    }
}
</style>