#include "rs485_slave.h"
#include "board_pins.h"
#include "../../shared/protocol_version.h"
#include <ModbusServerRTU.h>
#include <vector>
using std::vector;

// eModbus 워커는 일반 함수 포인터로만 등록할 수 있어서, 인스턴스는 전역 포인터로 참조한다.
static ModbusServerRTU MBserver(RS485_RESPONSE_TIMEOUT_MS);
static RelayController* g_relays = nullptr;
static SafetyManager* g_safety = nullptr;
static uint16_t g_lastCmdSequence = 0; // HR_CMD_SEQUENCE에 마지막으로 쓰인 값 (순수 진단용)

// ── FC01: READ_COIL — 현재 릴레이 실측 상태를 비트로 반환 ────────────────────
static ModbusMessage handleReadCoil(ModbusMessage request) {
  ModbusMessage response;
  uint16_t start = 0, numCoils = 0;
  request.get(2, start, numCoils);

  if (numCoils == 0 || (start + numCoils) > COIL_COUNT) {
    response.setError(request.getServerID(), request.getFunctionCode(), ILLEGAL_DATA_ADDRESS);
  } else {
    uint8_t byteCount = (numCoils + 7) / 8;
    vector<uint8_t> coilBytes(byteCount, 0);
    uint8_t mask = g_relays ? g_relays->outputBitmask() : 0;
    for (uint16_t i = 0; i < numCoils; i++) {
      bool bitOn = (mask >> (start + i)) & 0x01;
      if (bitOn) coilBytes[i / 8] |= (1 << (i % 8));
    }
    response.add(request.getServerID(), request.getFunctionCode(), byteCount, coilBytes);
  }
  if (g_safety) g_safety->noteValidFrameReceived();
  return response;
}

// ── FC05: WRITE_COIL — 단일 릴레이 채널 ON/OFF ────────────────────────────────
static ModbusMessage handleWriteCoil(ModbusMessage request) {
  ModbusMessage response;
  uint16_t addr = 0, value = 0;
  request.get(2, addr, value);

  if (addr >= COIL_COUNT) {
    response.setError(request.getServerID(), request.getFunctionCode(), ILLEGAL_DATA_ADDRESS);
  } else if (value != 0x0000 && value != 0xFF00) {
    response.setError(request.getServerID(), request.getFunctionCode(), ILLEGAL_DATA_VALUE);
  } else {
    // setChannel()이 알아서 멱등 처리(이미 같은 상태면 무시, 타이머도 안 건드림)
    if (g_relays) g_relays->setChannel((uint8_t)addr, value == 0xFF00);
    response = ECHO_RESPONSE; // 표준 규격: 받은 요청을 그대로 되돌려줌
  }
  if (g_safety) g_safety->noteValidFrameReceived();
  return response;
}

// ── FC15(0x0F): WRITE_MULT_COILS — 여러 채널 동시 설정(재동기화용) ───────────
static ModbusMessage handleWriteMultCoils(ModbusMessage request) {
  ModbusMessage response;
  uint16_t start = 0, numCoils = 0;
  uint8_t numBytes = 0;
  uint16_t offset = 2;
  offset = request.get(offset, start, numCoils, numBytes);

  if (numCoils == 0 || (start + numCoils) > COIL_COUNT) {
    response.setError(request.getServerID(), request.getFunctionCode(), ILLEGAL_DATA_ADDRESS);
  } else if (numBytes != ((numCoils - 1) >> 3) + 1) {
    response.setError(request.getServerID(), request.getFunctionCode(), ILLEGAL_DATA_VALUE);
  } else {
    vector<uint8_t> coilBytes;
    request.get(offset, coilBytes, numBytes);
    for (uint16_t i = 0; i < numCoils; i++) {
      bool on = (coilBytes[i / 8] >> (i % 8)) & 0x01;
      if (g_relays) g_relays->setChannel((uint8_t)(start + i), on);
    }
    response.add(request.getServerID(), request.getFunctionCode(), start, numCoils);
  }
  if (g_safety) g_safety->noteValidFrameReceived();
  return response;
}

