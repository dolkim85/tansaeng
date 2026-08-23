#pragma once
// ESP32-S3-ETH-8DI-8RO (RS485 버전, -C 아님) 공식 핀맵.
// 근거/출처: ../../docs/hardware-verification.md ("1. ESP32-S3-ETH-8DI-8RO" 항목,
// ESPHome 공식 기기DB + 실제 동작하는 GitHub 예제 BoardPins.h + 제품페이지, 4개 독립 출처 일치)
//
// 이 핀 번호들을 다른 파일에 절대 중복 하드코딩하지 말 것. 전체 코드에서 이 파일만 include한다.

// ── W5500 이더넷 (SPI) ────────────────────────────────────────────────────
#define PIN_W5500_CS    16
#define PIN_W5500_INT   12
#define PIN_W5500_RST   39  // ⚠️ 원 저자 주석: "Not exposed on this board" — 코드에서 이 핀을 GPIO로
                              // 직접 제어하지 않는다(자동 파워온리셋으로 추정). NEEDS_HARDWARE_TEST.
#define PIN_SPI_SCLK    15
#define PIN_SPI_MISO    14
#define PIN_SPI_MOSI    13

// ── RS485 (Modbus Master) ─────────────────────────────────────────────────
#define PIN_RS485_TX    17
#define PIN_RS485_RX    18
// 하드웨어 자동 방향제어 — DE/RE GPIO 설정 불필요 (hardware-verification.md)

// ── I2C (TCA9554 릴레이 확장기 — 1차 버전 미사용, 예비) ─────────────────────
#define PIN_I2C_SDA     42
#define PIN_I2C_SCL     41
#define TCA9554_I2C_ADDR 0x20

// ── 절연 디지털 입력 (DI1~DI8, 직접 GPIO, active-low, INPUT_PULLUP) ────────
// [2026-08-23] 유량계(YF-B10-S)는 밸브/유량계가 팔 노드와 현장에서 더 가깝다는
// 이유로 팔 노드 쪽으로 이전했다(firmware/arm_relay_6ch/flow_sensor.cpp,
// FLOW_PULSE_PIN — NEEDS_HARDWARE_CONFIRMATION). 메인 노드 DI1~8은 이번 버전에서
// 전부 미사용(예비)이다.
#define PIN_DI1  4
#define PIN_DI2  5
#define PIN_DI3  6
#define PIN_DI4  7
#define PIN_DI5  8
#define PIN_DI6  9
#define PIN_DI7  10
#define PIN_DI8  11
