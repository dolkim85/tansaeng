#pragma once
// NVS(비휘발성 저장소) — 유량 누적값(mL) 체크포인트 저장.
// 매 펄스마다 쓰지 않고 FLOW_TOTAL_SAVE_INTERVAL_MS(기본 5분)마다만 저장해 Flash
// 마모를 줄인다(작업지시서 요구사항). 정상 종료를 기대하지 않으므로, 손실 가능
// 범위는 "마지막 저장 이후 최대 5분치 사용량"으로 제한된다(문서화됨, docs/open-decisions.md).
// 버전/CRC 검증 후 사용, 손상되면 안전 기본값(0) 사용. 저장 실패는 로그만 남기고
// 릴레이 제어에는 전혀 영향을 주지 않는다(별도 태스크/블로킹 없음).

#include <Arduino.h>

class Storage {
public:
  void begin();
  uint64_t loadFlowTotalMl();
  void saveFlowTotalMl(uint64_t ml);

private:
  static const uint32_t STORAGE_VERSION = 1;
};
