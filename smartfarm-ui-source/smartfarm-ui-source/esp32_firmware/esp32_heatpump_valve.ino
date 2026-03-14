#include <WiFi.h>
#include <PubSubClient.h>
#include <DHT.h>
#include <OneWire.h>
#include <DallasTemperature.h>

// ===================== 사용자 설정 =====================
const char* WIFI_SSID     = "U+Net5FA0";
const char* WIFI_PASSWORD = "82C@6HA4D3";

const char* MQTT_BROKER   = "192.168.0.10";
const int   MQTT_PORT     = 1883;
const char* MQTT_USER     = "";
const char* MQTT_PASS     = "";

// 탄생 스마트팜 UI와 일치하는 컨트롤러 ID
// 토픽 패턴: tansaeng/<CONTROLLER_ID>/<device>/cmd|state
const char* CONTROLLER_ID = "ctlr-heat-001";

// 핀 설정
const int PIN_PUMP    = 18;   // 순환펌프 릴레이
const int PIN_HEATER  = 19;   // 전기온열기 릴레이
const int PIN_FAN     = 23;   // 열교환기 팬 릴레이
const int PIN_DHT     = 4;    // DHT22 DATA
const int PIN_DS18B20 = 15;   // DS18B20 DATA

// 출력 논리 (LOW 트리거 릴레이 모듈이면 OUTPUT_ON_LEVEL = LOW 로 변경)
const bool OUTPUT_ON_LEVEL  = HIGH;
const bool OUTPUT_OFF_LEVEL = LOW;

// 센서 타입
#define DHTTYPE DHT22

// AUTO 제어 기준값
const float AIR_TEMP_ON_C    = 18.0;  // 공기온도 이하 → 난방 시작
const float AIR_TEMP_OFF_C   = 20.0;  // 공기온도 이상 → 난방 정지

const float WATER_TEMP_ON_C  = 22.0;  // 물온도 이하 → 난방 시작
const float WATER_TEMP_OFF_C = 25.0;  // 물온도 이상 → 난방 정지

// 후순환 시간 (히터 OFF 후 펌프·팬 계속 가동)
const unsigned long POSTRUN_MS = 60000;   // 60초

// 주기 설정
const unsigned long SENSOR_MS    = 3000;   // 센서 읽기 주기
const unsigned long HEARTBEAT_MS = 60000;  // 하트비트 발행 주기
const unsigned long STATUS_MS    = 30000;  // 상태 재발행 주기

// ======================================================

// MQTT 토픽 (탄생 UI와 동일한 tansaeng/ prefix)
String topicModeCmd     = "tansaeng/" + String(CONTROLLER_ID) + "/mode/cmd";
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
WiFiClient espClient;
PubSubClient mqtt(espClient);
DHT dht(PIN_DHT, DHTTYPE);
OneWire oneWire(PIN_DS18B20);
DallasTemperature ds18b20(&oneWire);

// 상태
enum Mode { AUTO_MODE, MANUAL_MODE };
Mode mode = AUTO_MODE;

bool pumpOn   = false;
bool heaterOn = false;
bool fanOn    = false;

bool manualPump   = false;
bool manualHeater = false;
bool manualFan    = false;

bool heatDemand    = false;
bool postRunActive = false;
unsigned long postRunStart = 0;

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

void publishStates() {
  mqtt.publish(topicPumpState.c_str(),   pumpOn   ? "ON" : "OFF", true);
  mqtt.publish(topicHeaterState.c_str(), heaterOn ? "ON" : "OFF", true);
  mqtt.publish(topicFanState.c_str(),    fanOn    ? "ON" : "OFF", true);
}

