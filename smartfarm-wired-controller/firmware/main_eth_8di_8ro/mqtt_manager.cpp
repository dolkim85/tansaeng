#include "mqtt_manager.h"
#include "config.h"

void MqttManager::begin(const char* host, uint16_t port, const char* user, const char* pass,
                         const char* clientIdPrefix, const char* lwtTopic, MqttMessageCallback callback) {
  host_ = host;
  port_ = port;
  user_ = user;
  pass_ = pass;
  lwtTopic_ = lwtTopic;

  // MQTT 네트워크 clientId는 CONTROLLER_ID(토픽/UI 호환용)와 분리해 장치마다 고유하게
  // 만든다 — 기존 WiFi ctlr-0004와 새 유선 메인 노드가 전환/롤백 테스트 중 동시에
  // 켜져 있어도 같은 clientId 때문에 브로커가 한쪽을 끊어버리는 사고를 방지한다.
  uint64_t mac = ESP.getEfuseMac();
  // %X는 unsigned int를 기대하는데 uint32_t는 이 플랫폼에서 "long unsigned int"라
  // 타입이 달라 -Wformat 경고가 남는다(값 자체는 동일 크기라 문제 없었지만 2026-08-23
  // --warnings all 재검증 중 발견해 명시적으로 unsigned int로 캐스팅해 정리).
  snprintf(clientId_, sizeof(clientId_), "%s-%04X%08X", clientIdPrefix,
           (unsigned int)(uint16_t)(mac >> 32), (unsigned int)(uint32_t)mac);
  Serial.printf("[MQTT] clientId: %s\n", clientId_);

  // 순수 호스트명만 허용 — 프로토콜/포트가 섞여 들어오는 흔한 실수 방어
  if (strstr(host_, "://") != nullptr || strchr(host_, ':') != nullptr) {
    Serial.printf("[MQTT] ⚠️ MQTT_HOST에 프로토콜/포트가 포함된 것으로 보입니다: '%s' — 순수 호스트명만 사용하세요\n", host_);
  }

#ifdef USE_TLS_VERIFY
  // 2차 버전: HiveMQ Cloud CA 루트 인증서 고정 (현재 미사용 — open-decisions.md 6번)
#else
  sslClient_.setInsecure(); // 1차 버전: 기존 시스템 전체와 동일한 정책. 평문(1883)으로
                              // 우회하지 않는다 — 데이터는 여전히 TLS로 암호화된다.
#endif
  sslClient_.setHandshakeTimeout(8); // 초 — 기본값이 너무 길면 재연결 주기와 충돌해 부팅이 오래 걸림

  mqttClient_.setCallback(callback);
  mqttClient_.setBufferSize(512); // 기존 ctlr-heat-001 사고 교훈(작은 기본버퍼로 heartbeat 조용히 실패) 반영
  mqttClient_.setSocketTimeout(10);
  mqttClient_.setKeepAlive(30);

  lastAttemptMs_ = 0; // 즉시 첫 연결 시도
}

// DNS 조회 — 고전 Ethernet.h가 내부에서 하는 것과 동일한 DNSClient를 우리가 직접 먼저
// 호출해서 성공/실패와 결과 IP를 로그로 남긴다. IP 문자열이 그대로 들어온 경우(예:
// 임시로 IP를 config.h에 넣어 DNS 문제를 우회 테스트할 때)는 조회 없이 바로 사용한다.
bool MqttManager::resolveHost_(IPAddress& outIp) {
  if (outIp.fromString(host_)) {
    Serial.printf("[DNS] MQTT_HOST가 이미 IP 형식: %s\n", host_);
    return true;
  }

  IPAddress dnsServer = Ethernet.dnsServerIP();
  Serial.printf("[DNS] 사용 중인 DNS 서버: %s\n", dnsServer.toString().c_str());
  if (dnsServer == IPAddress((uint32_t)0)) {
    Serial.println("[DNS] ⚠️ DHCP로 DNS 서버 IP를 받지 못함(0.0.0.0) — 공유기 DHCP 설정 확인 필요");
  }

  DNSClient dns;
  dns.begin(dnsServer);
  if (dns.getHostByName(host_, outIp)) {
    Serial.printf("[DNS] MQTT host resolved: %s -> %s\n", host_, outIp.toString().c_str());
    return true;
  }

  Serial.println("[DNS] resolution failed");
  return false;
}

// 순수 TCP 연결성만 별도 소켓으로 확인(TLS 이전 단계를 분리해서 보기 위함).
// 성공하면 즉시 닫아서 W5500의 8개 하드웨어 소켓 중 하나를 계속 점유하지 않는다.
bool MqttManager::tcpPreTest_(const IPAddress& ip) {
  EthernetClient testClient;
  bool ok = testClient.connect(ip, port_);
  if (ok) {
    Serial.printf("[TCP] %s:%u connected\n", ip.toString().c_str(), port_);
    testClient.stop();
  } else {
    Serial.printf("[TCP] connect failed (%s:%u)\n", ip.toString().c_str(), port_);
  }
  return ok;
}

void MqttManager::attemptConnect_() {
  Serial.println("[MQTT] ── 연결 시도 ──");

  IPAddress resolvedIp;
  if (!resolveHost_(resolvedIp)) {
    return; // DNS 실패 — RECONNECT_INTERVAL_MS 후 재시도
  }

  if (!tcpPreTest_(resolvedIp)) {
    return; // DNS는 됐지만 서버 도달 불가(방화벽/포트차단/서버다운 등)
  }

  // 실제 MQTT 연결은 해석된 IP로 직접 시도 — EthernetClient가 connect(host,port)에서
  // 매번 다시 DNS를 조회하는 경로를 건너뛴다(그 경로가 바로 rc=-2의 원인이었음).
  mqttClient_.setServer(resolvedIp, port_);

  bool ok = mqttClient_.connect(clientId_, user_, pass_, lwtTopic_, 0, true, "offline");
  if (ok) {
    Serial.println("[TLS] handshake success");
    Serial.printf("[MQTT] connected, clientId=%s\n", clientId_);
    mqttClient_.publish(lwtTopic_, "online", true);
    return;
  }

  char errBuf[100] = {0};
  int sslErr = sslClient_.lastError(errBuf, sizeof(errBuf));
  if (sslErr == -2) {
    // ssl__client.cpp의 init_tcp_connection()이 반환하는 내부 코드(TLS 이전 단계,
    // SSLClient가 자기 소켓을 여는 과정 실패). 방금 TCP 사전테스트는 성공했으므로
    // W5500 소켓 일시 고갈/타이밍 문제일 가능성이 높다 — 재시도로 보통 해소된다.
    Serial.println("[TLS] handshake failed: TCP 재연결 단계 실패(SSLClient 내부 -2, W5500 소켓 일시 고갈 가능성)");
  } else if (sslErr != 0) {
    Serial.printf("[TLS] handshake failed: %s (code %d)\n", errBuf, sslErr);
  } else {
    Serial.printf("[MQTT] CONNECT rejected: rc=%d\n", mqttClient_.state());
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
