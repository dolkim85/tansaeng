#pragma once
// 유량계(YF-B10-S) 펄스 측정 — DI1(GPIO4, 직접 GPIO, active-low)에 연결.
// 기존 ctlr-0004 계산식/기준값을 그대로 유지(K factor 등). 근거: docs/hardware-verification.md,
// 작업지시서 7번.
//
// ⚠️ NEEDS_HARDWARE_TEST: DI1이 실제로 active-low인지, 인터럽트 엣지가 FALLING이
// 맞는지는 알려진 주파수 펄스 발생기로 실물 검증 필요(test/flow_pulse_generator).

#include <Arduino.h>

class FlowMeter {
public:
  void begin();

  // loop()에서 매번 호출 — 내부적으로 FLOW_PUBLISH_INTERVAL_MS(1초)마다만 실제로 계산.
  // 반환값: 이번 호출에서 새로 계산됐으면 true(호출자가 MQTT 발행할 시점).
  bool update();

  float getRateLpm() const { return rateLpm_; }
  double getTotalLiters() const { return totalLiters_; }
  unsigned long getLastIntervalPulses() const { return lastIntervalPulses_; }

  // NVS에서 복원한 누적값을 반영(재부팅 후 총량 유지, 작업지시서 7번)
  void restoreTotalLiters(double liters) { totalLiters_ = liters; }

private:
  unsigned long lastCalcMs_ = 0;
  double totalLiters_ = 0.0;
  float rateLpm_ = 0.0f;
  unsigned long lastIntervalPulses_ = 0;
};
