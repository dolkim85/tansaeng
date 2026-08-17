#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>
#include <DHT.h>
#include <OneWire.h>
#include <DallasTemperature.h>

// ===================== 사용자 설정 =====================
const char* WIFI_SSID     = "KT_GiGA_4619";
const char* WIFI_PASSWORD = "add68bb834";

const char* MQTT_BROKER   = "22ada06fd6cf4059bd700ddbf6004d68.s1.eu.hivemq.cloud";
const int   MQTT_PORT     = 8883;
const char* MQTT_USER     = "esp32-client-01";
const char* MQTT_PASS     = "Qjawns3445";

// 탄생 스마트팜 UI와 일치하는 컨트롤러 ID
// 토픽 패턴: tansaeng/<CONTROLLER_ID>/<device>/cmd|state
const char* CONTROLLER_ID = "ctlr-heat-001";

// 핀 설정
const int PIN_PUMP    = 18;   // 순환펌프 릴레이
const int PIN_HEATER  = 19;   // 전기온열기 릴레이
const int PIN_FAN     = 23;   // 열교환기 팬 릴레이
const int PIN_DHT     = 4;    // DHT22 DATA
const int PIN_DS18B20 = 5;    // DS18B20 DATA (2026-08-18: GPIO4 24V 직결 손상으로 GPIO15 → GPIO5 변경)

// 출력 논리 (LOW 트리거 릴레이 모듈이면 OUTPUT_ON_LEVEL = LOW 로 변경)
const bool OUTPUT_ON_LEVEL  = HIGH;
const bool OUTPUT_OFF_LEVEL = LOW;

// 센서 타입
#define DHTTYPE DHT22

// 주기 설정
const unsigned long SENSOR_MS    = 3000;   // 센서 읽기 주기
const unsigned long HEARTBEAT_MS = 60000;  // 하트비트 발행 주기
const unsigned long STATUS_MS    = 30000;  // 상태 재발행 주기

// ======================================================
// ★ AUTO 판단은 서버 데몬(smartfarm_auto_control_daemon.cjs, tansaeng/hp-control/*)이
//   전담한다. ESP32는 순수 명령 실행기(system ON/OFF + 개별 pump/heater/fan cmd 적용)로만
//   동작하며, 온보드 AUTO 로직은 두지 않는다.
//
//   [2026-08-18 수정 이유] 예전 버전은 ESP32 자체에 mode(AUTO/MANUAL) 상태를 두고,
//   재부팅 시 자기 자신이 과거에 발행한 retain 값(tansaeng/ctlr-heat-001/mode/state)을
//   구독해 복원했다. 그런데 개발 초기에 한 번 "AUTO"가 발행된 retain 값이 브로커에 계속
//   남아있어서, 재부팅 때마다 온보드 AUTO가 되살아나 서버가 보내는 개별 pump/heater/fan
//   명령을 전부 무시하고 하드코딩된 기준값(공기 18/20도, 물 22/25도)으로 펌프·히터·팬을
//   하나로 묶어 작동시키는 사고가 실제로 발생 중이었다(라이브로 확인됨). mode 개념 자체를
//   없애 이 사고 유형을 구조적으로 차단한다.
//
//   히터 OFF 후 펌프/팬을 잠시 더 돌리던 후순환(POSTRUN) 안전장치는
//   daemons/smartfarm_auto_control_daemon.cjs의 runHpAutoControl()로 이전했다.
// ======================================================

// MQTT 토픽 (탄생 UI와 동일한 tansaeng/ prefix)
String topicSystemCmd   = "tansaeng/" + String(CONTROLLER_ID) + "/system/cmd";   // UI 전원 스위치
String topicSystemState = "tansaeng/" + String(CONTROLLER_ID) + "/system/state"; // 전원 상태 공유
String topicModeCmd     = "tansaeng/" + String(CONTROLLER_ID) + "/mode/cmd";     // UI 호환용 — 항상 MANUAL로만 응답
String topicModeState   = "tansaeng/" + String(CONTROLLER_ID) + "/mode/state";
String topicPumpCmd     = "tansaeng/" + String(CONTROLLER_ID) + "/pump/cmd";
String topicHeaterCmd   = "tansaeng/" + String(CONTROLLER_ID) + "/heater/cmd";
String topicFanCmd      = "tansaeng/" + String(CONTROLLER_ID) + "/fan/cmd";

