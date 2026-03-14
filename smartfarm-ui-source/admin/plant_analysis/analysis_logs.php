<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;
$search = $_GET['search'] ?? '';
$date_filter = $_GET['date'] ?? '';
$action_filter = $_GET['action'] ?? 'all';

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Create plant_analysis_logs table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS plant_analysis_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        INDEX idx_created_at (created_at)
    )");
    
    $where_conditions = ["1=1"];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(action LIKE ? OR details LIKE ? OR ip_address LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }
    
    if ($date_filter) {
        $where_conditions[] = "DATE(created_at) = ?";
        $params[] = $date_filter;
    }
    
    if ($action_filter !== 'all') {
        $where_conditions[] = "action = ?";
        $params[] = $action_filter;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $count_sql = "SELECT COUNT(*) FROM plant_analysis_logs $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_logs = $stmt->fetchColumn();
    
    $total_pages = ceil($total_logs / $per_page);
    
    // Get logs with user information
    $per_page_int = (int) $per_page;
    $offset_int = (int) $offset;
    $sql = "SELECT pal.*, u.name as user_name, u.email as user_email 
            FROM plant_analysis_logs pal 
            LEFT JOIN users u ON pal.user_id = u.id 
            $where_clause 
            ORDER BY pal.created_at DESC 
            LIMIT $per_page_int OFFSET $offset_int";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Get action statistics
    $stats_sql = "SELECT 
        action,
        COUNT(*) as count,
        MAX(created_at) as last_occurrence
        FROM plant_analysis_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY action 
        ORDER BY count DESC";
    $action_stats = $pdo->query($stats_sql)->fetchAll();
    
} catch (Exception $e) {
    $error = "로그 데이터를 불러오는데 실패했습니다: " . $e->getMessage();
    $logs = [];
    $total_logs = 0;
    $total_pages = 0;
    $action_stats = [];
}

