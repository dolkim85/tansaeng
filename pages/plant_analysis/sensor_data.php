<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
$dbConnected = false;
$sensorData = [];
$latestData = null;

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = Auth::getInstance();
    
    // Check if user is logged in and has plant analysis permission
    $auth->requirePlantAnalysisPermission();
    
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $dbConnected = true;
    
    // Get recent sensor data
    $sensorData = $db->select(
        "SELECT * FROM sensor_readings ORDER BY recorded_at DESC LIMIT 100"
    );
    
    $latestData = $db->selectOne(
        "SELECT * FROM sensor_readings ORDER BY recorded_at DESC LIMIT 1"
    );
    
} catch (Exception $e) {
    if (strpos($e->getMessage(), '권한') !== false || strpos($e->getMessage(), '로그인') !== false) {
        header('Location: /pages/plant_analysis/access_denied.php');
        exit;
    }
    
    // Fallback data for demo
    $latestData = [
        'temperature' => 24.5,
        'humidity' => 65.2,
        'light_intensity' => 850.0,
        'ph_value' => 6.2,
        'ec_value' => 1.8,
        'recorded_at' => date('Y-m-d H:i:s')
    ];
    
    $sensorData = [];
    for ($i = 0; $i < 24; $i++) {
        $sensorData[] = [
            'temperature' => rand(200, 280) / 10,
            'humidity' => rand(550, 750) / 10,
            'light_intensity' => rand(700, 1000),
            'ph_value' => rand(55, 75) / 10,
            'ec_value' => rand(15, 25) / 10,
            'recorded_at' => date('Y-m-d H:i:s', strtotime("-{$i} hours"))
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>환경 데이터 - 식물분석 시스템</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/analysis.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main >
        <div class="container">
            <div class="page-header">
                <nav class="breadcrumb">
                    <a href="/pages/plant_analysis/">식물분석</a> > 환경 데이터
                </nav>
                <h1>📈 스마트팜 환경 모니터링</h1>
                <p>실시간 센서 데이터로 최적의 재배 환경을 유지하세요.</p>
            </div>

            <!-- Current Status -->
            <div class="current-status">
                <h3>🌡️ 현재 환경 상태</h3>
                <div class="status-time">
                    마지막 업데이트: <?= $latestData ? date('Y-m-d H:i:s', strtotime($latestData['recorded_at'])) : '데이터 없음' ?>
                    <button onclick="refreshData()" class="btn btn-outline btn-sm">🔄 새로고침</button>
                </div>
                
                <div class="sensor-dashboard">
                    <div class="sensor-card temperature">
                        <div class="sensor-header">
                            <span class="sensor-icon">🌡️</span>
                            <span class="sensor-name">온도</span>
                        </div>
                        <div class="sensor-value">
                            <span class="value"><?= $latestData ? number_format($latestData['temperature'], 1) : '0.0' ?></span>
                            <span class="unit">°C</span>
                        </div>
                        <div class="sensor-status status-<?= $latestData && $latestData['temperature'] >= 20 && $latestData['temperature'] <= 28 ? 'good' : 'warning' ?>">
                            <?= $latestData && $latestData['temperature'] >= 20 && $latestData['temperature'] <= 28 ? '적정' : '주의' ?>
                        </div>
                        <div class="optimal-range">적정: 20-28°C</div>
                    </div>

                    <div class="sensor-card humidity">
                        <div class="sensor-header">
                            <span class="sensor-icon">💧</span>
                            <span class="sensor-name">습도</span>
                        </div>
                        <div class="sensor-value">
                            <span class="value"><?= $latestData ? number_format($latestData['humidity'], 1) : '0.0' ?></span>
                            <span class="unit">%</span>
                        </div>
                        <div class="sensor-status status-<?= $latestData && $latestData['humidity'] >= 60 && $latestData['humidity'] <= 80 ? 'good' : 'warning' ?>">
                            <?= $latestData && $latestData['humidity'] >= 60 && $latestData['humidity'] <= 80 ? '적정' : '주의' ?>
                        </div>
                        <div class="optimal-range">적정: 60-80%</div>
                    </div>

                    <div class="sensor-card light">
                        <div class="sensor-header">
                            <span class="sensor-icon">☀️</span>
                            <span class="sensor-name">광량</span>
                        </div>
                        <div class="sensor-value">
                            <span class="value"><?= $latestData ? number_format($latestData['light_intensity']) : '0' ?></span>
                            <span class="unit">lux</span>
                        </div>
                        <div class="sensor-status status-<?= $latestData && $latestData['light_intensity'] >= 800 ? 'good' : 'warning' ?>">
                            <?= $latestData && $latestData['light_intensity'] >= 800 ? '적정' : '부족' ?>
                        </div>
                        <div class="optimal-range">최소: 800 lux</div>
                    </div>

                    <div class="sensor-card ph">
                        <div class="sensor-header">
                            <span class="sensor-icon">⚗️</span>
                            <span class="sensor-name">pH</span>
                        </div>
                        <div class="sensor-value">
                            <span class="value"><?= $latestData ? number_format($latestData['ph_value'], 1) : '0.0' ?></span>
                            <span class="unit"></span>
                        </div>
                        <div class="sensor-status status-<?= $latestData && $latestData['ph_value'] >= 5.5 && $latestData['ph_value'] <= 6.8 ? 'good' : 'warning' ?>">
                            <?= $latestData && $latestData['ph_value'] >= 5.5 && $latestData['ph_value'] <= 6.8 ? '적정' : '조정필요' ?>
                        </div>
                        <div class="optimal-range">적정: 5.5-6.8</div>
                    </div>

                    <div class="sensor-card ec">
                        <div class="sensor-header">
                            <span class="sensor-icon">⚡</span>
                            <span class="sensor-name">EC</span>
                        </div>
                        <div class="sensor-value">
                            <span class="value"><?= $latestData ? number_format($latestData['ec_value'], 1) : '0.0' ?></span>
                            <span class="unit">mS/cm</span>
                        </div>
                        <div class="sensor-status status-<?= $latestData && $latestData['ec_value'] >= 1.2 && $latestData['ec_value'] <= 2.0 ? 'good' : 'warning' ?>">
                            <?= $latestData && $latestData['ec_value'] >= 1.2 && $latestData['ec_value'] <= 2.0 ? '적정' : '조정필요' ?>
                        </div>
                        <div class="optimal-range">적정: 1.2-2.0</div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <div class="charts-controls">
                    <h3>📊 시간별 변화 추이</h3>
                    <div class="time-range-buttons">
                        <button onclick="loadChartData('1h')" class="btn btn-outline btn-sm active">1시간</button>
                        <button onclick="loadChartData('6h')" class="btn btn-outline btn-sm">6시간</button>
                        <button onclick="loadChartData('24h')" class="btn btn-outline btn-sm">24시간</button>
                        <button onclick="loadChartData('7d')" class="btn btn-outline btn-sm">7일</button>
                    </div>
                </div>

                <div class="charts-grid">
                    <div class="chart-container">
                        <h4>🌡️ 온도 변화</h4>
                        <canvas id="temperatureChart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h4>💧 습도 변화</h4>
                        <canvas id="humidityChart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h4>☀️ 광량 변화</h4>
                        <canvas id="lightChart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h4>⚗️ pH & EC 변화</h4>
                        <canvas id="phEcChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Alerts & Recommendations -->
            <div class="alerts-section">
                <h3>⚠️ 알림 및 권장사항</h3>
                <div class="alerts-list">
                    <?php
                    $alerts = [];
                    if ($latestData) {
                        if ($latestData['temperature'] < 20 || $latestData['temperature'] > 28) {
                            $alerts[] = [
                                'type' => 'warning',
                                'icon' => '🌡️',
                                'title' => '온도 주의',
                                'message' => '현재 온도가 적정 범위를 벗어났습니다. 환경 제어를 확인해주세요.',
                                'action' => '온도 조절'
                            ];
                        }
                        if ($latestData['humidity'] < 60 || $latestData['humidity'] > 80) {
                            $alerts[] = [
                                'type' => 'warning',
                                'icon' => '💧',
                                'title' => '습도 주의',
                                'message' => '습도가 적정 범위를 벗어났습니다. 가습기나 제습기를 확인해주세요.',
                                'action' => '습도 조절'
                            ];
                        }
                        if ($latestData['ph_value'] < 5.5 || $latestData['ph_value'] > 6.8) {
                            $alerts[] = [
                                'type' => 'critical',
                                'icon' => '⚗️',
                                'title' => 'pH 조정 필요',
                                'message' => 'pH가 적정 범위를 벗어났습니다. 양액을 조정해주세요.',
                                'action' => 'pH 조정'
                            ];
                        }
                    }
                    
                    if (empty($alerts)) {
                        $alerts[] = [
                            'type' => 'success',
                            'icon' => '✅',
                            'title' => '환경 상태 양호',
                            'message' => '모든 환경 지표가 적정 범위 내에 있습니다.',
                            'action' => '현상 유지'
                        ];
                    }
                    ?>
                    
                    <?php foreach ($alerts as $alert): ?>
                    <div class="alert-item alert-<?= $alert['type'] ?>">
                        <div class="alert-icon"><?= $alert['icon'] ?></div>
                        <div class="alert-content">
                            <h4><?= $alert['title'] ?></h4>
                            <p><?= $alert['message'] ?></p>
                        </div>
                        <div class="alert-action">
                            <button class="btn btn-outline btn-sm"><?= $alert['action'] ?></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Environmental Control System -->
            <div class="control-section">
                <h3>🎛️ 스마트팜 환경 제어 시스템</h3>

                <!-- Fan Controls -->
                <div class="control-category">
                    <h4>🌀 팬 제어</h4>
                    <div class="control-grid-fans">
                        <div class="control-item">
                            <div class="control-header">
                                <span class="control-icon">🌀</span>
                                <span class="control-name">내부팬 앞</span>
                                <span class="control-status" id="status-fan-front">OFF</span>
                            </div>
                            <div class="control-buttons">
                                <button onclick="controlDevice('fan_front', 'on')" class="btn btn-success">ON</button>
                                <button onclick="controlDevice('fan_front', 'off')" class="btn btn-secondary">OFF</button>
                            </div>
                        </div>

                        <div class="control-item">
                            <div class="control-header">
                                <span class="control-icon">🌀</span>
                                <span class="control-name">내부팬 뒤</span>
                                <span class="control-status" id="status-fan-rear">OFF</span>
                            </div>
                            <div class="control-buttons">
                                <button onclick="controlDevice('fan_rear', 'on')" class="btn btn-success">ON</button>
                                <button onclick="controlDevice('fan_rear', 'off')" class="btn btn-secondary">OFF</button>
                            </div>
                        </div>

                        <div class="control-item">
                            <div class="control-header">
                                <span class="control-icon">🌀</span>
                                <span class="control-name">천장팬</span>
                                <span class="control-status" id="status-fan-ceiling">OFF</span>
                            </div>
                            <div class="control-buttons">
                                <button onclick="controlDevice('fan_ceiling', 'on')" class="btn btn-success">ON</button>
                                <button onclick="controlDevice('fan_ceiling', 'off')" class="btn btn-secondary">OFF</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Window Opener Controls -->
                <div class="control-category">
                    <h4>🪟 개폐기 제어</h4>
                    <div class="control-grid-openers">
                        <div class="control-item-slider">
                            <div class="control-header">
                                <span class="control-icon">🪟</span>
                                <span class="control-name">측창개폐기 Left</span>
                                <span class="control-value" id="value-side-left">0%</span>
                            </div>
                            <div class="slider-container">
                                <input type="range" min="0" max="100" value="0"
                                       class="opener-slider"
                                       id="slider-side-left"
                                       oninput="updateOpener('side_left', this.value)">
                                <div class="slider-labels">
                                    <span>닫힘 (0%)</span>
                                    <span>열림 (100%)</span>
                                </div>
                            </div>
                        </div>

                        <div class="control-item-slider">
                            <div class="control-header">
                                <span class="control-icon">🪟</span>
                                <span class="control-name">측창개폐기 Right</span>
                                <span class="control-value" id="value-side-right">0%</span>
                            </div>
                            <div class="slider-container">
                                <input type="range" min="0" max="100" value="0"
                                       class="opener-slider"
                                       id="slider-side-right"
                                       oninput="updateOpener('side_right', this.value)">
                                <div class="slider-labels">
                                    <span>닫힘 (0%)</span>
                                    <span>열림 (100%)</span>
                                </div>
                            </div>
                        </div>

                        <div class="control-item-slider">
                            <div class="control-header">
                                <span class="control-icon">🪟</span>
                                <span class="control-name">천창개폐기 Left</span>
                                <span class="control-value" id="value-roof-left">0%</span>
                            </div>
                            <div class="slider-container">
                                <input type="range" min="0" max="100" value="0"
                                       class="opener-slider"
                                       id="slider-roof-left"
                                       oninput="updateOpener('roof_left', this.value)">
                                <div class="slider-labels">
                                    <span>닫힘 (0%)</span>
                                    <span>열림 (100%)</span>
                                </div>
                            </div>
                        </div>

                        <div class="control-item-slider">
                            <div class="control-header">
                                <span class="control-icon">🪟</span>
                                <span class="control-name">천창개폐기 Right</span>
                                <span class="control-value" id="value-roof-right">0%</span>
                            </div>
                            <div class="slider-container">
                                <input type="range" min="0" max="100" value="0"
                                       class="opener-slider"
                                       id="slider-roof-right"
                                       oninput="updateOpener('roof_right', this.value)">
                                <div class="slider-labels">
                                    <span>닫힘 (0%)</span>
                                    <span>열림 (100%)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pump Controls -->
                <div class="control-category">
                    <h4>💧 펌프 제어</h4>
                    <div class="control-grid-pumps">
                        <div class="control-item">
                            <div class="control-header">
                                <span class="control-icon">💧</span>
                                <span class="control-name">양액탱크 급수펌프</span>
                                <span class="control-status" id="status-pump-nutrient">OFF</span>
                            </div>
                            <div class="control-buttons">
                                <button onclick="controlDevice('pump_nutrient', 'on')" class="btn btn-success">ON</button>
                                <button onclick="controlDevice('pump_nutrient', 'off')" class="btn btn-secondary">OFF</button>
                            </div>
                        </div>

                        <div class="control-item">
                            <div class="control-header">
                                <span class="control-icon">💧</span>
                                <span class="control-name">수막펌프</span>
                                <span class="control-status" id="status-pump-curtain">OFF</span>
                            </div>
                            <div class="control-buttons">
                                <button onclick="controlDevice('pump_curtain', 'on')" class="btn btn-success">ON</button>
                                <button onclick="controlDevice('pump_curtain', 'off')" class="btn btn-secondary">OFF</button>
                            </div>
                        </div>

                        <div class="control-item">
                            <div class="control-header">
                                <span class="control-icon">💧</span>
                                <span class="control-name">히팅탱크 급수펌프</span>
                                <span class="control-status" id="status-pump-heating">OFF</span>
                            </div>
                            <div class="control-buttons">
                                <button onclick="controlDevice('pump_heating', 'on')" class="btn btn-success">ON</button>
                                <button onclick="controlDevice('pump_heating', 'off')" class="btn btn-secondary">OFF</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Misting System Control -->
                <div class="control-category">
                    <h4>🌫️ 분무수경 시스템</h4>
                    <div class="misting-control">
                        <div class="control-item">
                            <div class="control-header">
                                <span class="control-icon">🌫️</span>
                                <span class="control-name">분무수경 밸브</span>
                                <span class="control-status" id="status-mist-valve">OFF</span>
                            </div>
                            <div class="control-buttons">
                                <button onclick="controlDevice('mist_valve', 'on')" class="btn btn-success">ON</button>
                                <button onclick="controlDevice('mist_valve', 'off')" class="btn btn-secondary">OFF</button>
                                <button onclick="openMistingSchedule()" class="btn btn-primary">📅 스케줄 설정</button>
                            </div>
                        </div>

                        <div class="misting-schedule" id="misting-schedule" style="display: none;">
                            <h5>분무 스케줄 설정</h5>
                            <div class="schedule-config">
                                <div class="schedule-row">
                                    <label>운영 모드:</label>
                                    <select id="mist-mode" class="schedule-input">
                                        <option value="day">주간</option>
                                        <option value="night">야간</option>
                                        <option value="both">주간+야간</option>
                                        <option value="custom">시간 지정</option>
                                    </select>
                                </div>
                                <div class="schedule-row" id="custom-time-row" style="display: none;">
                                    <label>시작 시간:</label>
                                    <input type="time" id="mist-start" class="schedule-input">
                                    <label>종료 시간:</label>
                                    <input type="time" id="mist-end" class="schedule-input">
                                </div>
                                <div class="schedule-row">
                                    <label>작동 시간:</label>
                                    <input type="number" id="mist-duration" class="schedule-input" min="1" max="300" value="10">
                                    <span>초</span>
                                </div>
                                <div class="schedule-row">
                                    <label>쉬는 시간:</label>
                                    <input type="number" id="mist-interval" class="schedule-input" min="1" max="3600" value="300">
                                    <span>초</span>
                                </div>
                                <div class="schedule-buttons">
                                    <button onclick="saveMistingSchedule()" class="btn btn-primary">저장</button>
                                    <button onclick="closeMistingSchedule()" class="btn btn-secondary">취소</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Camera System -->
                <div class="control-category">
                    <h4>📷 카메라 모니터링</h4>
                    <div class="camera-grid">
                        <div class="camera-group">
                            <h5>카메라 1번 - 외부</h5>
                            <div class="camera-views">
                                <div class="camera-view" onclick="openCamera('cam1_1')">
                                    <div class="camera-placeholder">
                                        <span class="camera-icon">📹</span>
                                        <span class="camera-label">외부1</span>
                                    </div>
                                </div>
                                <div class="camera-view" onclick="openCamera('cam1_2')">
                                    <div class="camera-placeholder">
                                        <span class="camera-icon">📹</span>
                                        <span class="camera-label">외부2</span>
                                    </div>
                                </div>
                                <div class="camera-view" onclick="openCamera('cam1_3')">
                                    <div class="camera-placeholder">
                                        <span class="camera-icon">📹</span>
                                        <span class="camera-label">외부3</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="camera-group">
                            <h5>카메라 2번 - 배드</h5>
                            <div class="camera-views">
                                <div class="camera-view" onclick="openCamera('cam2_a')">
                                    <div class="camera-placeholder">
                                        <span class="camera-icon">📹</span>
                                        <span class="camera-label">배드A</span>
                                    </div>
                                </div>
                                <div class="camera-view" onclick="openCamera('cam2_b')">
                                    <div class="camera-placeholder">
                                        <span class="camera-icon">📹</span>
                                        <span class="camera-label">배드B</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="camera-group">
                            <h5>카메라 3번 - 육묘실</h5>
                            <div class="camera-views">
                                <div class="camera-view" onclick="openCamera('cam3')">
                                    <div class="camera-placeholder">
                                        <span class="camera-icon">📹</span>
                                        <span class="camera-label">육묘실</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="camera-group">
                            <h5>카메라 4번 - 펌프실</h5>
                            <div class="camera-views">
                                <div class="camera-view" onclick="openCamera('cam4')">
                                    <div class="camera-placeholder">
                                        <span class="camera-icon">📹</span>
                                        <span class="camera-label">펌프실</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Device Connection Status -->
                <div class="control-category">
                    <h4>🔗 디바이스 연결 상태</h4>
                    <div class="device-connection">
                        <div class="connection-status">
                            <span class="status-indicator" id="mqtt-status">⚫</span>
                            <span>MQTT 브로커 연결: <span id="mqtt-status-text">연결 대기중</span></span>
                        </div>
                        <div class="connection-actions">
                            <button onclick="openDeviceSetup()" class="btn btn-primary">⚙️ 디바이스 설정</button>
                            <button onclick="reconnectMQTT()" class="btn btn-outline">🔄 재연결</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <script>
    let charts = {};
    
    // Initialize charts
    document.addEventListener('DOMContentLoaded', function() {
        initializeCharts();
        loadChartData('24h');
    });

    function initializeCharts() {
        // Temperature Chart
        charts.temperature = new Chart(document.getElementById('temperatureChart'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: '온도 (°C)',
                    data: [],
                    borderColor: '#ff6b6b',
                    backgroundColor: 'rgba(255, 107, 107, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: '°C'
                        }
                    }
                }
            }
        });

        // Humidity Chart
        charts.humidity = new Chart(document.getElementById('humidityChart'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: '습도 (%)',
                    data: [],
                    borderColor: '#4ecdc4',
                    backgroundColor: 'rgba(78, 205, 196, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: '%'
                        }
                    }
                }
            }
        });

        // Light Chart
        charts.light = new Chart(document.getElementById('lightChart'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: '광량 (lux)',
                    data: [],
                    borderColor: '#ffd93d',
                    backgroundColor: 'rgba(255, 217, 61, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'lux'
                        }
                    }
                }
            }
        });

        // pH & EC Chart
        charts.phEc = new Chart(document.getElementById('phEcChart'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'pH',
                    data: [],
                    borderColor: '#a8e6cf',
                    backgroundColor: 'rgba(168, 230, 207, 0.1)',
                    yAxisID: 'y'
                }, {
                    label: 'EC (mS/cm)',
                    data: [],
                    borderColor: '#88d8c0',
                    backgroundColor: 'rgba(136, 216, 192, 0.1)',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'pH'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'EC (mS/cm)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    }

    function loadChartData(timeRange) {
        // Update button states
        document.querySelectorAll('.time-range-buttons .btn').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
        
        // Generate sample data based on PHP data
        const sensorData = <?= json_encode(array_reverse($sensorData)) ?>;
        const labels = sensorData.map(item => {
            const date = new Date(item.recorded_at);
            return date.getHours() + ':' + date.getMinutes().toString().padStart(2, '0');
        });
        
        // Update charts
        charts.temperature.data.labels = labels;
        charts.temperature.data.datasets[0].data = sensorData.map(item => parseFloat(item.temperature));
        charts.temperature.update();
        
        charts.humidity.data.labels = labels;
        charts.humidity.data.datasets[0].data = sensorData.map(item => parseFloat(item.humidity));
        charts.humidity.update();
        
        charts.light.data.labels = labels;
        charts.light.data.datasets[0].data = sensorData.map(item => parseFloat(item.light_intensity));
        charts.light.update();
        
        charts.phEc.data.labels = labels;
        charts.phEc.data.datasets[0].data = sensorData.map(item => parseFloat(item.ph_value));
        charts.phEc.data.datasets[1].data = sensorData.map(item => parseFloat(item.ec_value));
        charts.phEc.update();
    }

    function refreshData() {
        location.reload();
    }

    // MQTT Connection variables
    let mqttClient = null;
    let deviceStates = {};

    // Control Device Function
    function controlDevice(device, action) {
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = '실행 중...';
        btn.disabled = true;

        // Send MQTT command
        publishMQTTCommand(device, action);

        // Update UI status
        const statusElement = document.getElementById(`status-${device}`);
        if (statusElement) {
            setTimeout(() => {
                statusElement.textContent = action.toUpperCase();
                statusElement.className = 'control-status ' + (action === 'on' ? 'status-on' : 'status-off');
                btn.textContent = originalText;
                btn.disabled = false;
            }, 500);
        } else {
            setTimeout(() => {
                btn.textContent = originalText;
                btn.disabled = false;
            }, 500);
        }
    }

    // Update Opener Position
    function updateOpener(opener, value) {
        document.getElementById(`value-${opener}`).textContent = value + '%';
        publishMQTTCommand(opener, 'position', value);
    }

    // Misting Schedule Functions
    function openMistingSchedule() {
        document.getElementById('misting-schedule').style.display = 'block';
    }

    function closeMistingSchedule() {
        document.getElementById('misting-schedule').style.display = 'none';
    }

    document.getElementById('mist-mode').addEventListener('change', function() {
        const customTimeRow = document.getElementById('custom-time-row');
        customTimeRow.style.display = this.value === 'custom' ? 'flex' : 'none';
    });

    function saveMistingSchedule() {
        const mode = document.getElementById('mist-mode').value;
        const duration = document.getElementById('mist-duration').value;
        const interval = document.getElementById('mist-interval').value;

        const schedule = {
            mode: mode,
            duration: parseInt(duration),
            interval: parseInt(interval)
        };

        if (mode === 'custom') {
            schedule.start_time = document.getElementById('mist-start').value;
            schedule.end_time = document.getElementById('mist-end').value;
        }

        // Send schedule to server
        fetch('/api/smartfarm/schedule.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                device: 'misting_system',
                schedule: schedule
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('분무 스케줄이 저장되었습니다.');
                closeMistingSchedule();
                publishMQTTCommand('mist_schedule', 'update', schedule);
            } else {
                alert('스케줄 저장에 실패했습니다: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('스케줄 저장 중 오류가 발생했습니다.');
        });
    }

    // Camera Functions
    function openCamera(cameraId) {
        // Open camera feed in modal or new window
        window.open(`/api/smartfarm/camera.php?id=${cameraId}`, 'camera_' + cameraId, 'width=800,height=600');
    }

    // Device Setup Function
    function openDeviceSetup() {
        window.location.href = '/pages/plant_analysis/device_setup.php';
    }

    // MQTT Functions
    function connectMQTT() {
        // MQTT.js connection will be implemented here
        // For now, simulate connection
        updateMQTTStatus('connecting');

        fetch('/api/smartfarm/mqtt_config.php')
            .then(response => response.json())
            .then(config => {
                if (config.success && config.broker_url) {
                    // Load MQTT.js and connect
                    initMQTTConnection(config);
                } else {
                    updateMQTTStatus('disconnected');
                }
            })
            .catch(error => {
                console.error('MQTT config error:', error);
                updateMQTTStatus('disconnected');
            });
    }

    function initMQTTConnection(config) {
        // This will be expanded with actual MQTT.js implementation
        console.log('Initializing MQTT with config:', config);

        // Simulate connection for now
        setTimeout(() => {
            updateMQTTStatus('connected');
        }, 1000);
    }

    function updateMQTTStatus(status) {
        const indicator = document.getElementById('mqtt-status');
        const text = document.getElementById('mqtt-status-text');

        switch(status) {
            case 'connected':
                indicator.textContent = '🟢';
                text.textContent = '연결됨';
                break;
            case 'connecting':
                indicator.textContent = '🟡';
                text.textContent = '연결 중...';
                break;
            case 'disconnected':
                indicator.textContent = '🔴';
                text.textContent = '연결 끊김';
                break;
            default:
                indicator.textContent = '⚫';
                text.textContent = '연결 대기중';
        }
    }

    function reconnectMQTT() {
        connectMQTT();
    }

    function publishMQTTCommand(device, action, value = null) {
        const command = {
            device: device,
            action: action,
            value: value,
            timestamp: Date.now()
        };

        // Send via MQTT (to be implemented)
        console.log('Publishing MQTT command:', command);

        // Also save to database via API
        fetch('/api/smartfarm/control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(command)
        })
        .then(response => response.json())
        .then(data => {
            console.log('Command sent:', data);
        })
        .catch(error => {
            console.error('Error sending command:', error);
        });
    }

    // Initialize MQTT connection on page load
    document.addEventListener('DOMContentLoaded', function() {
        connectMQTT();
    });
    </script>
</body>
</html>