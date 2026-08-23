#include "flow_meter.h"
#include "board_pins.h"
#include "config.h"
#include "../../shared/protocol_version.h"

// attachInterrupt는 멤버함수를 직접 받을 수 없어서 파일 범위 정적 카운터를 사용한다
// (팔 노드 rs485_slave.cpp와 동일한 이유의 패턴).
static volatile unsigned long g_pulseCount = 0;

static void IRAM_ATTR flowISR() {
  g_pulseCount++;
}

void FlowMeter::begin() {
  pinMode(PIN_DI1, INPUT_PULLUP); // DI1은 옵토아이솔레이션 + 내부 풀업(hardware-verification.md)
  attachInterrupt(digitalPinToInterrupt(PIN_DI1), flowISR, FLOW_PULSE_EDGE);
  lastCalcMs_ = millis();
}

bool FlowMeter::update() {
  unsigned long now = millis();
  if (now - lastCalcMs_ < FLOW_PUBLISH_INTERVAL_MS) return false;

  noInterrupts();
  unsigned long pulses = g_pulseCount;
  g_pulseCount = 0;
  interrupts();

  float elapsedSec = (now - lastCalcMs_) / 1000.0f;
  lastCalcMs_ = now;
  lastIntervalPulses_ = pulses;

  // 기존 ctlr-0004와 동일한 K factor 계산식: Frequency(Hz) = 10*Q - 5 -> Q(L/min) = (Freq+5)/10
  float frequency = pulses / elapsedSec;
  rateLpm_ = (pulses == 0) ? 0.0f : (frequency + 5.0f) / 10.0f;
  if (rateLpm_ < 0) rateLpm_ = 0;

  totalLiters_ += pulses / FLOW_PULSES_PER_LITER;

  return true;
}
