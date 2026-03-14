<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
$dbConnected = false;

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = Auth::getInstance();
    
    // Check if user is logged in and has plant analysis permission
    $auth->requirePlantAnalysisPermission();
    
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $dbConnected = true;
} catch (Exception $e) {
    header('Location: /pages/plant_analysis/access_denied.php');
    exit;
}

// Get raspberry pi devices
$raspberryDevices = [];
if ($dbConnected) {
    try {
        $raspberryDevices = $db->select(
            "SELECT * FROM raspberry_devices WHERE status = 'online' ORDER BY device_name"
        );
    } catch (Exception $e) {
        // Fallback data for demo
        $raspberryDevices = [
            [
                'id' => 1,
                'device_name' => 'RaspberryPi-001',
                'device_id' => 'rpi001',
                'ip_address' => '192.168.1.100',
                'status' => 'online',
                'last_contact' => date('Y-m-d H:i:s')
            ]
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>실시간 관찰 - 식물분석 시스템</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/analysis.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main >
        <div class="container">
            <div class="page-header">
                <nav class="breadcrumb">
                    <a href="/pages/plant_analysis/">식물분석</a> > 실시간 관찰
                </nav>
                <h1>📹 실시간 식물 관찰</h1>
                <p>라즈베리파이 카메라를 통해 실시간으로 식물을 관찰하고 촬영할 수 있습니다.</p>
            </div>

            <!-- Device Selection -->
            <div class="device-section">
                <h3>카메라 선택</h3>
                <div class="device-grid">
                    <?php if (empty($raspberryDevices)): ?>
                        <div class="no-devices">
                            <div class="no-data-icon">📷</div>
                            <h3>연결된 카메라가 없습니다</h3>
                            <p>라즈베리파이 장비를 연결하고 설정을 완료해주세요.</p>
                            <a href="/pages/support/contact.php" class="btn btn-primary">기술지원 문의</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($raspberryDevices as $device): ?>
                        <div class="device-card" data-device-id="<?= $device['device_id'] ?>">
                            <div class="device-status status-<?= $device['status'] ?>"></div>
                            <div class="device-info">
                                <h4><?= htmlspecialchars($device['device_name']) ?></h4>
                                <p>IP: <?= htmlspecialchars($device['ip_address'] ?? 'Unknown') ?></p>
                                <small>최종 연결: <?= date('m/d H:i', strtotime($device['last_contact'])) ?></small>
                            </div>
                            <button onclick="connectToDevice('<?= $device['device_id'] ?>')" 
                                    class="btn btn-primary btn-sm">연결</button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Live Video Stream -->
            <div class="stream-section" id="streamSection" style="display: none;">
                <div class="stream-header">
                    <h3>실시간 영상</h3>
                    <div class="stream-controls">
                        <button onclick="captureImage()" class="btn btn-success">📸 촬영</button>
                        <button onclick="toggleFullscreen()" class="btn btn-outline">🔍 전체화면</button>
                        <button onclick="disconnectDevice()" class="btn btn-outline">🔌 연결해제</button>
                    </div>
                </div>
                
                <div class="stream-container">
                    <div class="stream-video">
                        <video id="liveStream" autoplay muted playsinline>
                            <p>브라우저가 비디오를 지원하지 않습니다.</p>
                        </video>
                        <div class="stream-overlay">
                            <div class="stream-info">
                                <span id="deviceName">Device</span>
                                <span id="streamStatus">연결 중...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Camera Settings -->
            <div class="settings-section" id="settingsSection" style="display: none;">
                <h3>카메라 설정</h3>
                <div class="settings-grid">
                    <div class="setting-item">
                        <label>해상도</label>
                        <select id="resolution" onchange="updateCameraSetting('resolution', this.value)">
                            <option value="640x480">640x480</option>
                            <option value="1280x720" selected>1280x720 (HD)</option>
                            <option value="1920x1080">1920x1080 (Full HD)</option>
                        </select>
                    </div>
                    
                    <div class="setting-item">
                        <label>밝기</label>
                        <input type="range" id="brightness" min="0" max="100" value="50" 
                               onchange="updateCameraSetting('brightness', this.value)">
                        <span id="brightnessValue">50%</span>
                    </div>
                    
                    <div class="setting-item">
                        <label>대비</label>
                        <input type="range" id="contrast" min="0" max="100" value="50" 
                               onchange="updateCameraSetting('contrast', this.value)">
                        <span id="contrastValue">50%</span>
                    </div>
                    
                    <div class="setting-item">
                        <label>자동 촬영</label>
                        <div class="toggle-group">
                            <input type="checkbox" id="autoCapture" onchange="toggleAutoCapture(this.checked)">
                            <label for="autoCapture">활성화</label>
                        </div>
                        <input type="number" id="captureInterval" min="1" max="60" value="10" disabled>
                        <span>분마다</span>
                    </div>
                </div>
            </div>

            <!-- Recent Captures -->
            <div class="captures-section">
                <div class="section-header">
                    <h3>최근 촬영 이미지</h3>
                    <a href="image_list.php" class="btn btn-outline btn-sm">전체보기</a>
                </div>
                <div class="captures-grid" id="recentCaptures">
                    <div class="no-data">
                        <p>촬영된 이미지가 없습니다.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <script>
    let currentDevice = null;
    let autoCapturing = false;
    let captureTimer = null;

    function connectToDevice(deviceId) {
        currentDevice = deviceId;
        
        // Show loading
        document.getElementById('streamSection').style.display = 'block';
        document.getElementById('settingsSection').style.display = 'block';
        document.getElementById('streamStatus').textContent = '연결 중...';
        
        // Simulate connection (in real implementation, this would connect to actual device)
        setTimeout(() => {
            // Mock video stream (in real implementation, this would be actual stream)
            startMockStream();
            document.getElementById('streamStatus').textContent = '연결됨';
            document.getElementById('deviceName').textContent = 'RaspberryPi-' + deviceId;
            
            // Load recent captures
            loadRecentCaptures();
        }, 2000);
    }

    function startMockStream() {
        // In real implementation, this would connect to actual raspberry pi stream
        const video = document.getElementById('liveStream');
        
        // For demo purposes, we'll show a placeholder
        video.style.background = 'linear-gradient(45deg, #4CAF50, #2E7D32)';
        video.style.display = 'flex';
        video.style.alignItems = 'center';
        video.style.justifyContent = 'center';
        video.innerHTML = '<div style="color: white; text-align: center;"><h3>📹 Live Stream</h3><p>Demo Mode - 실제 스트림은 라즈베리파이 연결 후 사용 가능</p></div>';
    }

    function captureImage() {
        if (!currentDevice) {
            alert('먼저 카메라를 연결해주세요.');
            return;
        }

        // Show capturing animation
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = '촬영 중...';
        btn.disabled = true;

        // Simulate capture (in real implementation, this would capture from actual stream)
        setTimeout(() => {
            btn.textContent = '완료!';
            setTimeout(() => {
                btn.textContent = originalText;
                btn.disabled = false;
                
                // Add to recent captures
                addRecentCapture();
            }, 1000);
        }, 1500);
    }

    function addRecentCapture() {
        const capturesGrid = document.getElementById('recentCaptures');
        if (capturesGrid.querySelector('.no-data')) {
            capturesGrid.innerHTML = '';
        }

        const captureItem = document.createElement('div');
        captureItem.className = 'capture-item';
        captureItem.innerHTML = `
            <div class="capture-image">
                <img src="/assets/images/products/placeholder.jpg" alt="촬영 이미지">
                <div class="capture-overlay">
                    <span class="capture-time">${new Date().toLocaleString('ko-KR')}</span>
                    <button onclick="analyzeImage(this)" class="btn btn-primary btn-sm">분석</button>
                </div>
            </div>
        `;
        
        capturesGrid.insertBefore(captureItem, capturesGrid.firstChild);
        
        // Keep only 6 recent items
        while (capturesGrid.children.length > 6) {
            capturesGrid.removeChild(capturesGrid.lastChild);
        }
    }

    function analyzeImage(btn) {
        const originalText = btn.textContent;
        btn.textContent = '분석 중...';
        btn.disabled = true;
        
        setTimeout(() => {
            btn.textContent = '완료';
            alert('분석이 완료되었습니다. 분석 결과 페이지에서 확인할 수 있습니다.');
        }, 3000);
    }

    function updateCameraSetting(setting, value) {
        console.log(`Setting ${setting} to ${value}`);
        
        if (setting === 'brightness') {
            document.getElementById('brightnessValue').textContent = value + '%';
        } else if (setting === 'contrast') {
            document.getElementById('contrastValue').textContent = value + '%';
        }
        
        // In real implementation, send to raspberry pi
    }

    function toggleAutoCapture(enabled) {
        const intervalInput = document.getElementById('captureInterval');
        intervalInput.disabled = !enabled;
        
        if (enabled) {
            const interval = parseInt(intervalInput.value) * 60 * 1000; // Convert to milliseconds
            captureTimer = setInterval(() => {
                if (currentDevice) {
                    captureImage();
                }
            }, interval);
            autoCapturing = true;
        } else {
            if (captureTimer) {
                clearInterval(captureTimer);
                captureTimer = null;
            }
            autoCapturing = false;
        }
    }

    function toggleFullscreen() {
        const video = document.getElementById('liveStream');
        if (video.requestFullscreen) {
            video.requestFullscreen();
        }
    }

    function disconnectDevice() {
        currentDevice = null;
        document.getElementById('streamSection').style.display = 'none';
        document.getElementById('settingsSection').style.display = 'none';
        
        if (captureTimer) {
            clearInterval(captureTimer);
            captureTimer = null;
        }
        autoCapturing = false;
    }

    function loadRecentCaptures() {
        // In real implementation, load from server
        // For demo, we'll show some sample captures
        setTimeout(() => {
            for (let i = 0; i < 3; i++) {
                addRecentCapture();
            }
        }, 1000);
    }
    </script>
</body>
</html>