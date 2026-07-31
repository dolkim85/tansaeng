#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>

const char* WIFI_SSID     = "KT_GiGA_4619";
const char* WIFI_PASSWORD = "add68bb834";

const char* MQTT_BROKER   = "22ada06fd6cf4059bd700ddbf6004d68.s1.eu.hivemq.cloud";
const int   MQTT_PORT     = 8883;
const char* MQTT_USERNAME = "esp32-client-01";
const char* MQTT_PASSWORD = "Qjawns3445";

const char* CONTROLLER_ID     = "ctlr-0004";
const char* VALVE1_CHANNEL_ID = "valve1";   // 메인밸브(구역A)
const char* VALVE2_CHANNEL_ID = "valve2";   // 포깅밸브
const char* VALVE3_CHANNEL_ID = "valve3";   // ★ 바이패스밸브(구역A 백업)
const char* FLOW1_CHANNEL_ID  = "flow1";    // ★ 유량계(메인밸브 라인, YF-B10-S)

const int VALVE1_PIN = 18;
const int VALVE2_PIN = 19;
const int VALVE3_PIN = 21;   // ★ 바이패스밸브 핀
const int FLOW1_PIN  = 4;    // ★ 유량계 신호핀 (내부 풀업 사용, INPUT_PULLUP)

// 밸브 안전 타임아웃: 열린 후 이 시간 동안 새 명령 없으면 자동 닫힘 (침수 방지)
// 분무 시간(보통 10~30초)보다 충분히 길게 (기본 90초)
const unsigned long VALVE_SAFETY_TIMEOUT_MS = 90000;  // 90초
const unsigned long FLOW_PUBLISH_INTERVAL_MS = 1000;  // ★ 유량 계산/발행 주기 1초
const float FLOW_PULSES_PER_LITER = 595.0;            // ★ 데이터시트: 1L ≈ 595 pulse

String topicValve1Cmd   = "tansaeng/" + String(CONTROLLER_ID) + "/" + VALVE1_CHANNEL_ID + "/cmd";
String topicValve1State = "tansaeng/" + String(CONTROLLER_ID) + "/" + VALVE1_CHANNEL_ID + "/state";
String topicValve2Cmd   = "tansaeng/" + String(CONTROLLER_ID) + "/" + VALVE2_CHANNEL_ID + "/cmd";
String topicValve2State = "tansaeng/" + String(CONTROLLER_ID) + "/" + VALVE2_CHANNEL_ID + "/state";
String topicValve3Cmd   = "tansaeng/" + String(CONTROLLER_ID) + "/" + VALVE3_CHANNEL_ID + "/cmd";    // ★
String topicValve3State = "tansaeng/" + String(CONTROLLER_ID) + "/" + VALVE3_CHANNEL_ID + "/state";  // ★
String topicFlow1Rate   = "tansaeng/" + String(CONTROLLER_ID) + "/" + FLOW1_CHANNEL_ID + "/rate";    // ★ L/min
String topicFlow1Total  = "tansaeng/" + String(CONTROLLER_ID) + "/" + FLOW1_CHANNEL_ID + "/total";   // ★ 누적 L
String topicFlow1Pulses   = "tansaeng/" + String(CONTROLLER_ID) + "/" + FLOW1_CHANNEL_ID + "/pulses";   // ★ 진단용 원시 펄스카운트(비-retain)
String topicFlow1PinLevel = "tansaeng/" + String(CONTROLLER_ID) + "/" + FLOW1_CHANNEL_ID + "/pinLevel"; // ★ 진단용 GPIO4 순간 레벨(비-retain) — 배선 단선/단락 구분용
String topicStatus      = "tansaeng/" + String(CONTROLLER_ID) + "/status";
String topicReset       = "tansaeng/" + String(CONTROLLER_ID) + "/restart";

WiFiClientSecure wifiClient;
PubSubClient mqttClient(wifiClient);

bool valve1On = false;
bool valve2On = false;
bool valve3On = false;                // ★
unsigned long valve1OpenTime = 0;     // valve1 열린 시각 (안전 타임아웃용)
unsigned long valve2OpenTime = 0;     // valve2 열린 시각
unsigned long valve3OpenTime = 0;     // ★ valve3 열린 시각
unsigned long lastStatusTime = 0;

