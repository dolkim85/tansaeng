#pragma once
// 이 파일을 config.h로 복사해서 사용하세요 (config.h는 .gitignore에 등록되어 있습니다).
//
// ⚠️ 기존 코드(붙여넣은 코드.cpp)에 있던 실제 WiFi/MQTT 비밀번호는 평문 노출된
// 상태였습니다. 아래 값은 예시이며, 반드시 실제 값으로 교체하되 절대 이 저장소에
// (config.h 파일로도) 커밋하지 마세요. 가능하면 MQTT 비밀번호도 새로 발급받으세요.

#include "../../shared/protocol_version.h"

// ── MQTT (HiveMQ Cloud, 기존과 동일 — 메인 노드는 WiFi가 아니라 유선 이더넷으로 붙는다) ──
#define MQTT_HOST      "your-hivemq-host"
#define MQTT_PORT      8883
#define MQTT_USERNAME  "your-username"
#define MQTT_PASSWORD  "your-password"

// 기존 UI/서버와 동일한 컨트롤러 ID — 절대 바꾸지 말 것(바꾸면 UI에서 다른 장치로 인식됨)
#define CONTROLLER_ID  "ctlr-0004"

// ── TLS 정책 (docs/open-decisions.md 6번) ───────────────────────────────────
// 1차 버전은 기존 시스템 전체와 동일하게 setInsecure() 사용. CA 검증을 켜려면
// USE_TLS_VERIFY를 정의하고 hivemq_ca_cert.h에 실제 루트 인증서를 채워 넣을 것
// (이번 버전에서는 미사용 — 컴파일 플래그로 분리만 해둠).
// #define USE_TLS_VERIFY

// ── 이더넷 ───────────────────────────────────────────────────────────────
// DHCP 사용 시 true. 고정IP를 쓰려면 false로 바꾸고 아래 값을 채우세요.
#define ETH_USE_DHCP   true
#define ETH_STATIC_IP   192, 168, 0, 177
#define ETH_STATIC_DNS  192, 168, 0, 1
#define ETH_STATIC_GW   192, 168, 0, 1
#define ETH_STATIC_SUBNET 255, 255, 255, 0

// ── RS485 팔 노드 ────────────────────────────────────────────────────────
#define ARM_NODE_SLAVE_ADDRESS  RS485_SLAVE_ADDR_RELAY1

// ── 밸브별 안전 타임아웃 (메인 노드의 2중 안전 타이머 — 팔 노드 것과 별개로 동일값 유지) ──
#define VALVE1_SAFETY_TIMEOUT_MS  VALVE_SAFETY_TIMEOUT_MS_DEFAULT
#define VALVE2_SAFETY_TIMEOUT_MS  VALVE_SAFETY_TIMEOUT_MS_DEFAULT
#define VALVE3_SAFETY_TIMEOUT_MS  VALVE_SAFETY_TIMEOUT_MS_DEFAULT

// ── 구역A(valve1/valve3) 로컬재생 — 확정 오프라인 판단 기준 ────────────────
#define OFFLINE_CONFIRM_MS  OFFLINE_CONFIRM_MS_DEFAULT

// ── 유량계 DI1 인터럽트 엣지 (NEEDS_HARDWARE_TEST로 확정 전까지 FALLING 가정) ──
#define FLOW_PULSE_EDGE  FALLING
