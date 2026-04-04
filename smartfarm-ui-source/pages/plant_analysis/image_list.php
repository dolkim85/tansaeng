<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
$dbConnected = false;
$plantImages = [];

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = Auth::getInstance();
    
    // Check if user is logged in and has plant analysis permission
    $auth->requirePlantAnalysisPermission();
    
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $dbConnected = true;
    
    // Get user's plant images
    $plantImages = $db->select(
        "SELECT pi.*, pa.species, pa.health_status, pa.confidence 
         FROM plant_images pi 
         LEFT JOIN plant_analysis pa ON pi.id = pa.image_id 
         WHERE pi.user_id = :user_id 
         ORDER BY pi.captured_at DESC",
        ['user_id' => $currentUser['id']]
    );
} catch (Exception $e) {
    if (strpos($e->getMessage(), '권한') !== false || strpos($e->getMessage(), '로그인') !== false) {
        header('Location: /pages/plant_analysis/access_denied.php');
        exit;
    }
    
    // Fallback data for demo
    $plantImages = [
        [
            'id' => 1,
            'filename' => 'demo_plant_1.jpg',
            'original_filename' => '토마토_001.jpg',
            'captured_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'analysis_status' => 'completed',
            'species' => '토마토',
            'health_status' => 'healthy',
            'confidence' => 95.5
        ],
        [
            'id' => 2,
            'filename' => 'demo_plant_2.jpg',
            'original_filename' => '상추_002.jpg',
            'captured_at' => date('Y-m-d H:i:s', strtotime('-3 hours')),
            'analysis_status' => 'analyzing',
            'species' => null,
            'health_status' => null,
            'confidence' => null
        ],
        [
            'id' => 3,
            'filename' => 'demo_plant_3.jpg',
            'original_filename' => '파프리카_003.jpg',
            'captured_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'analysis_status' => 'completed',
            'species' => '파프리카',
            'health_status' => 'warning',
            'confidence' => 88.2
        ]
    ];
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;
$totalImages = count($plantImages);
$totalPages = ceil($totalImages / $limit);
$paginatedImages = array_slice($plantImages, $offset, $limit);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>이미지 갤러리 - 식물분석 시스템</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/analysis.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main >
        <div class="container">
            <div class="page-header">
                <nav class="breadcrumb">
                    <a href="/pages/plant_analysis/">식물분석</a> > 이미지 갤러리
                </nav>
                <h1>🖼️ 식물 이미지 갤러리</h1>
                <p>촬영된 식물 이미지를 확인하고 분석 결과를 관리할 수 있습니다.</p>
            </div>

            <!-- Stats -->
            <div class="image-stats">
                <div class="stat-item">
                    <span class="stat-number"><?= $totalImages ?></span>
                    <span class="stat-label">총 이미지</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= count(array_filter($plantImages, function($img) { return $img['analysis_status'] === 'completed'; })) ?></span>
                    <span class="stat-label">분석 완료</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= count(array_filter($plantImages, function($img) { return $img['analysis_status'] === 'analyzing'; })) ?></span>
                    <span class="stat-label">분석 중</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= count(array_filter($plantImages, function($img) { return $img['analysis_status'] === 'pending'; })) ?></span>
                    <span class="stat-label">대기 중</span>
                </div>
            </div>

            <!-- Filter & Actions -->
            <div class="image-controls">
                <div class="filter-group">
                    <select id="statusFilter" onchange="filterImages()">
                        <option value="">전체 상태</option>
                        <option value="completed">분석 완료</option>
                        <option value="analyzing">분석 중</option>
                        <option value="pending">대기 중</option>
                        <option value="failed">실패</option>
                    </select>
                    
                    <input type="date" id="dateFilter" onchange="filterImages()">
                    
                    <select id="sortOrder" onchange="sortImages()">
                        <option value="newest">최신 순</option>
                        <option value="oldest">오래된 순</option>
                        <option value="name">이름 순</option>
                    </select>
                </div>
                
                <div class="action-group">
                    <button onclick="selectAll()" class="btn btn-outline btn-sm">전체 선택</button>
                    <button onclick="analyzeSelected()" class="btn btn-primary btn-sm">선택된 항목 분석</button>
                    <button onclick="deleteSelected()" class="btn btn-danger btn-sm">선택된 항목 삭제</button>
                </div>
            </div>

            <!-- Image Grid -->
            <?php if (empty($paginatedImages)): ?>
                <div class="no-images">
                    <div class="no-data-icon">📷</div>
                    <h3>촬영된 이미지가 없습니다</h3>
                    <p>라이브 뷰에서 식물을 촬영해보세요.</p>
                    <a href="live_view.php" class="btn btn-primary">지금 촬영하기</a>
                </div>
            <?php else: ?>
                <div class="images-grid">
                    <?php foreach ($paginatedImages as $image): ?>
                    <div class="image-card" data-status="<?= $image['analysis_status'] ?>" data-date="<?= date('Y-m-d', strtotime($image['captured_at'])) ?>">
                        <input type="checkbox" class="image-select" value="<?= $image['id'] ?>">
                        
                        <div class="image-container">
                            <img src="/assets/images/products/placeholder.jpg" alt="<?= htmlspecialchars($image['original_filename'] ?? 'Plant Image') ?>" loading="lazy">
                            <div class="image-overlay">
                                <div class="image-actions">
                                    <button onclick="viewImage(<?= $image['id'] ?>)" class="action-btn" title="크게 보기">🔍</button>
                                    <button onclick="analyzeImage(<?= $image['id'] ?>)" class="action-btn" title="분석하기">🔬</button>
                                    <button onclick="downloadImage(<?= $image['id'] ?>)" class="action-btn" title="다운로드">💾</button>
                                    <button onclick="deleteImage(<?= $image['id'] ?>)" class="action-btn danger" title="삭제">🗑️</button>
                                </div>
                            </div>
                            <div class="image-status status-<?= $image['analysis_status'] ?>">
                                <?php
                                $statusLabels = [
                                    'completed' => '분석완료',
                                    'analyzing' => '분석중',
                                    'pending' => '대기중',
                                    'failed' => '실패'
                                ];
                                echo $statusLabels[$image['analysis_status']] ?? $image['analysis_status'];
                                ?>
                            </div>
                        </div>

                        <div class="image-info">
                            <h4><?= htmlspecialchars($image['original_filename'] ?? 'Unknown') ?></h4>
                            <div class="image-meta">
                                <span class="capture-time">📅 <?= date('m/d H:i', strtotime($image['captured_at'])) ?></span>
                                <?php if ($image['species']): ?>
                                    <span class="species">🌱 <?= htmlspecialchars($image['species']) ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($image['analysis_status'] === 'completed' && $image['health_status']): ?>
                            <div class="analysis-summary">
                                <div class="health-status health-<?= $image['health_status'] ?>">
                                    <?php
                                    $healthLabels = [
                                        'healthy' => '건강함',
                                        'warning' => '주의필요',
                                        'critical' => '위험',
                                        'unknown' => '미확인'
                                    ];
                                    echo $healthLabels[$image['health_status']] ?? $image['health_status'];
                                    ?>
                                </div>
                                <?php if ($image['confidence']): ?>
                                    <div class="confidence">신뢰도: <?= number_format($image['confidence'], 1) ?>%</div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
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

    <!-- Image Modal -->
    <div id="imageModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalImageTitle">이미지 상세</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="modal-image">
                    <img id="modalImage" src="" alt="Plant Image">
                </div>
                <div class="modal-info" id="modalInfo">
                    <!-- Image details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
    function filterImages() {
        const statusFilter = document.getElementById('statusFilter').value;
        const dateFilter = document.getElementById('dateFilter').value;
        const cards = document.querySelectorAll('.image-card');
        
        cards.forEach(card => {
            let show = true;
            
            if (statusFilter && card.dataset.status !== statusFilter) {
                show = false;
            }
            
            if (dateFilter && card.dataset.date !== dateFilter) {
                show = false;
            }
            
            card.style.display = show ? 'block' : 'none';
        });
    }

    function sortImages() {
        const sortOrder = document.getElementById('sortOrder').value;
        const grid = document.querySelector('.images-grid');
        const cards = Array.from(document.querySelectorAll('.image-card'));
        
        cards.sort((a, b) => {
            if (sortOrder === 'newest') {
                return new Date(b.dataset.date) - new Date(a.dataset.date);
            } else if (sortOrder === 'oldest') {
                return new Date(a.dataset.date) - new Date(b.dataset.date);
            } else if (sortOrder === 'name') {
                const nameA = a.querySelector('h4').textContent.toLowerCase();
                const nameB = b.querySelector('h4').textContent.toLowerCase();
                return nameA.localeCompare(nameB);
            }
        });
        
        cards.forEach(card => grid.appendChild(card));
    }

    function selectAll() {
        const checkboxes = document.querySelectorAll('.image-select');
        const allSelected = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(cb => {
            cb.checked = !allSelected;
        });
    }

    function getSelectedIds() {
        return Array.from(document.querySelectorAll('.image-select:checked'))
                   .map(cb => cb.value);
    }

    function analyzeSelected() {
        const selected = getSelectedIds();
        if (selected.length === 0) {
            alert('분석할 이미지를 선택해주세요.');
            return;
        }
        
        if (confirm(`선택된 ${selected.length}개 이미지를 분석하시겠습니까?`)) {
            // Simulate analysis
            alert('분석이 시작되었습니다. 결과는 분석 완료 후 확인할 수 있습니다.');
        }
    }

    function deleteSelected() {
        const selected = getSelectedIds();
        if (selected.length === 0) {
            alert('삭제할 이미지를 선택해주세요.');
            return;
        }
        
        if (confirm(`선택된 ${selected.length}개 이미지를 삭제하시겠습니까?`)) {
            selected.forEach(id => {
                const card = document.querySelector(`input[value="${id}"]`).closest('.image-card');
                card.remove();
            });
        }
    }

    function viewImage(id) {
        // Load image details and show modal
        document.getElementById('modalImageTitle').textContent = `이미지 #${id}`;
        document.getElementById('modalImage').src = '/assets/images/products/placeholder.jpg';
        document.getElementById('modalInfo').innerHTML = `
            <div class="detail-grid">
                <div><strong>파일명:</strong> demo_plant_${id}.jpg</div>
                <div><strong>촬영시간:</strong> ${new Date().toLocaleString('ko-KR')}</div>
                <div><strong>분석상태:</strong> 완료</div>
                <div><strong>식물종류:</strong> 토마토</div>
                <div><strong>건강상태:</strong> 건강함</div>
                <div><strong>신뢰도:</strong> 95.2%</div>
            </div>
        `;
        document.getElementById('imageModal').style.display = 'block';
    }

    function analyzeImage(id) {
        if (confirm('이 이미지를 분석하시겠습니까?')) {
            alert('분석이 시작되었습니다.');
            // Update status in the card
            const card = document.querySelector(`input[value="${id}"]`).closest('.image-card');
            const statusBadge = card.querySelector('.image-status');
            statusBadge.textContent = '분석중';
            statusBadge.className = 'image-status status-analyzing';
        }
    }

    function downloadImage(id) {
        alert('이미지 다운로드가 시작됩니다.');
        // In real implementation, trigger download
    }

    function deleteImage(id) {
        if (confirm('이 이미지를 삭제하시겠습니까?')) {
            const card = document.querySelector(`input[value="${id}"]`).closest('.image-card');
            card.remove();
        }
    }

    function closeModal() {
        document.getElementById('imageModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('imageModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
    </script>
</body>
</html>