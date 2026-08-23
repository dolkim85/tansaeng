#include "flow_sensor.h"
#include "config.h"
#include "storage.h"
#include "protocol_version.h"

#ifndef FLOW_PULSE_PIN
#error "FLOW_PULSE_PIN이 config.h에 정의되지 않았습니다. docs/hardware-verification.md \
'2-1. ESP32-S3-Relay-6CH — 유량계 입력용 여유 GPIO' 절과 docs/wiring.md '유량계 배선' \
절을 먼저 읽고 Pico HAT 헤더의 실제 GPIO를 확인한 뒤 config.h에 FLOW_PULSE_PIN을 \
정의하세요. 확인 전에는 임의의 GPIO를 추측해서 넣지 마세요(NEEDS_HARDWARE_CONFIRMATION)."
#endif

static Storage g_storage;

// ISR은 카운터 증가 + 글리치필터 비교(정수 연산)만 수행 — Serial/Modbus/float/NVS 절대 금지.
static volatile uint32_t g_rawPulseCount = 0;
static volatile unsigned long g_lastPulseMicros = 0;

static void IRAM_ATTR flowISR() {
  unsigned long now = micros();
  // 최소 펄스 간격 글리치 필터 — 정수 뺄셈 비교만(micros() 오버플로에도 안전:
  // unsigned 뺄셈은 오버플로를 넘어도 올바른 경과시간을 준다).
  if ((unsigned long)(now - g_lastPulseMicros) < FLOW_MIN_PULSE_INTERVAL_US) return;
  g_lastPulseMicros = now;
  g_rawPulseCount = g_rawPulseCount + 1;  // volatile 변수의 ++ 연산자는 C++20에서 폐기(deprecated) 예정
}

void FlowSensor::begin(RelayController* relays) {
  relays_ = relays;

  g_storage.begin();
  totalMlAccumulator_ = g_storage.loadFlowTotalMl();

#ifdef FLOW_TOTAL_ML_SEED
  // "최초 부팅"(NVS에 저장된 값이 없어 0으로 복원됨)일 때만 시드값 적용.
  // 두 번째 부팅부터는 팔 노드 자체 누적값이 이미 있으므로 이 매크로는 무시된다
  // (자동 합산 금지 — docs/open-decisions.md 마이그레이션 정책).
  if (totalMlAccumulator_ == 0) {
    totalMlAccumulator_ = (uint64_t)(FLOW_TOTAL_ML_SEED);
    Serial.printf("[FLOW] 최초 부팅 감지 — 시드값 적용: %llumL\n", (unsigned long long)totalMlAccumulator_);
    g_storage.saveFlowTotalMl(totalMlAccumulator_); // 즉시 저장해 다음 부팅부터는 시드 재적용 안 됨
  }
#endif

  pinMode(FLOW_PULSE_PIN, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(FLOW_PULSE_PIN), flowISR, FLOW_PULSE_EDGE);

  unsigned long now = millis();
  lastCalcMs_ = now;
  lastPulseSeenMs_ = now; // 시작 시점엔 "방금 펄스가 있었다"고 가정 — 부팅 직후 즉시
                           // NO_FLOW_TIMEOUT 오탐 방지(밸브가 이미 켜진 채로 재부팅된 경우 대비)
  lastSaveMs_ = now;

  publishSnapshot_(0, 0, totalMlAccumulator_, 0, 0, FLOW_PULSES_PER_LITER, 0);

  Serial.printf("[FLOW] 초기화 완료 — 핀 GPIO%d, 엣지=%s, 펄스/L=%d, 복원누적=%llumL\n",
                FLOW_PULSE_PIN, (FLOW_PULSE_EDGE == FALLING) ? "FALLING" : "RISING/CHANGE",
                FLOW_PULSES_PER_LITER, (unsigned long long)totalMlAccumulator_);
}

void FlowSensor::publishSnapshot_(uint32_t rate, uint32_t interval, uint64_t total, uint32_t msSince,
                                   uint16_t diag, uint16_t ppl, uint32_t raw) {
  // seqlock: 쓰기 시작(홀수) -> 필드 갱신 -> 쓰기 종료(짝수). 읽는 쪽(getSnapshot)이
  // 시작/끝 시퀀스가 같고 짝수일 때만 유효한 복사로 인정한다.
  // volatile 변수에 ++ 대신 대입식을 쓰는 이유: C++20에서 volatile ++ 연산자가
  // 폐기(deprecated) 예정이라 --warnings all 컴파일 시 경고가 남는다(2026-08-23 확인).
  seq_ = seq_ + 1;
  sRateMlPerMin_ = rate;
  sIntervalMl_ = interval;
  sTotalMl_ = total;
  sMsSincePulse_ = msSince;
  sDiagFlags_ = diag;
  sPulsesPerLiter_ = ppl;
  sRawPulseCount_ = raw;
  seq_ = seq_ + 1;
}

