# 하드웨어 핀맵 검증 근거

이 문서는 코드에 사용된 모든 GPIO 번호의 출처를 기록합니다. `www.waveshare.com`의 공식 위키/제품 페이지는 자동 스크래핑 도구의 접근을 막고 있어(HTTP 403) 직접 인용할 수 없었습니다. 대신 **서로 독립적인 복수의 신뢰 가능한 출처가 일치하는 값만** 채택했고, 출처가 하나뿐이거나 불확실한 값은 명시적으로 표시했습니다.

**실물 보드 입수 후 반드시 Waveshare 공식 위키(`waveshare.com/wiki/ESP32-S3-ETH-8DI-8RO`, `waveshare.com/wiki/ESP32-S3-Relay-6CH`)와 대조 확인해야 합니다.** 이 문서의 값만 믿고 고전압 배선을 확정하지 마십시오.

---

## 1. ESP32-S3-ETH-8DI-8RO (메인 노드)

### 제품 라인업 확인
검색 결과 Waveshare는 동일 폼팩터로 여러 변형을 판매합니다:
- `ESP32-S3-ETH-8DI-8RO` — **RS485 버전** (이 프로젝트에서 사용)
- `ESP32-S3-ETH-8DI-8RO-C` — **CAN 버전** (다름, 사용 안 함)
- `ESP32-S3-POE-ETH-8DI-8RO` — PoE 급전 지원 변형 (RS485 인터페이스는 동일 계열로 추정)

