/**
 * 탄생농원 스마트팜 ESP32 펌웨어
 *
 * HiveMQ Cloud 연결 정보:
 * - MQTT Broker: 22ada06fd6cf4059bd700ddbf6004d68.s1.eu.hivemq.cloud:8883
 * - Username: esp32-client-01
 * - Password: Qjawns3445
 * - TLS: Enabled
 *
 * GPIO 핀 매핑:
 * - GPIO 5: Fan1 (릴레이)
 * - GPIO 4: DHT22 온습도 센서
 */

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>
#include <DHT.h>
#include <ArduinoJson.h>

// ===== WiFi 설정 =====
const char* WIFI_SSID = "YOUR_WIFI_SSID";        // WiFi SSID로 변경
const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD"; // WiFi 비밀번호로 변경

// ===== HiveMQ Cloud 설정 =====
const char* MQTT_BROKER = "22ada06fd6cf4059bd700ddbf6004d68.s1.eu.hivemq.cloud";
const int MQTT_PORT = 8883;
const char* MQTT_USERNAME = "esp32-client-01";
const char* MQTT_PASSWORD = "Qjawns3445";
const char* MQTT_CLIENT_ID = "esp32-client-01";

// ===== GPIO 핀 설정 =====
#define FAN1_PIN 5       // 내부팬 앞
#define DHT_PIN 4        // DHT22 온습도 센서
#define DHT_TYPE DHT22   // DHT22 센서 타입

// ===== MQTT 토픽 =====
const char* TOPIC_COMMAND = "tansaeng/control/command";
const char* TOPIC_STATUS = "tansaeng/sensors/status";
const char* TOPIC_TEMPERATURE = "tansaeng/sensors/temperature";
const char* TOPIC_HUMIDITY = "tansaeng/sensors/humidity";

// ===== 객체 생성 =====
WiFiClientSecure wifiClient;
PubSubClient mqttClient(wifiClient);
DHT dht(DHT_PIN, DHT_TYPE);

// ===== 상태 변수 =====
bool fan1State = false;
unsigned long lastSensorRead = 0;
const unsigned long SENSOR_INTERVAL = 5000; // 5초마다 센서 데이터 전송

// ===== HiveMQ Cloud Root CA Certificate =====
// (2025년 기준 DigiCert Global Root CA)
const char* ROOT_CA = R"EOF(
-----BEGIN CERTIFICATE-----
MIIDrzCCApegAwIBAgIQCDvgVpBCRrGhdWrJWZHHSjANBgkqhkiG9w0BAQUFADBh
MQswCQYDVQQGEwJVUzEVMBMGA1UEChMMRGlnaUNlcnQgSW5jMRkwFwYDVQQLExB3
d3cuZGlnaWNlcnQuY29tMSAwHgYDVQQDExdEaWdpQ2VydCBHbG9iYWwgUm9vdCBD
QTAeFw0wNjExMTAwMDAwMDBaFw0zMTExMTAwMDAwMDBaMGExCzAJBgNVBAYTAlVT
MRUwEwYDVQQKEwxEaWdpQ2VydCBJbmMxGTAXBgNVBAsTEHd3dy5kaWdpY2VydC5j
b20xIDAeBgNVBAMTF0RpZ2lDZXJ0IEdsb2JhbCBSb290IENBMIIBIjANBgkqhkiG
9w0BAQEFAAOCAQ8AMIIBCgKCAQEA4jvhEXLeqKTTo1eqUKKPC3eQyaKl7hLOllsB
CSDMAZOnTjC3U/dDxGkAV53ijSLdhwZAAIEJzs4bg7/fzTtxRuLWZscFs3YnFo97
nh6Vfe63SKMI2tavegw5BmV/Sl0fvBf4q77uKNd0f3p4mVmFaG5cIzJLv07A6Fpt
43C/dxC//AH2hdmoRBBYMql1GNXRor5H4idq9Joz+EkIYIvUX7Q6hL+hqkpMfT7P
T19sdl6gSzeRntwi5m3OFBqOasv+zbMUZBfHWymeMr/y7vrTC0LUq7dBMtoM1O/4
gdW7jVg/tRvoSSiicNoxBN33shbyTApOB6jtSj1etX+jkMOvJwIDAQABo2MwYTAO
BgNVHQ8BAf8EBAMCAYYwDwYDVR0TAQH/BAUwAwEB/zAdBgNVHQ4EFgQUA95QNVbR
TLtm8KPiGxvDl7I90VUwHwYDVR0jBBgwFoAUA95QNVbRTLtm8KPiGxvDl7I90VUw
DQYJKoZIhvcNAQEFBQADggEBAMucN6pIExIK+t1EnE9SsPTfrgT1eXkIoyQY/Esr
hMAtudXH/vTBH1jLuG2cenTnmCmrEbXjcKChzUyImZOMkXDiqw8cvpOp/2PV5Adg
06O/nVsJ8dWO41P0jmP6P6fbtGbfYmbW0W5BjfIttep3Sp+dWOIrWcBAI+0tKIJF
PnlUkiaY4IBIqDfv8NZ5YBberOgOzW6sRBc4L0na4UU+Krk2U886UAb3LujEV0ls
YSEY1QSteDwsOoBrp+uvFRTp2InBuThs4pFsiv9kuXclVzDAGySj4dzp30d8tbQk
CAUw7C29C79Fv1C5qfPrmAESrciIxpg0X40KPMbp1ZWVbd4=
-----END CERTIFICATE-----
)EOF";

