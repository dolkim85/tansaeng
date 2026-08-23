#pragma once
// W5500 유선 이더넷 연결 관리. 블로킹 재연결 루프 없이 millis() 상태머신으로 동작
// (작업지시서 6.2번: "Ethernet/MQTT 재연결은 비동기 또는 짧은 단계식 상태머신으로 수행,
//  연결 재시도를 기다리는 while 무한루프 금지, 네트워크 실패만으로 ESP.restart() 금지").

#include <Arduino.h>

class EthernetManager {
public:
  void begin(); // SPI/W5500 초기화 + DHCP(또는 고정IP) 시작. 실패해도 재부팅하지 않음.

  // loop()에서 매번 호출 — 링크 상태를 감시하고 필요 시 비차단으로 재시도.
  void update();

  bool isLinked() const;
  bool hasIp() const;

private:
  enum class State { INIT, WAIT_DHCP, CONNECTED, LINK_DOWN };
  State state_ = State::INIT;
  unsigned long lastRetryMs_ = 0;
  unsigned long lastLinkCheckMs_ = 0;
  bool wasLinked_ = false;
};
