#pragma once
// 메인 노드와 팔 노드 펌웨어가 공유하는 프로토콜 상수.
// 반드시 두 펌웨어 모두 이 파일을 include해서 사용할 것 — 레지스터 번호를
// 각자 따로 하드코딩하면 한쪽만 고치고 다른 쪽을 안 고치는 사고가 날 수 있음.
// 근거: docs/modbus-map.md

#define FIRMWARE_VERSION_MAJOR 1
#define FIRMWARE_VERSION_MINOR 0
#define FIRMWARE_VERSION_CODE  (FIRMWARE_VERSION_MAJOR * 100 + FIRMWARE_VERSION_MINOR)

// ── RS485 통신 설정 ──────────────────────────────────────────────────────────
#define RS485_BAUD_RATE            9600
#define RS485_SLAVE_ADDR_RELAY1    1     // 첫 번째 팔 노드(Relay-6CH). 추가 시 2, 3...으로 확장
#define RS485_RESPONSE_TIMEOUT_MS  200
#define RS485_MAX_RETRIES          3
#define RS485_POLL_INTERVAL_MS     500   // 정상 시 폴링 주기(요구사항: 1초 이내)
#define RS485_COMM_TIMEOUT_MS      10000 // 팔 노드가 이 시간 동안 마스터로부터 유효 프레임을 못 받으면 안전 OFF

// ── Coils (FC01/05/15) — 릴레이 채널 명령, 0-base offset ────────────────────
#define COIL_CH1_MAIN_VALVE     0
#define COIL_CH2_FOGGING_VALVE  1
#define COIL_CH3_BYPASS_VALVE   2
#define COIL_CH4_SPARE          3
#define COIL_CH5_SPARE          4
#define COIL_CH6_SPARE          5
#define COIL_COUNT              6

// ── Input Registers (FC04) — 팔 노드 상태 보고, 0-base offset ───────────────
#define IR_OUTPUT_BITMASK       0   // bit0~5 = CH1~CH6 실측 릴레이 상태
#define IR_FAULT_BITMASK        1   // 아래 FAULT_BIT_* 참고
#define IR_UPTIME_LOW           2   // 32bit uptime(초)의 하위 16bit
#define IR_UPTIME_HIGH          3   // 32bit uptime(초)의 상위 16bit
#define IR_FIRMWARE_VERSION     4
#define IR_LAST_CMD_SEQ         5   // HR_CMD_SEQUENCE를 그대로 echo (진단용)
#define IR_COUNT                6

#define FAULT_BIT_CH1_TIMEOUT   0
#define FAULT_BIT_CH2_TIMEOUT   1
#define FAULT_BIT_CH3_TIMEOUT   2
#define FAULT_BIT_CH4_TIMEOUT   3
#define FAULT_BIT_CH5_TIMEOUT   4
#define FAULT_BIT_CH6_TIMEOUT   5
#define FAULT_BIT_CRC_ERROR     8

// ── Holding Registers (FC03/06/16) — 메인→팔 진단용, 0-base offset ──────────
#define HR_CMD_SEQUENCE         0
#define HR_COUNT                1

// ── 밸브 안전 타임아웃 (팔 노드가 독립적으로 수행 — RS485 장애와 무관하게 동작) ──
#define VALVE_SAFETY_TIMEOUT_MS_DEFAULT  90000UL   // 90초, 기존 ctlr-0004와 동일 기본값

// ── 유량계 (기존 YF-B10-S 기준값 그대로 유지) ────────────────────────────────
#define FLOW_PULSES_PER_LITER    595.0f
#define FLOW_PUBLISH_INTERVAL_MS 1000UL

// ── 구역A 로컬재생 확정 오프라인 판단 기준 (docs/open-decisions.md 3번) ─────
#define OFFLINE_CONFIRM_MS_DEFAULT  240000UL  // 4분