void setup() {
  Serial.begin(115200);
  Serial.println("\n\n=== 탄생농원 스마트팜 ESP32 시작 ===");

  // GPIO 핀 초기화
  pinMode(FAN1_PIN, OUTPUT);
  digitalWrite(FAN1_PIN, LOW);

  // DHT22 센서 초기화
  dht.begin();

  // WiFi 연결
  connectWiFi();

  // MQTT 설정
  wifiClient.setCACert(ROOT_CA);
  mqttClient.setServer(MQTT_BROKER, MQTT_PORT);
  mqttClient.setCallback(mqttCallback);
  mqttClient.setKeepAlive(60);

  // MQTT 연결
  connectMQTT();
}

void loop() {
  // WiFi 재연결 확인
  if (WiFi.status() != WL_CONNECTED) {
    connectWiFi();
  }

  // MQTT 재연결 확인
  if (!mqttClient.connected()) {
    connectMQTT();
  }

  mqttClient.loop();

  // 주기적으로 센서 데이터 전송
  unsigned long now = millis();
  if (now - lastSensorRead >= SENSOR_INTERVAL) {
    lastSensorRead = now;
    publishSensorData();
  }
}

// ===== WiFi 연결 함수 =====
void connectWiFi() {
  Serial.print("WiFi 연결 중: ");
  Serial.println(WIFI_SSID);

  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n✅ WiFi 연결 성공!");
    Serial.print("IP 주소: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("\n❌ WiFi 연결 실패!");
  }
}

// ===== MQTT 연결 함수 =====
void connectMQTT() {
  Serial.println("HiveMQ Cloud 연결 중...");

  int attempts = 0;
  while (!mqttClient.connected() && attempts < 5) {
    Serial.print("MQTT 연결 시도 ");
    Serial.print(attempts + 1);
    Serial.println("/5");

    if (mqttClient.connect(MQTT_CLIENT_ID, MQTT_USERNAME, MQTT_PASSWORD)) {
      Serial.println("✅ HiveMQ Cloud 연결 성공!");

      // 명령 토픽 구독
      mqttClient.subscribe(TOPIC_COMMAND);
      Serial.print("📥 구독: ");
      Serial.println(TOPIC_COMMAND);

      // 초기 상태 발행
      publishStatus();

    } else {
      Serial.print("❌ 연결 실패, 오류 코드: ");
      Serial.println(mqttClient.state());
      Serial.println("5초 후 재시도...");
      delay(5000);
    }

    attempts++;
  }
}

// ===== MQTT 메시지 수신 콜백 =====
void mqttCallback(char* topic, byte* payload, unsigned int length) {
  Serial.print("📩 메시지 수신 [");
  Serial.print(topic);
  Serial.print("]: ");

  // JSON 파싱
  StaticJsonDocument<256> doc;
  DeserializationError error = deserializeJson(doc, payload, length);

  if (error) {
    Serial.println("JSON 파싱 실패!");
    return;
  }

  // 명령 처리
  const char* device = doc["device"];
  const char* action = doc["action"];

  Serial.print(device);
  Serial.print(" -> ");
  Serial.println(action);

  // Fan1 제어
  if (strcmp(device, "fan1-front") == 0) {
    if (strcmp(action, "on") == 0) {
      digitalWrite(FAN1_PIN, HIGH);
      fan1State = true;
      Serial.println("✅ Fan1 ON");
    } else if (strcmp(action, "off") == 0) {
      digitalWrite(FAN1_PIN, LOW);
      fan1State = false;
      Serial.println("✅ Fan1 OFF");
    }

    // 상태 발행
    publishStatus();
  }
}

// ===== 센서 데이터 발행 =====
void publishSensorData() {
  float temperature = dht.readTemperature();
  float humidity = dht.readHumidity();

  if (isnan(temperature) || isnan(humidity)) {
    Serial.println("⚠️ DHT22 센서 읽기 실패!");
    return;
  }

  // JSON 생성
  StaticJsonDocument<256> doc;
  doc["temperature"] = temperature;
  doc["humidity"] = humidity;
  doc["timestamp"] = millis();

  char buffer[256];
  serializeJson(doc, buffer);

  // 온도 발행
  mqttClient.publish(TOPIC_TEMPERATURE, buffer);

  // 습도 발행
  mqttClient.publish(TOPIC_HUMIDITY, buffer);

  Serial.print("📤 센서 데이터: 온도=");
  Serial.print(temperature);
  Serial.print("°C, 습도=");
  Serial.print(humidity);
  Serial.println("%");
}

// ===== 장치 상태 발행 =====
void publishStatus() {
  StaticJsonDocument<256> doc;
  doc["fan1"] = fan1State ? "on" : "off";
  doc["timestamp"] = millis();

  char buffer[256];
  serializeJson(doc, buffer);

  mqttClient.publish(TOPIC_STATUS, buffer);

  Serial.println("📤 상태 발행 완료");
}