String topicPumpState   = "tansaeng/" + String(CONTROLLER_ID) + "/pump/state";
String topicHeaterState = "tansaeng/" + String(CONTROLLER_ID) + "/heater/state";
String topicFanState    = "tansaeng/" + String(CONTROLLER_ID) + "/fan/state";

String topicAirTemp     = "tansaeng/" + String(CONTROLLER_ID) + "/air/temperature";
String topicAirHum      = "tansaeng/" + String(CONTROLLER_ID) + "/air/humidity";
String topicWaterTemp   = "tansaeng/" + String(CONTROLLER_ID) + "/water/temperature";

String topicStatus      = "tansaeng/" + String(CONTROLLER_ID) + "/status";
String topicHeartbeat   = "tansaeng/" + String(CONTROLLER_ID) + "/heartbeat";

// 객체
WiFiClientSecure espClient;
PubSubClient mqtt(espClient);
DHT dht(PIN_DHT, DHTTYPE);
OneWire oneWire(PIN_DS18B20);
DallasTemperature ds18b20(&oneWire);

// 상태
bool systemOn  = false;   // 재부팅 후 자동 가동 방지: UI에서 명시적으로 ON 해야 동작

bool pumpOn   = false;
bool heaterOn = false;
bool fanOn    = false;

// 마지막으로 수신한 개별 명령 — system이 다시 ON될 때 재적용용
bool pumpCmd   = false;
bool heaterCmd = false;
bool fanCmd    = false;

float lastAirTemp   = NAN;
float lastAirHum    = NAN;
float lastWaterTemp = NAN;

unsigned long lastSensorTime    = 0;
unsigned long lastHeartbeatTime = 0;
unsigned long lastStatusTime    = 0;

// ===================== 출력 제어 =====================
void applyOutput(int pin, bool on) {
  digitalWrite(pin, on ? OUTPUT_ON_LEVEL : OUTPUT_OFF_LEVEL);
}

void setPump(bool on) {
  if (pumpOn == on) return;
  pumpOn = on;
  applyOutput(PIN_PUMP, on);
  mqtt.publish(topicPumpState.c_str(), on ? "ON" : "OFF", true);
  Serial.printf("[PUMP] %s\n", on ? "ON" : "OFF");
}

void setHeater(bool on) {
  if (heaterOn == on) return;
  heaterOn = on;
  applyOutput(PIN_HEATER, on);
  mqtt.publish(topicHeaterState.c_str(), on ? "ON" : "OFF", true);
  Serial.printf("[HEATER] %s\n", on ? "ON" : "OFF");
}

void setFan(bool on) {
  if (fanOn == on) return;
  fanOn = on;
  applyOutput(PIN_FAN, on);
  mqtt.publish(topicFanState.c_str(), on ? "ON" : "OFF", true);
  Serial.printf("[FAN] %s\n", on ? "ON" : "OFF");
}

void allOff() {
  setHeater(false);
  setPump(false);
  setFan(false);
}

// ===================== 상태 발행 =====================
void publishSensor() {
  char buf[16];

  if (!isnan(lastAirTemp)) {
    snprintf(buf, sizeof(buf), "%.2f", lastAirTemp);
    mqtt.publish(topicAirTemp.c_str(), buf, false);
  }
  if (!isnan(lastAirHum)) {
    snprintf(buf, sizeof(buf), "%.2f", lastAirHum);
    mqtt.publish(topicAirHum.c_str(), buf, false);
  }
  if (!isnan(lastWaterTemp)) {
    snprintf(buf, sizeof(buf), "%.2f", lastWaterTemp);
    mqtt.publish(topicWaterTemp.c_str(), buf, false);
  }
}

