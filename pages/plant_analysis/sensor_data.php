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
                <div class="control-category-enhanced">
                    <div class="category-header">
                        <div class="category-title">
                            <span class="category-icon">🌀</span>
                            <h4>팬 제어</h4>
                        </div>
                        <div class="category-info">총 3개 디바이스</div>
                    </div>
                    <div class="control-grid-enhanced">
                        <div class="control-card">
                            <div class="card-header">
                                <div class="card-title">
                                    <span class="device-icon">🌀</span>
                                    <span class="device-name">내부팬 앞</span>
                                </div>
                                <span class="status-badge" id="badge-fan-front">OFF</span>
                            </div>
                            <div class="card-body">
                                <div class="toggle-control">
                                    <span class="toggle-label">전원</span>
                                    <label class="toggle-switch-large">
                                        <input type="checkbox" id="toggle-fan-front" onchange="toggleDevice('fan_front', this.checked)">
                                        <span class="toggle-slider-large"></span>
                                    </label>
                                </div>
                                <div class="device-info">
                                    <small>마지막 작동: <span id="last-fan-front">-</span></small>
                                </div>
                            </div>
                        </div>

                        <div class="control-card">
                            <div class="card-header">
                                <div class="card-title">
                                    <span class="device-icon">🌀</span>
                                    <span class="device-name">내부팬 뒤</span>
                                </div>
                                <span class="status-badge" id="badge-fan-rear">OFF</span>
                            </div>
                            <div class="card-body">
                                <div class="toggle-control">
                                    <span class="toggle-label">전원</span>
                                    <label class="toggle-switch-large">
                                        <input type="checkbox" id="toggle-fan-rear" onchange="toggleDevice('fan_rear', this.checked)">
                                        <span class="toggle-slider-large"></span>
                                    </label>
                                </div>
                                <div class="device-info">
                                    <small>마지막 작동: <span id="last-fan-rear">-</span></small>
                                </div>
                            </div>
                        </div>

                        <div class="control-card">
                            <div class="card-header">
                                <div class="card-title">
                                    <span class="device-icon">🌀</span>
                                    <span class="device-name">천장팬</span>
                                </div>
                                <span class="status-badge" id="badge-fan-ceiling">OFF</span>
                            </div>
                            <div class="card-body">
                                <div class="toggle-control">
                                    <span class="toggle-label">전원</span>
                                    <label class="toggle-switch-large">
                                        <input type="checkbox" id="toggle-fan-ceiling" onchange="toggleDevice('fan_ceiling', this.checked)">
                                        <span class="toggle-slider-large"></span>
                                    </label>
                                </div>
                                <div class="device-info">
                                    <small>마지막 작동: <span id="last-fan-ceiling">-</span></small>
                                </div>
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
                <div class="control-category-enhanced">
                    <div class="category-header">
                        <div class="category-title">
                            <span class="category-icon">💧</span>
                            <h4>펌프 제어</h4>
                        </div>
                        <div class="category-info">총 3개 디바이스</div>
                    </div>
                    <div class="control-grid-enhanced">
                        <div class="control-card">
                            <div class="card-header">
                                <div class="card-title">
                                    <span class="device-icon">💧</span>
                                    <span class="device-name">양액탱크 급수펌프</span>
                                </div>
                                <span class="status-badge" id="badge-pump-nutrient">OFF</span>
                            </div>
                            <div class="card-body">
                                <div class="toggle-control">
                                    <span class="toggle-label">전원</span>
                                    <label class="toggle-switch-large">
                                        <input type="checkbox" id="toggle-pump-nutrient" onchange="toggleDevice('pump_nutrient', this.checked)">
                                        <span class="toggle-slider-large"></span>
                                    </label>
                                </div>
                                <div class="device-info">
                                    <small>마지막 작동: <span id="last-pump-nutrient">-</span></small>
                                </div>
                            </div>
                        </div>

                        <div class="control-card">
                            <div class="card-header">
                                <div class="card-title">
                                    <span class="device-icon">💧</span>
                                    <span class="device-name">수막펌프</span>
                                </div>
                                <span class="status-badge" id="badge-pump-curtain">OFF</span>
                            </div>
                            <div class="card-body">
                                <div class="toggle-control">
                                    <span class="toggle-label">전원</span>
                                    <label class="toggle-switch-large">
                                        <input type="checkbox" id="toggle-pump-curtain" onchange="toggleDevice('pump_curtain', this.checked)">
                                        <span class="toggle-slider-large"></span>
                                    </label>
                                </div>
                                <div class="device-info">
                                    <small>마지막 작동: <span id="last-pump-curtain">-</span></small>
                                </div>
                            </div>
                        </div>

                        <div class="control-card">
                            <div class="card-header">
                                <div class="card-title">
                                    <span class="device-icon">💧</span>
                                    <span class="device-name">히팅탱크 급수펌프</span>
                                </div>
                                <span class="status-badge" id="badge-pump-heating">OFF</span>
                            </div>
                            <div class="card-body">
                                <div class="toggle-control">
                                    <span class="toggle-label">전원</span>
                                    <label class="toggle-switch-large">
                                        <input type="checkbox" id="toggle-pump-heating" onchange="toggleDevice('pump_heating', this.checked)">
                                        <span class="toggle-slider-large"></span>
                                    </label>
                                </div>
                                <div class="device-info">
                                    <small>마지막 작동: <span id="last-pump-heating">-</span></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Misting System Control -->
                <div class="control-category-enhanced">
                    <div class="category-header">
                        <div class="category-title">
                            <span class="category-icon">🌫️</span>
                            <h4>분무수경 시스템</h4>
                        </div>
                        <div class="category-info">자동 스케줄 관리</div>
                    </div>
                    <div class="misting-full-control">
                        <div class="control-card-wide">
                            <div class="card-header">
                                <div class="card-title">
                                    <span class="device-icon">🌫️</span>
                                    <span class="device-name">분무수경 밸브</span>
                                </div>
                                <span class="status-badge" id="badge-mist-valve">OFF</span>
                            </div>
                            <div class="card-body">
                                <div class="toggle-control-wide">
                                    <div class="manual-control">
                                        <span class="toggle-label">수동 제어</span>
                                        <label class="toggle-switch-large">
                                            <input type="checkbox" id="toggle-mist-valve" onchange="toggleDevice('mist_valve', this.checked)">
                                            <span class="toggle-slider-large"></span>
                                        </label>
                                    </div>
                                    <div class="auto-control">
                                        <span class="toggle-label">자동 스케줄</span>
                                        <label class="toggle-switch-large">
                                            <input type="checkbox" id="toggle-mist-auto" onchange="toggleAutoSchedule(this.checked)">
                                            <span class="toggle-slider-large"></span>
                                        </label>
                                        <div class="active-schedule-display" id="active-schedule-name">
                                            <span class="schedule-name-label">선택된 스케줄:</span>
                                            <span class="schedule-name-value" id="active-schedule-text">없음</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Schedule Panel (항상 표시) -->
                        <div class="schedule-panel">
                            <h5>📅 자동 스케줄 설정</h5>
                            <div class="schedule-config-enhanced">
                                <div class="schedule-section">
                                    <label class="schedule-label">
                                        <span class="label-icon">⏰</span>
                                        운영 모드 선택
                                    </label>
                                    <div class="mode-selector">
                                        <label class="mode-option">
                                            <input type="radio" name="mist-mode" value="day" checked onchange="switchMistMode('day')">
                                            <span class="mode-label">
                                                <span class="mode-icon">☀️</span>
                                                주간
                                            </span>
                                        </label>
                                        <label class="mode-option">
                                            <input type="radio" name="mist-mode" value="night" onchange="switchMistMode('night')">
                                            <span class="mode-label">
                                                <span class="mode-icon">🌙</span>
                                                야간
                                            </span>
                                        </label>
                                        <label class="mode-option">
                                            <input type="radio" name="mist-mode" value="both" onchange="switchMistMode('both')">
                                            <span class="mode-label">
                                                <span class="mode-icon">🔄</span>
                                                24시간
                                            </span>
                                        </label>
                                        <label class="mode-option">
                                            <input type="radio" name="mist-mode" value="custom" onchange="switchMistMode('custom')">
                                            <span class="mode-label">
                                                <span class="mode-icon">⚙️</span>
                                                사용자 지정
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Day Mode Settings -->
                                <div class="mode-settings" id="mode-day-settings">
                                    <div class="mode-settings-header">
                                        <h6>☀️ 주간 모드 설정 (6:00 - 18:00)</h6>
                                    </div>
                                    <div class="setting-row">
                                        <label class="setting-label">무한 반복</label>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="day-repeat" checked>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    <div class="cycle-config">
                                        <div class="cycle-item">
                                            <label>분무 시간</label>
                                            <div class="input-with-unit">
                                                <input type="number" id="day-duration" min="1" max="300" value="10" onchange="updateCyclePreview('day')">
                                                <span class="unit">초</span>
                                            </div>
                                        </div>
                                        <span class="cycle-separator">→</span>
                                        <div class="cycle-item">
                                            <label>쉬는 시간</label>
                                            <div class="input-with-unit">
                                                <input type="number" id="day-interval" min="1" max="3600" value="300" onchange="updateCyclePreview('day')">
                                                <span class="unit">초</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="cycle-preview" id="day-preview">
                                        <small>💡 10초 분무 → 5분 대기 → 반복</small>
                                    </div>
                                </div>

                                <!-- Night Mode Settings -->
                                <div class="mode-settings" id="mode-night-settings" style="display: none;">
                                    <div class="mode-settings-header">
                                        <h6>🌙 야간 모드 설정 (18:00 - 6:00)</h6>
                                    </div>
                                    <div class="setting-row">
                                        <label class="setting-label">무한 반복</label>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="night-repeat" checked>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    <div class="cycle-config">
                                        <div class="cycle-item">
                                            <label>분무 시간</label>
                                            <div class="input-with-unit">
                                                <input type="number" id="night-duration" min="1" max="300" value="10" onchange="updateCyclePreview('night')">
                                                <span class="unit">초</span>
                                            </div>
                                        </div>
                                        <span class="cycle-separator">→</span>
                                        <div class="cycle-item">
                                            <label>쉬는 시간</label>
                                            <div class="input-with-unit">
                                                <input type="number" id="night-interval" min="1" max="3600" value="600" onchange="updateCyclePreview('night')">
                                                <span class="unit">초</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="cycle-preview" id="night-preview">
                                        <small>💡 10초 분무 → 10분 대기 → 반복</small>
                                    </div>
                                </div>

                                <!-- 24h Mode Settings -->
                                <div class="mode-settings" id="mode-both-settings" style="display: none;">
                                    <div class="mode-settings-header">
                                        <h6>🔄 24시간 모드 설정</h6>
                                    </div>
                                    <div class="setting-row">
                                        <label class="setting-label">무한 반복</label>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="both-repeat" checked>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    <div class="cycle-config">
                                        <div class="cycle-item">
                                            <label>분무 시간</label>
                                            <div class="input-with-unit">
                                                <input type="number" id="both-duration" min="1" max="300" value="10" onchange="updateCyclePreview('both')">
                                                <span class="unit">초</span>
                                            </div>
                                        </div>
                                        <span class="cycle-separator">→</span>
                                        <div class="cycle-item">
                                            <label>쉬는 시간</label>
                                            <div class="input-with-unit">
                                                <input type="number" id="both-interval" min="1" max="3600" value="300" onchange="updateCyclePreview('both')">
                                                <span class="unit">초</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="cycle-preview" id="both-preview">
                                        <small>💡 10초 분무 → 5분 대기 → 반복</small>
                                    </div>
                                </div>

                                <!-- Custom Mode Settings -->
                                <div class="mode-settings" id="mode-custom-settings" style="display: none;">
                                    <div class="mode-settings-header">
                                        <h6>⚙️ 사용자 지정 시간대</h6>
                                        <button onclick="addCustomTimeSlot()" class="btn btn-sm btn-success">
                                            ➕ 시간대 추가
                                        </button>
                                    </div>
                                    <div id="custom-time-slots">
                                        <!-- 시간대 목록이 여기에 동적으로 추가됨 -->
                                    </div>
                                </div>

                                <div class="schedule-actions">
                                    <button onclick="addMistingSchedule()" class="btn btn-primary btn-lg">
                                        ➕ 스케줄 추가
                                    </button>
                                    <button onclick="testMisting()" class="btn btn-outline btn-lg">
                                        🧪 테스트 실행
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Saved Schedules List -->
                        <div class="saved-schedules-panel">
                            <h5>📋 등록된 스케줄 목록</h5>
                            <div id="saved-schedules-list">
                                <!-- 스케줄 목록이 여기에 동적으로 추가됨 -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Camera System -->
                <div class="control-category-enhanced">
                    <div class="category-header">
                        <div class="category-title">
                            <span class="category-icon">📷</span>
                            <h4>카메라 모니터링</h4>
                        </div>
                        <button onclick="openAddCameraModal()" class="btn btn-sm btn-success">
                            ➕ 카메라 추가
                        </button>
                    </div>
                    <div class="camera-grid-live" id="camera-grid">
                        <!-- 카메라 목록이 동적으로 추가됨 -->
                    </div>
                </div>

                <!-- Add Camera Modal -->
                <div id="add-camera-modal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>📷 카메라 추가</h3>
                            <span class="close" onclick="closeAddCameraModal()">&times;</span>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label>카메라 이름</label>
                                <input type="text" id="camera-name" class="form-control" placeholder="예: 외부1, 배드A">
                            </div>
                            <div class="form-group">
                                <label>스트림 타입</label>
                                <select id="camera-stream-type" class="form-control">
                                    <option value="rtsp">RTSP</option>
                                    <option value="mjpeg">MJPEG</option>
                                    <option value="hls">HLS</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>스트림 URL</label>
                                <input type="text" id="camera-stream-url" class="form-control"
                                       placeholder="rtsp://192.168.1.100:554/stream1">
                                <small>예: rtsp://username:password@ip:port/path</small>
                            </div>
                            <div class="form-group">
                                <label>카메라 아이콘</label>
                                <select id="camera-icon" class="form-control">
                                    <option value="📹">📹 기본</option>
                                    <option value="🎥">🎥 비디오</option>
                                    <option value="📸">📸 사진기</option>
                                    <option value="🔍">🔍 감시</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button onclick="saveCamera()" class="btn btn-primary">➕ 추가</button>
                            <button onclick="closeAddCameraModal()" class="btn btn-secondary">취소</button>
                        </div>
                    </div>
                </div>

                <!-- Camera Modal -->
                <div id="camera-modal" class="camera-modal" style="display: none;">
                    <div class="camera-modal-content">
                        <div class="camera-modal-header">
                            <h3 id="camera-modal-title">카메라</h3>
                            <button onclick="closeCameraModal()" class="btn-close">✕</button>
                        </div>
                        <div class="camera-modal-body">
                            <div id="camera-modal-feed" class="camera-feed-large">
                                <div class="camera-loading">
                                    <span class="loading-icon">📹</span>
                                    <span>카메라 로딩중...</span>
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

    // ========== State Persistence (localStorage) ==========
    function saveDeviceState(device, state) {
        try {
            const states = JSON.parse(localStorage.getItem('deviceStates') || '{}');
            states[device] = {
                state: state,
                timestamp: new Date().toISOString()
            };
            localStorage.setItem('deviceStates', JSON.stringify(states));
            console.log(`💾 Saved state for ${device}: ${state}`);
        } catch (error) {
            console.error('Error saving device state:', error);
        }
    }

    function loadDeviceState(device) {
        try {
            const states = JSON.parse(localStorage.getItem('deviceStates') || '{}');
            return states[device]?.state || false;
        } catch (error) {
            console.error('Error loading device state:', error);
            return false;
        }
    }

    function restoreAllDeviceStates() {
        console.log('🔄 Restoring device states from localStorage...');

        // List of all controllable devices
        const devices = [
            'fan-front', 'fan-rear', 'fan-ceiling',
            'pump-nutrient', 'pump-curtain', 'pump-heating',
            'mist_valve'
        ];

        // Restore each device state
        devices.forEach(device => {
            const state = loadDeviceState(device);
            const toggle = document.getElementById(`toggle-${device}`);
            if (toggle) {
                toggle.checked = state;
                updateDeviceBadge(device, state);
                if (state) {
                    publishMQTTCommand(device, 'on');
                }
                console.log(`  ✓ ${device}: ${state ? 'ON' : 'OFF'}`);
            }
        });

        // Restore auto schedule state
        const autoScheduleState = loadDeviceState('mist_auto_schedule');
        const autoScheduleToggle = document.getElementById('toggle-mist-auto');
        if (autoScheduleToggle) {
            autoScheduleToggle.checked = autoScheduleState;
            if (autoScheduleState) {
                publishMQTTCommand('mist_schedule', 'start');
            }
            console.log(`  ✓ mist_auto_schedule: ${autoScheduleState ? 'ON' : 'OFF'}`);
        }

        console.log('✅ All device states restored');
    }

    function updateDeviceBadge(device, isOn) {
        const badge = document.getElementById(`badge-${device}`);
        if (badge) {
            badge.textContent = isOn ? 'ON' : 'OFF';
            badge.className = 'status-badge ' + (isOn ? 'status-on' : 'status-off');
        }
    }

    // Toggle Device Function (for switches)
    function toggleDevice(device, isOn) {
        // 분무수경 밸브의 경우 자동 스케줄과 상호 배타적
        if (device === 'mist_valve') {
            if (isOn) {
                // 수동 ON → 자동 스케줄 OFF
                const autoToggle = document.getElementById('toggle-mist-auto');
                if (autoToggle && autoToggle.checked) {
                    autoToggle.checked = false;
                    saveDeviceState('mist_auto_schedule', false);
                    alert('⚠️ 수동 제어를 활성화하여 자동 스케줄이 중지되었습니다.');
                }
            }
        }

        const action = isOn ? 'on' : 'off';
        publishMQTTCommand(device, action);

        // Save state to localStorage
        saveDeviceState(device, isOn);

        // Update status badge
        updateDeviceBadge(device, isOn);

        // Update last activity
        const lastElement = document.getElementById(`last-${device}`);
        if (lastElement) {
            const now = new Date();
            lastElement.textContent = now.toLocaleTimeString('ko-KR');
        }

        console.log(`Device ${device} turned ${action}`);
    }

    // Toggle Auto Schedule (자동 스케줄 시작/멈춤)
    function toggleAutoSchedule(isOn) {
        if (isOn) {
            // 자동 ON → 수동 밸브 OFF
            const manualToggle = document.getElementById('toggle-mist-valve');
            if (manualToggle && manualToggle.checked) {
                manualToggle.checked = false;
                saveDeviceState('mist_valve', false);
                // 수동 밸브 OFF 명령 전송
                publishMQTTCommand('mist_valve', 'off');
                updateDeviceBadge('mist_valve', false);
            }

            // 스케줄 시작
            publishMQTTCommand('mist_schedule', 'start');
            saveDeviceState('mist_auto_schedule', true);
            alert('✅ 자동 스케줄이 시작되었습니다. 등록된 스케줄대로 자동 작동합니다.');
        } else {
            // 스케줄 중지
            publishMQTTCommand('mist_schedule', 'stop');
            saveDeviceState('mist_auto_schedule', false);
            alert('⏸️ 자동 스케줄이 중지되었습니다.');
        }
    }

    // Test Misting
    function testMisting() {
        if (confirm('분무 테스트를 실행하시겠습니까? (10초간 작동)')) {
            publishMQTTCommand('mist_valve', 'test', 10);
            alert('테스트 분무가 시작되었습니다.');
        }
    }

    // Control Device Function (legacy)
    function controlDevice(device, action) {
        publishMQTTCommand(device, action);
    }

    // Update Opener Position
    function updateOpener(opener, value) {
        document.getElementById(`value-${opener}`).textContent = value + '%';
        publishMQTTCommand(opener, 'position', value);
    }

    // Misting Schedule Functions
    let customTimeSlotCounter = 0;
    let customTimeSlots = [];

    // Switch Misting Mode
    function switchMistMode(mode) {
        // Hide all mode settings
        document.getElementById('mode-day-settings').style.display = 'none';
        document.getElementById('mode-night-settings').style.display = 'none';
        document.getElementById('mode-both-settings').style.display = 'none';
        document.getElementById('mode-custom-settings').style.display = 'none';

        // Show selected mode settings
        document.getElementById('mode-' + mode + '-settings').style.display = 'block';

        // Initialize custom mode with one slot if empty
        if (mode === 'custom' && customTimeSlots.length === 0) {
            addCustomTimeSlot();
        }

        // Update active schedule display with current selected mode
        const modeNames = {
            day: '☀️ 주간',
            night: '🌙 야간',
            both: '🔄 24시간',
            custom: '⚙️ 사용자 지정'
        };
        const displayElement = document.getElementById('active-schedule-text');
        if (displayElement) {
            // Check if there's an active saved schedule
            const activeSchedule = savedSchedules.find(s => s.enabled);
            if (activeSchedule) {
                // Show active saved schedule
                displayElement.textContent = activeSchedule.name;
                displayElement.style.color = '#4CAF50';
            } else {
                // Show currently selecting mode
                displayElement.textContent = modeNames[mode] + ' (선택 중)';
                displayElement.style.color = '#FF9800'; // Orange color for "selecting" state
            }
            displayElement.style.fontWeight = 'bold';
        }
    }

    // Update Cycle Preview for each mode
    function updateCyclePreview(mode) {
        const duration = document.getElementById(mode + '-duration').value;
        const interval = document.getElementById(mode + '-interval').value;
        const preview = document.getElementById(mode + '-preview');

        const intervalMin = Math.floor(interval / 60);
        const intervalSec = interval % 60;
        const intervalText = intervalMin > 0 ? `${intervalMin}분 ${intervalSec}초` : `${intervalSec}초`;
        preview.innerHTML = `<small>💡 ${duration}초 분무 → ${intervalText} 대기 → 반복</small>`;
    }

    // Add Custom Time Slot
    function addCustomTimeSlot() {
        const slotId = customTimeSlotCounter++;
        const container = document.getElementById('custom-time-slots');

        const slotDiv = document.createElement('div');
        slotDiv.className = 'time-slot-card';
        slotDiv.id = 'slot-' + slotId;
        slotDiv.innerHTML = `
            <div class="time-slot-header">
                <h6>시간대 ${slotId + 1}</h6>
                <button onclick="removeCustomTimeSlot(${slotId})" class="btn btn-sm btn-danger">🗑️ 삭제</button>
            </div>
            <div class="time-slot-body">
                <div class="time-range-input">
                    <label>작동 시간</label>
                    <div class="time-inputs">
                        <input type="time" id="custom-start-${slotId}" class="time-input" value="08:00" onchange="validateTimeSlot(${slotId})">
                        <span class="time-separator">~</span>
                        <input type="time" id="custom-end-${slotId}" class="time-input" value="10:00" onchange="validateTimeSlot(${slotId})">
                    </div>
                    <small class="time-validation" id="validation-${slotId}"></small>
                </div>
                <div class="setting-row">
                    <label class="setting-label">무한 반복</label>
                    <label class="toggle-switch">
                        <input type="checkbox" id="custom-repeat-${slotId}" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="cycle-config">
                    <div class="cycle-item">
                        <label>분무 시간</label>
                        <div class="input-with-unit">
                            <input type="number" id="custom-duration-${slotId}" min="1" max="300" value="10" onchange="updateCustomPreview(${slotId})">
                            <span class="unit">초</span>
                        </div>
                    </div>
                    <span class="cycle-separator">→</span>
                    <div class="cycle-item">
                        <label>쉬는 시간</label>
                        <div class="input-with-unit">
                            <input type="number" id="custom-interval-${slotId}" min="1" max="3600" value="300" onchange="updateCustomPreview(${slotId})">
                            <span class="unit">초</span>
                        </div>
                    </div>
                </div>
                <div class="cycle-preview" id="custom-preview-${slotId}">
                    <small>💡 10초 분무 → 5분 대기 → 반복</small>
                </div>
            </div>
        `;

        container.appendChild(slotDiv);
        customTimeSlots.push(slotId);
    }

    // Remove Custom Time Slot
    function removeCustomTimeSlot(slotId) {
        const slot = document.getElementById('slot-' + slotId);
        if (slot) {
            slot.remove();
            customTimeSlots = customTimeSlots.filter(id => id !== slotId);
        }
    }

    // Update Custom Slot Preview
    function updateCustomPreview(slotId) {
        const duration = document.getElementById('custom-duration-' + slotId).value;
        const interval = document.getElementById('custom-interval-' + slotId).value;
        const preview = document.getElementById('custom-preview-' + slotId);

        const intervalMin = Math.floor(interval / 60);
        const intervalSec = interval % 60;
        const intervalText = intervalMin > 0 ? `${intervalMin}분 ${intervalSec}초` : `${intervalSec}초`;
        preview.innerHTML = `<small>💡 ${duration}초 분무 → ${intervalText} 대기 → 반복</small>`;
    }

    // Validate Time Slot (check overlaps)
    function validateTimeSlot(slotId) {
        const startTime = document.getElementById('custom-start-' + slotId).value;
        const endTime = document.getElementById('custom-end-' + slotId).value;
        const validation = document.getElementById('validation-' + slotId);

        // Check if end time is after start time
        if (startTime >= endTime) {
            validation.textContent = '⚠️ 종료 시간은 시작 시간보다 늦어야 합니다.';
            validation.style.color = '#f44336';
            return false;
        }

        // Check overlaps with other slots
        for (let otherId of customTimeSlots) {
            if (otherId === slotId) continue;

            const otherStart = document.getElementById('custom-start-' + otherId)?.value;
            const otherEnd = document.getElementById('custom-end-' + otherId)?.value;

            if (!otherStart || !otherEnd) continue;

            // Check if times overlap
            if ((startTime < otherEnd && endTime > otherStart)) {
                validation.textContent = `⚠️ 시간대 ${otherId + 1}과(와) 겹칩니다.`;
                validation.style.color = '#f44336';
                return false;
            }
        }

        validation.textContent = '✓ 유효한 시간대입니다.';
        validation.style.color = '#4CAF50';
        return true;
    }

    // Saved Schedules Management
    let savedSchedules = [];
    let scheduleIdCounter = 0;

    // Add Misting Schedule
    function addMistingSchedule() {
        const mode = document.querySelector('input[name="mist-mode"]:checked').value;
        let schedule = {
            id: scheduleIdCounter++,
            mode: mode,
            enabled: true,
            created_at: new Date().toLocaleString('ko-KR')
        };

        if (mode === 'custom') {
            // Validate all custom slots
            const slots = [];

            for (let slotId of customTimeSlots) {
                if (!validateTimeSlot(slotId)) {
                    alert('시간대 ' + (slotId + 1) + '에 오류가 있습니다. 확인해주세요.');
                    return;
                }

                slots.push({
                    start_time: document.getElementById('custom-start-' + slotId).value,
                    end_time: document.getElementById('custom-end-' + slotId).value,
                    repeat: document.getElementById('custom-repeat-' + slotId).checked,
                    duration: parseInt(document.getElementById('custom-duration-' + slotId).value),
                    interval: parseInt(document.getElementById('custom-interval-' + slotId).value)
                });
            }

            if (slots.length === 0) {
                alert('최소 하나의 시간대를 추가해주세요.');
                return;
            }

            schedule.slots = slots;
            schedule.name = `사용자 지정 (${slots.length}개 시간대)`;
        } else {
            // Standard modes (day, night, both)
            schedule.repeat = document.getElementById(mode + '-repeat').checked;
            schedule.duration = parseInt(document.getElementById(mode + '-duration').value);
            schedule.interval = parseInt(document.getElementById(mode + '-interval').value);

            const modeNames = { day: '☀️ 주간', night: '🌙 야간', both: '🔄 24시간' };
            schedule.name = modeNames[mode];
        }

        // Send schedule to server
        fetch('/api/smartfarm/schedule.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                device: 'misting_system',
                action: 'add',
                schedule: schedule
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                schedule.id = data.schedule_id || schedule.id;
                savedSchedules.push(schedule);
                renderSavedSchedules();
                updateActiveScheduleDisplay(); // 활성화된 스케줄 이름 업데이트
                alert('✅ 스케줄이 추가되었습니다.');
                publishMQTTCommand('mist_schedule', 'update', savedSchedules);
            } else {
                alert('❌ 스케줄 추가에 실패했습니다: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ 스케줄 추가 중 오류가 발생했습니다.');
        });
    }

    // Render Saved Schedules
    function renderSavedSchedules() {
        const container = document.getElementById('saved-schedules-list');

        if (savedSchedules.length === 0) {
            container.innerHTML = `
                <div class="empty-schedules">
                    <p>📭 등록된 스케줄이 없습니다.</p>
                    <small>위에서 스케줄을 설정하고 "➕ 스케줄 추가" 버튼을 눌러주세요.</small>
                </div>
            `;
            return;
        }

        container.innerHTML = savedSchedules.map(sch => {
            let detailsHTML = '';

            if (sch.mode === 'custom' && sch.slots) {
                detailsHTML = sch.slots.map(slot =>
                    `<div class="schedule-detail">⏰ ${slot.start_time} ~ ${slot.end_time} (${slot.duration}초 분무 / ${slot.interval}초 대기)</div>`
                ).join('');
            } else {
                detailsHTML = `<div class="schedule-detail">⏱️ ${sch.duration}초 분무 → ${sch.interval}초 대기 (${sch.repeat ? '무한반복' : '1회'})</div>`;
            }

            // 주간/야간/24시간 모드는 라디오 버튼처럼 동작 (하나만 선택)
            // 커스텀 모드는 여러 개 활성화 가능
            const isBasicMode = ['day', 'night', 'both'].includes(sch.mode);
            const toggleHtml = isBasicMode
                ? `<label class="toggle-switch">
                       <input type="radio" name="basic-mode-schedule" ${sch.enabled ? 'checked' : ''}
                              onchange="toggleSchedule(${sch.id}, this.checked, '${sch.mode}')">
                       <span class="toggle-slider"></span>
                   </label>`
                : `<label class="toggle-switch">
                       <input type="checkbox" ${sch.enabled ? 'checked' : ''}
                              onchange="toggleSchedule(${sch.id}, this.checked, '${sch.mode}')">
                       <span class="toggle-slider"></span>
                   </label>`;

            return `
                <div class="schedule-item ${sch.enabled ? 'enabled' : 'disabled'}">
                    <div class="schedule-item-header">
                        <div class="schedule-info">
                            <h6>${sch.name}</h6>
                            <small>등록: ${sch.created_at}</small>
                        </div>
                        <div class="schedule-controls">
                            ${toggleHtml}
                            <button onclick="deleteSchedule(${sch.id})" class="btn btn-sm btn-danger">
                                🗑️ 삭제
                            </button>
                        </div>
                    </div>
                    <div class="schedule-item-body">
                        ${detailsHTML}
                    </div>
                </div>
            `;
        }).join('');
    }

    // Update Active Schedule Display
    function updateActiveScheduleDisplay() {
        const activeSchedule = savedSchedules.find(s => s.enabled);
        const displayElement = document.getElementById('active-schedule-text');

        if (displayElement) {
            if (activeSchedule) {
                displayElement.textContent = activeSchedule.name;
                displayElement.style.color = '#4CAF50';
                displayElement.style.fontWeight = 'bold';
            } else {
                displayElement.textContent = '없음';
                displayElement.style.color = '#999';
                displayElement.style.fontWeight = 'normal';
            }
        }
    }

    // Toggle Schedule Enable/Disable
    function toggleSchedule(scheduleId, enabled, mode) {
        const schedule = savedSchedules.find(s => s.id === scheduleId);
        if (!schedule) return;

        // 주간/야간/24시간 모드는 상호 배타적 (하나만 활성화 가능)
        const isBasicMode = ['day', 'night', 'both'].includes(mode);

        if (isBasicMode && enabled) {
            // 다른 주간/야간/24시간 모드 스케줄을 모두 비활성화
            savedSchedules.forEach(s => {
                if (['day', 'night', 'both'].includes(s.mode) && s.id !== scheduleId) {
                    s.enabled = false;
                }
            });
            schedule.enabled = true;
            alert('✅ ' + schedule.name + ' 스케줄이 활성화되었습니다.\n다른 주간/야간/24시간 스케줄은 자동으로 비활성화되었습니다.');
        } else {
            // 커스텀 모드 또는 비활성화인 경우
            schedule.enabled = enabled;
            alert(enabled ? '✅ 스케줄이 활성화되었습니다.' : '⏸️ 스케줄이 비활성화되었습니다.');
        }

        renderSavedSchedules();
        updateActiveScheduleDisplay(); // 활성화된 스케줄 이름 업데이트

        // Send update to server
        fetch('/api/smartfarm/schedule.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                device: 'misting_system',
                action: 'toggle',
                schedule_id: scheduleId,
                enabled: enabled,
                mode: mode
            })
        });

        publishMQTTCommand('mist_schedule', 'update', savedSchedules);
    }

    // Delete Schedule
    function deleteSchedule(scheduleId) {
        if (!confirm('이 스케줄을 삭제하시겠습니까?')) return;

        // Send delete to server
        fetch('/api/smartfarm/schedule.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                device: 'misting_system',
                action: 'delete',
                schedule_id: scheduleId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                savedSchedules = savedSchedules.filter(s => s.id !== scheduleId);
                renderSavedSchedules();
                updateActiveScheduleDisplay(); // 활성화된 스케줄 이름 업데이트
                publishMQTTCommand('mist_schedule', 'update', savedSchedules);
                alert('✅ 스케줄이 삭제되었습니다.');
            } else {
                alert('❌ 스케줄 삭제에 실패했습니다: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ 스케줄 삭제 중 오류가 발생했습니다.');
        });
    }

    // Load Saved Schedules on page load
    function loadSavedSchedules() {
        fetch('/api/smartfarm/schedule.php?device=misting_system')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.schedules) {
                    savedSchedules = data.schedules;
                }
                // 항상 렌더링 (데이터가 없어도 빈 메시지 표시)
                renderSavedSchedules();
                updateActiveScheduleDisplay(); // 활성화된 스케줄 이름 업데이트
            })
            .catch(error => {
                console.error('Error loading schedules:', error);
                // 에러가 나도 빈 메시지 표시
                renderSavedSchedules();
                updateActiveScheduleDisplay(); // 에러 시에도 업데이트
            });
    }

    // Camera Management
    let cameras = [];
    let cameraIdCounter = 0;

    // Load Cameras
    function loadCameras() {
        fetch('/api/smartfarm/get_camera.php?action=list')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.cameras) {
                    cameras = data.cameras;
                }
                // 항상 렌더링 (데이터가 없어도 빈 메시지 표시)
                renderCameras();
            })
            .catch(error => {
                console.error('Error loading cameras:', error);
                // 에러가 나도 빈 메시지 표시
                renderCameras();
            });
    }

    // Render Cameras
    function renderCameras() {
        const grid = document.getElementById('camera-grid');

        if (cameras.length === 0) {
            grid.innerHTML = `
                <div class="empty-cameras">
                    <p>📭 등록된 카메라가 없습니다.</p>
                    <small>"➕ 카메라 추가" 버튼을 눌러 카메라를 등록하세요.</small>
                </div>
            `;
            return;
        }

        grid.innerHTML = cameras.map(cam => `
            <div class="camera-live-card">
                <div class="camera-live-header">
                    <span>${cam.icon || '📹'} ${cam.name}</span>
                    <div class="camera-header-actions">
                        <button onclick="fullscreenCamera('${cam.id}')" class="btn-icon">⛶</button>
                        <button onclick="deleteCamera(${cam.id})" class="btn-icon btn-danger-icon">🗑️</button>
                    </div>
                </div>
                <div class="camera-feed" id="feed-${cam.id}" onclick="openCameraModal('${cam.id}', '${cam.name}')">
                    <div class="camera-loading">
                        <span class="loading-icon">${cam.icon || '📹'}</span>
                        <span>카메라 연결 대기중...</span>
                    </div>
                </div>
            </div>
        `).join('');

        // Load camera feeds
        cameras.forEach(cam => {
            if (cam.stream_url) {
                loadCameraFeed(cam.id, document.getElementById('feed-' + cam.id));
            }
        });
    }

    // Open Add Camera Modal
    function openAddCameraModal() {
        document.getElementById('add-camera-modal').style.display = 'flex';
    }

    // Close Add Camera Modal
    function closeAddCameraModal() {
        document.getElementById('add-camera-modal').style.display = 'none';
        // Reset form
        document.getElementById('camera-name').value = '';
        document.getElementById('camera-stream-url').value = '';
        document.getElementById('camera-stream-type').value = 'rtsp';
        document.getElementById('camera-icon').value = '📹';
    }

    // Save Camera
    function saveCamera() {
        const name = document.getElementById('camera-name').value.trim();
        const streamUrl = document.getElementById('camera-stream-url').value.trim();
        const streamType = document.getElementById('camera-stream-type').value;
        const icon = document.getElementById('camera-icon').value;

        if (!name) {
            alert('카메라 이름을 입력해주세요.');
            return;
        }

        if (!streamUrl) {
            alert('스트림 URL을 입력해주세요.');
            return;
        }

        const camera = {
            name: name,
            stream_url: streamUrl,
            stream_type: streamType,
            icon: icon,
            enabled: true
        };

        fetch('/api/smartfarm/camera.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add',
                camera: camera
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                camera.id = data.camera_id || cameraIdCounter++;
                cameras.push(camera);
                renderCameras();
                closeAddCameraModal();
                alert('✅ 카메라가 추가되었습니다.');
            } else {
                alert('❌ 카메라 추가에 실패했습니다: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ 카메라 추가 중 오류가 발생했습니다.');
        });
    }

    // Delete Camera
    function deleteCamera(cameraId) {
        if (!confirm('이 카메라를 삭제하시겠습니까?')) return;

        fetch('/api/smartfarm/camera.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete',
                camera_id: cameraId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                cameras = cameras.filter(c => c.id !== cameraId);
                renderCameras();
                alert('✅ 카메라가 삭제되었습니다.');
            } else {
                alert('❌ 카메라 삭제에 실패했습니다: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ 카메라 삭제 중 오류가 발생했습니다.');
        });
    }

    // Open Camera Modal
    function openCameraModal(cameraId, cameraName) {
        const modal = document.getElementById('camera-modal');
        const title = document.getElementById('camera-modal-title');
        const feed = document.getElementById('camera-modal-feed');

        title.textContent = '📹 ' + cameraName;
        modal.style.display = 'flex';

        // Load camera feed
        const camera = cameras.find(c => c.id == cameraId);
        if (camera && camera.stream_url) {
            loadCameraFeed(cameraId, feed);
        }
    }

    // Close Camera Modal
    function closeCameraModal() {
        const modal = document.getElementById('camera-modal');
        modal.style.display = 'none';
    }

    // Fullscreen Camera
    function fullscreenCamera(cameraId) {
        const feed = document.getElementById('feed-' + cameraId);
        if (feed && feed.requestFullscreen) {
            feed.requestFullscreen();
        }
    }

    function loadCameraFeed(cameraId, container) {
        // API에서 카메라 설정 가져오기
        fetch(`/api/smartfarm/get_camera.php?id=${cameraId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.stream_url) {
                    // 카메라 스트림이 설정되어 있으면 표시
                    container.innerHTML = `
                        <img src="${data.stream_url}"
                             alt="${cameraId}"
                             style="width: 100%; height: 100%; object-fit: contain;"
                             onerror="this.src='/assets/images/camera-offline.png'">
                    `;
                } else {
                    // 설정되지 않은 경우
                    container.innerHTML = `
                        <div class="camera-loading">
                            <span class="loading-icon">📹</span>
                            <span>카메라가 설정되지 않았습니다</span>
                            <button onclick="window.location.href='/pages/plant_analysis/device_setup.php'"
                                    class="btn btn-primary btn-sm" style="margin-top: 1rem;">
                                카메라 설정하기
                            </button>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Camera load error:', error);
                container.innerHTML = `
                    <div class="camera-loading">
                        <span class="loading-icon">❌</span>
                        <span>카메라 로드 실패</span>
                    </div>
                `;
            });
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

    // ========== Page Initialization ==========
    // 모든 초기화를 한 곳에서 처리
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 Page initialization started...');

        // 1. 차트 초기화
        initializeCharts();
        loadChartData('24h');

        // 2. 스케줄 로드 및 렌더링
        loadSavedSchedules();

        // 3. 카메라 로드 및 렌더링
        loadCameras();

        // 4. 분무 모드 초기화 (기본값: 주간)
        switchMistMode('day');

        // 5. 장치 상태 복원 (localStorage에서 읽기)
        setTimeout(() => {
            restoreAllDeviceStates();
        }, 1000); // MQTT 연결 후 1초 뒤에 상태 복원

        // 6. MQTT 연결
        connectMQTT();

        console.log('✅ Page initialization completed');
    });
    </script>
</body>
</html>