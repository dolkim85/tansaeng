#pragma once
// eModbus ModbusServerRTU를 감싸는 래퍼. RS485 하드웨어 자동 방향제어이므로
// DE/RE 핀 설정이 필요 없다 (docs/hardware-verification.md 참고).
//
// eModbus의 워커 함수는 일반 함수 포인터라 멤버함수를 직접 등록할 수 없어서,
// 이 파일 내부에서 전역 포인터로 RelayController/SafetyManager를 참조한다.
// (임베디드 C 콜백 API를 감쌀 때 흔한 패턴 — begin()에 전달받은 두 인스턴스를
//  static 포인터에 저장해두고 워커 함수들이 그걸 사용한다.)

#include <Arduino.h>
#include "relay_controller.h"
#include "safety_manager.h"
#include "flow_sensor.h"

class Rs485Slave {
public:
  void begin(uint8_t slaveAddress, RelayController* relays, SafetyManager* safety, FlowSensor* flow);

private:
  static uint16_t lastCmdSequence_;
};