volatile unsigned long flow1PulseCount = 0;   // ★ ISR에서 증가
unsigned long flow1LastPublishTime = 0;       // ★
double flow1TotalLiters = 0.0;                // ★ 누적 유량(리터, 재부팅 시 초기화됨)

void connectWiFi();
void connectMQTT();
void mqttCallback(char* topic, byte* payload, unsigned int length);
void publishValve1State();
void publishValve2State();
void publishValve3State();            // ★
void setValve1(bool on);
void setValve2(bool on);
void setValve3(bool on);              // ★
void checkValveSafety();
void IRAM_ATTR flow1ISR();            // ★
void updateFlow1(unsigned long now);  // ★

void setup() {
  Serial.begin(115200);
  delay(2000);

  pinMode(VALVE1_PIN, OUTPUT);
  digitalWrite(VALVE1_PIN, LOW);
  pinMode(VALVE2_PIN, OUTPUT);
  digitalWrite(VALVE2_PIN, LOW);
  pinMode(VALVE3_PIN, OUTPUT);        // ★
  digitalWrite(VALVE3_PIN, LOW);      // ★

  pinMode(FLOW1_PIN, INPUT_PULLUP);                                    // ★
  attachInterrupt(digitalPinToInterrupt(FLOW1_PIN), flow1ISR, RISING); // ★

  connectWiFi();

  wifiClient.setInsecure();
  mqttClient.setServer(MQTT_BROKER, MQTT_PORT);
  mqttClient.setCallback(mqttCallback);
  mqttClient.setKeepAlive(60);
  mqttClient.setSocketTimeout(30);

  connectMQTT();

  flow1LastPublishTime = millis();  // ★
}

void loop() {
  // WiFi 끊김 감지 후 재연결
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[WiFi] disconnected - reconnecting");
    mqttClient.disconnect();
    connectWiFi();
  }

  if (!mqttClient.connected()) {
    connectMQTT();
  }
  mqttClient.loop();

  // 밸브 안전 타임아웃 체크 (연결 끊겨도 매 loop마다 동작)
  checkValveSafety();

  unsigned long now = millis();

  updateFlow1(now);   // ★ 유량 계산/발행

  if (now - lastStatusTime > 60000) {
    lastStatusTime = now;
    mqttClient.publish(topicStatus.c_str(), "online", true);
    Serial.println("[STATUS] heartbeat: online");
  }
}

// ★ 유량계 인터럽트 서비스 루틴 - 최소 작업만 수행
void IRAM_ATTR flow1ISR() {
  flow1PulseCount++;
}

// ★ 1초마다 펄스카운트를 읽어 유량(L/min)/누적유량(L) 계산 후 발행
void updateFlow1(unsigned long now) {
  if (now - flow1LastPublishTime < FLOW_PUBLISH_INTERVAL_MS) return;

  noInterrupts();
  unsigned long pulses = flow1PulseCount;
  flow1PulseCount = 0;
  interrupts();

  float elapsedSec = (now - flow1LastPublishTime) / 1000.0;
  flow1LastPublishTime = now;

  // K Factor: Frequency(Hz) = 10*Q - 5  ->  Q(L/min) = (Frequency + 5) / 10
  float frequency = pulses / elapsedSec;
  float flowRateLpm = (pulses == 0) ? 0.0 : (frequency + 5.0) / 10.0;
  if (flowRateLpm < 0) flowRateLpm = 0;

  flow1TotalLiters += pulses / FLOW_PULSES_PER_LITER;

  char rateBuf[16];
  char totalBuf[16];
  dtostrf(flowRateLpm, 0, 2, rateBuf);
  dtostrf(flow1TotalLiters, 0, 3, totalBuf);

  mqttClient.publish(topicFlow1Rate.c_str(), rateBuf, true);
  mqttClient.publish(topicFlow1Total.c_str(), totalBuf, true);

  // ★ 진단용: ISR이 실제로 펄스를 받고 있는지 + GPIO4 순간 레벨(배선 단선 vs 단락 구분용) 원격 확인 (retain 안 함)
  char pulsesBuf[12];
  itoa((int)pulses, pulsesBuf, 10);
  mqttClient.publish(topicFlow1Pulses.c_str(), pulsesBuf, false);
  // 단선/미연결(내부 풀업만 작동) → 항상 HIGH / 신호선 단락·GND 쇼트 → 항상 LOW / 정상 신호 있음 → HIGH·LOW 번갈아 관측됨
  bool pinLevel = digitalRead(FLOW1_PIN);
  mqttClient.publish(topicFlow1PinLevel.c_str(), pinLevel ? "HIGH" : "LOW", false);

  Serial.print("[FLOW1] pulses=");
  Serial.print(pulses);
  Serial.print(" pin=");
  Serial.print(pinLevel ? "HIGH" : "LOW");
  Serial.print(" rate=");
  Serial.print(rateBuf);
  Serial.print(" L/min, total=");
  Serial.print(totalBuf);
  Serial.println(" L");
}

