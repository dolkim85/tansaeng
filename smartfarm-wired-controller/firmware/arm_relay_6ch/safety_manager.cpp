#include "safety_manager.h"
#include "../../shared/protocol_version.h"

void SafetyManager::begin(RelayController* relays) {
  relays_ = relays;
  for (uint8_t i = 0; i < RelayController::CHANNEL_COUNT; i++) {
    valveTimeoutMs_[i] = VALVE_SAFETY_TIMEOUT_MS_DEFAULT;
  }
  lastValidFrameMs_ = millis();
}

void SafetyManager::setValveTimeoutMs(uint8_t ch, unsigned long ms) {
  if (ch >= RelayController::CHANNEL_COUNT) return;
  valveTimeoutMs_[ch] = ms;
}

void SafetyManager::setCommWatchdogTimeoutMs(unsigned long ms) {
  commWatchdogTimeoutMs_ = ms;
}

void SafetyManager::checkValveTimeouts() {
  if (!relays_) return;
  unsigned long now = millis();
  for (uint8_t i = 0; i < RelayController::CHANNEL_COUNT; i++) {
    if (!relays_->getState(i)) continue;
    // millis() 오버플로에도 안전한 비교 (부호 없는 뺄셈은 오버플로를 넘어도 올바른 경과시간을 준다)
    unsigned long elapsed = now - relays_->getOnSinceMs(i);
    if (elapsed > valveTimeoutMs_[i]) {
      relays_->setChannel(i, false);
      faultBits_ |= (1 << i); // FAULT_BIT_CH1_TIMEOUT..CH6_TIMEOUT과 동일한 비트 위치
      Serial.printf("[SAFETY] CH%d 안전타임아웃(%lums) 초과 -> 강제 OFF\n", i + 1, valveTimeoutMs_[i]);
    }
  }
}

void SafetyManager::noteValidFrameReceived() {
  lastValidFrameMs_ = millis();
  if (!commWasOk_) {
    Serial.println("[SAFETY] RS485 통신 복구됨");
    commWasOk_ = true;
  }
}

void SafetyManager::checkCommWatchdog() {
  unsigned long elapsed = millis() - lastValidFrameMs_;
  if (elapsed > commWatchdogTimeoutMs_) {
    if (commWasOk_) {
      Serial.printf("[SAFETY] RS485 통신 두절 %lums 초과 -> 전체 강제 OFF\n", commWatchdogTimeoutMs_);
      commWasOk_ = false;
    }
    if (relays_) relays_->forceAllOff();
  }
}

void SafetyManager::noteCommError() {
  faultBits_ |= (1 << FAULT_BIT_CRC_ERROR);
}