// 모든 공유 상태를 retain 으로 발행 (다른 브라우저/기기가 접속 시 즉시 동기화)
void publishStates() {
  mqtt.publish(topicSystemState.c_str(), systemOn ? "ON" : "OFF", true);
  mqtt.publish(topicModeState.c_str(),   "MANUAL", true);  // 항상 MANUAL 고정 (서버가 AUTO 판단 전담)
  mqtt.publish(topicPumpState.c_str(),   pumpOn   ? "ON" : "OFF", true);
  mqtt.publish(topicHeaterState.c_str(), heaterOn ? "ON" : "OFF", true);
  mqtt.publish(topicFanState.c_str(),    fanOn    ? "ON" : "OFF", true);
}

void publishHeartbeat() {
  char payload[320];
  snprintf(payload, sizeof(payload),
    "{\"system\":\"%s\","
    "\"mode\":\"MANUAL\","
    "\"pump\":\"%s\","
    "\"heater\":\"%s\","
    "\"fan\":\"%s\","
    "\"air_temp\":%.2f,"
    "\"air_hum\":%.2f,"
    "\"water_temp\":%.2f,"
    "\"uptime\":%lu}",
    systemOn ? "ON" : "OFF",
    pumpOn   ? "ON" : "OFF",
    heaterOn ? "ON" : "OFF",
    fanOn    ? "ON" : "OFF",
    isnan(lastAirTemp)   ? -999.0 : lastAirTemp,
    isnan(lastAirHum)    ? -999.0 : lastAirHum,
    isnan(lastWaterTemp) ? -999.0 : lastWaterTemp,
    millis() / 1000UL
  );

  mqtt.publish(topicHeartbeat.c_str(), payload, false);
  mqtt.publish(topicStatus.c_str(), "online", false);
  Serial.println("[HEARTBEAT] published");
}

// ===================== MQTT 콜백 =====================
void mqttCallback(char* topic, byte* payload, unsigned int len) {
  String t(topic);
  String msg;
  for (unsigned int i = 0; i < len; i++) msg += (char)payload[i];
  msg.trim();
  msg.toUpperCase();

  Serial.printf("[MQTT IN] %s => %s\n", t.c_str(), msg.c_str());

  // 재부팅 후 마지막 시스템 전원 상태 복원 (retain된 state 토픽에서)
  if (t == topicSystemState) {
    systemOn = (msg == "ON");
    Serial.printf("[RESTORE] 시스템전원 복원: %s\n", msg.c_str());
    return;
  }

  // 시스템 전원 스위치 (모든 브라우저와 공유)
  if (t == topicSystemCmd) {
    if (msg == "ON") {
      systemOn = true;
      mqtt.publish(topicSystemState.c_str(), "ON", true);
      Serial.println("[SYSTEM] ON");
      // 마지막으로 받은 개별 명령 재적용
      setPump(pumpCmd);
      setHeater(heaterCmd);
      setFan(fanCmd);
    } else if (msg == "OFF") {
      systemOn = false;
      mqtt.publish(topicSystemState.c_str(), "OFF", true);
      Serial.println("[SYSTEM] OFF -> all OFF");
      allOff();
    }
    return;
  }

  // ★ mode/cmd — 어떤 값이 와도 온보드 AUTO로 전환하지 않고 항상 MANUAL로 재확인 발행만 함
  if (t == topicModeCmd) {
    mqtt.publish(topicModeState.c_str(), "MANUAL", true);
    Serial.printf("[MODE] '%s' 수신 -> MANUAL 고정 응답 (온보드 AUTO 비활성화됨)\n", msg.c_str());
    return;
  }

  // 펌프 명령
  if (t == topicPumpCmd) {
    pumpCmd = (msg == "ON");
    if (systemOn) setPump(pumpCmd);
    return;
  }

  // 히터 명령
  if (t == topicHeaterCmd) {
    heaterCmd = (msg == "ON");
    if (systemOn) setHeater(heaterCmd);
    return;
  }

  // 팬 명령
  if (t == topicFanCmd) {
    fanCmd = (msg == "ON");
    if (systemOn) setFan(fanCmd);
    return;
  }
}

// ===================== 네트워크 =====================
void connectWiFi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  Serial.print("[WiFi] connecting");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.printf("\n[WiFi] connected: %s\n", WiFi.localIP().toString().c_str());
}

