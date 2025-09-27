<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
$dbConnected = false;

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    require_once __DIR__ . '/../../classes/Database.php';
    $auth = Auth::getInstance();
    
    // Check if user is logged in and has plant analysis permission
    $auth->requirePlantAnalysisPermission();
    
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $dbConnected = true;
} catch (Exception $e) {
    // 권한이 없거나 데이터베이스 연결 실패시 접근 거부 페이지로 리다이렉트
    header('Location: /pages/plant_analysis/access_denied.php');
    exit;
}

// Get recent plant images
$recentImages = [];
$recentAnalyses = [];
$latestSensorData = null;

if ($dbConnected) {
    try {
        $recentImages = $db->select(
            "SELECT * FROM plant_images WHERE user_id = :user_id ORDER BY captured_at DESC LIMIT 10",
            ['user_id' => $currentUser['id']]
        );

        // Get recent analyses
        $recentAnalyses = $db->select(
            "SELECT pa.*, pi.filename FROM plant_analysis pa 
             LEFT JOIN plant_images pi ON pa.image_id = pi.id 
             WHERE pa.user_id = :user_id ORDER BY pa.analyzed_at DESC LIMIT 5",
            ['user_id' => $currentUser['id']]
        );

        // Get sensor data (latest)
        $latestSensorData = $db->selectOne(
            "SELECT * FROM sensor_readings ORDER BY recorded_at DESC LIMIT 1"
        );

        // Log access
        $db->insert('plant_analysis_logs', [
            'user_id' => $currentUser['id'],
            'action' => 'access_main',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        // 데이터베이스 오류시 빈 배열로 계속 진행
        error_log("Database query failed: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>식물분석 시스템 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/analysis.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main >
        <!-- Hero Section -->
        <section class="analysis-hero">
            <div class="container">
                <div class="hero-content">
                    <div class="hero-text">
                        <h1>🌱 AI 식물분석 시스템</h1>
                        <p>첨단 인공지능과 IoT 기술로 스마트한 식물 관리</p>
                        <p class="hero-description">
                            <strong><?= htmlspecialchars($currentUser['name']) ?></strong>님, 
                            라즈베리파이 카메라와 환경 센서를 활용한 통합 식물분석 시스템에 오신 것을 환영합니다!
                        </p>
                        <div class="hero-badges">
                            <span class="badge">📹 실시간 모니터링</span>
                            <span class="badge">🤖 AI 분석</span>
                            <span class="badge">📊 데이터 분석</span>
                            <span class="badge">🎛️ 환경 제어</span>
                        </div>
                    </div>
                    
                    <div class="hero-visual">
                        <div class="system-overview">
                            <div class="system-component">
                                <div class="component-icon">📷</div>
                                <h4>라즈베리파이<br>카메라</h4>
                            </div>
                            <div class="connection-line"></div>
                            <div class="system-component">
                                <div class="component-icon">🧠</div>
                                <h4>AI 분석<br>엔진</h4>
                            </div>
                            <div class="connection-line"></div>
                            <div class="system-component">
                                <div class="component-icon">📱</div>
                                <h4>모니터링<br>대시보드</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <div class="container">

            <!-- Quick Status -->
            <div class="status-grid">
                <div class="status-card">
                    <div class="status-icon">📸</div>
                    <div class="status-info">
                        <h3><?= count($recentImages) ?>장</h3>
                        <p>촬영된 이미지</p>
                    </div>
                </div>
                <div class="status-card">
                    <div class="status-icon">🔬</div>
                    <div class="status-info">
                        <h3><?= count($recentAnalyses) ?>건</h3>
                        <p>분석 완료</p>
                    </div>
                </div>
                <div class="status-card">
                    <div class="status-icon">🌡️</div>
                    <div class="status-info">
                        <h3><?= $latestSensorData ? number_format($latestSensorData['temperature'] ?? 0, 1) . '°C' : '-' ?></h3>
                        <p>현재 온도</p>
                    </div>
                </div>
                <div class="status-card">
                    <div class="status-icon">💧</div>
                    <div class="status-info">
                        <h3><?= $latestSensorData ? number_format($latestSensorData['humidity'] ?? 0, 1) . '%' : '-' ?></h3>
                        <p>현재 습도</p>
                    </div>
                </div>
            </div>

            <!-- Main Actions -->
            <div class="main-actions">
                <div class="action-grid">
                    <a href="live_view.php" class="action-card">
                        <div class="action-icon">📹</div>
                        <h3>실시간 관찰</h3>
                        <p>라즈베리파이 카메라를 통한 실시간 식물 관찰</p>
                    </a>
                    
                    <a href="image_list.php" class="action-card">
                        <div class="action-icon">🖼️</div>
                        <h3>이미지 갤러리</h3>
                        <p>촬영된 식물 이미지 목록과 관리</p>
                    </a>
                    
                    <a href="analysis_result.php" class="action-card">
                        <div class="action-icon">📊</div>
                        <h3>분석 결과</h3>
                        <p>AI 기반 식물 건강 분석 결과 확인</p>
                    </a>
                    
                    <a href="sensor_data.php" class="action-card">
                        <div class="action-icon">📈</div>
                        <h3>환경 데이터</h3>
                        <p>온도, 습도, pH, EC 등 환경 센서 데이터</p>
                    </a>
                    
                    <a href="export.php" class="action-card">
                        <div class="action-icon">📄</div>
                        <h3>데이터 내보내기</h3>
                        <p>분석 결과를 Excel, PDF로 다운로드</p>
                    </a>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="recent-activities">
                <div class="activities-grid">
                    <!-- Recent Images -->
                    <div class="activity-section">
                        <div class="section-header">
                            <h3>최근 촬영 이미지</h3>
                            <a href="image_list.php" class="view-all">전체보기</a>
                        </div>
                        <div class="recent-images">
                            <?php if (empty($recentImages)): ?>
                                <div class="no-data">
                                    <p>촬영된 이미지가 없습니다.</p>
                                    <a href="live_view.php" class="btn btn-primary btn-sm">지금 촬영하기</a>
                                </div>
                            <?php else: ?>
                                <div class="image-grid">
                                    <?php foreach (array_slice($recentImages, 0, 6) as $image): ?>
                                    <div class="image-item">
                                        <img src="/uploads/plant_images/<?= htmlspecialchars($image['filename']) ?>" 
                                             alt="식물 이미지" loading="lazy">
                                        <div class="image-overlay">
                                            <span class="image-date"><?= date('m/d H:i', strtotime($image['captured_at'])) ?></span>
                                            <span class="image-status status-<?= $image['analysis_status'] ?>"><?= $image['analysis_status'] ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Analyses -->
                    <div class="activity-section">
                        <div class="section-header">
                            <h3>최근 분석 결과</h3>
                            <a href="analysis_result.php" class="view-all">전체보기</a>
                        </div>
                        <div class="recent-analyses">
                            <?php if (empty($recentAnalyses)): ?>
                                <div class="no-data">
                                    <p>분석 결과가 없습니다.</p>
                                    <a href="image_list.php" class="btn btn-primary btn-sm">분석 시작하기</a>
                                </div>
                            <?php else: ?>
                                <div class="analysis-list">
                                    <?php foreach ($recentAnalyses as $analysis): ?>
                                    <div class="analysis-item">
                                        <div class="analysis-info">
                                            <h4><?= htmlspecialchars($analysis['species'] ?? '미분류') ?></h4>
                                            <p class="analysis-health health-<?= $analysis['health_status'] ?>">
                                                <?= ucfirst($analysis['health_status'] ?? 'unknown') ?>
                                            </p>
                                            <small><?= date('m/d H:i', strtotime($analysis['analyzed_at'])) ?></small>
                                        </div>
                                        <div class="analysis-confidence">
                                            <?= $analysis['confidence'] ? number_format($analysis['confidence'], 1) . '%' : '-' ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Environmental Data -->
            <?php if ($latestSensorData): ?>
            <div class="environmental-section">
                <h3>현재 환경 상태</h3>
                <div class="sensor-grid">
                    <div class="sensor-card">
                        <div class="sensor-icon">🌡️</div>
                        <div class="sensor-data">
                            <span class="sensor-value"><?= number_format($latestSensorData['temperature'] ?? 0, 1) ?>°C</span>
                            <span class="sensor-label">온도</span>
                        </div>
                    </div>
                    <div class="sensor-card">
                        <div class="sensor-icon">💧</div>
                        <div class="sensor-data">
                            <span class="sensor-value"><?= number_format($latestSensorData['humidity'] ?? 0, 1) ?>%</span>
                            <span class="sensor-label">습도</span>
                        </div>
                    </div>
                    <div class="sensor-card">
                        <div class="sensor-icon">💡</div>
                        <div class="sensor-data">
                            <span class="sensor-value"><?= number_format($latestSensorData['light_intensity'] ?? 0) ?></span>
                            <span class="sensor-label">광량 (lux)</span>
                        </div>
                    </div>
                    <div class="sensor-card">
                        <div class="sensor-icon">⚗️</div>
                        <div class="sensor-data">
                            <span class="sensor-value"><?= number_format($latestSensorData['ph_level'] ?? 0, 1) ?></span>
                            <span class="sensor-label">pH</span>
                        </div>
                    </div>
                    <div class="sensor-card">
                        <div class="sensor-icon">⚡</div>
                        <div class="sensor-data">
                            <span class="sensor-value"><?= number_format($latestSensorData['ec_level'] ?? 0, 1) ?></span>
                            <span class="sensor-label">EC</span>
                        </div>
                    </div>
                </div>
                <div class="sensor-update">
                    <small>마지막 업데이트: <?= date('Y-m-d H:i:s', strtotime($latestSensorData['recorded_at'])) ?></small>
                    <button onclick="refreshSensorData()" class="btn btn-outline btn-sm">새로고침</button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tips -->
            <div class="tips-section">
                <h3>💡 사용 팁</h3>
                <div class="tips-grid">
                    <div class="tip-item">
                        <h4>최적의 촬영 시간</h4>
                        <p>자연광이 충분한 오전 10시~오후 2시 사이에 촬영하면 더 정확한 분석 결과를 얻을 수 있습니다.</p>
                    </div>
                    <div class="tip-item">
                        <h4>정기적인 모니터링</h4>
                        <p>식물의 변화를 추적하기 위해 주 2-3회 정기적으로 촬영하고 분석하는 것을 권장합니다.</p>
                    </div>
                    <div class="tip-item">
                        <h4>환경 데이터 활용</h4>
                        <p>온도, 습도 등의 환경 데이터를 함께 확인하여 식물의 건강상태를 종합적으로 판단하세요.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/analysis.js"></script>
</body>
</html>