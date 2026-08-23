// 유량계 펄스 시뮬레이터 — 다른(테스트용) ESP32에 올려서 메인 노드의 DI1 입력에
// 연결하면, 정해진 주파수로 사각파를 출력해 유량계 신호를 흉내낸다.
// 작업지시서 7번/12번 4단계(유량계 시험) 용도.
//
// 사용법:
// 1) 이 스케치를 테스트용 보드(메인 노드와 다른 아무 ESP32)에 업로드
// 2) PIN_PULSE_OUT(기본 GPIO2)을 메인 노드의 DI1(GPIO4)에 연결, GND도 공통으로 연결
// 3) 시리얼 모니터(115200bps)에 1, 10, 50, 100 중 숫자를 입력해 주파수(Hz) 변경
// 4) 메인 노드 시리얼 로그의 [DS18B20]... 아 잘못 — [FLOW1] rate/pulses 로그와 비교

const int PIN_PULSE_OUT = 2;

volatile float targetFrequencyHz = 1.0f;
unsigned long halfPeriodUs = 500000; // 1Hz 기본값 = 500ms on, 500ms off
unsigned long lastToggleUs = 0;
bool pinState = false;

void setFrequency(float hz) {
  if (hz <= 0) hz = 1.0f;
  targetFrequencyHz = hz;
  halfPeriodUs = (unsigned long)(1000000.0f / hz / 2.0f);
  Serial.printf("주파수 설정: %.1fHz (half period %luus)\n", hz, halfPeriodUs);
}

void setup() {
  Serial.begin(115200);
  delay(300);
  pinMode(PIN_PULSE_OUT, OUTPUT);
  digitalWrite(PIN_PULSE_OUT, LOW);
  setFrequency(1.0f);
  Serial.println("=== 유량계 펄스 시뮬레이터 ===");
  Serial.println("시리얼로 숫자(Hz)를 입력하면 주파수가 바뀝니다. 예: 10");
  Serial.println("YF-B10-S 테스트 권장값: 1, 10, 50, 100");
}

void loop() {
  if (Serial.available()) {
    String line = Serial.readStringUntil('\n');
    line.trim();
    float hz = line.toFloat();
    if (hz > 0) setFrequency(hz);
  }

  unsigned long now = micros();
  if (now - lastToggleUs >= halfPeriodUs) {
    lastToggleUs = now;
    pinState = !pinState;
    digitalWrite(PIN_PULSE_OUT, pinState ? HIGH : LOW);
  }
}
