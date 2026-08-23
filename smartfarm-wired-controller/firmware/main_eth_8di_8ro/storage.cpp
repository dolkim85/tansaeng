#include "storage.h"
#include <Preferences.h>

static Preferences prefs;

// 간단한 CRC32(표준 다항식) — 저장된 값이 전원차단/플래시손상으로 깨졌는지 검증하는 용도.
// 안전에 직결되지 않는 참고값(유량 누적)이라 복잡한 라이브러리 없이 자체 구현으로 충분하다.
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
  prefs.begin("wiredctrl", false);
}

double Storage::loadFlowTotal() {
  uint32_t version = prefs.getUInt("fw_ver", 0);
  double liters = prefs.getDouble("flow_total", 0.0);
  uint32_t storedCrc = prefs.getUInt("flow_crc", 0);

  if (version != STORAGE_VERSION) {
    Serial.println("[STORAGE] 유량 누적값 버전 불일치 — 0.0으로 시작");
    return 0.0;
  }

  uint32_t calcCrc = crc32((const uint8_t*)&liters, sizeof(liters));
  if (calcCrc != storedCrc) {
    Serial.println("[STORAGE] 유량 누적값 CRC 불일치(손상 의심) — 0.0으로 시작");
    return 0.0;
  }

  Serial.printf("[STORAGE] 유량 누적값 복원: %.3fL\n", liters);
  return liters;
}

void Storage::saveFlowTotal(double liters) {
  uint32_t crc = crc32((const uint8_t*)&liters, sizeof(liters));
  prefs.putUInt("fw_ver", STORAGE_VERSION);
  prefs.putDouble("flow_total", liters);
  prefs.putUInt("flow_crc", crc);
}
