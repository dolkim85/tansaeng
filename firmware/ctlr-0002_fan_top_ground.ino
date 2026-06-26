/****************************************************
 * ESP32 + SSR + DHT22  천창팬 + 지상팬(앞) + 지상팬(뒤) 제어  (ctlr-0002)
 *  ── 견고화(2026-06-26) ───────────────────────────
 *   1) 하드웨어 워치독(Task WDT): 루프 정지(크래시/행) 시 강제 재부팅
 *   2) WiFi 끊김 → 일정시간 복구 실패 시 재부팅 (+ 자동 재연결)
 *   3) MQTT 끊김 → 일정시간 복구 실패 시 재부팅
 *   4) 원격 재시작 토픽: tansaeng/ctlr-0002/restart 로 "restart" 수신 시 재부팅
 *   5) 5분 이상 오프라인 지속 시 재부팅 (좀비 상태 방지)
 ****************************************************/

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>
#include <DHT.h>
#include "esp_task_wdt.h"        // ★ 하드웨어 워치독

// =================== 사용자 설정 ===================
const char* WIFI_SSID     = "KT_GiGA_4619";
const char* WIFI_PASSWORD = "add68bb834";

const char* MQTT_BROKER   = "22ada06fd6cf4059bd700ddbf6004d68.s1.eu.hivemq.cloud";
const int   MQTT_PORT     = 8883;
const char* MQTT_USERNAME = "esp32-client-01";
const char* MQTT_PASSWORD = "Qjawns3445";

// 장치 식별
const char* CONTROLLER_ID = "ctlr-0002";
const char* SENSOR_TYPE   = "dht22";

// 팬 채널
const char* FAN_TOP          = "fan_top";
const char* FAN_GROUND       = "fan_ground";
const char* FAN_GROUND_BACK  = "fan_ground_back";

// 핀
const int FAN_TOP_PIN         = 5;
const int FAN_GROUND_PIN      = 18;
const int FAN_GROUND_BACK_PIN = 19;
const int DHT_PIN             = 4;

// AUTO 기준
const float TEMP_ON  = 28.0;
const float TEMP_OFF = 26.5;
const float HUM_ON   = 80.0;
const float HUM_OFF  = 75.0;

// 주기
const unsigned long SENSOR_MS    = 3000;
const unsigned long HEARTBEAT_MS = 60000;

// 보호 시간
const unsigned long MIN_ON_MS  = 15000;
const unsigned long MIN_OFF_MS = 8000;

// ★ 견고화 설정
const unsigned long WIFI_TIMEOUT_MS = 30000;    // WiFi 30초 내 못 붙으면 재부팅
const unsigned long MQTT_TIMEOUT_MS = 90000;    // MQTT 90초 내 못 붙으면 재부팅
const int           WDT_TIMEOUT_S   = 180;      // 루프 180초 멈추면 워치독 강제 재부팅
const unsigned long MAX_OFFLINE_MS  = 300000;   // 5분 이상 오프라인 지속 시 재부팅
// ===================================================

// 토픽
String topicCmdTop    = "tansaeng/" + String(CONTROLLER_ID) + "/" + FAN_TOP + "/cmd";
String topicStateTop  = "tansaeng/" + String(CONTROLLER_ID) + "/" + FAN_TOP + "/state";

String topicCmdGround   = "tansaeng/" + String(CONTROLLER_ID) + "/" + FAN_GROUND + "/cmd";
String topicStateGround = "tansaeng/" + String(CONTROLLER_ID) + "/" + FAN_GROUND + "/state";

String topicCmdGroundBack   = "tansaeng/" + String(CONTROLLER_ID) + "/" + FAN_GROUND_BACK + "/cmd";
String topicStateGroundBack = "tansaeng/" + String(CONTROLLER_ID) + "/" + FAN_GROUND_BACK + "/state";

String topicTemp    = "tansaeng/" + String(CONTROLLER_ID) + "/" + SENSOR_TYPE + "/temperature";
String topicHum     = "tansaeng/" + String(CONTROLLER_ID) + "/" + SENSOR_TYPE + "/humidity";
String topicStatus  = "tansaeng/" + String(CONTROLLER_ID) + "/status";
String topicRestart = "tansaeng/" + String(CONTROLLER_ID) + "/restart";   // ★ 원격 재시작

// 객체
WiFiClientSecure wifiClient;
PubSubClient mqtt(wifiClient);
DHT dht(DHT_PIN, DHT22);

// 상태
enum Mode { AUTO_MODE, MANUAL_MODE };
Mode mode = AUTO_MODE;

// 천창팬
bool fanTopOn = false;
bool manualTop = false;
unsigned long lastTopChange = 0;

// 지상팬 앞
bool fanGroundOn = false;
bool manualGround = false;
unsigned long lastGroundChange = 0;

// 지상팬 뒤
bool fanGroundBackOn = false;
bool manualGroundBack = false;
unsigned long lastGroundBackChange = 0;

float lastTemp = NAN;
float lastHum  = NAN;

