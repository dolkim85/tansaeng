#pragma once
// ESP32-S3-Relay-6CH 공식 핀맵.
// 근거/출처: ../../docs/hardware-verification.md ("2. ESP32-S3-Relay-6CH" 항목,
// ESPHome 공식 기기DB와 작업지시서 표가 완전히 일치 — 신뢰도 높음)
//
// 이 핀 번호들을 다른 파일에 절대 중복 하드코딩하지 말 것. 전체 코드에서 이 파일만 include한다.

#define PIN_RELAY_CH1   1   // 메인밸브
#define PIN_RELAY_CH2   2   // 포깅밸브
#define PIN_RELAY_CH3   41  // 바이패스밸브
#define PIN_RELAY_CH4   42  // 예비
#define PIN_RELAY_CH5   45  // 예비 — ESP32-S3 부트 스트래핑 핀, 주의
#define PIN_RELAY_CH6   46  // 예비 — ESP32-S3 부트 스트래핑 핀, 주의

#define PIN_RS485_TX    17
#define PIN_RS485_RX    18

#define PIN_BUZZER      21  // 선택 사용(이번 버전 미사용)
#define PIN_RGB_LED     38  // 선택 사용(이번 버전 미사용)

// 릴레이 활성 레벨 — active-high로 확인됨(hardware-verification.md, ESPHome 설정에 inversion 없음)
#define RELAY_ACTIVE_LEVEL    HIGH
#define RELAY_INACTIVE_LEVEL  LOW
