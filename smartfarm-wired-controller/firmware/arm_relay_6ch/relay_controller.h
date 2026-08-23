#pragma once
// 릴레이 6채널을 관리한다. 가장 중요한 규칙: 상태가 "실제로 바뀔 때만"
// GPIO를 조작하고 전이 시각을 기록한다. 이미 ON인 채널에 다시 ON 명령이 와도
// 아무 일도 일어나지 않는다 — 이게 안전 타임아웃(SafetyManager)이 반복 명령으로
// 리셋되지 않도록 보장하는 핵심 규칙이다 (docs/modbus-map.md "멱등성" 참고).

#include <Arduino.h>

class RelayController {
public:
  static const uint8_t CHANNEL_COUNT = 6;

  void begin();

  // 명령 적용. 반환값: 이번 호출로 실제 상태가 바뀌었으면 true(=OFF→ON 또는 ON→OFF 전이),
  // 이미 같은 상태였으면 false(=아무 것도 하지 않음, 타이머도 안 건드림).
  bool setChannel(uint8_t ch, bool on);

  bool getState(uint8_t ch) const;

  // 이 채널이 마지막으로 OFF→ON 전이한 시각(millis() 기준). ON 상태가 아니면 의미 없음.
  unsigned long getOnSinceMs(uint8_t ch) const;

  // 모든 채널 강제 OFF (안전타임아웃/통신워치독/부팅 시 사용). 실제로 켜져 있던
  // 채널만 전이로 취급해 상태발행 대상이 되도록 각 채널에 setChannel(ch,false)를 적용.
  void forceAllOff();

  // FC04 IR_OUTPUT_BITMASK용 — bit0~5 = CH1~CH6 실측 상태
  uint8_t outputBitmask() const;

private:
  bool state_[CHANNEL_COUNT] = {false, false, false, false, false, false};
  unsigned long onSince_[CHANNEL_COUNT] = {0, 0, 0, 0, 0, 0};
  int pin_[CHANNEL_COUNT];
};
