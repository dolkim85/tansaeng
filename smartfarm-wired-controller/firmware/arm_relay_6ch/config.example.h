#pragma once
// 이 파일을 config.h로 복사해서 사용하세요 (config.h는 .gitignore에 등록되어 있습니다).
// 팔 노드는 WiFi/MQTT를 쓰지 않으므로 네트워크 인증정보가 필요 없습니다.

#include "protocol_version.h"

// 이 팔 노드의 RS485 슬레이브 주소. 추가 팔 노드를 붙일 경우 서로 다른 값을 사용하세요.
#define MY_SLAVE_ADDRESS   RS485_SLAVE_ADDR_RELAY1

// 채널별 안전 타임아웃(ms) — 필요하면 채널마다 다르게 설정 가능. 기본은 전부 90초.
#define CH1_SAFETY_TIMEOUT_MS  VALVE_SAFETY_TIMEOUT_MS_DEFAULT
#define CH2_SAFETY_TIMEOUT_MS  VALVE_SAFETY_TIMEOUT_MS_DEFAULT
#define CH3_SAFETY_TIMEOUT_MS  VALVE_SAFETY_TIMEOUT_MS_DEFAULT
#define CH4_SAFETY_TIMEOUT_MS  VALVE_SAFETY_TIMEOUT_MS_DEFAULT
#define CH5_SAFETY_TIMEOUT_MS  VALVE_SAFETY_TIMEOUT_MS_DEFAULT
#define CH6_SAFETY_TIMEOUT_MS  VALVE_SAFETY_TIMEOUT_MS_DEFAULT

// RS485 통신 워치독 — 마스터로부터 이 시간 동안 유효 프레임을 못 받으면 전 채널 강제 OFF
#define COMM_WATCHDOG_TIMEOUT_MS  RS485_COMM_TIMEOUT_MS

// ═══════════════════════════════════════════════════════════════════════════
// 유량계(YF-B10-S) — 2026-08-23 메인 노드 DI1에서 이곳으로 이전
// ═══════════════════════════════════════════════════════════════════════════
//
// FLOW_PULSE_PIN = GPIO4, 물리적 위치 = 확장 헤더 H1 15번 핀
// 근거: Waveshare 공식 ESP32-S3-Relay-6CH 회로도(PDF, files.waveshare.com/wiki/
// ESP32-S3-Relay-6CH/ESP32-S3-Relay-6CH.pdf) 넷리스트에서 직접 확인
// (net "GPIO4"가 U4(ESP32-S3-WROOM-1U) 4번 핀과 H1 15번 핀에만 연결되고,
// 다른 어떤 넷과도 공유되지 않음 — docs/hardware-verification.md "2-1"절에
// 넷리스트 원문 발췌와 함께 상세 근거 기록).
// CH1~6/RS485/부저/RGB/USB/부트스트랩/Flash·PSRAM 핀과 충돌하지 않음을 같은
// 회로도로 교차검증함. 유량계는 이 핀에 절대 직접 연결하지 않고, 반드시
// PC817 절연회로의 출력단(포토트랜지스터 쪽)만 연결한다(docs/wiring.md 참고).
//
// ⚠️ 남은 확인 사항: H1 15번 핀의 "물리적 방향"(보드 위 어느 위치가 1번 핀인지,
// 헤더가 실제로 실크스크린 라벨과 일치하는지)은 회로도만으로는 알 수 없으므로
// 실물 보드 실크스크린 사진으로 최종 대조가 필요합니다(NEEDS_HARDWARE_CONFIRMATION
// 은 "GPIO 번호 자체"가 아니라 이 물리적 대조 항목으로 좁혀짐 — docs/wiring.md 참고).
#define FLOW_PULSE_PIN   4

// 인터럽트 엣지 방향 — 유량계가 오픈컬렉터(active-low)라 보통 FALLING이지만,
// 실제 절연회로(포토커플러) 출력 극성에 따라 반전될 수 있으므로 실측 후 확정하세요
// (docs/wiring.md "유량계 배선" 참고).
#define FLOW_PULSE_EDGE   FALLING

// 최소 펄스 간격(글리치 필터, us) — 이보다 짧은 간격의 연속 엣지는 노이즈로 간주해
// 무시합니다. YF-B10-S 최대 주파수(~100Hz)면 최소 간격은 이론상 10ms(10000us)인데,
// 여유를 두고 절반 이하(2000us=2ms, 즉 이론상 최대 500Hz까지 허용)로 기본 설정 —
// 너무 크게 잡으면 실제 고유량 펄스를 걸러버리니 주의.
#define FLOW_MIN_PULSE_INTERVAL_US  2000UL

// 펄스/리터 보정계수 (기존 YF-B10-S 기준값)
#define FLOW_PULSES_PER_LITER  FLOW_PULSES_PER_LITER_DEFAULT

// 밸브 ON인데 이 시간 동안 펄스가 없으면 "무유량" 진단 플래그(FLOW_DIAG_BIT_NO_FLOW_TIMEOUT)
#define FLOW_NO_FLOW_TIMEOUT_MS  FLOW_NO_FLOW_TIMEOUT_MS_DEFAULT

// ── 유량 누적값 마이그레이션 (기존 메인 노드 DI1 측정값 이어받기, 선택) ──────
// 이전 버전(메인 노드가 DI1로 직접 측정하던 시절)의 NVS 누적값을 기억하고 있다면,
// 그 값을 여기 mL 단위로 채워 넣으세요. **팔 노드가 정말 "최초 부팅"(NVS에 저장된
// 값이 아직 없음)일 때 한 번만** 이 값에서 시작합니다 — 자동으로 두 누적값을
// 더하지 않고, 이미 팔 노드 자체 누적값이 있으면(두 번째 부팅부터) 이 값은 완전히
// 무시됩니다. 기본은 정의하지 않음(0에서 시작).
// #define FLOW_TOTAL_ML_SEED   12345