unsigned long lastSensorTime    = 0;
unsigned long lastHeartbeatTime = 0;
unsigned long lastOnlineMs      = 0;   // ★ 마지막으로 MQTT 정상 연결됐던 시각

// =================== 워치독 / 재부팅 ===================
inline void feedWatchdog() { esp_task_wdt_reset(); }   // 워치독 먹이기(타이머 리셋)

void initWatchdog() {
#if ESP_ARDUINO_VERSION_MAJOR >= 3
  esp_task_wdt_config_t cfg = {
    .timeout_ms     = (uint32_t)WDT_TIMEOUT_S * 1000,
    .idle_core_mask = 0,
    .trigger_panic  = true
  };
  if (esp_task_wdt_init(&cfg) == ESP_ERR_INVALID_STATE) {
    esp_task_wdt_reconfigure(&cfg);   // 이미 초기화돼 있으면 재설정
  }
#else
  esp_task_wdt_init(WDT_TIMEOUT_S, true);
#endif
  esp_task_wdt_add(NULL);   // 현재(loop) 태스크를 워치독 감시 대상에 등록
}

void rebootDevice(const char* reason) {
  Serial.printf("[REBOOT] %s\n", reason);
  // 재부팅 전 오프라인 상태를 알릴 수 있으면 알림
  if (mqtt.connected()) { mqtt.publish(topicStatus.c_str(), "offline", true); mqtt.loop(); }
  delay(1000);
  ESP.restart();
}

// =================== 팬 제어 ===================
void setFanTop(bool on) {
  if (fanTopOn == on) return;
  unsigned long elapsed = millis() - lastTopChange;
  if ( on && elapsed < MIN_OFF_MS) return;
  if (!on && elapsed < MIN_ON_MS)  return;

  fanTopOn = on;
  digitalWrite(FAN_TOP_PIN, on ? HIGH : LOW);
  lastTopChange = millis();
  mqtt.publish(topicStateTop.c_str(), on ? "ON" : "OFF", true);
}

void setFanGround(bool on) {
  if (fanGroundOn == on) return;
  unsigned long elapsed = millis() - lastGroundChange;
  if ( on && elapsed < MIN_OFF_MS) return;
  if (!on && elapsed < MIN_ON_MS)  return;

  fanGroundOn = on;
  digitalWrite(FAN_GROUND_PIN, on ? HIGH : LOW);
  lastGroundChange = millis();
  mqtt.publish(topicStateGround.c_str(), on ? "ON" : "OFF", true);
}

void setFanGroundBack(bool on) {
  if (fanGroundBackOn == on) return;
  unsigned long elapsed = millis() - lastGroundBackChange;
  if ( on && elapsed < MIN_OFF_MS) return;
  if (!on && elapsed < MIN_ON_MS)  return;

  fanGroundBackOn = on;
  digitalWrite(FAN_GROUND_BACK_PIN, on ? HIGH : LOW);
  lastGroundBackChange = millis();
  mqtt.publish(topicStateGroundBack.c_str(), on ? "ON" : "OFF", true);
}

// =================== AUTO ===================
void handleAuto() {
  if (isnan(lastTemp) || isnan(lastHum)) return;

  bool needOn  = (lastTemp >= TEMP_ON)  || (lastHum >= HUM_ON);
  bool needOff = (lastTemp <= TEMP_OFF) && (lastHum <= HUM_OFF);

  if (needOn) {
    setFanTop(true);
    setFanGround(true);
    setFanGroundBack(true);
  }
  if (needOff) {
    setFanTop(false);
    setFanGround(false);
    setFanGroundBack(false);
  }
}

// =================== MQTT ===================
void mqttCallback(char* topic, byte* payload, unsigned int len) {
  String t(topic);
  String msg;
  for (unsigned int i = 0; i < len; i++) msg += (char)payload[i];
  msg.trim();
  msg.toUpperCase();

  // ★ 원격 재시작
  if (t == topicRestart) {
    if (msg == "RESTART" || msg == "REBOOT" || msg == "1") rebootDevice("원격 재시작 명령 수신");
    return;
  }

  // 천창팬
  if (t == topicCmdTop) {
    if (msg == "AUTO")        { mode = AUTO_MODE; }
    else if (msg == "MANUAL") { mode = MANUAL_MODE; }
    else if (msg == "ON")     { mode = MANUAL_MODE; manualTop = true;  setFanTop(true); }
    else if (msg == "OFF")    { mode = MANUAL_MODE; manualTop = false; setFanTop(false); }
  }

  // 지상팬 앞
  if (t == topicCmdGround) {
    if (msg == "ON")  { manualGround = true;  setFanGround(true); }
    if (msg == "OFF") { manualGround = false; setFanGround(false); }
  }

  // 지상팬 뒤
  if (t == topicCmdGroundBack) {
    if (msg == "ON")  { manualGroundBack = true;  setFanGroundBack(true); }
    if (msg == "OFF") { manualGroundBack = false; setFanGroundBack(false); }
  }
}

