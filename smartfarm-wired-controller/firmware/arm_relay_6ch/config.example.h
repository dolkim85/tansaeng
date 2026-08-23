#pragma once
// 이 파일을 config.h로 복사해서 사용하세요 (config.h는 .gitignore에 등록되어 있습니다).
// 팔 노드는 WiFi/MQTT를 쓰지 않으므로 네트워크 인증정보가 필요 없습니다.

#include "../../shared/protocol_version.h"

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
