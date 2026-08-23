#include "storage.h"
#include <Preferences.h>

static Preferences prefs;

// 간단한 CRC32(표준 다항식) — main_eth_8di_8ro/storage.cpp와 동일한 방식.
static uint32_t crc32(const uint8_t* data, size_t len) {
  uint32_t crc = 0xFFFFFFFF;
  for (size_t i = 0; i < len; i++) {
    crc ^= data[i];
    for (int b = 0; b < 8; b++) {
      crc = (crc >> 1) ^ (0xEDB88320 & (-(int32_t)(crc & 1)));
    }
  }
  return ~crc;
}

void Storage::begin() {
  prefs.begin("armflow", false);
}

uint64_t Storage::loadFlowTotalMl() {
  uint32_t version = prefs.getUInt("ver", 0);
  uint64_t ml = prefs.getULong64("total_ml", 0);
  uint32_t storedCrc = prefs.getUInt("crc", 0);

  if (version != STORAGE_VERSION) {
    Serial.println("[STORAGE] 유량 누적값 버전 불일치 — 0으로 시작");
    return 0;
  }

  uint32_t calcCrc = crc32((const uint8_t*)&ml, sizeof(ml));
  if (calcCrc != storedCrc) {
    Serial.println("[STORAGE] 유량 누적값 CRC 불일치(손상 의심) — 0으로 시작");
    return 0;
  }

  Serial.printf("[STORAGE] 유량 누적값 복원: %llumL\n", (unsigned long long)ml);
  return ml;
}

void Storage::saveFlowTotalMl(uint64_t ml) {
  uint32_t crc = crc32((const uint8_t*)&ml, sizeof(ml));
  prefs.putUInt("ver", STORAGE_VERSION);
  prefs.putULong64("total_ml", ml);
  prefs.putUInt("crc", crc);
  // 저장 실패(Flash 오류 등)는 Preferences 내부에서 false를 반환할 뿐 예외를 던지지
  // 않으므로 여기서 별도 처리 없이도 릴레이 제어 루프는 절대 멈추지 않는다.
}
