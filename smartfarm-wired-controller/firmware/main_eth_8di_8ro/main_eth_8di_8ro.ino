// ESP32-S3-ETH-8DI-8RO — 메인 노드 (RS485 Master)
// WiFi 대신 유선 이더넷으로 HiveMQ Cloud에 접속한다. 기존 ctlr-0004와 완전히 동일한
// MQTT 토픽을 사용하므로 서버/UI는 수정할 필요가 없다(docs/mqtt-topics.md).
//
// 빌드 전: config.example.h를 config.h로 복사해서 사용하세요.

#include "config.h"
#include "board_pins.h"
#include "ethernet_manager.h"
#include "mqtt_manager.h"
#include "rs485_master.h"
#include "controller.h"
#include "../../shared/protocol_version.h"
#include <ArduinoJson.h>  // v7.x — localReplay JSON 페이로드 파싱용

EthernetManager ethMgr;
MqttManager mqttMgr;
Rs485Master rs485;
Controller controller;

// ── MQTT 토픽 (기존과 완전 동일 — docs/mqtt-topics.md) ──────────────────────
String topicValve1Cmd, topicValve1State;
String topicValve2Cmd, topicValve2State;
String topicValve3Cmd, topicValve3State;
String topicFlow1Rate, topicFlow1Total, topicFlow1Pulses;
String topicStatus, topicRestart;
// 신규 진단 토픽
String topicNetworkEthernet, topicRs485Status, topicRs485LastSeen, topicFault, topicUptime;
// 신규 구역A 로컬재생 캐시(데몬 발행, 메인 노드 구독)
String topicValve1LocalReplay;

bool lastValve1Reported = false, lastValve2Reported = false, lastValve3Reported = false;

unsigned long lastStatusPublishMs = 0;
const unsigned long STATUS_PUBLISH_INTERVAL_MS = 30000;
unsigned long lastRs485StatusPublishMs = 0;
const unsigned long RS485_STATUS_PUBLISH_INTERVAL_MS = 5000;
bool lastRs485Online = false;

unsigned long lastFlowPublishMs = 0;
const unsigned long FLOW_PUBLISH_INTERVAL_MS_MAIN = 1000; // 기존과 동일한 1초 주기 유지
bool loggedFlowUnsupportedOnce = false;

void publishFlowSnapshot() {
  // mL -> L, mL/min -> L/min 변환만 하고 계산/누적은 절대 하지 않는다(단일 기준
  // 원장은 팔 노드). 기존 UI가 받던 필드/단위(L, L/min)를 그대로 유지한다.
  char buf[24];
  dtostrf(rs485.getFlowRateMlPerMin() / 1000.0, 0, 2, buf);
  mqttMgr.publish(topicFlow1Rate.c_str(), buf, true);

  // 누적량은 mL 단위 uint64라 L 변환 시 double로도 마지막 몇 자리가 근사값이 될 수
  // 있으나, 기존 프론트엔드가 소수점 3자리(mL 단위)까지만 쓰므로 실용상 문제없다.
  double totalLiters = (double)rs485.getFlowTotalMl() / 1000.0;
  dtostrf(totalLiters, 0, 3, buf);
  mqttMgr.publish(topicFlow1Total.c_str(), buf, true);

  // 원시 누적 펄스카운트(진단용, 비-retain) — 의미는 기존과 동일하게 유지(그대로 "펄스" 값)
  char pulsesBuf[16];
  snprintf(pulsesBuf, sizeof(pulsesBuf), "%lu", (unsigned long)rs485.getFlowRawPulses());
  mqttMgr.publish(topicFlow1Pulses.c_str(), pulsesBuf, false);
}

void buildTopics() {
  String prefix = "tansaeng/" + String(CONTROLLER_ID) + "/";
  topicValve1Cmd   = prefix + "valve1/cmd";
  topicValve1State = prefix + "valve1/state";
  topicValve2Cmd   = prefix + "valve2/cmd";
  topicValve2State = prefix + "valve2/state";
  topicValve3Cmd   = prefix + "valve3/cmd";
  topicValve3State = prefix + "valve3/state";
  topicFlow1Rate     = prefix + "flow1/rate";
  topicFlow1Total    = prefix + "flow1/total";
  topicFlow1Pulses   = prefix + "flow1/pulses";
  // flow1/pinLevel(DI4 순간레벨 진단용)은 유량계가 팔 노드로 이전되며 더 이상
  // 발행하지 않는다 — 그 자리를 대신할 진단 정보는 팔 노드 IR_FLOW_DIAG_FLAGS를
  // 반영하는 topicFault로 대체(docs/mqtt-topics.md 참고, 의도적 변경사항).
  topicStatus  = prefix + "status";
  topicRestart = prefix + "restart";

  topicNetworkEthernet = prefix + "network/ethernet";
  topicRs485Status     = prefix + "rs485/node1/status";
  topicRs485LastSeen   = prefix + "rs485/node1/lastSeen";
  topicFault           = prefix + "fault";
  topicUptime          = prefix + "system/uptime";

  topicValve1LocalReplay = prefix + "valve1/localReplay";
}