void publishHeartbeat() {
  char payload[320];
  snprintf(payload, sizeof(payload),
    "{\"mode\":\"%s\","
    "\"pump\":\"%s\","
    "\"heater\":\"%s\","
    "\"fan\":\"%s\","
    "\"air_temp\":%.2f,"
    "\"air_hum\":%.2f,"
    "\"water_temp\":%.2f,"
    "\"uptime\":%lu}",
    mode == AUTO_MODE ? "AUTO" : "MANUAL",
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

// ===================== AUTO 로직 =====================
void handleAutoControl() {
  bool airValid   = !isnan(lastAirTemp);
  bool waterValid = !isnan(lastWaterTemp);

  // 센서 둘 다 실패면 보수적으로 전부 OFF
  if (!airValid && !waterValid) {
    setHeater(false);
    setPump(false);
    setFan(false);
    heatDemand    = false;
    postRunActive = false;
    Serial.println("[AUTO] sensor fail -> all OFF");
    return;
  }

  bool needHeatOn  = false;
  bool needHeatOff = false;

  // ON 조건: 공기온도 또는 물온도 중 하나라도 기준 이하
  if (airValid   && lastAirTemp   <= AIR_TEMP_ON_C)   needHeatOn = true;
  if (waterValid && lastWaterTemp <= WATER_TEMP_ON_C)  needHeatOn = true;

  // OFF 조건: 유효한 센서가 모두 OFF 기준 이상
  bool airRecovered   = (!airValid)   || (lastAirTemp   >= AIR_TEMP_OFF_C);
  bool waterRecovered = (!waterValid) || (lastWaterTemp >= WATER_TEMP_OFF_C);
  needHeatOff = airRecovered && waterRecovered;

  if (!heatDemand && needHeatOn) {
    heatDemand    = true;
    postRunActive = false;
    Serial.println("[AUTO] heat demand ON");
  }

  if (heatDemand && needHeatOff) {
    heatDemand    = false;
    postRunActive = true;
    postRunStart  = millis();
    Serial.println("[AUTO] heat demand OFF -> postrun start");
  }

  if (heatDemand) {
    setHeater(true);
    setPump(true);
    setFan(true);
  } else {
    setHeater(false);

    if (postRunActive) {
      if (millis() - postRunStart < POSTRUN_MS) {
        setPump(true);
        setFan(true);
      } else {
        postRunActive = false;
        setPump(false);
        setFan(false);
        Serial.println("[AUTO] postrun complete -> all OFF");
      }
    } else {
      setPump(false);
      setFan(false);
    }
  }
}

// ===================== MQTT 콜백 =====================
void mqttCallback(char* topic, byte* payload, unsigned int len) {
  String t(topic);
  String msg;
  for (unsigned int i = 0; i < len; i++) msg += (char)payload[i];
  msg.trim();
  msg.toUpperCase();

  Serial.printf("[MQTT IN] %s => %s\n", t.c_str(), msg.c_str());

  // 모드 전환
  if (t == topicModeCmd) {
    if (msg == "AUTO") {
      mode = AUTO_MODE;
      Serial.println("[MODE] AUTO");
      handleAutoControl();
    } else if (msg == "MANUAL") {
      mode = MANUAL_MODE;
      Serial.println("[MODE] MANUAL");
      setPump(manualPump);
      setHeater(manualHeater);
      setFan(manualFan);
    }
    return;
  }

  // 펌프 명령
  if (t == topicPumpCmd) {
    if (msg == "ON") {
      manualPump = true;
      if (mode == MANUAL_MODE) setPump(true);
    } else if (msg == "OFF") {
      manualPump = false;
      if (mode == MANUAL_MODE) setPump(false);
    }
    return;
  }

  // 히터 명령
  if (t == topicHeaterCmd) {
    if (msg == "ON") {
      manualHeater = true;
      if (mode == MANUAL_MODE) setHeater(true);
    } else if (msg == "OFF") {
      manualHeater = false;
      if (mode == MANUAL_MODE) setHeater(false);
    }
    return;
  }

  // 팬 명령
  if (t == topicFanCmd) {
    if (msg == "ON") {
      manualFan = true;
      if (mode == MANUAL_MODE) setFan(true);
    } else if (msg == "OFF") {
      manualFan = false;
      if (mode == MANUAL_MODE) setFan(false);
    }
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

    bool ok;
    if (strlen(MQTT_USER) == 0) {
      ok = mqtt.connect(clientId.c_str(), topicStatus.c_str(), 0, false, "offline");
    } else {
      ok = mqtt.connect(clientId.c_str(), MQTT_USER, MQTT_PASS,
                        topicStatus.c_str(), 0, false, "offline");
    }

    if (ok) {
      Serial.println("connected");
      mqtt.publish(topicStatus.c_str(), "online", false);

      mqtt.subscribe(topicModeCmd.c_str(),   1);
      mqtt.subscribe(topicPumpCmd.c_str(),   1);
      mqtt.subscribe(topicHeaterCmd.c_str(), 1);
      mqtt.subscribe(topicFanCmd.c_str(),    1);

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
  mqtt.setServer(MQTT_BROKER, MQTT_PORT);
  mqtt.setCallback(mqttCallback);
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
    if (wt != DEVICE_DISCONNECTED_C) {
      lastWaterTemp = wt;
      Serial.printf("[DS18B20] Water Temp=%.1fC\n", wt);
    } else {
      Serial.println("[DS18B20] read failed");
      lastWaterTemp = NAN;
    }

    publishSensor();

    if (mode == AUTO_MODE) {
      handleAutoControl();
    }
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