void FlowSensor::getSnapshot(FlowSnapshot& out) const {
  uint16_t s1, s2;
  int guard = 0;
  do {
    s1 = seq_;
    if ((s1 & 1) == 0) {
      out.rateMlPerMin = sRateMlPerMin_;
      out.intervalMl = sIntervalMl_;
      out.totalMl = sTotalMl_;
      out.msSincePulse = sMsSincePulse_;
      out.diagFlags = sDiagFlags_;
      out.pulsesPerLiter = sPulsesPerLiter_;
      out.rawPulseCount = sRawPulseCount_;
    }
    s2 = seq_;
    guard++;
  } while ((s1 != s2 || (s1 & 1)) && guard < 100);
  // guard 한도(100회)를 넘기면(사실상 불가능한 경합) 마지막으로 읽은 값을 그대로
  // 사용한다 — 유량 스냅샷 하나 때문에 Modbus 응답 자체가 멈추면 안 되므로
  // "약간 오래된 값"을 반환하는 쪽이 "응답 없음"보다 안전하다.
}

void FlowSensor::update() {
  unsigned long now = millis();
  if (now - lastCalcMs_ < FLOW_CALC_INTERVAL_MS) return;
  unsigned long elapsedMs = now - lastCalcMs_;
  lastCalcMs_ = now;

  noInterrupts();
  uint32_t rawCount = g_rawPulseCount;
  interrupts();

  uint32_t deltaPulses = rawCount - lastRawSnapshotForDelta_; // unsigned 뺄셈 — 롤오버 안전
  lastRawSnapshotForDelta_ = rawCount;

  if (deltaPulses > 0) lastPulseSeenMs_ = now;
  uint32_t msSincePulse = now - lastPulseSeenMs_;

  // 순간 유량 — 기존 K factor 계산식 그대로: Frequency(Hz)=10*Q-5 -> Q(L/min)=(Freq+5)/10
  float elapsedSec = elapsedMs / 1000.0f;
  float freqHz = (elapsedSec > 0) ? (deltaPulses / elapsedSec) : 0;
  float qLpm = (deltaPulses == 0) ? 0.0f : (freqHz + 5.0f) / 10.0f;
  if (qLpm < 0) qLpm = 0;
  uint32_t rateMlPerMin = (uint32_t)(qLpm * 1000.0f + 0.5f);

  // 이번 tick 사용량(mL) — 정수 펄스/L 기준
  uint16_t ppl = FLOW_PULSES_PER_LITER;
  uint32_t intervalMlThisTick = (ppl > 0) ? (uint32_t)(((uint64_t)deltaPulses * 1000UL) / ppl) : 0;

  // 60초 롤링 윈도우
  bucketMl_[bucketIdx_] = intervalMlThisTick;
  bucketIdx_ = (bucketIdx_ + 1) % 60;
  uint32_t rollingMl = 0;
  for (uint8_t i = 0; i < 60; i++) rollingMl += bucketMl_[i];

  totalMlAccumulator_ += intervalMlThisTick;

  // 이상 감지 — 밸브 실측 상태 기준(명령이 아니라 RelayController의 실제 GPIO 상태)
  bool anyValveOn = relays_->getState(COIL_CH1_MAIN_VALVE) ||
                     relays_->getState(COIL_CH2_FOGGING_VALVE) ||
                     relays_->getState(COIL_CH3_BYPASS_VALVE);

  uint16_t diag = 0;
  if (!anyValveOn && deltaPulses > 0) {
    diag |= (1 << FLOW_DIAG_BIT_UNEXPECTED_FLOW);
    Serial.println("[FLOW] ⚠️ 밸브 전부 OFF인데 펄스 발생 — 누수/밸브 고장 의심");
  }
  if (anyValveOn && msSincePulse >= FLOW_NO_FLOW_TIMEOUT_MS) {
    diag |= (1 << FLOW_DIAG_BIT_NO_FLOW_TIMEOUT);
  }
  if (ppl == 0) {
    diag |= (1 << FLOW_DIAG_BIT_CONFIG_INVALID);
  }

  publishSnapshot_(rateMlPerMin, rollingMl, totalMlAccumulator_, msSincePulse, diag, ppl, rawCount);

  // NVS 체크포인트 저장 (5분 주기 — 매 tick 저장 금지, Flash 마모 방지).
  // 저장 실패해도 여기서 끝날 뿐 릴레이 제어 루프(arm_relay_6ch.ino)는 계속 돈다.
  if (now - lastSaveMs_ >= FLOW_TOTAL_SAVE_INTERVAL_MS) {
    lastSaveMs_ = now;
    g_storage.saveFlowTotalMl(totalMlAccumulator_);
  }
}