void mqttCallback(char* topic, byte* payload, unsigned int length) {
  String msg;
  for (unsigned int i = 0; i < length; i++) msg += (char)payload[i];
  msg.trim();
  String upperMsg = msg;
  upperMsg.toUpperCase();

  String t(topic);
  Serial.printf("[MQTT IN] %s => %s\n", t.c_str(), msg.c_str());

  if (t == topicValve1Cmd) {
    if (upperMsg == "ON" || upperMsg == "OPEN") controller.onServerValveCmd(COIL_CH1_MAIN_VALVE, true);
    else if (upperMsg == "OFF" || upperMsg == "CLOSE") controller.onServerValveCmd(COIL_CH1_MAIN_VALVE, false);

  } else if (t == topicValve2Cmd) {
    if (upperMsg == "ON" || upperMsg == "OPEN") controller.onServerValveCmd(COIL_CH2_FOGGING_VALVE, true);
    else if (upperMsg == "OFF" || upperMsg == "CLOSE") controller.onServerValveCmd(COIL_CH2_FOGGING_VALVE, false);

  } else if (t == topicValve3Cmd) {
    if (upperMsg == "ON" || upperMsg == "OPEN") controller.onServerValveCmd(COIL_CH3_BYPASS_VALVE, true);
    else if (upperMsg == "OFF" || upperMsg == "CLOSE") controller.onServerValveCmd(COIL_CH3_BYPASS_VALVE, false);

  } else if (t == topicRestart) {
    if (msg.equalsIgnoreCase("restart")) {
      Serial.println("[SYSTEM] restart 명령 수신");
      mqttMgr.publish(topicStatus.c_str(), "offline", true);
      delay(200); // 종료 메시지 전송을 위한 짧은 1회성 대기(루프를 막는 재시도 루프 아님)
      ESP.restart();
    }

  } else if (t == topicValve1LocalReplay) {
    JsonDocument doc;
    DeserializationError err = deserializeJson(doc, msg);
    if (err) {
      Serial.printf("[CTRL] localReplay JSON 파싱 실패: %s\n", err.c_str());
    } else {
      bool enabled = doc["enabled"] | false;
      uint16_t spray = doc["sprayDurationSeconds"] | 0;
      uint16_t stop = doc["stopDurationSeconds"] | 0;
      bool bypass = doc["bypassActive"] | false;
      controller.onLocalReplayUpdate(enabled, spray, stop, bypass);
    }
  } else {
    Serial.println("[MQTT] 알 수 없는 토픽 — 무시");
  }
}

void setup() {
  Serial.begin(115200);
  delay(300);
  Serial.println("=== ESP32-S3-ETH-8DI-8RO 메인 노드 시작 ===");
  Serial.printf("펌웨어 버전: %d.%d\n", FIRMWARE_VERSION_MAJOR, FIRMWARE_VERSION_MINOR);

  buildTopics();

  // [2026-08-23] 유량계는 팔 노드로 이전 — 메인 노드는 더 이상 자체 측정/저장하지
  // 않는다(NVS storage.begin()/flowMeter.begin() 호출 삭제됨). rs485.begin()이
  // 유량 데이터도 함께 폴링해온다.

  ethMgr.begin();

  rs485.begin(ARM_NODE_SLAVE_ADDRESS);
  controller.begin(&rs485);

  // clientId 접두사는 CONTROLLER_ID("ctlr-0004", 토픽/UI 호환용)와는 별개다.
  // MqttManager::begin()이 여기에 MAC 접미사를 붙여 장치별 고유 clientId를 만든다 —
  // 기존 WiFi ctlr-0004와 전환/롤백 테스트 중 동시에 켜져 있어도 clientId 충돌로
  // 브로커가 어느 한쪽을 끊어버리는 사고를 방지한다(2026-08-23 수정).
  mqttMgr.begin(MQTT_HOST, MQTT_PORT, MQTT_USERNAME, MQTT_PASSWORD, CONTROLLER_ID "-eth",
                topicStatus.c_str(), mqttCallback);

  Serial.println("=== 초기화 완료 — 연결 대기 중 ===");
}

