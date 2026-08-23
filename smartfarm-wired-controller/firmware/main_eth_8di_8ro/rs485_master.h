#pragma once
// RS485 Modbus Master — 팔 노드(Relay-6CH)에 밸브 명령을 보내고, 실제 출력 상태를
// 읽어 명령 성공 여부를 확인한다("명령을 보냈다"와 "실제로 그렇게 됐다"를 구분,
// docs/architecture.md 참고). eModbus는 별도 태스크에서 통신하므로 여기서는
// 논블로킹 폴링 주기만 관리한다.

#include <Arduino.h>

class Rs485Master {
public:
  void begin(uint8_t slaveAddress);

  // loop()에서 매번 호출 — 정기 폴링(RS485_POLL_INTERVAL_MS)을 트리거.
  void update();

  // 밸브 명령(논블로킹) — 결과는 다음 폴링에서 getActualState()로 확인.
  void commandValve(uint8_t coilIndex, bool on);

  bool getActualState(uint8_t coilIndex) const; // 팔 노드가 마지막으로 보고한 실측 상태
  bool isNodeOnline() const;                     // 최근 폴링에 정상 응답했는지
  uint16_t faultBitmask() const;

  // eModbus 콜백에서만 호출됨(내부용, public인 이유는 콜백이 멤버함수가 아니라서)
  void onPollSuccess(const uint16_t regs[]);
  void onCommError();

private:
  uint8_t slaveAddress_ = 1;
  unsigned long lastPollMs_ = 0;
  unsigned long lastGoodResponseMs_ = 0;
  uint16_t lastOutputBitmask_ = 0;
  uint16_t lastFaultBitmask_ = 0;
  bool online_ = false;
};
