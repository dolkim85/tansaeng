<?php
require_once __DIR__ . '/../../classes/Auth.php';

$currentUser = null;
$mqttConfig = null;
$devices = [];

try {
    $auth = Auth::getInstance();
    $auth->requirePlantAnalysisPermission();
    $currentUser = $auth->getCurrentUser();

    $db = Database::getInstance();

    // MQTT 설정 조회
    $mqttConfig = $db->selectOne(
        "SELECT * FROM smartfarm_mqtt_configs WHERE user_id = ?",
        [$currentUser['id']]
    );

    // 등록된 디바이스 조회
    $devices = $db->select(
        "SELECT * FROM smartfarm_devices WHERE user_id = ? ORDER BY created_at DESC",
        [$currentUser['id']]
    );

} catch (Exception $e) {
    if (strpos($e->getMessage(), '권한') !== false || strpos($e->getMessage(), '로그인') !== false) {
        header('Location: /pages/plant_analysis/access_denied.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>디바이스 설정 - 식물분석 시스템</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/analysis.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main class="analysis-main">
        <div class="container">
            <div class="page-header">
                <nav class="breadcrumb">
                    <a href="/pages/plant_analysis/">식물분석</a> >
                    <a href="/pages/plant_analysis/sensor_data.php">환경 데이터</a> >
                    디바이스 설정
                </nav>
                <h1>⚙️ 디바이스 설정</h1>
                <p>ESP32 또는 라즈베리파이를 연결하고 MQTT 브로커를 설정하세요.</p>
            </div>

            <!-- MQTT 브로커 설정 -->
            <div class="settings-section">
                <h3>🌐 MQTT 브로커 설정</h3>
                <p style="text-align: center; color: #666; margin-bottom: 2rem;">
                    HiveMQ Cloud 또는 다른 MQTT 브로커의 연결 정보를 입력하세요.
                </p>

                <form id="mqtt-config-form" class="config-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="broker-url">브로커 URL *</label>
                            <input type="text" id="broker-url" name="broker_url"
                                   value="<?= $mqttConfig['broker_url'] ?? '' ?>"
                                   placeholder="예: xxxxxxxx.s1.eu.hivemq.cloud" required>
                            <small>HiveMQ Cloud 대시보드에서 확인하세요</small>
                        </div>

                        <div class="form-group">
                            <label for="broker-port">포트 *</label>
                            <input type="number" id="broker-port" name="broker_port"
                                   value="<?= $mqttConfig['broker_port'] ?? '8883' ?>" required>
                            <small>기본값: 8883 (TLS), 8884 (WebSocket)</small>
                        </div>

                        <div class="form-group">
                            <label for="mqtt-username">사용자명 *</label>
                            <input type="text" id="mqtt-username" name="username"
                                   value="<?= $mqttConfig['username'] ?? '' ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="mqtt-password">비밀번호 *</label>
                            <input type="password" id="mqtt-password" name="password"
                                   placeholder="<?= $mqttConfig ? '비밀번호 변경시에만 입력' : '비밀번호 입력' ?>"
                                   <?= $mqttConfig ? '' : 'required' ?>>
                        </div>

                        <div class="form-group">
                            <label for="use-tls">
                                <input type="checkbox" id="use-tls" name="use_tls"
                                       <?= ($mqttConfig['use_tls'] ?? 1) ? 'checked' : '' ?>>
                                TLS/SSL 사용 (권장)
                            </label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">💾 설정 저장</button>
                        <button type="button" onclick="testMQTTConnection()" class="btn btn-outline">🔌 연결 테스트</button>
                    </div>
                </form>

                <div id="mqtt-test-result" class="test-result" style="display: none;"></div>
            </div>

            <!-- 디바이스 등록 -->
            <div class="settings-section">
                <h3>📡 디바이스 등록</h3>
                <p style="text-align: center; color: #666; margin-bottom: 2rem;">
                    ESP32 또는 라즈베리파이를 등록하여 연결하세요.
                </p>

                <!-- 연결 가이드 -->
                <div class="connection-guide">
                    <h4>🔗 연결 방법</h4>
                    <div class="guide-steps">
                        <div class="guide-step">
                            <span class="step-number">1</span>
                            <div class="step-content">
                                <h5>MQTT 정보 입력</h5>
                                <p>ESP32/라즈베리파이 코드에 다음 정보를 입력하세요:</p>
                                <div class="code-block">
                                    <code>
                                        <div>브로커: <?= $mqttConfig['broker_url'] ?? '[위에서 설정]' ?></div>
                                        <div>포트: <?= $mqttConfig['broker_port'] ?? '8883' ?></div>
                                        <div>사용자 ID: <?= $currentUser['id'] ?></div>
                                        <div>토픽 접두사: smartfarm/<?= $currentUser['id'] ?>/</div>
                                    </code>
                                </div>
                            </div>
                        </div>

                        <div class="guide-step">
                            <span class="step-number">2</span>
                            <div class="step-content">
                                <h5>디바이스 등록</h5>
                                <p>아래 폼에서 디바이스 정보를 입력하고 등록하세요.</p>
                            </div>
                        </div>

                        <div class="guide-step">
                            <span class="step-number">3</span>
                            <div class="step-content">
                                <h5>디바이스 연결</h5>
                                <p>ESP32/라즈베리파이를 전원에 연결하면 자동으로 연결됩니다.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 디바이스 등록 폼 -->
                <form id="device-register-form" class="config-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="device-id">디바이스 ID *</label>
                            <input type="text" id="device-id" name="device_id"
                                   placeholder="예: ESP32_A1B2C3 또는 RPI_X1Y2Z3" required>
                            <small>ESP32 MAC 주소 또는 고유 ID</small>
                        </div>

                        <div class="form-group">
                            <label for="device-name">디바이스 이름 *</label>
                            <input type="text" id="device-name" name="device_name"
                                   placeholder="예: 1번 온실" required>
                        </div>

                        <div class="form-group">
                            <label for="device-type">디바이스 타입 *</label>
                            <select id="device-type" name="device_type" required>
                                <option value="esp32">ESP32</option>
                                <option value="raspberry_pi">라즈베리파이</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">➕ 디바이스 등록</button>
                    </div>
                </form>

                <!-- 등록된 디바이스 목록 -->
                <div class="registered-devices">
                    <h4>등록된 디바이스</h4>
                    <?php if (empty($devices)): ?>
                        <div class="no-devices">
                            <p>등록된 디바이스가 없습니다.</p>
                        </div>
                    <?php else: ?>
                        <div class="device-list">
                            <?php foreach ($devices as $device): ?>
                                <div class="device-card">
                                    <div class="device-status <?= $device['is_active'] ? 'status-online' : 'status-offline' ?>"></div>
                                    <div class="device-info">
                                        <h4><?= htmlspecialchars($device['device_name']) ?></h4>
                                        <p>ID: <?= htmlspecialchars($device['device_id']) ?></p>
                                        <small>타입: <?= htmlspecialchars($device['device_type']) ?> |
                                              마지막 접속: <?= $device['last_online'] ? date('Y-m-d H:i', strtotime($device['last_online'])) : '없음' ?></small>
                                    </div>
                                    <button onclick="deleteDevice(<?= $device['id'] ?>)" class="btn btn-sm btn-danger">삭제</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 샘플 코드 -->
            <div class="settings-section">
                <h3>💻 샘플 코드</h3>
                <p style="text-align: center; color: #666; margin-bottom: 2rem;">
                    ESP32 또는 라즈베리파이에서 사용할 수 있는 샘플 코드입니다.
                </p>

                <div class="code-samples">
                    <div class="code-sample">
                        <h4>ESP32 Arduino 코드</h4>
                        <button onclick="downloadSampleCode('esp32')" class="btn btn-outline btn-sm">⬇️ 다운로드</button>
                        <pre class="code-preview">
<code>#include &lt;WiFi.h&gt;
#include &lt;PubSubClient.h&gt;

const char* ssid = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";
const char* mqtt_server = "<?= $mqttConfig['broker_url'] ?? 'YOUR_BROKER_URL' ?>";
const int mqtt_port = <?= $mqttConfig['broker_port'] ?? '8883' ?>;
const char* mqtt_user = "<?= $mqttConfig['username'] ?? 'YOUR_USERNAME' ?>";
const char* mqtt_password = "YOUR_MQTT_PASSWORD";
const char* user_id = "<?= $currentUser['id'] ?>";

WiFiClient espClient;
PubSubClient client(espClient);

void setup() {
  Serial.begin(115200);
  setup_wifi();
  client.setServer(mqtt_server, mqtt_port);
  client.setCallback(callback);
}

void setup_wifi() {
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("WiFi connected");
}

void callback(char* topic, byte* payload, unsigned int length) {
  // 제어 명령 수신 처리
  String message = "";
  for (int i = 0; i < length; i++) {
    message += (char)payload[i];
  }
  Serial.println("Message: " + message);

  // JSON 파싱 및 장치 제어
  // ...
}

void loop() {
  if (!client.connected()) {
    reconnect();
  }
  client.loop();

  // 센서 데이터 전송
  // ...
}</code>
                        </pre>
                    </div>

                    <div class="code-sample">
                        <h4>라즈베리파이 Python 코드</h4>
                        <button onclick="downloadSampleCode('raspberry')" class="btn btn-outline btn-sm">⬇️ 다운로드</button>
                        <pre class="code-preview">
<code>import paho.mqtt.client as mqtt
import json
import time

BROKER = "<?= $mqttConfig['broker_url'] ?? 'YOUR_BROKER_URL' ?>"
PORT = <?= $mqttConfig['broker_port'] ?? '8883' ?>
USERNAME = "<?= $mqttConfig['username'] ?? 'YOUR_USERNAME' ?>"
PASSWORD = "YOUR_MQTT_PASSWORD"
USER_ID = "<?= $currentUser['id'] ?>"
TOPIC_PREFIX = f"smartfarm/{USER_ID}/"

def on_connect(client, userdata, flags, rc):
    print(f"Connected with result code {rc}")
    # 모든 제어 토픽 구독
    client.subscribe(f"{TOPIC_PREFIX}+")

def on_message(client, userdata, msg):
    print(f"Topic: {msg.topic}, Message: {msg.payload.decode()}")
    data = json.loads(msg.payload.decode())

    # 제어 명령 처리
    # ...

client = mqtt.Client()
client.username_pw_set(USERNAME, PASSWORD)
client.on_connect = on_connect
client.on_message = on_message

# TLS 설정 (필요시)
# client.tls_set()

client.connect(BROKER, PORT, 60)

# 메인 루프
client.loop_start()

while True:
    # 센서 데이터 수집 및 전송
    sensor_data = {
        "temperature": 25.5,
        "humidity": 65.0,
        # ...
    }

    client.publish(f"{TOPIC_PREFIX}sensors", json.dumps(sensor_data))
    time.sleep(60)  # 60초마다 전송</code>
                        </pre>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <script>
    // MQTT 설정 저장
    document.getElementById('mqtt-config-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const data = {
            broker_url: formData.get('broker_url'),
            broker_port: parseInt(formData.get('broker_port')),
            username: formData.get('username'),
            password: formData.get('password'),
            use_tls: formData.get('use_tls') ? 1 : 0
        };

        try {
            const response = await fetch('/api/smartfarm/save_mqtt_config.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            if (result.success) {
                alert('MQTT 설정이 저장되었습니다.');
                location.reload();
            } else {
                alert('저장 실패: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('저장 중 오류가 발생했습니다.');
        }
    });

    // 디바이스 등록
    document.getElementById('device-register-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const data = {
            device_id: formData.get('device_id'),
            device_name: formData.get('device_name'),
            device_type: formData.get('device_type')
        };

        try {
            const response = await fetch('/api/smartfarm/register_device.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            if (result.success) {
                alert('디바이스가 등록되었습니다.');
                location.reload();
            } else {
                alert('등록 실패: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('등록 중 오류가 발생했습니다.');
        }
    });

    // 디바이스 삭제
    async function deleteDevice(deviceId) {
        if (!confirm('정말 삭제하시겠습니까?')) return;

        try {
            const response = await fetch('/api/smartfarm/delete_device.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ device_id: deviceId })
            });

            const result = await response.json();
            if (result.success) {
                alert('디바이스가 삭제되었습니다.');
                location.reload();
            } else {
                alert('삭제 실패: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('삭제 중 오류가 발생했습니다.');
        }
    }

    // MQTT 연결 테스트
    function testMQTTConnection() {
        alert('MQTT 연결 테스트 기능은 구현 예정입니다.');
    }

    // 샘플 코드 다운로드
    function downloadSampleCode(type) {
        window.location.href = `/api/smartfarm/download_sample.php?type=${type}`;
    }
    </script>

    <style>
    .config-form {
        max-width: 800px;
        margin: 0 auto;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-group label {
        font-weight: 600;
        color: #2E7D32;
    }

    .form-group input,
    .form-group select {
        padding: 0.8rem;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 1rem;
    }

    .form-group small {
        color: #666;
        font-size: 0.85rem;
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
    }

    .connection-guide {
        background: #f8f9fa;
        padding: 2rem;
        border-radius: 12px;
        margin-bottom: 2rem;
    }

    .connection-guide h4 {
        color: #2E7D32;
        margin-bottom: 1.5rem;
        text-align: center;
    }

    .guide-steps {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .guide-step {
        display: flex;
        gap: 1rem;
        align-items: flex-start;
    }

    .step-number {
        width: 40px;
        height: 40px;
        background: #4CAF50;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        flex-shrink: 0;
    }

    .step-content h5 {
        color: #2E7D32;
        margin-bottom: 0.5rem;
    }

    .code-block {
        background: #2d2d2d;
        color: #f8f8f2;
        padding: 1rem;
        border-radius: 8px;
        margin-top: 0.5rem;
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
    }

    .code-block code div {
        margin: 0.3rem 0;
    }

    .registered-devices {
        margin-top: 2rem;
    }

    .registered-devices h4 {
        color: #2E7D32;
        margin-bottom: 1rem;
    }

    .device-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .btn-danger {
        background: #f44336;
        color: white;
        border: 2px solid #f44336;
    }

    .btn-danger:hover {
        background: #d32f2f;
        border-color: #d32f2f;
    }

    .code-samples {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 2rem;
    }

    .code-sample {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 12px;
    }

    .code-sample h4 {
        color: #2E7D32;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .code-preview {
        background: #2d2d2d;
        color: #f8f8f2;
        padding: 1rem;
        border-radius: 8px;
        overflow-x: auto;
        font-size: 0.85rem;
        max-height: 400px;
        overflow-y: auto;
    }

    .code-preview code {
        font-family: 'Courier New', monospace;
        line-height: 1.5;
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }

        .code-samples {
            grid-template-columns: 1fr;
        }
    }
    </style>
</body>
</html>