// =================== 연결 ===================
void connectWiFi() {
  Serial.println("[WiFi] connecting...");
  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);        // 끊겨도 자동 재접속 시도
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED) {
    feedWatchdog();                   // 대기 중에도 워치독 먹이기
    delay(500);
    Serial.print(".");
    if (millis() - start > WIFI_TIMEOUT_MS) {
      rebootDevice("WiFi 30초 연결 실패");
    }
  }
  Serial.println("\n[WiFi] connected!  IP: " + WiFi.localIP().toString());
}

void connectMQTT() {
  unsigned long start = millis();
  while (!mqtt.connected()) {
    feedWatchdog();

    // 연결 시도 중 WiFi가 끊기면 loop()가 먼저 WiFi 복구하도록 빠져나감
    if (WiFi.status() != WL_CONNECTED) return;

    String cid = "ESP32-FAN-";
    cid += String((uint32_t)ESP.getEfuseMac(), HEX);

    if (mqtt.connect(cid.c_str(),
                     MQTT_USERNAME, MQTT_PASSWORD,
                     topicStatus.c_str(), 0, true, "offline")) {

      mqtt.publish(topicStatus.c_str(), "online", true);

      mqtt.subscribe(topicCmdTop.c_str(), 1);
      mqtt.subscribe(topicCmdGround.c_str(), 1);
      mqtt.subscribe(topicCmdGroundBack.c_str(), 1);
      mqtt.subscribe(topicRestart.c_str(), 1);     // ★ 원격 재시작 구독

      mqtt.publish(topicStateTop.c_str(),         fanTopOn         ? "ON" : "OFF", true);
      mqtt.publish(topicStateGround.c_str(),      fanGroundOn      ? "ON" : "OFF", true);
      mqtt.publish(topicStateGroundBack.c_str(),  fanGroundBackOn  ? "ON" : "OFF", true);

      lastOnlineMs = millis();        // ★ 연결 성공 기록
      Serial.println("[MQTT] connected!");
    } else {
      Serial.printf("[MQTT] 실패 (rc=%d) — 재시도\n", mqtt.state());
      if (millis() - start > MQTT_TIMEOUT_MS) {
        rebootDevice("MQTT 90초 연결 실패");
      }
      // 재시도 대기(5초) 중에도 워치독 먹이기
      for (int i = 0; i < 10 && !mqtt.connected(); i++) { feedWatchdog(); delay(500); }
    }
  }
}

// =================== setup ===================
void setup() {
  Serial.begin(115200);

  pinMode(FAN_TOP_PIN,         OUTPUT);
  pinMode(FAN_GROUND_PIN,      OUTPUT);
  pinMode(FAN_GROUND_BACK_PIN, OUTPUT);

  digitalWrite(FAN_TOP_PIN,         LOW);
  digitalWrite(FAN_GROUND_PIN,      LOW);
  digitalWrite(FAN_GROUND_BACK_PIN, LOW);

  dht.begin();

  initWatchdog();                     // ★ 가장 먼저 워치독 설치
  lastOnlineMs = millis();

  connectWiFi();
  wifiClient.setInsecure();

  mqtt.setServer(MQTT_BROKER, MQTT_PORT);
  mqtt.setCallback(mqttCallback);
  mqtt.setKeepAlive(30);              // 끊김 빠르게 감지(기본 15s → 30s 여유)

  connectMQTT();
}

// =================== loop ===================
void loop() {
  feedWatchdog();                     // ★ 매 루프 워치독 먹이기

  if (WiFi.status() != WL_CONNECTED) connectWiFi();
  if (!mqtt.connected())             connectMQTT();
  mqtt.loop();

  // ★ 오프라인 지속 감시: 정상 연결이면 시각 갱신, 5분 넘게 끊겨있으면 재부팅
  if (mqtt.connected()) {
    lastOnlineMs = millis();
  } else if (millis() - lastOnlineMs > MAX_OFFLINE_MS) {
    rebootDevice("5분 이상 오프라인 지속");
  }

  unsigned long now = millis();

  if (now - lastSensorTime >= SENSOR_MS) {
    lastSensorTime = now;

    float h = dht.readHumidity();
    float t = dht.readTemperature();

    if (!isnan(t) && !isnan(h)) {
      lastTemp = t;
      lastHum  = h;

      Serial.printf("[DHT] 온도: %.1f°C  습도: %.1f%%\n", t, h);

      char buf[12];
      snprintf(buf, sizeof(buf), "%.2f", t);
      mqtt.publish(topicTemp.c_str(), buf, false);

      snprintf(buf, sizeof(buf), "%.2f", h);
      mqtt.publish(topicHum.c_str(), buf, false);

      if (mode == AUTO_MODE) handleAuto();
      else {
        setFanTop(manualTop);
        setFanGround(manualGround);
        setFanGroundBack(manualGroundBack);
      }
    }
  }

  if (now - lastHeartbeatTime >= HEARTBEAT_MS) {
    lastHeartbeatTime = now;
    mqtt.publish(topicStatus.c_str(), "online", true);
  }
}
