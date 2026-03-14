<?php
// Initialize session and auth before any output
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$search = $_GET['search'] ?? '';
$date_filter = $_GET['date'] ?? '';

try {
    $pdo = Database::getInstance()->getConnection();
    
    // 식물 분석 결과 테이블이 없으면 생성
    $pdo->exec("CREATE TABLE IF NOT EXISTS plant_analysis_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        image_path VARCHAR(500) NOT NULL,
        original_filename VARCHAR(255),
        analysis_result JSON,
        confidence_score DECIMAL(5,2),
        plant_species VARCHAR(255),
        health_status ENUM('healthy', 'disease', 'pest', 'nutrient_deficiency', 'unknown') DEFAULT 'unknown',
        recommendations TEXT,
        processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processing_time_ms INT DEFAULT 0,
        INDEX idx_user_id (user_id),
        INDEX idx_processed_at (processed_at),
        INDEX idx_plant_species (plant_species)
    )");
    
    // 센서 데이터 테이블
    $pdo->exec("CREATE TABLE IF NOT EXISTS sensor_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id VARCHAR(50),
        temperature DECIMAL(5,2),
        humidity DECIMAL(5,2),
        light_intensity INT,
        soil_moisture DECIMAL(5,2),
        ph_level DECIMAL(4,2),
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_device_id (device_id),
        INDEX idx_recorded_at (recorded_at)
    )");
    
    $where_conditions = ["1=1"];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(plant_species LIKE ? OR original_filename LIKE ? OR health_status LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }
    
    if ($date_filter) {
        $where_conditions[] = "DATE(processed_at) = ?";
        $params[] = $date_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $count_sql = "SELECT COUNT(*) FROM plant_analysis_results WHERE $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_analyses = $stmt->fetchColumn();
    
    $total_pages = ceil($total_analyses / $per_page);
    
    // Fix LIMIT/OFFSET binding issue
    $per_page_int = (int) $per_page;
    $offset_int = (int) $offset;
    $sql = "SELECT par.*, u.name as user_name, u.email as user_email 
            FROM plant_analysis_results par 
            LEFT JOIN users u ON par.user_id = u.id 
            WHERE $where_clause 
            ORDER BY par.processed_at DESC 
            LIMIT $per_page_int OFFSET $offset_int";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $analyses = $stmt->fetchAll();
    
    // 통계 데이터
    $stats_sql = "SELECT 
        COUNT(*) as total_count,
        AVG(confidence_score) as avg_confidence,
        SUM(CASE WHEN health_status = 'healthy' THEN 1 ELSE 0 END) as healthy_count,
        SUM(CASE WHEN health_status = 'disease' THEN 1 ELSE 0 END) as disease_count,
        SUM(CASE WHEN health_status = 'pest' THEN 1 ELSE 0 END) as pest_count,
        AVG(processing_time_ms) as avg_processing_time
        FROM plant_analysis_results";
    $stats = $pdo->query($stats_sql)->fetch();
    
    // 최근 센서 데이터
    $sensor_sql = "SELECT * FROM sensor_data ORDER BY recorded_at DESC LIMIT 5";
    $recent_sensors = $pdo->query($sensor_sql)->fetchAll();
    
} catch (Exception $e) {
    $error = "분석 데이터를 불러오는데 실패했습니다.";
    $analyses = [];
    $total_analyses = 0;
    $total_pages = 0;
    $stats = ['total_count' => 0, 'avg_confidence' => 0, 'healthy_count' => 0, 'disease_count' => 0, 'pest_count' => 0, 'avg_processing_time' => 0];
    $recent_sensors = [];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>식물 분석 현황 - 탄생 관리자</title>
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
                        <h1>식물 분석 현황</h1>
                        <p>AI 식물 분석 결과와 센서 데이터를 관리합니다</p>
                    </div>
                    <div class="page-actions">
                        <a href="user_permissions.php" class="btn btn-secondary">권한 관리</a>
                        <a href="analysis_logs.php" class="btn btn-outline">분석 로그</a>
                    </div>
                </div>

                <!-- 통계 카드 -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">🔬</div>
                        <div class="stat-content">
                            <div class="stat-number"><?= number_format($stats['total_count']) ?></div>
                            <div class="stat-label">총 분석 건수</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🎯</div>
                        <div class="stat-content">
                            <div class="stat-number"><?= number_format($stats['avg_confidence'], 1) ?>%</div>
                            <div class="stat-label">평균 정확도</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-content">
                            <div class="stat-number"><?= number_format($stats['healthy_count']) ?></div>
                            <div class="stat-label">건강한 식물</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⚠️</div>
                        <div class="stat-content">
                            <div class="stat-number"><?= number_format($stats['disease_count']) ?></div>
                            <div class="stat-label">질병 발견</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⚡</div>
                        <div class="stat-content">
                            <div class="stat-number"><?= number_format($stats['avg_processing_time']) ?>ms</div>
                            <div class="stat-label">평균 처리 시간</div>
                        </div>
                    </div>
                </div>

                <!-- 최근 센서 데이터 -->
                <?php if (!empty($recent_sensors)): ?>
                <div class="admin-card">
                    <div class="card-header">
                        <h3>최근 센서 데이터</h3>
                    </div>
                    <div class="card-body">
                        <div class="sensor-grid">
                            <?php foreach ($recent_sensors as $sensor): ?>
                                <div class="sensor-card">
                                    <div class="sensor-device">장치 <?= htmlspecialchars($sensor['device_id'] ?? 'Unknown') ?></div>
                                    <div class="sensor-data">
                                        <div class="sensor-item">
                                            <span class="sensor-label">온도:</span>
                                            <span class="sensor-value"><?= number_format($sensor['temperature'], 1) ?>°C</span>
                                        </div>
                                        <div class="sensor-item">
                                            <span class="sensor-label">습도:</span>
                                            <span class="sensor-value"><?= number_format($sensor['humidity'], 1) ?>%</span>
                                        </div>
                                        <div class="sensor-item">
                                            <span class="sensor-label">토양수분:</span>
                                            <span class="sensor-value"><?= number_format($sensor['soil_moisture'], 1) ?>%</span>
                                        </div>
                                        <div class="sensor-item">
                                            <span class="sensor-label">pH:</span>
                                            <span class="sensor-value"><?= number_format($sensor['ph_level'], 1) ?></span>
                                        </div>
                                    </div>
                                    <div class="sensor-time"><?= date('m-d H:i', strtotime($sensor['recorded_at'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="admin-card">
                    <div class="card-header">
                        <h3>분석 결과 목록</h3>
                        <div class="search-form">
                            <form method="get" class="admin-search">
                                <input type="date" name="date" value="<?= htmlspecialchars($date_filter) ?>" class="form-input">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="식물종, 파일명, 상태로 검색" class="form-input">
                                <button type="submit" class="btn btn-primary">검색</button>
                                <a href="?" class="btn btn-outline">전체</a>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if (!empty($analyses)): ?>
                            <div class="table-responsive">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th width="80">이미지</th>
                                            <th width="100">사용자</th>
                                            <th width="120">식물종</th>
                                            <th width="80">건강상태</th>
                                            <th width="80">정확도</th>
                                            <th width="100">처리시간</th>
                                            <th width="120">분석일시</th>
                                            <th width="80">관리</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($analyses as $analysis): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($analysis['image_path'] && file_exists($_SERVER['DOCUMENT_ROOT'] . $analysis['image_path'])): ?>
                                                        <img src="<?= htmlspecialchars($analysis['image_path']) ?>" 
                                                             alt="분석 이미지" class="analysis-thumb"
                                                             onclick="showImageModal('<?= htmlspecialchars($analysis['image_path']) ?>')">
                                                    <?php else: ?>
                                                        <div class="no-image">🌱</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($analysis['user_name']): ?>
                                                        <div class="user-info">
                                                            <div class="user-name"><?= htmlspecialchars($analysis['user_name']) ?></div>
                                                            <div class="user-email"><?= htmlspecialchars($analysis['user_email']) ?></div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="anonymous">익명</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($analysis['plant_species']): ?>
                                                        <span class="plant-species"><?= htmlspecialchars($analysis['plant_species']) ?></span>
                                                    <?php else: ?>
                                                        <span class="unknown">미확인</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="health-badge health-<?= $analysis['health_status'] ?>">
                                                        <?php
                                                        switch($analysis['health_status']) {
                                                            case 'healthy': echo '건강함'; break;
                                                            case 'disease': echo '질병'; break;
                                                            case 'pest': echo '해충'; break;
                                                            case 'nutrient_deficiency': echo '영양부족'; break;
                                                            default: echo '미확인';
                                                        }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td class="confidence-cell">
                                                    <?= number_format($analysis['confidence_score'], 1) ?>%
                                                </td>
                                                <td><?= number_format($analysis['processing_time_ms']) ?>ms</td>
                                                <td><?= date('m-d H:i', strtotime($analysis['processed_at'])) ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button onclick="showAnalysisDetail(<?= $analysis['id'] ?>)" 
                                                               class="btn btn-sm btn-outline" title="상세보기">👁️</button>
                                                        <a href="analysis_export.php?id=<?= $analysis['id'] ?>" 
                                                           class="btn btn-sm btn-secondary" title="내보내기">📊</a>
                                                    </div>
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
                                            <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&date=<?= $date_filter ?>" 
                                               class="pagination-link">이전</a>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page-5); $i <= min($total_pages, $page+5); $i++): ?>
                                            <?php if ($i == $page): ?>
                                                <span class="pagination-current"><?= $i ?></span>
                                            <?php else: ?>
                                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&date=<?= $date_filter ?>" 
                                                   class="pagination-link"><?= $i ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&date=<?= $date_filter ?>" 
                                               class="pagination-link">다음</a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pagination-info">
                                        총 <?= number_format($total_analyses) ?>건 중 <?= ($page-1)*$per_page+1 ?>-<?= min($page*$per_page, $total_analyses) ?>건
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <div class="no-data-icon">🔬</div>
                                <div class="no-data-text">
                                    <?= $search || $date_filter ? '검색 조건에 맞는 분석 결과가 없습니다.' : '분석 결과가 없습니다.' ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- 이미지 모달 -->
    <div id="imageModal" class="modal" style="display: none;" onclick="closeModal('imageModal')">
        <div class="modal-content image-modal-content" onclick="event.stopPropagation()">
            <span class="modal-close" onclick="closeModal('imageModal')">&times;</span>
            <img id="modalImage" src="" alt="분석 이미지">
        </div>
    </div>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/admin.js"></script>
    <script>
        function showImageModal(imagePath) {
            document.getElementById('modalImage').src = imagePath;
            showModal('imageModal');
        }
        
        function showAnalysisDetail(analysisId) {
            // TODO: 분석 상세 정보 모달 구현
            alert('분석 ID: ' + analysisId + '의 상세 정보를 표시합니다.');
        }
        
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = '';
        }
    </script>
</body>
</html>