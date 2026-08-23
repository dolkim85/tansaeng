#pragma once
// RS485 Modbus Master — 팔 노드(Relay-6CH)에 밸브 명령을 보내고, 실제 출력 상태를
// 읽어 명령 성공 여부를 확인한다("명령을 보냈다"와 "실제로 그렇게 됐다"를 구분,
// docs/architecture.md 참고). eModbus는 별도 태스크에서 통신하므로 여기서는
// 논블로킹 폴링 주기만 관리한다.
//
// [2026-08-23] 유량계가 팔 노드로 이전되며 IR 블록이 확장됨(shared/protocol_version.h
// PROTOCOL_VERSION=2). 이 클래스는 유량 데이터를 "계산"하지 않고 팔 노드가 계산한
// 값을 그대로 옮겨오기만 한다 — 메인 노드는 이중으로 누적하지 않는다(단일 기준
// 원장은 팔 노드). RS485가 끊기면 마지막 값을 그대로 유지하고 "정체됨"만 표시한다.

#include <Arduino.h>

class Rs485Master {
public:
  void begin(uint8_t slaveAddress);

  // loop()에서 매번 호출 — 정기 폴링(RS485_POLL_INTERVAL_MS)을 트리거.
  void update();

  // 밸브 명령(논블로킹) — 결과는 다음 폴링에서 getActualState()로 확인.
  void commandValve(uint8_t coilIndex, bool on);

  bool getActualState(uint8_t coilIndex) const; // 팔 노드가 마지막으로 보고한 실측 상태
  bool isNodeOnline() const;                     // 최근 폴링에 정상 응답했는지
  unsigned long msSinceLastGoodResponse() const; // stale 판단/표시용
  uint16_t faultBitmask() const;

  // 팔 노드 레지스터맵 버전이 메인이 기대하는 값과 같은지 — 다르면(특히 낮으면)
  // 유량 필드를 신뢰하지 않고 "미지원"으로 안전 처리한다.
  bool isFlowSupported() const;
  uint16_t remoteProtocolVersion() const { return remoteProtocolVersion_; }

  // 유량 — 팔 노드가 계산한 값을 그대로 노출(메인은 재계산/재누적하지 않음)
  uint32_t getFlowRateMlPerMin() const { return flowRateMlPerMin_; }
  uint32_t getFlowIntervalMl() const { return flowIntervalMl_; }
  uint64_t getFlowTotalMl() const { return flowTotalMl_; }
  uint32_t getFlowMsSincePulse() const { return flowMsSincePulse_; }
  uint16_t getFlowDiagFlags() const { return flowDiagFlags_; }
  uint16_t getFlowPulsesPerLiter() const { return flowPulsesPerLiter_; }
  uint32_t getFlowRawPulses() const { return flowRawPulses_; }

  // eModbus 콜백에서만 호출됨(내부용, public인 이유는 콜백이 멤버함수가 아니라서)
  void onPollSuccess(const uint16_t regs[]);
  void onCommError();

private:
  uint8_t slaveAddress_ = 1;
  unsigned long lastPollMs_ = 0;
  unsigned long lastGoodResponseMs_ = 0;
  uint16_t lastOutputBitmask_ = 0;
  uint16_t lastFaultBitmask_ = 0;
  bool online_ = false;

  uint16_t remoteProtocolVersion_ = 0;
  uint32_t lastRemoteUptimeSec_ = 0;
  bool sawFirstPoll_ = false;

  uint32_t flowRateMlPerMin_ = 0;
  uint32_t flowIntervalMl_ = 0;
  uint64_t flowTotalMl_ = 0;
  uint32_t flowMsSincePulse_ = 0;
  uint16_t flowDiagFlags_ = 0;
  uint16_t flowPulsesPerLiter_ = 0;
  uint32_t flowRawPulses_ = 0;
};
