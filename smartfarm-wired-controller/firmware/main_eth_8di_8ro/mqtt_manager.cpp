#include "mqtt_manager.h"
#include "config.h"

void MqttManager::begin(const char* host, uint16_t port, const char* user, const char* pass,
                         const char* clientId, const char* lwtTopic, MqttMessageCallback callback) {
  host_ = host;
  port_ = port;
  user_ = user;
  pass_ = pass;
  clientId_ = clientId;
  lwtTopic_ = lwtTopic;

#ifdef USE_TLS_VERIFY
  // 2차 버전: HiveMQ Cloud CA 루트 인증서 고정 (현재 미사용 — open-decisions.md 6번)
  // sslClient_.setCACert(HIVEMQ_ROOT_CA);
#else
  sslClient_.setInsecure(); // 1차 버전: 기존 시스템 전체와 동일한 정책
#endif

  mqttClient_.setServer(host_, port_);
  mqttClient_.setCallback(callback);
  mqttClient_.setBufferSize(512); // 기존 ctlr-heat-001 사고 교훈(작은 기본버퍼로 heartbeat 발행 조용히 실패) 반영

  lastAttemptMs_ = 0; // 즉시 첫 연결 시도
}

void MqttManager::attemptConnect_() {
  Serial.print("[MQTT] 연결 시도... ");
  bool ok = mqttClient_.connect(clientId_, user_, pass_, lwtTopic_, 0, true, "offline");
  if (ok) {
    Serial.println("연결됨");
    mqttClient_.publish(lwtTopic_, "online", true);
  } else {
    Serial.printf("실패, rc=%d — %lums 후 재시도\n", mqttClient_.state(), RECONNECT_INTERVAL_MS);
  }
}

void MqttManager::update() {
  if (mqttClient_.connected()) {
    mqttClient_.loop();
    return;
  }

  unsigned long now = millis();
  if (now - lastAttemptMs_ >= RECONNECT_INTERVAL_MS) {
    lastAttemptMs_ = now;
    attemptConnect_();
  }
}

bool MqttManager::isConnected() {
  return mqttClient_.connected();
}

void MqttManager::subscribe(const char* topic) {
  mqttClient_.subscribe(topic);
}

void MqttManager::publish(const char* topic, const char* payload, bool retain) {
  mqttClient_.publish(topic, payload, retain);
}
