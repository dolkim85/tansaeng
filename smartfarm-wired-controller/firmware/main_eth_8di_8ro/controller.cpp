#include "controller.h"
#include "config.h"
#include "../../shared/protocol_version.h"

void Controller::begin(Rs485Master* rs485) {
  rs485_ = rs485;
  wasMqttConnected_ = true; // 부팅 직후엔 아직 판단할 근거가 없으므로 "연결됨"으로 가정해
                             // 곧바로 확정오프라인 카운트다운이 도는 것을 방지(연결 시도 시간을 줌)
  disconnectedSinceMs_ = 0;
}

void Controller::onServerValveCmd(uint8_t coilIndex, bool on) {
  // 서버 명령이 도착했다는 것 자체가 "서버가 살아있다"는 뜻이므로, 로컬재생 중이었다면
  // 여기서도 한번 더 방어적으로 중단한다(update()에서 이미 처리됐어야 하지만 이중 안전장치).
  if (inLocalReplay_) {
    Serial.println("[CTRL] 서버 명령 수신 -> 로컬재생 즉시 중단(서버 우선)");
    inLocalReplay_ = false;
  }
  if (rs485_) rs485_->commandValve(coilIndex, on);
}

void Controller::onLocalReplayUpdate(bool enabled, uint16_t sprayDurationSeconds, uint16_t stopDurationSeconds, bool bypassActive) {
  replayEnabled_ = enabled;
  spraySec_ = sprayDurationSeconds;
  stopSec_ = stopDurationSeconds;
  bypassActive_ = bypassActive;
  Serial.printf("[CTRL] 로컬재생 캐시 갱신: enabled=%d spray=%us stop=%us bypass=%d\n",
                enabled, sprayDurationSeconds, stopDurationSeconds, bypassActive);
}

uint8_t Controller::replayTargetCoil_() const {
  return bypassActive_ ? COIL_CH3_BYPASS_VALVE : COIL_CH1_MAIN_VALVE;
}

void Controller::update(bool mqttConnected) {
  if (mqttConnected) {
    if (!wasMqttConnected_) {
      onReconnected_();
    }
    disconnectedSinceMs_ = 0;
    confirmedOffline_ = false;
  } else {
    if (wasMqttConnected_) {
      // 지금 막 끊김 — 카운트다운 시작
      disconnectedSinceMs_ = millis();
    }
    if (!confirmedOffline_ && disconnectedSinceMs_ != 0 &&
        (millis() - disconnectedSinceMs_ >= OFFLINE_CONFIRM_MS)) {
      confirmedOffline_ = true;
      onConfirmedOffline_();
    }
    if (inLocalReplay_) {
      runLocalReplayCycle_();
    }
  }
  wasMqttConnected_ = mqttConnected;
}

void Controller::onConfirmedOffline_() {
  Serial.println("[CTRL] MQTT 확정 오프라인 — 포깅 안전정지, 구역A 로컬재생 검토");

  // 포깅(valve2)은 습도조건을 재현할 수 없으므로 항상 안전하게 정지 (open-decisions.md 1번)
  if (rs485_) rs485_->commandValve(COIL_CH2_FOGGING_VALVE, false);

  if (replayEnabled_) {
    inLocalReplay_ = true;
    replayValveOpen_ = false; // 항상 정지 상태부터 시작 (open-decisions.md 2번)
    replayPhaseStartMs_ = millis();
    if (rs485_) rs485_->commandValve(replayTargetCoil_(), false);
    Serial.printf("[CTRL] 구역A 로컬재생 시작 (대상: %s, 분무%us/정지%us)\n",
                  bypassActive_ ? "바이패스(valve3)" : "메인(valve1)", spraySec_, stopSec_);
  } else {
    inLocalReplay_ = false;
    Serial.println("[CTRL] 구역A 로컬재생 비활성 상태 — 관수 없이 대기");
  }
}

void Controller::onReconnected_() {
  if (inLocalReplay_) {
    Serial.println("[CTRL] MQTT 재연결 -> 로컬재생 중단, 서버 명령 대기");
  }
  inLocalReplay_ = false;
}

void Controller::runLocalReplayCycle_() {
  if (spraySec_ == 0 || stopSec_ == 0) return; // 유효하지 않은 캐시 — 안전하게 아무 것도 안 함

  unsigned long elapsedMs = millis() - replayPhaseStartMs_;
  uint8_t target = replayTargetCoil_();

  if (replayValveOpen_) {
    if (elapsedMs >= (unsigned long)spraySec_ * 1000UL) {
      replayValveOpen_ = false;
      replayPhaseStartMs_ = millis();
      if (rs485_) rs485_->commandValve(target, false);
    }
  } else {
    if (elapsedMs >= (unsigned long)stopSec_ * 1000UL) {
      replayValveOpen_ = true;
      replayPhaseStartMs_ = millis();
      if (rs485_) rs485_->commandValve(target, true);
    }
  }
}
