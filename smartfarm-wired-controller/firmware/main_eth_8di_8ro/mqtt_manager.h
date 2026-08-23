#pragma once
// TLS MQTT over 유선 이더넷.
//
// [2026-08-23 rc=-2 진단/수정] 고전 Arduino Ethernet.h는 TLS를 지원하지 않아
// SSLClient(govorox/SSLClient, BearSSL, EthernetClient 래핑)로 감싼 뒤 PubSubClient에
// 연결한다. rc=-2(MQTT_CONNECT_FAILED)는 MQTT 프로토콜 단계 이전, "네트워크 연결
// 자체" 실패를 뜻한다. 라이브러리 소스를 직접 추적한 결과:
//   PubSubClient::connect() → _client->connect(domain, port) [SSLClient]
//     → start_ssl_client() → init_tcp_connection() → pClient->connect(host, port) [EthernetClient]
//       → 고전 Ethernet.h의 EthernetClient::connect(const char*, port)는 내부적으로
//         DNSClient::getHostByName()으로 자체 DNS 조회를 하고, 실패하면 아무 로그
//         없이 그냥 0을 반환한다 (arduino-libraries/Ethernet/src/EthernetClient.cpp).
// 즉 PC에서 8883 TCP는 열려도(방화벽/포트는 문제없음) ESP32 쪽은 DNS 조회
// 실패만으로 조용히 rc=-2가 난다 — 사용자가 시도한 W5500_WORKAROUND는
// TLS handshake 재시도 루프에만 영향을 주는 매크로라 이 단계와는 무관하다
// (ssl__client.cpp 735행 부근, `_W5500_H_`가 이미 자동 정의되어 사실상 중복이었음).
//
// 수정 방향: DNS를 우리가 직접(같은 DNSClient로) 미리 조회해 로그로 남기고,
// 실제 MQTT 연결은 해석된 IP로 바로 붙어(EthernetClient의 재조회 경로를 건너뜀)
// TCP/TLS/MQTT 각 단계 성공 여부를 분리해서 시리얼에 남긴다.

#include <Arduino.h>
#include <Ethernet.h>
#include <Dns.h>
#include <PubSubClient.h>
#include <SSLClient.h>

typedef void (*MqttMessageCallback)(char* topic, byte* payload, unsigned int length);

class MqttManager {
public:
  // clientIdPrefix: 사람이 읽는 접두사(예: "ctlr-0004-eth"). 실제 MQTT 네트워크
  // clientId는 여기에 MAC 기반 접미사를 붙여 장치마다 고유하게 만든다 — 기존
  // WiFi ctlr-0004(clientId가 별도 규칙)와 겹치지 않도록. CONTROLLER_ID(토픽 이름에
  // 쓰이는 "ctlr-0004")와는 별개의 값이다.
  void begin(const char* host, uint16_t port, const char* user, const char* pass,
             const char* clientIdPrefix, const char* lwtTopic, MqttMessageCallback callback);

  // loop()에서 매번 호출 — 논블로킹. 재시도 간격(RECONNECT_INTERVAL_MS)을 지켜
  // "짧은 루프"가 되지 않게 한다. 단, 개별 시도 자체(DNS/TCP/TLS)는 각 라이브러리의
  // 내부 타임아웃만큼 짧게 블로킹될 수 있다는 한계는 여전하다(수 초 이내로 제한됨,
  // 분 단위로 부팅이 멈추던 예전 문제와는 다름 — 아래 setHandshakeTimeout 참고).
  void update();

  bool isConnected();
  void subscribe(const char* topic);
  void publish(const char* topic, const char* payload, bool retain);
  const char* clientId() const { return clientId_; }

private:
  EthernetClient ethClient_;
  SSLClient sslClient_{&ethClient_};
  PubSubClient mqttClient_{sslClient_};

  const char* host_ = nullptr;
  uint16_t port_ = 8883;
  const char* user_ = nullptr;
  const char* pass_ = nullptr;
  const char* lwtTopic_ = nullptr;
  char clientId_[40] = {0};

  unsigned long lastAttemptMs_ = 0;
  static const unsigned long RECONNECT_INTERVAL_MS = 5000;

  void attemptConnect_();
  bool resolveHost_(IPAddress& outIp);
  bool tcpPreTest_(const IPAddress& ip);
};