void connectMQTT() {
  while (!mqtt.connected()) {
    Serial.print("[MQTT] connecting... ");

    String clientId = "ESP32-";
    clientId += CONTROLLER_ID;
    clientId += "-";
    clientId += String((uint32_t)ESP.getEfuseMac(), HEX);

    bool ok = mqtt.connect(clientId.c_str(), MQTT_USER, MQTT_PASS,
                           topicStatus.c_str(), 0, false, "offline");

    if (ok) {
      Serial.println("connected");
      mqtt.publish(topicStatus.c_str(), "online", false);

      // 재부팅 후 마지막 시스템 전원 상태 복원용 구독
      mqtt.subscribe(topicSystemState.c_str(), 1);

      // 명령 토픽 구독
      mqtt.subscribe(topicSystemCmd.c_str(), 1);
      mqtt.subscribe(topicModeCmd.c_str(),   1);
      mqtt.subscribe(topicPumpCmd.c_str(),   1);
      mqtt.subscribe(topicHeaterCmd.c_str(), 1);
      mqtt.subscribe(topicFanCmd.c_str(),    1);

      // retain 메시지 수신 대기 (200ms)
      unsigned long waitStart = millis();
      while (millis() - waitStart < 200) {
        mqtt.loop();
        delay(10);
      }

      // 예전에 잘못 남아있을 수 있는 mode retain 값을 즉시 MANUAL로 덮어써 정정
      mqtt.publish(topicModeState.c_str(), "MANUAL", true);

      // 현재 상태 전체 발행 (UI가 접속하면 즉시 동기화)
      publishStates();
      publishHeartbeat();
    } else {
      Serial.printf("failed rc=%d, retry 3s\n", mqtt.state());
      delay(3000);
    }
  }
}

// ===================== setup =====================
void setup() {
  Serial.begin(115200);
  delay(300);

  pinMode(PIN_PUMP,   OUTPUT);
  pinMode(PIN_HEATER, OUTPUT);
  pinMode(PIN_FAN,    OUTPUT);

  applyOutput(PIN_PUMP,   false);
  applyOutput(PIN_HEATER, false);
  applyOutput(PIN_FAN,    false);

  dht.begin();
  ds18b20.begin();

  connectWiFi();

  espClient.setInsecure();
  mqtt.setServer(MQTT_BROKER, MQTT_PORT);
  mqtt.setCallback(mqttCallback);
  mqtt.setBufferSize(512);

  connectMQTT();
}

// ===================== loop =====================
void loop() {
  if (WiFi.status() != WL_CONNECTED) connectWiFi();
  if (!mqtt.connected()) connectMQTT();
  mqtt.loop();

  unsigned long now = millis();

  // 센서 읽기
  if (now - lastSensorTime >= SENSOR_MS) {
    lastSensorTime = now;

    // DHT22 공기온습도
    float h = dht.readHumidity();
    float t = dht.readTemperature();
    if (!isnan(t) && !isnan(h)) {
      lastAirTemp = t;
      lastAirHum  = h;
      Serial.printf("[DHT22] Air Temp=%.1fC  Hum=%.1f%%\n", t, h);
    } else {
      Serial.println("[DHT22] read failed");
    }

    // DS18B20 물온도
    ds18b20.requestTemperatures();
    float wt = ds18b20.getTempCByIndex(0);
    if (wt != DEVICE_DISCONNECTED_C && wt > -100.0 && wt < 150.0) {
      lastWaterTemp = wt;
      Serial.printf("[DS18B20] Water Temp=%.1fC\n", wt);
    } else {
      Serial.println("[DS18B20] read failed");
      lastWaterTemp = NAN;
    }

    publishSensor();
  }

  // 상태 재발행
  if (now - lastStatusTime >= STATUS_MS) {
    lastStatusTime = now;
    publishStates();
  }

  // heartbeat
  if (now - lastHeartbeatTime >= HEARTBEAT_MS) {
    lastHeartbeatTime = now;
    publishHeartbeat();
  }
}