// ===== 밸브 제어 (안전 타임아웃 타이머 포함) =====
void setValve1(bool on) {
  valve1On = on;
  digitalWrite(VALVE1_PIN, on ? HIGH : LOW);
  if (on) valve1OpenTime = millis();   // 열릴 때 타이머 시작
  Serial.print("[VALVE1] ");
  Serial.println(on ? "OPEN" : "CLOSE");
  publishValve1State();
}

void setValve2(bool on) {
  valve2On = on;
  digitalWrite(VALVE2_PIN, on ? HIGH : LOW);
  if (on) valve2OpenTime = millis();
  Serial.print("[VALVE2] ");
  Serial.println(on ? "OPEN" : "CLOSE");
  publishValve2State();
}

// ★ 바이패스밸브 제어
void setValve3(bool on) {
  valve3On = on;
  digitalWrite(VALVE3_PIN, on ? HIGH : LOW);
  if (on) valve3OpenTime = millis();
  Serial.print("[VALVE3] ");
  Serial.println(on ? "OPEN" : "CLOSE");
  publishValve3State();
}

// ===== 안전 타임아웃: 너무 오래 열려있으면 강제 닫기 =====
void checkValveSafety() {
  unsigned long now = millis();

  if (valve1On && (now - valve1OpenTime > VALVE_SAFETY_TIMEOUT_MS)) {
    Serial.println("[SAFETY] valve1 타임아웃 - 강제 CLOSE");
    setValve1(false);
  }
  if (valve2On && (now - valve2OpenTime > VALVE_SAFETY_TIMEOUT_MS)) {
    Serial.println("[SAFETY] valve2 타임아웃 - 강제 CLOSE");
    setValve2(false);
  }
  if (valve3On && (now - valve3OpenTime > VALVE_SAFETY_TIMEOUT_MS)) {   // ★
    Serial.println("[SAFETY] valve3 타임아웃 - 강제 CLOSE");
    setValve3(false);
  }
}

void connectWiFi() {
  Serial.println("[WiFi] connecting...");
  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  int retry = 0;
  while (WiFi.status() != WL_CONNECTED && retry < 40) {
    delay(500);
    Serial.print(".");
    retry++;
    checkValveSafety();   // 재연결 중에도 안전 타임아웃 동작
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("");
    Serial.println("[WiFi] connected!");
    Serial.print("[WiFi] IP: ");
    Serial.println(WiFi.localIP());
    Serial.print("[WiFi] RSSI: ");
    Serial.println(WiFi.RSSI());
  } else {
    Serial.println("[WiFi] failed - restarting ESP32");
    digitalWrite(VALVE1_PIN, LOW);   // 재시작 전 밸브 닫기
    digitalWrite(VALVE2_PIN, LOW);
    digitalWrite(VALVE3_PIN, LOW);   // ★
    delay(3000);
    ESP.restart();
  }
}

