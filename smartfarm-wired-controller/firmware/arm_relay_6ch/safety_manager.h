#pragma once
// 팔 노드의 독립 안전 기능 — 메인 노드/RS485와 완전히 무관하게 동작해야 한다
// (작업지시서 6.4번: "안전 타임아웃은 팔 노드가 직접 수행해야 한다. 메인 노드에만
// 구현하면 RS485 장애 시 밸브를 닫을 수 없기 때문이다.")
//
// 두 가지 독립된 감시를 수행한다:
// 1) 채널별 밸브 안전 타임아웃 — 켜진 채로 너무 오래 있으면 강제 OFF
// 2) RS485 통신 워치독 — 마스터로부터 너무 오래 아무 프레임도 못 받으면 전체 OFF

#include <Arduino.h>
#include "relay_controller.h"

class SafetyManager {
public:
  void begin(RelayController* relays);
  void setValveTimeoutMs(uint8_t ch, unsigned long ms);
  void setCommWatchdogTimeoutMs(unsigned long ms);

  // loop()에서 매번 호출 — 채널별 타임아웃을 검사해 초과한 채널을 강제 OFF.
  void checkValveTimeouts();

  // RS485에서 유효한(CRC 정상) 프레임을 받을 때마다 호출.
  void noteValidFrameReceived();

  // loop()에서 매번 호출 — 통신 두절이 임계치를 넘었으면 전체 강제 OFF.
  void checkCommWatchdog();

  // CRC 오류 등 통신 이상을 감지했을 때 호출 — FAULT_BIT_CRC_ERROR를 세운다.
  void noteCommError();

  uint8_t faultBitmask() const { return faultBits_; }

private:
  RelayController* relays_ = nullptr;
  unsigned long valveTimeoutMs_[RelayController::CHANNEL_COUNT];
  unsigned long commWatchdogTimeoutMs_ = 10000UL;
  unsigned long lastValidFrameMs_ = 0;
  bool commWasOk_ = true; // 통신두절→복귀 전이 로그를 위한 상태 추적
  uint8_t faultBits_ = 0;
};