// Define action descriptions
$action_descriptions = [
    'access_main' => '메인 페이지 접근',
    'image_upload' => '이미지 업로드',
    'analysis_request' => '분석 요청',
    'analysis_complete' => '분석 완료',
    'analysis_view' => '분석 결과 조회',
    'export_data' => '데이터 내보내기',
    'permission_denied' => '권한 거부',
    'error_occurred' => '오류 발생'
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>식물분석 로그 - 탄생 관리자</title>
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
                        <h1>📋 식물분석 로그</h1>
                        <p>식물분석 시스템의 사용자 활동 로그를 조회합니다</p>
                    </div>
                    <div class="page-actions">
                        <a href="index.php" class="btn btn-outline">분석 현황</a>
                        <a href="user_permissions.php" class="btn btn-secondary">권한 관리</a>
                    </div>
                </div>

                <!-- 액션 통계 -->
                <?php if (!empty($action_stats)): ?>
                <div class="stats-section">
                    <h3>최근 30일 활동 통계</h3>
                    <div class="action-stats-grid">
                        <?php foreach (array_slice($action_stats, 0, 6) as $stat): ?>
                            <div class="action-stat-card">
                                <div class="action-name">
                                    <?= $action_descriptions[$stat['action']] ?? $stat['action'] ?>
                                </div>
                                <div class="action-count"><?= number_format($stat['count']) ?>회</div>
                                <div class="action-last">
                                    마지막: <?= date('m-d H:i', strtotime($stat['last_occurrence'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="admin-card">
                    <div class="card-header">
                        <h3>활동 로그</h3>
                        <div class="search-form">
                            <form method="get" class="admin-search">
                                <input type="date" name="date" value="<?= htmlspecialchars($date_filter) ?>" class="form-input">
                                <select name="action" class="form-select">
                                    <option value="all" <?= $action_filter === 'all' ? 'selected' : '' ?>>모든 액션</option>
                                    <?php foreach ($action_descriptions as $action => $desc): ?>
                                        <option value="<?= $action ?>" <?= $action_filter === $action ? 'selected' : '' ?>><?= $desc ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="액션, 상세내용, IP 주소로 검색" class="form-input">
                                <button type="submit" class="btn btn-primary">검색</button>
                                <a href="?" class="btn btn-outline">전체</a>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if (!empty($logs)): ?>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th width="120">시간</th>
                                            <th width="100">사용자</th>
                                            <th width="120">액션</th>
                                            <th>상세 내용</th>
                                            <th width="120">IP 주소</th>
                                            <th width="80">관리</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log): ?>
                                            <tr class="log-row log-<?= $log['action'] ?>">
                                                <td class="log-time">
                                                    <?= date('m-d H:i:s', strtotime($log['created_at'])) ?>
                                                </td>
                                                <td>
                                                    <?php if ($log['user_name']): ?>
                                                        <div class="user-info">
                                                            <div class="user-name"><?= htmlspecialchars($log['user_name']) ?></div>
                                                            <div class="user-email"><?= htmlspecialchars($log['user_email']) ?></div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="anonymous">익명</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="action-badge action-<?= $log['action'] ?>">
                                                        <?= $action_descriptions[$log['action']] ?? $log['action'] ?>
                                                    </span>
                                                </td>
                                                <td class="log-details">
                                                    <?php if ($log['details']): ?>
                                                        <div class="details-content" onclick="toggleDetails(this)">
                                                            <div class="details-preview"><?= htmlspecialchars(mb_strimwidth($log['details'], 0, 100, '...')) ?></div>
                                                            <div class="details-full" style="display: none;"><?= htmlspecialchars($log['details']) ?></div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="no-details">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="log-ip">
                                                    <?= htmlspecialchars($log['ip_address'] ?? '-') ?>
                                                </td>
                                                <td>
                                                    <?php if ($log['user_agent']): ?>
                                                        <button onclick="showUserAgent('<?= htmlspecialchars(addslashes($log['user_agent'])) ?>')" 
                                                                class="btn btn-sm btn-outline" title="브라우저 정보">🌐</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if ($total_pages > 1): ?>
                                <div class="pagination-wrapper">
                                    <div class="pagination">
                                        <?php if ($page > 1): ?>
                                            <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&date=<?= $date_filter ?>&action=<?= $action_filter ?>" 
                                               class="pagination-link">이전</a>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page-5); $i <= min($total_pages, $page+5); $i++): ?>
                                            <?php if ($i == $page): ?>
                                                <span class="pagination-current"><?= $i ?></span>
                                            <?php else: ?>
                                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&date=<?= $date_filter ?>&action=<?= $action_filter ?>" 
                                                   class="pagination-link"><?= $i ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&date=<?= $date_filter ?>&action=<?= $action_filter ?>" 
                                               class="pagination-link">다음</a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pagination-info">
                                        총 <?= number_format($total_logs) ?>건 중 <?= ($page-1)*$per_page+1 ?>-<?= min($page*$per_page, $total_logs) ?>건
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <div class="no-data-icon">📋</div>
                                <div class="no-data-text">
                                    <?= $search || $date_filter || $action_filter !== 'all' ? '검색 조건에 맞는 로그가 없습니다.' : '활동 로그가 없습니다.' ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- User Agent Modal -->
    <div id="userAgentModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>브라우저 정보</h3>
                <button type="button" class="modal-close" onclick="closeModal('userAgentModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="user-agent-content" id="userAgentContent"></div>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeModal('userAgentModal')" class="btn btn-outline">닫기</button>
            </div>
        </div>
    </div>

    <script src="/assets/js/main.js"></script>
    <script>
        function toggleDetails(element) {
            const preview = element.querySelector('.details-preview');
            const full = element.querySelector('.details-full');
            
            if (full.style.display === 'none') {
                preview.style.display = 'none';
                full.style.display = 'block';
            } else {
                preview.style.display = 'block';
                full.style.display = 'none';
            }
        }
        
        function showUserAgent(userAgent) {
            document.getElementById('userAgentContent').textContent = userAgent;
            showModal('userAgentModal');
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
        .stats-section {
            margin-bottom: 30px;
        }
        
        .stats-section h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .action-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .action-stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #28a745;
        }
        
        .action-name {
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }
        
        .action-count {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 4px;
        }
        
        .action-last {
            font-size: 12px;
            color: #666;
        }
        
        .log-row {
            border-left: 3px solid transparent;
        }
        
        .log-access_main { border-left-color: #17a2b8; }
        .log-analysis_request { border-left-color: #28a745; }
        .log-analysis_complete { border-left-color: #007bff; }
        .log-permission_denied { border-left-color: #dc3545; }
        .log-error_occurred { border-left-color: #ffc107; }
        
        .log-time {
            font-family: monospace;
            font-size: 13px;
            color: #666;
        }
        
        .user-info {
            min-width: 100px;
        }
        
        .user-name {
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .user-email {
            font-size: 11px;
            color: #666;
        }
        
        .anonymous {
            color: #999;
            font-style: italic;
        }
        
        .action-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .action-access_main { background: #d1ecf1; color: #0c5460; }
        .action-image_upload { background: #d4edda; color: #155724; }
        .action-analysis_request { background: #cce4f4; color: #004085; }
        .action-analysis_complete { background: #b8daff; color: #004085; }
        .action-permission_denied { background: #f8d7da; color: #721c24; }
        .action-error_occurred { background: #fff3cd; color: #856404; }
        
        .log-details {
            max-width: 300px;
        }
        
        .details-content {
            cursor: pointer;
            position: relative;
        }
        
        .details-content:hover {
            background: #f8f9fa;
        }
        
        .no-details {
            color: #999;
            font-style: italic;
        }
        
        .log-ip {
            font-family: monospace;
            font-size: 13px;
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
            max-width: 600px;
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
        
        .user-agent-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
            word-break: break-all;
            line-height: 1.4;
        }
    </style>
</body>
</html>