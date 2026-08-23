#include "relay_controller.h"
#include "board_pins.h"

void RelayController::begin() {
  pin_[0] = PIN_RELAY_CH1;
  pin_[1] = PIN_RELAY_CH2;
  pin_[2] = PIN_RELAY_CH3;
  pin_[3] = PIN_RELAY_CH4;
  pin_[4] = PIN_RELAY_CH5;
  pin_[5] = PIN_RELAY_CH6;

  // 부팅 직후 가능한 가장 이른 시점에 안전 레벨(OFF)로 설정 — 작업지시서 3.2번
  // "부팅 과정에서 릴레이가 순간적으로 켜지지 않도록" 요구사항.
  for (uint8_t i = 0; i < CHANNEL_COUNT; i++) {
    pinMode(pin_[i], OUTPUT);
    digitalWrite(pin_[i], RELAY_INACTIVE_LEVEL);
    state_[i] = false;
    onSince_[i] = 0;
  }
}

bool RelayController::setChannel(uint8_t ch, bool on) {
  if (ch >= CHANNEL_COUNT) return false;
  if (state_[ch] == on) return false;  // 멱등성 — 이미 같은 상태면 아무 것도 안 함(타이머도 유지)

  state_[ch] = on;
  digitalWrite(pin_[ch], on ? RELAY_ACTIVE_LEVEL : RELAY_INACTIVE_LEVEL);
  if (on) {
    onSince_[ch] = millis();
  }
  return true;
}

bool RelayController::getState(uint8_t ch) const {
  if (ch >= CHANNEL_COUNT) return false;
  return state_[ch];
}

unsigned long RelayController::getOnSinceMs(uint8_t ch) const {
  if (ch >= CHANNEL_COUNT) return 0;
  return onSince_[ch];
}

void RelayController::forceAllOff() {
  for (uint8_t i = 0; i < CHANNEL_COUNT; i++) {
    setChannel(i, false);
  }
}

uint8_t RelayController::outputBitmask() const {
  uint8_t mask = 0;
  for (uint8_t i = 0; i < CHANNEL_COUNT; i++) {
    if (state_[i]) mask |= (1 << i);
  }
  return mask;
}
