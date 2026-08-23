#pragma once
// 권한(authority) 원칙의 실제 구현체 — docs/architecture.md의 핵심 규칙을 코드로 강제한다:
// "서버가 살아있는 동안은 서버가 유일한 판단 주체다."
//
// - MQTT가 연결되어 있는 한: 서버가 보내는 valve1/2/3 cmd를 그대로 RS485로 전달만 함
// - MQTT가 OFFLINE_CONFIRM_MS 이상 끊기면(확정 오프라인):
//     · 포깅(valve2)은 안전하게 CLOSE (습도조건 로직을 흉내낼 수 없으므로 과다침수 방지 우선)
//     · 구역A(valve1, 바이패스 중이면 valve3)는 데몬이 마지막으로 캐싱해준 단순 주기로
//       "항상 정지 상태부터" 로컬재생 시작 (정밀 위상복원 안 함 — open-decisions.md 2번)
// - 서버가 재연결되는 순간(다음 유효 cmd 수신 전에 mqttConnected==true가 되는 순간) 즉시
//   로컬재생을 중단하고 서버가 다시 전권을 가짐 — ctlr-heat-001 사고(이중권한) 재발 방지가
//   이 클래스 전체의 존재 이유다.

#include <Arduino.h>
#include "rs485_master.h"

class Controller {
public:
  void begin(Rs485Master* rs485);

  // 서버(MQTT)로부터 밸브 cmd를 받았을 때 호출 — 항상 서버 명령이 최우선.
  void onServerValveCmd(uint8_t coilIndex, bool on);

  // 데몬이 발행하는 valve1/localReplay 캐시가 갱신될 때 호출.
  void onLocalReplayUpdate(bool enabled, uint16_t sprayDurationSeconds, uint16_t stopDurationSeconds, bool bypassActive);

  // loop()에서 매번 호출 — MQTT 연결 상태를 감시해 확정 오프라인/재연결 전환을 처리.
  void update(bool mqttConnected);

  bool isInLocalReplay() const { return inLocalReplay_; }

private:
  Rs485Master* rs485_ = nullptr;

  bool wasMqttConnected_ = true;
  unsigned long disconnectedSinceMs_ = 0;
  bool confirmedOffline_ = false;

  bool inLocalReplay_ = false;
  bool replayEnabled_ = false;
  uint16_t spraySec_ = 0;
  uint16_t stopSec_ = 0;
  bool bypassActive_ = false;

  bool replayValveOpen_ = false;
  unsigned long replayPhaseStartMs_ = 0;

  void onConfirmedOffline_();
  void onReconnected_();
  void runLocalReplayCycle_();
  uint8_t replayTargetCoil_() const;
};
