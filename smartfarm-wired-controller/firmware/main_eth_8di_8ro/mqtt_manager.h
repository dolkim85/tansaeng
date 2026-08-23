#pragma once
// TLS MQTT over 유선 이더넷.
//
// [2026-08-23 1차 진단/수정] 고전 Arduino Ethernet.h는 TLS를 지원하지 않아
// SSLClient(govorox/SSLClient, BearSSL, EthernetClient 래핑)로 감싼 뒤 PubSubClient에
// 연결한다. rc=-2(MQTT_CONNECT_FAILED)는 MQTT 프로토콜 단계 이전, "네트워크 연결
// 자체" 실패를 뜻한다. 최초 진단(DNS 실패 가설)은 이후 현장 로그로 반증됨 — 아래
// [2026-08-23 2차 진단] 참고.
//
// [2026-08-23 2차 진단 — 현장 실측 후 확정] 현장 로그: DNS 정상 해석, TCP 8883
// 연결도 성공(`[TCP] ...:8883 connected`)했는데도 계속 rc=-2. 원인은 "해석된
// IPAddress를 SSLClient/PubSubClient에 직접 넘겼다"는 점이었다. 라이브러리
// 소스(govorox/SSLClient 1.3.2)를 추적한 결과:
//   SSLClient::connect(IPAddress ip, port)
//     → connect(ip.toString().c_str(), port, ...)   // IP를 문자열로 바꿔 "host"로 사용
//       → ssl__client.cpp: start_ssl_client(..., host, ...)
//           Step1 init_tcp_connection(ssl_client, host, port)       // TCP 연결에 host 사용
//           Step5 set_hostname_for_tls(ssl_client, host)            // ★ 같은 host를
//               → mbedtls_ssl_set_hostname(&ssl_ctx, host)          //   TLS SNI로도 사용!
// 즉 IP로 바로 연결하면 TLS ClientHello의 SNI가 "54.73.92.158" 같은 IP 문자열이
// 되어버린다. HiveMQ Cloud는 여러 클러스터가 같은 로드밸런서/포트를 공유하는
// SNI 기반 TLS 종단이라, 잘못된 SNI로는 handshake 자체가 깊은 단계(mbedtls
// 내부)에서 실패한다. setInsecure()로 인증서 "검증"은 꺼도 SNI 전송 자체는
// 꺼지지 않으므로 이 문제와는 무관하다.
//
// 추가로 확인된 것: start_ssl_client()는 실패 원인이 무엇이든(TCP/handshake/인증서
// 등 어느 단계든) 최종적으로 항상 정확히 0을 반환한다(ssl__client.cpp 366~371행:
// `if (ret==0) return 1; handle_error(ret); return 0;` — ret이 무엇이든 성공이
// 아니면 0으로 뭉개짐). 그래서 SSLClient::lastError()도 실패 시 대부분 0을
// 반환한다 — **0이라고 "에러 없음"으로 오해하면 안 된다.** 상세 mbedtls 코드는
// handle_error()의 log_e()로만 나가고(ESP32 Core Debug Level을 올려야 시리얼에
// 보임), lastError()로는 전달되지 않는다.
//
// 또한 PubSubClient::connect() 소스 확인 결과, `_client->connect(...)`(SSLClient)가
// 1이 아니면 MQTT CONNECT 패킷 자체를 보내지 않고 곧바로 `_state=MQTT_CONNECT_FAILED`
// (=-2)로 설정한다 — 즉 rc=-2는 "브로커가 CONNECT를 거절했다"가 아니라 "TLS/TCP
// 연결 자체가 안 돼 CONNECT를 보내지도 못했다"는 뜻이다. 브로커가 실제로 CONNACK을
// 보내고 거절한 경우에만 상태값이 1~5(MQTT_CONNECT_BAD_PROTOCOL 등)가 된다.
//
// 최초 진단(DNS)도 완전히 틀린 것은 아니었다 — 고전 Ethernet.h의
// EthernetClient::connect(const char*, port)가 내부 DNS 실패를 조용히 삼키는
// 문제는 여전히 존재하고(그래서 resolveHost_()의 사전 DNS 조회는 진단 목적으로
// 유지한다), 이번엔 그 경로가 원인이 아니었을 뿐이다.
//
// 수정 방향(2차): resolveHost_()/tcpPreTest_()는 "진단"과 "순수 TCP 도달성 확인"
// 용도로만 남기고, **실제 TLS/MQTT 연결은 반드시 원래 호스트명 문자열로** 시도해
// SNI를 지킨다(EthernetClient가 내부적으로 DNS를 한 번 더 조회하게 되는 비용을
// 감수한다 — 현장에서 이미 DNS가 정상 동작함을 확인했으므로 안전한 트레이드오프).

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

  // loop()에서 매번 호출. ⚠️ "완전 논블로킹"이 아니다 — 재시도 사이 대기는
  // 논블로킹이지만, 연결을 실제로 "시도하는 한 번"(DNS/TCP/TLS)은 라이브러리
  // 내부에서 블로킹 소켓 호출을 한다(현장 실측 약 2.3초, 이전 보고의 "완전
  // 비차단"은 부정확했음 — 2026-08-23 정정). 이 블로킹 동안 loop()의 나머지
  // (rs485.update() 등)가 지연된다 — 다만 eModbus는 별도 FreeRTOS 태스크에서
  // UART 송수신을 하므로 이미 큐에 들어간 RS485 요청은 계속 처리되고, 팔 노드의
  // RS485_COMM_TIMEOUT_MS(10초) 워치독은 "10초간 유효 프레임 없음"이 기준이라
  // 2~3초 지연 정도로는 위협받지 않는다. 그래도 여유를 두기 위해 handshake/socket
  // 타임아웃을 각각 5초로 낮춰 최악의 경우에도 10초에 명확한 여유를 두었다(아래
  // begin() 구현 참고). 재시도 간격은 실패가 반복되면 지수적으로 늘어나는 제한된
  // backoff를 적용해(RECONNECT_INTERVAL_MS_BASE~MAX) 브로커를 과도하게 두드리지
  // 않는다.
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
  // 제한된 지수 backoff — 실패가 반복될수록 재시도 간격을 늘려 브로커를 과도하게
  // 두드리지 않는다. 성공하면 즉시 BASE로 리셋.
  static const unsigned long RECONNECT_INTERVAL_MS_BASE = 5000;
  static const unsigned long RECONNECT_INTERVAL_MS_MAX  = 60000;
  unsigned long reconnectIntervalMs_ = RECONNECT_INTERVAL_MS_BASE;

  void attemptConnect_();
  bool resolveHost_(IPAddress& outIp);
  bool tcpPreTest_(const IPAddress& ip);
};
