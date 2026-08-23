#pragma once
// ═══════════════════════════════════════════════════════════════════════════
// 기준 원장(canonical): smartfarm-wired-controller/shared/protocol_version.h
// ═══════════════════════════════════════════════════════════════════════════
// 이 파일은 firmware/arm_relay_6ch/protocol_version.h 와
// firmware/main_eth_8di_8ro/protocol_version.h 로 내용 그대로(byte-for-byte)
// 복사되어 각 스케치 폴더 안에도 존재합니다. Arduino IDE(특히 Windows)가
// 스케치 폴더 밖을 가리키는 "../../shared/..." 상대경로 include를 안정적으로
// 찾지 못하는 문제가 있어(2026-08-23 실제 컴파일 오류로 확인) 스케치 폴더
// 내부에 사본을 두는 방식으로 바꿨습니다.
//
// **지금 이 글을 firmware/arm_relay_6ch/protocol_version.h 나
// firmware/main_eth_8di_8ro/protocol_version.h 에서 읽고 있다면, 그 파일은
// 생성된 사본입니다 — 직접 수정하지 마세요.**
//
// 수정 규칙: 반드시 이 경로(shared/protocol_version.h)만 고친 뒤,
// scripts/sync_protocol_version.sh 를 실행해 두 스케치 사본에 동기화하세요.
// 같은 스크립트를 --check 옵션으로 실행하면 세 파일이 byte-for-byte 동일한지
// 검사합니다(다르면 0이 아닌 종료코드로 실패). 상세: docs/open-decisions.md
// "protocol_version.h 사본 동기화" 항목.
// ═══════════════════════════════════════════════════════════════════════════
//
// 메인 노드와 팔 노드 펌웨어가 공유하는 프로토콜 상수.
// 반드시 두 펌웨어 모두 이 파일을 include해서 사용할 것 — 레지스터 번호를
// 각자 따로 하드코딩하면 한쪽만 고치고 다른 쪽을 안 고치는 사고가 날 수 있음.
// 근거: docs/modbus-map.md
//
// [2026-08-23] 유량계 측정을 메인 노드 DI1 → 팔 노드로 이전하며 Input Register
// 맵을 확장. PROTOCOL_VERSION을 FIRMWARE_VERSION과 별도로 두어, 메인/팔 노드의
// 레지스터 맵 버전이 서로 다를 때(한쪽만 구버전 펌웨어) 감지할 수 있게 한다.

#define FIRMWARE_VERSION_MAJOR 1
#define FIRMWARE_VERSION_MINOR 1
#define FIRMWARE_VERSION_CODE  (FIRMWARE_VERSION_MAJOR * 100 + FIRMWARE_VERSION_MINOR)

// Input Register 맵 자체의 버전 — 레지스터 주소/필드가 바뀔 때만 올린다(펌웨어
// 자잘한 수정으로는 안 올림). 메인 노드는 이 값이 자신이 아는 값과 다르면
// "팔 노드 구버전 — 유량 기능 미지원"으로 안전하게 처리한다(docs/open-decisions.md).
#define PROTOCOL_VERSION 2  // v1: 릴레이/안전만. v2: 유량 레지스터 블록 추가(2026-08-23)

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
// 블록 1: 릴레이/시스템 상태 (v1부터, 변경 없음)
#define IR_OUTPUT_BITMASK       0   // bit0~5 = CH1~CH6 실측 릴레이 상태
#define IR_FAULT_BITMASK        1   // 아래 FAULT_BIT_* 참고
#define IR_UPTIME_LOW           2   // 32bit uptime(초)의 하위 16bit
#define IR_UPTIME_HIGH          3   // 32bit uptime(초)의 상위 16bit
#define IR_FIRMWARE_VERSION     4
#define IR_LAST_CMD_SEQ         5   // HR_CMD_SEQUENCE를 그대로 echo (진단용)
#define IR_PROTOCOL_VERSION     6   // PROTOCOL_VERSION — 메인이 자신의 값과 비교해 구버전 팔 노드 감지

// 블록 2: 유량계 (v2, 2026-08-23 추가) — 값 자체는 팔 노드에서 계산, 메인은 읽기만 함.
// 32/64bit 값은 워드 순서 = **빅엔디안(상위 워드 먼저)**로 통일. 예: 32bit는
// [high16][low16] 2워드, 64bit는 [word0=최상위]..[word3=최하위] 4워드.
#define IR_FLOW_SEQ              7   // 시퀀스락 패리티 카운터 — 홀수=팔노드가 쓰는 중(재시도), 짝수=안정
                                       // (동일 폴링 응답 안의 모든 레지스터가 eModbus 워커 1회 호출에서
                                       //  하나의 스냅샷으로부터 만들어지므로 응답 자체는 항상 일관되지만,
                                       //  팔 노드 자체 폴링 주기 대비 이 값이 멈춰있으면 유량계산 태스크
                                       //  이상으로 판단 가능)