// ── FC04: READ_INPUT_REGISTER — 상태/진단 보고 ───────────────────────────────
static ModbusMessage handleReadInputRegister(ModbusMessage request) {
  ModbusMessage response;
  uint16_t start = 0, numRegs = 0;
  request.get(2, start, numRegs);

  if (numRegs == 0 || (start + numRegs) > IR_COUNT) {
    response.setError(request.getServerID(), request.getFunctionCode(), ILLEGAL_DATA_ADDRESS);
    if (g_safety) g_safety->noteValidFrameReceived();
    return response;
  }

  uint16_t regs[IR_COUNT];
  regs[IR_OUTPUT_BITMASK]   = g_relays ? g_relays->outputBitmask() : 0;
  regs[IR_FAULT_BITMASK]    = g_safety ? g_safety->faultBitmask() : 0;
  uint32_t uptimeSec        = millis() / 1000UL;
  regs[IR_UPTIME_LOW]       = (uint16_t)(uptimeSec & 0xFFFF);
  regs[IR_UPTIME_HIGH]      = (uint16_t)(uptimeSec >> 16);
  regs[IR_FIRMWARE_VERSION] = FIRMWARE_VERSION_CODE;
  regs[IR_LAST_CMD_SEQ]     = g_lastCmdSequence;

  response.add(request.getServerID(), request.getFunctionCode(), (uint8_t)(numRegs * 2));
  for (uint16_t i = start; i < start + numRegs; i++) {
    response.add(regs[i]);
  }
  if (g_safety) g_safety->noteValidFrameReceived();
  return response;
}

// ── FC03: READ_HOLD_REGISTER — 진단용(명령 시퀀스 조회) ─────────────────────
static ModbusMessage handleReadHoldingRegister(ModbusMessage request) {
  ModbusMessage response;
  uint16_t start = 0, numRegs = 0;
  request.get(2, start, numRegs);

  if (numRegs == 0 || (start + numRegs) > HR_COUNT) {
    response.setError(request.getServerID(), request.getFunctionCode(), ILLEGAL_DATA_ADDRESS);
  } else {
    response.add(request.getServerID(), request.getFunctionCode(), (uint8_t)(numRegs * 2));
    for (uint16_t i = start; i < start + numRegs; i++) {
      response.add(g_lastCmdSequence); // HR_COUNT==1이라 실제로는 HR_CMD_SEQUENCE 하나뿐
    }
  }
  if (g_safety) g_safety->noteValidFrameReceived();
  return response;
}

// ── FC06: WRITE_HOLD_REGISTER — 진단용(메인이 명령 시퀀스 번호를 씀) ─────────
static ModbusMessage handleWriteHoldingRegister(ModbusMessage request) {
  ModbusMessage response;
  uint16_t addr = 0, value = 0;
  request.get(2, addr, value);

  if (addr != HR_CMD_SEQUENCE) {
    response.setError(request.getServerID(), request.getFunctionCode(), ILLEGAL_DATA_ADDRESS);
  } else {
    g_lastCmdSequence = value; // 로직에는 영향 없음, 순수 진단/echo용
    response = ECHO_RESPONSE;
  }
  if (g_safety) g_safety->noteValidFrameReceived();
  return response;
}

void Rs485Slave::begin(uint8_t slaveAddress, RelayController* relays, SafetyManager* safety) {
  g_relays = relays;
  g_safety = safety;

  // RS485는 하드웨어 자동 방향제어라 DE/RE 핀 설정이 필요 없음(hardware-verification.md)
  RTUutils::prepareHardwareSerial(Serial1);
  Serial1.begin(RS485_BAUD_RATE, SERIAL_8N1, PIN_RS485_RX, PIN_RS485_TX);

  MBserver.registerWorker(slaveAddress, READ_COIL, &handleReadCoil);
  MBserver.registerWorker(slaveAddress, WRITE_COIL, &handleWriteCoil);
  MBserver.registerWorker(slaveAddress, WRITE_MULT_COILS, &handleWriteMultCoils);
  MBserver.registerWorker(slaveAddress, READ_INPUT_REGISTER, &handleReadInputRegister);
  MBserver.registerWorker(slaveAddress, READ_HOLD_REGISTER, &handleReadHoldingRegister);
  MBserver.registerWorker(slaveAddress, WRITE_HOLD_REGISTER, &handleWriteHoldingRegister);

  MBserver.begin(Serial1);
}
