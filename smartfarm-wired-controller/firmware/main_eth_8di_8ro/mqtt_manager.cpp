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
  // handshake(5s) + socket timeout(5s) 합이 팔 노드의 RS485_COMM_TIMEOUT_MS(10초,
  // shared/protocol_version.h)보다 뚜렷이 작도록 잡는다 — 연결 시도 중 loop()가
  // 지연되는 동안에도 RS485 워치독에 여유가 남도록(2026-08-23 현장 실측 후 조정,
  // 기존 8s/10s 조합은 최악의 경우 워치독 창에 너무 근접할 수 있었음).
  sslClient_.setHandshakeTimeout(5);

  mqttClient_.setCallback(callback);
  mqttClient_.setBufferSize(512); // 기존 ctlr-heat-001 사고 교훈(작은 기본버퍼로 heartbeat 조용히 실패) 반영
  mqttClient_.setSocketTimeout(5);
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

// 순수 TCP 연결성만 "진단 전용 임시 소켓"으로 확인한다. 반드시 실제 MQTT에 쓰는
// ethClient_/sslClient_와는 별개의 EthernetClient 인스턴스를 써야 한다 — 같은
// 소켓을 공유하면 이 사전테스트가 실제 SSLClient의 연결 상태를 깨뜨릴 수 있다.
// 성공하면 즉시 닫아서 W5500의 8개 하드웨어 소켓 중 하나를 계속 점유하지 않는다.
bool MqttManager::tcpPreTest_(const IPAddress& ip) {
  EthernetClient testClient; // ethClient_와 무관한 별도 객체(요구사항: 소켓 공유 금지)
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
  unsigned long attemptStartMs = millis();

  IPAddress resolvedIp;
  if (!resolveHost_(resolvedIp)) {
    return; // DNS 실패 — 진단 목적. 아래 backoff에 따라 재시도
  }

  if (!tcpPreTest_(resolvedIp)) {
    return; // DNS는 됐지만 서버 도달 불가(방화벽/포트차단/서버다운 등) — 순수 진단용 별도 소켓
  }

  // ⚠️ 실제 TLS/MQTT 연결은 반드시 "원래 호스트명 문자열"로 시도한다 — 위에서 구한
  // IPAddress를 SSLClient/PubSubClient에 직접 넘기지 않는다. 이유(mqtt_manager.h
  // 상단 [2026-08-23 2차 진단] 참고): SSLClient::connect(IPAddress,...)는 내부에서
  // ip.toString()을 그대로 TLS SNI(mbedtls_ssl_set_hostname)에도 사용해버려 HiveMQ
  // Cloud 같은 SNI 기반 TLS 종단에서 handshake 자체가 실패한다. DNS를 한 번 더
  // 소비하더라도(EthernetClient가 host_로 내부 재조회) SNI를 지키는 쪽이 안전하다 —
  // 위에서 이미 DNS가 정상 동작함을 확인했다.
  mqttClient_.setServer(host_, port_);

  // TLS handshake는 mbedTLS 세션 컨텍스트(수 KB~수십 KB)를 힙에 할당한다 — 할당
  // 실패도 start_ssl_client()에서는 다른 실패와 마찬가지로 0으로 뭉개지므로, 힙이
  // 원인인지 별도로 눈으로 볼 수 있게 시도 전 여유를 남긴다(2026-08-23, SNI 수정
  // 이후에도 handshake 실패가 재현되어 추가).
  uint32_t heapBefore = ESP.getFreeHeap();

  bool ok = mqttClient_.connect(clientId_, user_, pass_, lwtTopic_, 0, true, "offline");

  unsigned long elapsedMs = millis() - attemptStartMs;
  if (elapsedMs >= 3000) {
    // 팔 노드 RS485_COMM_TIMEOUT_MS(10초)와 비교할 수 있도록 항상 계측해 남긴다 —
    // "완전 논블로킹"이 아니므로(mqtt_manager.h 참고) 실제 소요시간을 눈으로 볼 수 있어야 한다.
    Serial.printf("[MQTT] ⚠️ 연결 시도에 %lums 소요(RS485 워치독 10000ms 대비 여유 확인 필요)\n", elapsedMs);
  } else {
    Serial.printf("[MQTT] 연결 시도 소요시간: %lums\n", elapsedMs);
  }

  if (ok) {
    Serial.println("[TLS] handshake success");
    Serial.printf("[MQTT] connected, clientId=%s\n", clientId_);
    mqttClient_.publish(lwtTopic_, "online", true);
    reconnectIntervalMs_ = RECONNECT_INTERVAL_MS_BASE; // 성공 시 backoff 리셋
    return;
  }

  // 실패 직후 lastError/lastFailedStep 조회. [2026-08-23] 원본 GovoroxSSLClient
  // 1.3.2는 start_ssl_client()가 실패 원인이 무엇이든 최종 반환값을 항상 정확히
  // 0으로 뭉개(ssl__client.cpp) lastError()도 대부분 0만 나왔다. 이제
  // firmware/main_eth_8di_8ro/src/SSLClient/(로컬 사본, VENDORED_FROM.md 참고)가
  // 실제 mbedTLS/내부 에러코드와 실패 단계 이름을 보존하도록 패치돼 있어 여기서
  // 의미 있는 값을 볼 수 있다.
  char errBuf[100] = {0};
  int sslErr = sslClient_.lastError(errBuf, sizeof(errBuf));
  Serial.printf("[TLS] 실패 단계=%s\n", sslClient_.lastFailedStep());
  Serial.printf("[TLS] 실제 mbedTLS 오류번호=%d\n", sslErr);
  Serial.printf("[TLS] 오류 문자열=%s\n", errBuf[0] ? errBuf : "(문자열 없음 - 내부 코드가 mbedtls 표준 코드 범위 밖일 수 있음)");

  uint32_t heapAfter = ESP.getFreeHeap();
  Serial.printf("[TLS] 힙 여유: 시도전=%u, 시도후=%u bytes\n", (unsigned int)heapBefore, (unsigned int)heapAfter);
  if (heapAfter < 20000) {
    Serial.println("[TLS] ⚠️ 힙 여유가 낮음 — TLS 핸드셰이크 중 메모리 할당 실패 가능성 있음");
  }

  // PubSubClient 상태값 중 1~5(MQTT_CONNECT_BAD_PROTOCOL ~ MQTT_CONNECT_UNAUTHORIZED)만
  // "브로커가 실제로 CONNACK을 보내고 거절"한 경우다. PubSubClient::connect() 소스
  // 확인 결과, _client->connect(...)(SSLClient, 즉 TCP/TLS 단계)가 1이 아니면 MQTT
  // CONNECT 패킷 자체를 보내지 않고 곧바로 _state=MQTT_CONNECT_FAILED(-2)로 리턴한다
  // — 그 외 상태(-1/-2/-3/-4)는 CONNACK을 아예 받아본 적이 없다는 뜻이므로
  // "rejected"라고 부르면 안 된다.
  int state = mqttClient_.state();
  if (state >= 1 && state <= 5) {
    Serial.printf("[MQTT] CONNECT rejected: rc=%d\n", state);
  } else {
    Serial.println("[TLS] handshake/secure transport failed");
    Serial.println("[MQTT] CONNECT not sent");
    // [2026-08-23] 이전에는 여기서 "Core Debug Level을 올리라"고 안내했으나, 그것만
    // 으로는 부족했다(사용자가 실제로 Verbose로 올려 재현했는데도 ssl__client.cpp
    // 내부 로그가 전혀 안 보였음 — 라이브러리가 별도 translation unit이라 스케치의
    // #define이 전달되지 않는 문제와 별개로, 캐시된 라이브러리 오브젝트가 재사용됐을
    // 가능성도 있음). 이제는 위 "[TLS] 실패 단계"/"[TLS] 실제 mbedTLS 오류번호" 로그
    // 자체가 로컬 SSLClient 사본(src/SSLClient/, VENDORED_FROM.md 참고)이 직접
    // 보존해 주므로 Core Debug Level 설정과 무관하게 항상 확인 가능하다.
  }

  // 실패가 반복되면 재시도 간격을 지수적으로 늘려(최대 RECONNECT_INTERVAL_MS_MAX)
  // 브로커를 과도하게 두드리지 않는다.
  reconnectIntervalMs_ = min(reconnectIntervalMs_ * 2, RECONNECT_INTERVAL_MS_MAX);
}

void MqttManager::update() {
  if (mqttClient_.connected()) {
    mqttClient_.loop();
    return;
  }

  unsigned long now = millis();
  if (now - lastAttemptMs_ >= reconnectIntervalMs_) {
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
