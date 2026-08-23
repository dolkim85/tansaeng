// ESP32-S3-Relay-6CH — 팔 노드 (RS485 Slave #1)
// WiFi/Bluetooth/MQTT를 전혀 쓰지 않는다. 인터넷이나 메인 노드가 죽어도
// 이 보드 혼자서 밸브 안전(90초 타임아웃, 통신 두절 시 전체 OFF)을 지킨다.
//
// 빌드 전: config.example.h를 config.h로 복사해서 사용하세요.

#include "config.h"
#include "board_pins.h"
#include "relay_controller.h"
#include "safety_manager.h"
#include "rs485_slave.h"

RelayController relays;
SafetyManager safety;
Rs485Slave rs485;

void setup() {
  Serial.begin(115200);
  delay(300);
  Serial.println("=== ESP32-S3-Relay-6CH 팔 노드 시작 ===");
  Serial.printf("펌웨어 버전: %d.%d\n", FIRMWARE_VERSION_MAJOR, FIRMWARE_VERSION_MINOR);

  // 가장 먼저 릴레이를 안전 상태(OFF)로 초기화 — 부팅 중 순간 ON 방지
  relays.begin();

  safety.begin(&relays);
  safety.setValveTimeoutMs(COIL_CH1_MAIN_VALVE, CH1_SAFETY_TIMEOUT_MS);
  safety.setValveTimeoutMs(COIL_CH2_FOGGING_VALVE, CH2_SAFETY_TIMEOUT_MS);
  safety.setValveTimeoutMs(COIL_CH3_BYPASS_VALVE, CH3_SAFETY_TIMEOUT_MS);
  safety.setValveTimeoutMs(COIL_CH4_SPARE, CH4_SAFETY_TIMEOUT_MS);
  safety.setValveTimeoutMs(COIL_CH5_SPARE, CH5_SAFETY_TIMEOUT_MS);
  safety.setValveTimeoutMs(COIL_CH6_SPARE, CH6_SAFETY_TIMEOUT_MS);
  safety.setCommWatchdogTimeoutMs(COMM_WATCHDOG_TIMEOUT_MS);

  rs485.begin(MY_SLAVE_ADDRESS, &relays, &safety);

  Serial.printf("RS485 슬레이브 주소: %d, 통신워치독: %lums\n", MY_SLAVE_ADDRESS, (unsigned long)COMM_WATCHDOG_TIMEOUT_MS);
  Serial.println("=== 초기화 완료 — 대기 중 ===");
}

void loop() {
  // eModbus ModbusServerRTU는 자체 FreeRTOS 태스크에서 통신을 처리하므로
  // 여기서는 안전 감시만 millis() 기반으로 주기적으로 수행하면 된다.
  // 블로킹 delay/while 없음 (작업지시서 11번 요구사항).
  safety.checkValveTimeouts();
  safety.checkCommWatchdog();

  // CPU를 100% 점유하지 않도록 짧게 양보 (블로킹 delay가 아니라 워치독 검사 주기 조절용)
  delay(50);
}
