#pragma once
// NVS(비휘발성 저장소) 저장 — 유량 누적값을 재부팅 후에도 유지한다.
// 매 펄스마다 쓰지 않고 호출자가 주기적으로(예: 5분마다) saveFlowTotal()을 호출해야 한다
// (작업지시서 7번: "Flash에 쓰지 않는다" — Flash 마모 방지).
// 버전/CRC 검증 후 사용, 손상되면 안전 기본값(0.0) 사용 (작업지시서 6.1번).

#include <Arduino.h>

class Storage {
public:
  void begin();

  // 손상되었거나 버전이 다르면 0.0을 반환하고 로그를 남긴다(값을 아는 척 하지 않음).
  double loadFlowTotal();
  void saveFlowTotal(double liters);

private:
  static const uint32_t STORAGE_VERSION = 1;
};