출처: [Waveshare Wiki: ESP32-S3-ETH-8DI-8RO](https://www.waveshare.com/wiki/ESP32-S3-ETH-8DI-8RO), [Waveshare Wiki: ESP32-S3-ETH-8DI-8RO-C](https://www.waveshare.com/wiki/ESP32-S3-ETH-8DI-8RO-C) — "-C는 CAN, 무-C는 RS485"라는 설명을 검색 스니펫에서 확인.

⚠️ **NEEDS_HARDWARE_TEST**: 사용자가 보유한 보드가 정확히 `-C`가 아닌 RS485 버전인지, 그리고 PoE 유무가 핀맵에 영향이 없는지 실물 라벨/실크스크린으로 확인 필요 (작업지시서 15번 항목).

### 핀맵 (교차 검증됨 — 3개 독립 출처 일치)

| 기능 | GPIO | 신뢰도 |
|---|---:|---|
| W5500 SPI CLK | GPIO15 | 높음 (3개 출처 일치) |
| W5500 SPI MOSI | GPIO13 | 높음 (3개 출처 일치) |
| W5500 SPI MISO | GPIO14 | 높음 (3개 출처 일치) |
| W5500 SPI CS | GPIO16 | 높음 (3개 출처 일치) |
| W5500 INT | GPIO12 | 높음 (3개 출처 일치) |
| W5500 RESET | GPIO39 | 높음 (4개 출처 일치) — 단, [BoardPins.h](https://github.com/abrinlee/ESP32-S3-POE-ETH-8DI-8RO-Python-Ethernet-WiFi-AP-Mode) 원저자 주석: "Not exposed on this board" — **실물 보드에서 이 핀을 GPIO로 직접 제어할 필요/방법이 없을 수 있음(자동 파워온리셋으로 추정). 펌웨어에서 이 핀을 건드리지 않고, W5500이 자동으로 리셋되는지 실물로 확인** |
| RS485 TX | GPIO17 | 높음 (2개 출처 일치) |
| RS485 RX | GPIO18 | 높음 (2개 출처 일치) |
| RS485 방향제어 | **하드웨어 자동 제어** (DE/RE GPIO 불필요) | 중간 (제품 페이지 명시: "Hardware automatic control") |
| I2C SDA (TCA9554용) | GPIO42 | 높음 (2개 출처 일치) |
| I2C SCL (TCA9554용) | GPIO41 | 높음 (2개 출처 일치) |
| TCA9554 I2C 주소 | 0x20 | 높음 (2개 출처 일치) |
| DI1~DI8 | GPIO4, 5, 6, 7, 8, 9, 10, 11 | 높음 (2개 출처 일치) — **직접 GPIO, I/O 확장기 아님** |
| DI 신호 특성 | 옵토아이솔레이션, **active-low**, INPUT_PULLUP 모드 | 중간 (GitHub 소스 1건) |
| RO1~RO8 (릴레이 출력) | TCA9554 I2C 확장기를 통함 (직접 GPIO 아님) | 높음 (2개 출처 일치) — **1차 버전에서 미사용, 예비로만 둠** |

출처:
- [ESPHome Devices: WAVESHARE ESP32-S3-ETH-8DI-8RO](https://devices.esphome.io/devices/waveshare-esp32-s3-eth-8di-8ro/) — 공식 ESPHome 통합, 커뮤니티 검증된 실사용 설정
- [GitHub: abrinlee/ESP32-S3-POE-ETH-8DI-8RO-Python-Ethernet-WiFi-AP-Mode](https://github.com/abrinlee/ESP32-S3-POE-ETH-8DI-8RO-Python-Ethernet-WiFi-AP-Mode) — **실제로 컴파일/동작하는 완전한 Arduino 스케치**(`BoardPins.h` + `.ino`), `Ethernet.h`(SPI W5500) + `PubSubClient` 사용 패턴까지 확인. PoE 변형이지만 저자가 non-PoE와 핀 차이를 언급하지 않음
- [Waveshare 제품 페이지](https://www.waveshare.com/esp32-s3-eth-8di-8ro.htm) / [OpenELAB 리셀러 페이지](https://openelab.io/products/waveshare-industrial-8-channel-esp32-s3-wifi-relay-eth-8di-8ro-module-rs485) — TCA9554PWR 탑재, RS485 하드웨어 자동방향, 7~36V 전원, USB-C 5V 보조전원 확인

**Ethernet 라이브러리**: 고전 Arduino `Ethernet.h`(W5100/W5500 계열 SPI 지원) 사용 확인. `Ethernet.init(CS_PIN)` 후 `SPI.begin(SCLK, MISO, MOSI, CS)`, `Ethernet.begin(mac)`(DHCP) 패턴.

### 유량계(DI1) 관련 중요 사항

- DI1(GPIO4)은 **직접 GPIO**이므로 `attachInterrupt()` 사용 가능 — YF-B10-S 최대 펄스 주파수(약 100Hz 이하)를 인터럽트로 놓칠 걱정은 낮음.
- **active-low** 특성이므로 기존 `ctlr-0004` 코드의 `RISING` 인터럽트를 그대로 쓰면 안 되고, 실제 배선/극성에 맞춰 `FALLING` 또는 `CHANGE`로 조정하고 **실물 벤치 테스트로 펄스 카운트가 맞는지 반드시 확인**해야 합니다 (NEEDS_HARDWARE_TEST — `test/flow_pulse_generator` 참고).
- 유량계 신호(기존 24V 라인)를 **DI1의 절연 입력**에 연결 — 기존 `ctlr-0004` 사고(GPIO 24V 직결 손상)처럼 ESP32 GPIO에 직접 24V를 물리면 안 됩니다. DI1은 옵토아이솔레이션이라 이 문제 자체가 구조적으로 방지되지만, 배선 시 극성/전압 규격은 실물 확인 필요.

---

## 2. ESP32-S3-Relay-6CH (팔 노드)

작업지시서에 이미 기재된 핀맵을 검증했습니다 — **완전히 일치**.

| 기능 | GPIO | 신뢰도 |
|---|---:|---|
| CH1 (메인밸브) | GPIO1 | 높음 |
| CH2 (포깅밸브) | GPIO2 | 높음 |
| CH3 (바이패스밸브) | GPIO41 | 높음 |
| CH4~CH6 (예비) | GPIO42, 45, 46 | 높음 |
| RS485 TX | GPIO17 | 높음 |
| RS485 RX | GPIO18 | 높음 |
| 부저 | GPIO21 | 높음 |
| RGB LED (WS2812) | GPIO38 | 높음 |
| 릴레이 활성 레벨 | **Active-High** (ESPHome 설정에 inversion 없음) | 높음 |

출처: [ESPHome Devices: WAVESHARE-6CH-RELAY](https://devices.esphome.io/devices/waveshare-6ch-relay/) — 작업지시서의 표와 GPIO 번호 완전 일치.

⚠️ GPIO45/GPIO46은 ESP32-S3의 **부트 스트래핑 핀**입니다 (ESPHome 설정에도 `ignore_strapping_warning` 명시). 이번 1차 버전에서는 CH5/CH6(예비)를 사용하지 않지만, 나중에 사용하게 되면 부팅 시 순간적으로 예상치 못한 레벨이 될 수 있으니 주의가 필요합니다. CH1~3(실제 사용 채널)은 스트래핑 핀이 아니라 안전합니다.

RS485 통신 관련: 기본 보드레이트 9600, **120Ω 종단저항이 온보드 점퍼로 내장**되어 있어 필요 시 활성화 가능 (작업지시서 5번 baud rate 선택에서 9600을 기본값으로 채택하는 근거).

출처: [검색 결과 종합](https://www.waveshare.com/wiki/ESP32-S3-Relay-6CH-RS485)

---

## 3. 확정 사항 요약

- Modbus RTU 방향 제어: **메인/팔 노드 모두 RS485 하드웨어 자동방향 제어** — 펌웨어에서 DE/RE GPIO 토글 코드 불필요.
- RS485 기본 통신: 9600 baud, 8N1 (작업지시서 5번 기본값 그대로 채택).
- 메인 노드 릴레이 출력(RO1~8)은 TCA9554 I2C 확장기 경유 — 1차 버전에서 미사용이므로 이번 펌웨어에 구현하지 않음(예비로 문서만 남김).
- 팔 노드 릴레이는 직접 GPIO, active-high.
