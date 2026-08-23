#include "rs485_master.h"
#include "board_pins.h"
#include "../../shared/protocol_version.h"
#include <ModbusClientRTU.h>

// 하드웨어 자동 방향제어라 RTS/DE 핀 불필요(기본 생성자, rtsPin=-1) — hardware-verification.md
static ModbusClientRTU MB;
static Rs485Master* g_self = nullptr;

#define TOKEN_TYPE_MASK        0xFF000000UL
#define TOKEN_TYPE_POLL        0x01000000UL
#define TOKEN_TYPE_WRITE_COIL  0x02000000UL

static void onModbusData(ModbusMessage response, uint32_t token) {
  if (!g_self) return;
  uint32_t type = token & TOKEN_TYPE_MASK;

  if (type == TOKEN_TYPE_POLL) {
    uint16_t regs[IR_COUNT];
    uint16_t offset = 3; // serverID(1) + FC(1) + byteCount(1) 다음부터 데이터
    for (uint8_t i = 0; i < IR_COUNT; i++) {
      offset = response.get(offset, regs[i]);
    }
    g_self->onPollSuccess(regs);
  }
  // WRITE_COIL 응답은 별도 처리 불필요 — 다음 폴링에서 실측 상태로 성공여부가 자연히 드러남
  // (명령-확인 분리 원칙, docs/architecture.md)
}

static void onModbusError(Error error, uint32_t token) {
  if (!g_self) return;
  ModbusError me(error);
  Serial.printf("[RS485] 오류: %02X - %s\n", (int)me, (const char*)me);
  g_self->onCommError();
}

void Rs485Master::begin(uint8_t slaveAddress) {
  slaveAddress_ = slaveAddress;
  g_self = this;

  RTUutils::prepareHardwareSerial(Serial1);
  Serial1.begin(RS485_BAUD_RATE, SERIAL_8N1, PIN_RS485_RX, PIN_RS485_TX);

  MB.onDataHandler(&onModbusData);
  MB.onErrorHandler(&onModbusError);
  MB.setTimeout(RS485_RESPONSE_TIMEOUT_MS);
  MB.begin(Serial1);

  lastGoodResponseMs_ = millis(); // 시작 직후 바로 타임아웃 판정되지 않도록
}

void Rs485Master::update() {
  unsigned long now = millis();
  if (now - lastPollMs_ >= RS485_POLL_INTERVAL_MS) {
    lastPollMs_ = now;
    Error err = MB.addRequest(TOKEN_TYPE_POLL, slaveAddress_, READ_INPUT_REGISTER, 0, IR_COUNT);
    if (err != SUCCESS) {
      ModbusError me(err);
      Serial.printf("[RS485] 폴링 요청 생성 실패: %02X - %s\n", (int)me, (const char*)me);
    }
  }

  if (online_ && (now - lastGoodResponseMs_ > RS485_COMM_TIMEOUT_MS)) {
    online_ = false;
    Serial.println("[RS485] 팔 노드 응답 두절 — offline로 판단");
  }
}

void Rs485Master::commandValve(uint8_t coilIndex, bool on) {
  uint32_t token = TOKEN_TYPE_WRITE_COIL | coilIndex;
  Error err = MB.addRequest(token, slaveAddress_, WRITE_COIL, coilIndex, on ? 0xFF00 : 0x0000);
  if (err != SUCCESS) {
    ModbusError me(err);
    Serial.printf("[RS485] 밸브 명령 요청 생성 실패: %02X - %s\n", (int)me, (const char*)me);
  }
}

bool Rs485Master::getActualState(uint8_t coilIndex) const {
  return (lastOutputBitmask_ >> coilIndex) & 0x01;
}

bool Rs485Master::isNodeOnline() const {
  return online_;
}

uint16_t Rs485Master::faultBitmask() const {
  return lastFaultBitmask_;
}

void Rs485Master::onPollSuccess(const uint16_t regs[]) {
  lastOutputBitmask_ = regs[IR_OUTPUT_BITMASK];
  lastFaultBitmask_ = regs[IR_FAULT_BITMASK];
  lastGoodResponseMs_ = millis();
  if (!online_) {
    Serial.println("[RS485] 팔 노드 응답 복구됨");
    online_ = true;
  }
}

void Rs485Master::onCommError() {
  // 통신 상태(online_)는 lastGoodResponseMs_ 타임아웃 기준으로만 판정한다
  // (에러 1건으로 즉시 offline 처리하지 않음 — 일시적 잡음/충돌 재시도로 회복 가능하므로)
}
