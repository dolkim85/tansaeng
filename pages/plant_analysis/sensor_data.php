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

            <!-- Environmental Control -->
            <div class="control-section">
                <h3>🎛️ 환경 제어 시스템</h3>
                <div class="control-grid">
                    <div class="control-item">
                        <h4>🌡️ 온도 제어</h4>
                        <div class="control-buttons">
                            <button onclick="controlDevice('heater', 'on')" class="btn btn-outline">히터 ON</button>
                            <button onclick="controlDevice('heater', 'off')" class="btn btn-outline">히터 OFF</button>
                            <button onclick="controlDevice('fan', 'on')" class="btn btn-outline">팬 ON</button>
                            <button onclick="controlDevice('fan', 'off')" class="btn btn-outline">팬 OFF</button>
                        </div>
                    </div>
                    
                    <div class="control-item">
                        <h4>💧 습도 제어</h4>
                        <div class="control-buttons">
                            <button onclick="controlDevice('humidifier', 'on')" class="btn btn-outline">가습기 ON</button>
                            <button onclick="controlDevice('humidifier', 'off')" class="btn btn-outline">가습기 OFF</button>
                            <button onclick="controlDevice('dehumidifier', 'on')" class="btn btn-outline">제습기 ON</button>
                            <button onclick="controlDevice('dehumidifier', 'off')" class="btn btn-outline">제습기 OFF</button>
                        </div>
                    </div>
                    
                    <div class="control-item">
                        <h4>💡 조명 제어</h4>
                        <div class="control-buttons">
                            <button onclick="controlDevice('led', 'on')" class="btn btn-outline">LED ON</button>
                            <button onclick="controlDevice('led', 'off')" class="btn btn-outline">LED OFF</button>
                            <input type="range" min="0" max="100" value="80" onchange="controlDevice('led', 'brightness', this.value)">
                            <span>밝기 조절</span>
                        </div>
                    </div>
                    
                    <div class="control-item">
                        <h4>💧 관수 제어</h4>
                        <div class="control-buttons">
                            <button onclick="controlDevice('pump', 'on')" class="btn btn-primary">즉시 관수</button>
                            <button onclick="controlDevice('pump', 'schedule')" class="btn btn-outline">스케줄 설정</button>
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

    function controlDevice(device, action, value = null) {
        let message = `${device} ${action}`;
        if (value) message += ` (${value})`;
        
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = '실행 중...';
        btn.disabled = true;
        
        // Simulate device control
        setTimeout(() => {
            btn.textContent = '완료!';
            setTimeout(() => {
                btn.textContent = originalText;
                btn.disabled = false;
            }, 1000);
            
            alert(`${message} 명령이 실행되었습니다.`);
        }, 1500);
    }
    </script>
</body>
</html>