#pragma once
// 유량계(YF-B10-S) 측정 — 2026-08-23 메인 노드 DI1에서 팔 노드로 이전.
//
// 설계 원칙(작업지시서 요구사항 반영):
// - ISR은 펄스 카운터 증가(+글리치 필터 비교, 정수 연산만)만 수행. Serial/Modbus/
//   float계산/NVS저장은 전부 update()(메인 loop 컨텍스트)에서 처리.
// - 계산된 값들은 seqlock(시퀀스락)으로 보호된 스냅샷으로 노출 — Modbus 워커가
//   다른 FreeRTOS 태스크(eModbus 백그라운드 태스크)에서 읽어도 찢어진 값이 없다.
// - millis()/micros() 오버플로에 안전한 뺄셈 기반 경과시간 계산만 사용.
// - 유량 측정 자체의 오류(설정 이상, 계산 실패 등)가 릴레이 안전제어(SafetyManager)를
//   절대 막지 않는다 — FlowSensor는 RelayController를 "읽기만" 하고 아무 것도
//   금지/차단하지 않는다(진단 플래그만 세움).

#include <Arduino.h>
#include "relay_controller.h"

struct FlowSnapshot {
  uint32_t rateMlPerMin = 0;
  uint32_t intervalMl = 0;     // 최근 60초 롤링 사용량
  uint64_t totalMl = 0;        // 누적 사용량(기준 원장)
  uint32_t msSincePulse = 0;
  uint16_t diagFlags = 0;
  uint16_t pulsesPerLiter = 0;
  uint32_t rawPulseCount = 0;  // 진단용(롤오버 가능)
};

class FlowSensor {
public:
  void begin(RelayController* relays);

  // loop()에서 매번 호출 — 내부적으로 FLOW_CALC_INTERVAL_MS(1초)마다만 실제 계산,
  // FLOW_TOTAL_SAVE_INTERVAL_MS(5분)마다만 NVS 저장(Flash 마모 방지).
  void update();

  // Modbus 워커 등 다른 컨텍스트에서 안전하게 호출 — seqlock으로 일관된 스냅샷 반환.
  void getSnapshot(FlowSnapshot& out) const;

  // 현재 시퀀스 값(단일 16bit 읽기라 그 자체로 원자적 — seqlock 재시도 불필요).
  // 메인 노드가 폴링마다 이 값이 계속 바뀌는지 보고 유량계산 태스크의 생존여부를 진단.
  uint16_t getSeq() const { return seq_; }

private:
  RelayController* relays_ = nullptr;

  // 계산용 내부 상태 (loop 컨텍스트에서만 접근 — 별도 동기화 불필요)
  unsigned long lastCalcMs_ = 0;
  unsigned long lastPulseSeenMs_ = 0;
  uint32_t lastRawSnapshotForDelta_ = 0;
  uint64_t totalMlAccumulator_ = 0;
  uint32_t bucketMl_[60] = {0}; // 60초 롤링 윈도우(1초 버킷)
  uint8_t bucketIdx_ = 0;
  unsigned long lastSaveMs_ = 0;

  // seqlock으로 보호된 공유 스냅샷 (loop가 쓰고, Modbus 워커 태스크가 읽음)
  volatile uint16_t seq_ = 0;
  volatile uint32_t sRateMlPerMin_ = 0;
  volatile uint32_t sIntervalMl_ = 0;
  volatile uint64_t sTotalMl_ = 0;
  volatile uint32_t sMsSincePulse_ = 0;
  volatile uint16_t sDiagFlags_ = 0;
  volatile uint16_t sPulsesPerLiter_ = 0;
  volatile uint32_t sRawPulseCount_ = 0;

  void publishSnapshot_(uint32_t rate, uint32_t interval, uint64_t total, uint32_t msSince,
                         uint16_t diag, uint16_t ppl, uint32_t raw);
};
