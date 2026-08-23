#pragma once
// TLS MQTT over 유선 이더넷. 고전 Ethernet.h 라이브러리는 TLS를 지원하지 않으므로
// SSLClient(govorox/SSLClient, BearSSL 기반, EthernetClient를 감싸는 용도로 ESP32+W5500
// 호환성이 확인됨)로 감싼 뒤 PubSubClient에 연결한다. 근거: docs/hardware-verification.md,
// docs/open-decisions.md 6번(TLS 정책 — 1차 버전은 기존 시스템과 동일하게 setInsecure() 사용).
//
// 연결/재연결은 상태머신으로 처리하되, PubSubClient::connect()와 SSLClient::connect()
// 자체가 내부적으로 수 초간 블로킹될 수 있다는 라이브러리 한계가 있다(EthernetManager와
// 동일한 제약). 재시도 간격(RECONNECT_INTERVAL_MS)을 둬서 "짧은 루프"가 되지 않게 한다.

#include <Arduino.h>
#include <Ethernet.h>
#include <PubSubClient.h>
#include <SSLClient.h>

typedef void (*MqttMessageCallback)(char* topic, byte* payload, unsigned int length);

class MqttManager {
public:
  void begin(const char* host, uint16_t port, const char* user, const char* pass,
             const char* clientId, const char* lwtTopic, MqttMessageCallback callback);

  // loop()에서 매번 호출 — 연결 상태 감시 + 필요 시 논블로킹 간격 재연결 + mqttClient.loop()
  void update();

  bool isConnected(); // PubSubClient::connected()가 const가 아니라서 이 메서드도 non-const
  void subscribe(const char* topic);
  void publish(const char* topic, const char* payload, bool retain);

private:
  EthernetClient ethClient_;
  SSLClient sslClient_{&ethClient_};
  PubSubClient mqttClient_{sslClient_};

  const char* host_ = nullptr;
  uint16_t port_ = 8883;
  const char* user_ = nullptr;
  const char* pass_ = nullptr;
  const char* clientId_ = nullptr;
  const char* lwtTopic_ = nullptr;

  unsigned long lastAttemptMs_ = 0;
  static const unsigned long RECONNECT_INTERVAL_MS = 5000;

  void attemptConnect_();
};