void connectMQTT() {
  int attempts = 0;
  while (!mqttClient.connected()) {
    Serial.print("[MQTT] connecting... ");

    String clientId = "ESP32-VALVE-";
    clientId += String((uint32_t)ESP.getEfuseMac(), HEX);

    if (mqttClient.connect(
          clientId.c_str(),
          MQTT_USERNAME, MQTT_PASSWORD,
          topicStatus.c_str(),
          0,
          true,
          "offline"
        )) {
      Serial.println("connected!");

      mqttClient.publish(topicStatus.c_str(), "online", true);
      Serial.println("[STATUS] online");

      mqttClient.subscribe(topicValve1Cmd.c_str());
      Serial.print("[MQTT] subscribed: ");
      Serial.println(topicValve1Cmd);

      mqttClient.subscribe(topicValve2Cmd.c_str());
      Serial.print("[MQTT] subscribed: ");
      Serial.println(topicValve2Cmd);

      mqttClient.subscribe(topicValve3Cmd.c_str());   // ★
      Serial.print("[MQTT] subscribed: ");
      Serial.println(topicValve3Cmd);

      mqttClient.subscribe(topicReset.c_str());
      Serial.print("[MQTT] subscribed: ");
      Serial.println(topicReset);

      publishValve1State();
      publishValve2State();
      publishValve3State();   // ★

      lastStatusTime = millis();
      attempts = 0;

    } else {
      Serial.print("failed, rc=");
      Serial.print(mqttClient.state());
      attempts++;

      checkValveSafety();   // MQTT 재연결 중에도 안전 타임아웃 동작

      if (WiFi.status() != WL_CONNECTED) {
        Serial.println(" - WiFi lost, reconnecting");
        connectWiFi();
      } else {
        Serial.println(" - retry in 5s");
        delay(5000);
      }

      if (attempts >= 10) {
        Serial.println("[MQTT] 10 failures - restarting ESP32");
        digitalWrite(VALVE1_PIN, LOW);   // 재시작 전 밸브 닫기
        digitalWrite(VALVE2_PIN, LOW);
        digitalWrite(VALVE3_PIN, LOW);   // ★
        delay(3000);
        ESP.restart();
      }
    }
  }
}

void mqttCallback(char* topic, byte* payload, unsigned int length) {
  String msg = "";
  for (unsigned int i = 0; i < length; i++) {
    msg += (char)payload[i];
  }
  msg.trim();

  String t = String(topic);
  Serial.print("[MQTT] topic: ");
  Serial.println(t);
  Serial.print("[MQTT] msg: ");
  Serial.println(msg);

  if (t == topicValve1Cmd) {
    if (msg.equalsIgnoreCase("ON") || msg.equalsIgnoreCase("OPEN")) {
      setValve1(true);
    } else if (msg.equalsIgnoreCase("OFF") || msg.equalsIgnoreCase("CLOSE")) {
      setValve1(false);
    }

  } else if (t == topicValve2Cmd) {
    if (msg.equalsIgnoreCase("ON") || msg.equalsIgnoreCase("OPEN")) {
      setValve2(true);
    } else if (msg.equalsIgnoreCase("OFF") || msg.equalsIgnoreCase("CLOSE")) {
      setValve2(false);
    }

  } else if (t == topicValve3Cmd) {   // ★ 바이패스밸브
    if (msg.equalsIgnoreCase("ON") || msg.equalsIgnoreCase("OPEN")) {
      setValve3(true);
    } else if (msg.equalsIgnoreCase("OFF") || msg.equalsIgnoreCase("CLOSE")) {
      setValve3(false);
    }

  } else if (t == topicReset) {
    if (msg.equalsIgnoreCase("restart")) {
      Serial.println("[SYSTEM] restart command received");
      digitalWrite(VALVE1_PIN, LOW);   // 재시작 전 밸브 닫기
      digitalWrite(VALVE2_PIN, LOW);
      digitalWrite(VALVE3_PIN, LOW);   // ★
      mqttClient.publish(topicStatus.c_str(), "offline", true);
      delay(500);
      ESP.restart();
    }
  }
}

void publishValve1State() {
  String state = valve1On ? "OPEN" : "CLOSE";
  mqttClient.publish(topicValve1State.c_str(), state.c_str(), true);
  Serial.print("[VALVE1] state: ");
  Serial.println(state);
}

void publishValve2State() {
  String state = valve2On ? "OPEN" : "CLOSE";
  mqttClient.publish(topicValve2State.c_str(), state.c_str(), true);
  Serial.print("[VALVE2] state: ");
  Serial.println(state);
}

// ★ 바이패스밸브 상태 발행
void publishValve3State() {
  String state = valve3On ? "OPEN" : "CLOSE";
  mqttClient.publish(topicValve3State.c_str(), state.c_str(), true);
  Serial.print("[VALVE3] state: ");
  Serial.println(state);
}
