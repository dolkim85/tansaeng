<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
$dbConnected = false;
$analyses = [];
$stats = [];

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = Auth::getInstance();
    
    // Check if user is logged in and has plant analysis permission
    $auth->requirePlantAnalysisPermission();
    
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $dbConnected = true;
    
    // Get user's analysis results
    $analyses = $db->select(
        "SELECT pa.*, pi.filename, pi.original_filename, pi.captured_at 
         FROM plant_analysis pa 
         LEFT JOIN plant_images pi ON pa.image_id = pi.id 
         WHERE pa.user_id = :user_id 
         ORDER BY pa.analyzed_at DESC",
        ['user_id' => $currentUser['id']]
    );
    
    // Get analysis statistics
    $stats = [
        'total' => count($analyses),
        'healthy' => count(array_filter($analyses, function($a) { return $a['health_status'] === 'healthy'; })),
        'warning' => count(array_filter($analyses, function($a) { return $a['health_status'] === 'warning'; })),
        'critical' => count(array_filter($analyses, function($a) { return $a['health_status'] === 'critical'; })),
        'avg_confidence' => $analyses ? array_sum(array_column($analyses, 'confidence')) / count($analyses) : 0
    ];
    
} catch (Exception $e) {
    if (strpos($e->getMessage(), '권한') !== false || strpos($e->getMessage(), '로그인') !== false) {
        header('Location: /pages/plant_analysis/access_denied.php');
        exit;
    }
    
    // Fallback data for demo
    $analyses = [
        [
            'id' => 1,
            'species' => '토마토',
            'health_status' => 'healthy',
            'confidence' => 95.5,
            'recommendations' => '현재 상태가 매우 양호합니다. 정기적인 물 공급과 적절한 일조량을 유지하세요.',
            'analyzed_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'filename' => 'demo_plant_1.jpg',
            'original_filename' => '토마토_001.jpg',
            'captured_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'analysis_data' => json_encode([
                'leaf_health' => 92,
                'color_analysis' => 88,
                'growth_stage' => 'mature',
                'diseases_detected' => []
            ])
        ],
        [
            'id' => 2,
            'species' => '파프리카',
            'health_status' => 'warning',
            'confidence' => 88.2,
            'recommendations' => '잎에 약간의 황화 현상이 관찰됩니다. 질소 부족이 의심되므로 영양 공급을 확인해보세요.',
            'analyzed_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'filename' => 'demo_plant_3.jpg',
            'original_filename' => '파프리카_003.jpg',
            'captured_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'analysis_data' => json_encode([
                'leaf_health' => 75,
                'color_analysis' => 70,
                'growth_stage' => 'growing',
                'diseases_detected' => ['nitrogen_deficiency']
            ])
        ],
        [
            'id' => 3,
            'species' => '상추',
            'health_status' => 'critical',
            'confidence' => 76.8,
            'recommendations' => '심각한 병충해가 발견되었습니다. 즉시 전문가의 조치가 필요합니다.',
            'analyzed_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'filename' => 'demo_plant_2.jpg',
            'original_filename' => '상추_002.jpg',
            'captured_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'analysis_data' => json_encode([
                'leaf_health' => 45,
                'color_analysis' => 40,
                'growth_stage' => 'declining',
                'diseases_detected' => ['aphids', 'leaf_spot']
            ])
        ]
    ];
    
    $stats = [
        'total' => 3,
        'healthy' => 1,
        'warning' => 1,
        'critical' => 1,
        'avg_confidence' => 86.8
    ];
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$totalAnalyses = count($analyses);
$totalPages = ceil($totalAnalyses / $limit);
$paginatedAnalyses = array_slice($analyses, $offset, $limit);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>분석 결과 - 식물분석 시스템</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/analysis.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main >
        <div class="container">
            <div class="page-header">
                <nav class="breadcrumb">
                    <a href="/pages/plant_analysis/">식물분석</a> > 분석 결과
                </nav>
                <h1>📊 AI 식물분석 결과</h1>
                <p>인공지능이 분석한 식물 건강상태 결과를 확인하고 관리 방법을 제안받으세요.</p>
            </div>

            <!-- Analysis Statistics -->
            <div class="analysis-stats">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">🔬</div>
                        <div class="stat-info">
                            <h3><?= $stats['total'] ?>건</h3>
                            <p>총 분석 횟수</p>
                        </div>
                    </div>
                    <div class="stat-card healthy">
                        <div class="stat-icon">✅</div>
                        <div class="stat-info">
                            <h3><?= $stats['healthy'] ?>건</h3>
                            <p>건강함</p>
                        </div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-icon">⚠️</div>
                        <div class="stat-info">
                            <h3><?= $stats['warning'] ?>건</h3>
                            <p>주의 필요</p>
                        </div>
                    </div>
                    <div class="stat-card critical">
                        <div class="stat-icon">🚨</div>
                        <div class="stat-info">
                            <h3><?= $stats['critical'] ?>건</h3>
                            <p>위험</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🎯</div>
                        <div class="stat-info">
                            <h3><?= number_format($stats['avg_confidence'], 1) ?>%</h3>
                            <p>평균 신뢰도</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter & Export -->
            <div class="results-controls">
                <div class="filter-group">
                    <select id="healthFilter" onchange="filterResults()">
                        <option value="">모든 상태</option>
                        <option value="healthy">건강함</option>
                        <option value="warning">주의필요</option>
                        <option value="critical">위험</option>
                    </select>
                    
                    <select id="speciesFilter" onchange="filterResults()">
                        <option value="">모든 식물</option>
                        <option value="토마토">토마토</option>
                        <option value="상추">상추</option>
                        <option value="파프리카">파프리카</option>
                        <option value="오이">오이</option>
                    </select>
                    
                    <input type="date" id="dateFromFilter" placeholder="시작날짜" onchange="filterResults()">
                    <input type="date" id="dateToFilter" placeholder="종료날짜" onchange="filterResults()">
                </div>
                
                <div class="action-group">
                    <button onclick="exportResults('csv')" class="btn btn-outline">📄 CSV 내보내기</button>
                    <button onclick="exportResults('pdf')" class="btn btn-outline">📑 PDF 내보내기</button>
                    <button onclick="generateReport()" class="btn btn-primary">📊 리포트 생성</button>
                </div>
            </div>

            <!-- Analysis Results -->
            <?php if (empty($paginatedAnalyses)): ?>
                <div class="no-results">
                    <div class="no-data-icon">🔬</div>
                    <h3>분석 결과가 없습니다</h3>
                    <p>식물 이미지를 촬영하고 분석을 실행해보세요.</p>
                    <a href="live_view.php" class="btn btn-primary">지금 촬영하기</a>
                </div>
            <?php else: ?>
                <div class="results-list">
                    <?php foreach ($paginatedAnalyses as $analysis): ?>
                    <div class="result-card" data-health="<?= $analysis['health_status'] ?>" data-species="<?= $analysis['species'] ?>" data-date="<?= date('Y-m-d', strtotime($analysis['analyzed_at'])) ?>">
                        <div class="result-header">
                            <div class="result-image">
                                <img src="/assets/images/products/placeholder.jpg" alt="<?= htmlspecialchars($analysis['original_filename'] ?? 'Plant') ?>">
                            </div>
                            <div class="result-basic">
                                <h3><?= htmlspecialchars($analysis['species'] ?? '미분류') ?></h3>
                                <p class="filename"><?= htmlspecialchars($analysis['original_filename'] ?? 'Unknown') ?></p>
                                <div class="result-meta">
                                    <span class="analyzed-time">🕐 <?= date('Y-m-d H:i', strtotime($analysis['analyzed_at'])) ?></span>
                                    <span class="confidence">🎯 신뢰도 <?= number_format($analysis['confidence'], 1) ?>%</span>
                                </div>
                            </div>
                            <div class="health-status-large health-<?= $analysis['health_status'] ?>">
                                <?php
                                $healthLabels = [
                                    'healthy' => '건강함',
                                    'warning' => '주의필요',
                                    'critical' => '위험',
                                    'unknown' => '미확인'
                                ];
                                $healthIcons = [
                                    'healthy' => '✅',
                                    'warning' => '⚠️',
                                    'critical' => '🚨',
                                    'unknown' => '❓'
                                ];
                                ?>
                                <span class="health-icon"><?= $healthIcons[$analysis['health_status']] ?? '❓' ?></span>
                                <span class="health-label"><?= $healthLabels[$analysis['health_status']] ?? $analysis['health_status'] ?></span>
                            </div>
                        </div>

                        <div class="result-content">
                            <div class="recommendations">
                                <h4>🌱 관리 권장사항</h4>
                                <p><?= htmlspecialchars($analysis['recommendations'] ?? '권장사항이 없습니다.') ?></p>
                            </div>

                            <?php if ($analysis['analysis_data']): ?>
                            <?php $analysisData = json_decode($analysis['analysis_data'], true); ?>
                            <div class="detailed-analysis">
                                <h4>🔍 상세 분석 결과</h4>
                                <div class="analysis-metrics">
                                    <?php if (isset($analysisData['leaf_health'])): ?>
                                    <div class="metric">
                                        <label>잎 건강도</label>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?= $analysisData['leaf_health'] ?>%"></div>
                                            <span><?= $analysisData['leaf_health'] ?>%</span>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (isset($analysisData['color_analysis'])): ?>
                                    <div class="metric">
                                        <label>색상 분석</label>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?= $analysisData['color_analysis'] ?>%"></div>
                                            <span><?= $analysisData['color_analysis'] ?>%</span>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (isset($analysisData['growth_stage'])): ?>
                                    <div class="metric">
                                        <label>성장 단계</label>
                                        <span class="growth-stage"><?= htmlspecialchars($analysisData['growth_stage']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($analysisData['diseases_detected'])): ?>
                                <div class="diseases-detected">
                                    <h5>⚠️ 발견된 질병/문제</h5>
                                    <ul>
                                        <?php foreach ($analysisData['diseases_detected'] as $disease): ?>
                                        <li><?= htmlspecialchars($disease) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="result-actions">
                            <button onclick="viewFullAnalysis(<?= $analysis['id'] ?>)" class="btn btn-outline btn-sm">상세보기</button>
                            <button onclick="reanalyze(<?= $analysis['id'] ?>)" class="btn btn-outline btn-sm">재분석</button>
                            <button onclick="shareAnalysis(<?= $analysis['id'] ?>)" class="btn btn-outline btn-sm">공유</button>
                            <button onclick="deleteAnalysis(<?= $analysis['id'] ?>)" class="btn btn-danger btn-sm">삭제</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="btn btn-outline">이전</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="btn btn-primary"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>" class="btn btn-outline"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="btn btn-outline">다음</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <!-- Analysis Detail Modal -->
    <div id="analysisModal" class="modal" style="display: none;">
        <div class="modal-content large">
            <div class="modal-header">
                <h3 id="modalTitle">상세 분석 결과</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalContent">
                <!-- Detailed analysis content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
    function filterResults() {
        const healthFilter = document.getElementById('healthFilter').value;
        const speciesFilter = document.getElementById('speciesFilter').value;
        const dateFromFilter = document.getElementById('dateFromFilter').value;
        const dateToFilter = document.getElementById('dateToFilter').value;
        
        const cards = document.querySelectorAll('.result-card');
        
        cards.forEach(card => {
            let show = true;
            
            if (healthFilter && card.dataset.health !== healthFilter) {
                show = false;
            }
            
            if (speciesFilter && card.dataset.species !== speciesFilter) {
                show = false;
            }
            
            const cardDate = card.dataset.date;
            if (dateFromFilter && cardDate < dateFromFilter) {
                show = false;
            }
            
            if (dateToFilter && cardDate > dateToFilter) {
                show = false;
            }
            
            card.style.display = show ? 'block' : 'none';
        });
    }

    function exportResults(format) {
        alert(`${format.toUpperCase()} 형식으로 분석 결과를 내보냅니다.`);
        // In real implementation, trigger download
    }

    function generateReport() {
        alert('종합 분석 리포트를 생성합니다.');
        // In real implementation, generate comprehensive report
    }

    function viewFullAnalysis(id) {
        // Load detailed analysis data
        document.getElementById('modalTitle').textContent = `분석 결과 #${id} 상세정보`;
        document.getElementById('modalContent').innerHTML = `
            <div class="detailed-modal-content">
                <div class="modal-image-section">
                    <img src="/assets/images/products/placeholder.jpg" alt="Plant Analysis">
                    <div class="image-info">
                        <h4>토마토 #${id}</h4>
                        <p>촬영일시: ${new Date().toLocaleString('ko-KR')}</p>
                        <p>분석일시: ${new Date().toLocaleString('ko-KR')}</p>
                    </div>
                </div>
                <div class="modal-analysis-section">
                    <h4>AI 분석 결과</h4>
                    <div class="analysis-details">
                        <div class="detail-item">
                            <strong>식물 종류:</strong> 토마토 (Confidence: 95.2%)
                        </div>
                        <div class="detail-item">
                            <strong>건강 상태:</strong> 건강함
                        </div>
                        <div class="detail-item">
                            <strong>성장 단계:</strong> 성숙기
                        </div>
                        <div class="detail-item">
                            <strong>잎 건강도:</strong> 92%
                        </div>
                        <div class="detail-item">
                            <strong>색상 분석:</strong> 정상 범위
                        </div>
                        <div class="detail-item">
                            <strong>발견된 문제:</strong> 없음
                        </div>
                    </div>
                    <div class="recommendations-detailed">
                        <h4>상세 관리 권장사항</h4>
                        <ul>
                            <li>현재 상태가 매우 양호합니다.</li>
                            <li>정기적인 물 공급을 유지하세요 (토양 수분 60-70%).</li>
                            <li>적절한 일조량을 보장하세요 (하루 6-8시간).</li>
                            <li>주 1회 영양제 공급을 권장합니다.</li>
                            <li>다음 점검은 3일 후를 권장합니다.</li>
                        </ul>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('analysisModal').style.display = 'block';
    }

    function reanalyze(id) {
        if (confirm('이 이미지를 다시 분석하시겠습니까?')) {
            alert('재분석이 시작되었습니다. 완료까지 약 2-3분 소요됩니다.');
        }
    }

    function shareAnalysis(id) {
        const url = window.location.origin + `/pages/plant_analysis/analysis_result.php?id=${id}`;
        if (navigator.share) {
            navigator.share({
                title: '식물 분석 결과',
                text: 'AI가 분석한 식물 건강 상태를 확인해보세요.',
                url: url
            });
        } else {
            navigator.clipboard.writeText(url).then(() => {
                alert('링크가 클립보드에 복사되었습니다.');
            });
        }
    }

    function deleteAnalysis(id) {
        if (confirm('이 분석 결과를 삭제하시겠습니까?')) {
            const card = document.querySelector(`[onclick*="${id}"]`).closest('.result-card');
            card.remove();
        }
    }

    function closeModal() {
        document.getElementById('analysisModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('analysisModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
    </script>
</body>
</html>