#define IR_FLOW_RATE_MLPM_HI     8   // 순간 유량, mL/min, uint32 상위워드
#define IR_FLOW_RATE_MLPM_LO     9   //                        하위워드
#define IR_FLOW_INTERVAL_ML_HI   10  // 구간 사용량(최근 60초 롤링), mL, uint32 상위워드
#define IR_FLOW_INTERVAL_ML_LO   11
#define IR_FLOW_TOTAL_ML_W0      12  // 누적 사용량, mL, uint64 (최상위워드부터 w0..w3)
#define IR_FLOW_TOTAL_ML_W1      13
#define IR_FLOW_TOTAL_ML_W2      14
#define IR_FLOW_TOTAL_ML_W3      15  // 최하위워드
#define IR_FLOW_MS_SINCE_PULSE_HI 16 // 마지막 펄스 이후 경과시간(ms), uint32 상위워드
#define IR_FLOW_MS_SINCE_PULSE_LO 17
#define IR_FLOW_DIAG_FLAGS       18  // 아래 FLOW_DIAG_BIT_* 참고
#define IR_FLOW_PULSES_PER_LITER 19  // 설정된 보정계수(정수, 기본 595) — config.h에서 변경 가능
#define IR_FLOW_RAW_PULSES_HI    20  // 원시 누적 펄스카운트, uint32(진단용, 롤오버 가능 — 총사용량의
#define IR_FLOW_RAW_PULSES_LO    21  //   기준 원장은 IR_FLOW_TOTAL_ML_*이고 이건 참고값일 뿐)

#define IR_COUNT                 22

#define FAULT_BIT_CH1_TIMEOUT   0
#define FAULT_BIT_CH2_TIMEOUT   1
#define FAULT_BIT_CH3_TIMEOUT   2
#define FAULT_BIT_CH4_TIMEOUT   3
#define FAULT_BIT_CH5_TIMEOUT   4
#define FAULT_BIT_CH6_TIMEOUT   5
#define FAULT_BIT_CRC_ERROR     8

// 유량 진단 플래그 (IR_FLOW_DIAG_FLAGS)
#define FLOW_DIAG_BIT_UNEXPECTED_FLOW   0  // 밸브(CH1~3) 전부 OFF인데 펄스가 계속 발생 — 누수/밸브 고장 의심
#define FLOW_DIAG_BIT_NO_FLOW_TIMEOUT   1  // 밸브 ON인데 설정 시간 동안 펄스 없음 — 무유량(막힘/센서분리) 의심
#define FLOW_DIAG_BIT_SENSOR_FAULT      2  // 센서 신호선 이상 추정(장시간 완전 무신호 등, 진단용 참고치)
#define FLOW_DIAG_BIT_CONFIG_INVALID    3  // FLOW_PULSES_PER_LITER 등 설정값이 비정상(0 등)

// ── Holding Registers (FC03/06/16) — 메인→팔 진단용, 0-base offset ──────────
#define HR_CMD_SEQUENCE         0
#define HR_COUNT                1

// ── 밸브 안전 타임아웃 (팔 노드가 독립적으로 수행 — RS485 장애와 무관하게 동작) ──
#define VALVE_SAFETY_TIMEOUT_MS_DEFAULT  90000UL   // 90초, 기존 ctlr-0004와 동일 기본값

// ── 유량계 (기존 YF-B10-S 기준값 그대로 유지, 팔 노드에서 사용) ─────────────
#define FLOW_PULSES_PER_LITER_DEFAULT  595     // 정수 — 데이터시트 K factor와 일치, 스케일 불필요
#define FLOW_CALC_INTERVAL_MS          1000UL  // 순간유량/구간사용량 재계산 주기
#define FLOW_INTERVAL_WINDOW_MS        60000UL // "구간 사용량" 롤링 윈도우(최근 60초)
#define FLOW_NO_FLOW_TIMEOUT_MS_DEFAULT 30000UL // 밸브 ON인데 이 시간 동안 펄스 없으면 무유량 플래그
#define FLOW_TOTAL_SAVE_INTERVAL_MS    300000UL // NVS 체크포인트 저장 주기(5분 — Flash 마모 방지)

// ── 구역A 로컬재생 확정 오프라인 판단 기준 (docs/open-decisions.md 3번) ─────
#define OFFLINE_CONFIRM_MS_DEFAULT  240000UL  // 4분