void loop() {
  ethMgr.update();
  mqttMgr.update();
  rs485.update();

  bool mqttConnected = mqttMgr.isConnected();

  // 재연결 직후에만 구독을 (재)등록 — PubSubClient는 재연결마다 구독이 초기화되므로
  static bool wasConnectedForSub = false;
  if (mqttConnected && !wasConnectedForSub) {
    mqttMgr.subscribe(topicValve1Cmd.c_str());
    mqttMgr.subscribe(topicValve2Cmd.c_str());
    mqttMgr.subscribe(topicValve3Cmd.c_str());
    mqttMgr.subscribe(topicRestart.c_str());
    mqttMgr.subscribe(topicValve1LocalReplay.c_str());
    Serial.println("[MQTT] 토픽 구독 완료");
    // MQTT 복구 직후 현재 유량 스냅샷을 즉시 재발행 — 다음 정기 주기까지 기다리지 않음
    if (rs485.isFlowSupported()) publishFlowSnapshot();
  }
  wasConnectedForSub = mqttConnected;

  controller.update(mqttConnected);

  // ── 밸브 실측 상태 변화 시에만 발행(불필요한 트래픽 방지) ──
  bool v1 = rs485.getActualState(COIL_CH1_MAIN_VALVE);
  bool v2 = rs485.getActualState(COIL_CH2_FOGGING_VALVE);
  bool v3 = rs485.getActualState(COIL_CH3_BYPASS_VALVE);
  if (mqttConnected) {
    if (v1 != lastValve1Reported) { mqttMgr.publish(topicValve1State.c_str(), v1 ? "OPEN" : "CLOSE", true); lastValve1Reported = v1; }
    if (v2 != lastValve2Reported) { mqttMgr.publish(topicValve2State.c_str(), v2 ? "OPEN" : "CLOSE", true); lastValve2Reported = v2; }
    if (v3 != lastValve3Reported) { mqttMgr.publish(topicValve3State.c_str(), v3 ? "OPEN" : "CLOSE", true); lastValve3Reported = v3; }
  }

  unsigned long now = millis();

  // ── 유량계 — 팔 노드가 계산한 값을 RS485로 읽어와 그대로 중계 (1초 주기, 기존과 동일 간격) ──
  // 메인 노드는 재계산/재누적을 하지 않는다(단일 기준 원장 = 팔 노드). RS485가
  // 끊기면(rs485.isNodeOnline()==false) 새로 발행하지 않아 retain된 마지막 값이
  // 그대로 유지된다 — "값이 계속 증가하는 것처럼 보이는" 사고를 방지.
  if (mqttConnected && rs485.isNodeOnline() && now - lastFlowPublishMs >= FLOW_PUBLISH_INTERVAL_MS_MAIN) {
    if (rs485.isFlowSupported()) {
      lastFlowPublishMs = now;
      publishFlowSnapshot();

      // 유량 진단 플래그(누수 의심/무유량 등)를 fault 토픽으로 중계
      uint16_t diag = rs485.getFlowDiagFlags();
      if (diag != 0) {
        char faultMsg[96];
        snprintf(faultMsg, sizeof(faultMsg),
                 "{\"code\":\"flow_diag\",\"flags\":%u,\"unexpectedFlow\":%s,\"noFlowTimeout\":%s}",
                 diag,
                 (diag & (1 << FLOW_DIAG_BIT_UNEXPECTED_FLOW)) ? "true" : "false",
                 (diag & (1 << FLOW_DIAG_BIT_NO_FLOW_TIMEOUT)) ? "true" : "false");
        mqttMgr.publish(topicFault.c_str(), faultMsg, false);
      }
    } else if (!loggedFlowUnsupportedOnce) {
      loggedFlowUnsupportedOnce = true;
      Serial.printf("[FLOW] 팔 노드 프로토콜 버전(%u)이 메인이 기대하는 값(%u)보다 낮음 — 유량 기능 미지원으로 처리\n",
                    rs485.remoteProtocolVersion(), (unsigned)PROTOCOL_VERSION);
    }
  }

  // ── RS485 팔 노드 상태 진단 발행 (상태 변화 시 + 주기적) ──
  bool rs485Online = rs485.isNodeOnline();
  if (mqttConnected && (rs485Online != lastRs485Online || now - lastRs485StatusPublishMs >= RS485_STATUS_PUBLISH_INTERVAL_MS)) {
    lastRs485StatusPublishMs = now;
    lastRs485Online = rs485Online;
    mqttMgr.publish(topicRs485Status.c_str(), rs485Online ? "ok" : "timeout", true);
    if (rs485Online) {
      char tsBuf[16];
      snprintf(tsBuf, sizeof(tsBuf), "%lu", now);
      mqttMgr.publish(topicRs485LastSeen.c_str(), tsBuf, true);
    }
  }

  // ── 주기 상태 발행 (이더넷 링크, uptime, status) ──
  if (mqttConnected && (now - lastStatusPublishMs >= STATUS_PUBLISH_INTERVAL_MS)) {
    lastStatusPublishMs = now;
    mqttMgr.publish(topicStatus.c_str(), "online", true);
    mqttMgr.publish(topicNetworkEthernet.c_str(), ethMgr.isLinked() ? "up" : "down", true);
    char upBuf[16];
    snprintf(upBuf, sizeof(upBuf), "%lu", now / 1000UL);
    mqttMgr.publish(topicUptime.c_str(), upBuf, true);
  }
}
