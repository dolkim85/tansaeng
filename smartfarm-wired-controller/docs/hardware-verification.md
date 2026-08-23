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

### DI1~8 — 2026-08-23부로 유량계 용도 폐지, 전부 미사용(예비)

- 이전 버전에서는 DI1(GPIO4)을 유량계(YF-B10-S) 펄스 입력으로 썼으나, **밸브와 유량계가 현장에서 물리적으로 가까워 팔 노드(ESP32-S3-Relay-6CH)에서 직접 측정하는 구조로 이전**했습니다. 새 유량계 GPIO 확인 상황은 "2-1. ESP32-S3-Relay-6CH — 유량계 입력용 여유 GPIO" 절을 참고하세요(`NEEDS_HARDWARE_CONFIRMATION`).
- 메인 노드 DI1~8은 이번 버전에서 전부 미사용/예비이며, `firmware/main_eth_8di_8ro/board_pins.h`에도 그렇게 반영되어 있습니다. DI1이 옵토아이솔레이션·active-low라는 전기적 특성 자체는 유효하므로, 추후 다른 용도로 DI1~8을 쓰게 되면 이 표의 값(GPIO4~11, active-low, INPUT_PULLUP)을 그대로 참고할 수 있습니다.

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

## 2-1. ESP32-S3-Relay-6CH — 유량계 입력용 여유 GPIO (2026-08-23 조사)

**결론: `NEEDS_HARDWARE_CONFIRMATION`** — 근거 없이 임의의 GPIO를 확정하지 않았습니다.

조사 결과, 이 보드에는 **라즈베리파이 Pico HAT 호환 40핀 헤더가 내부에 2개 존재**하며 RTC/CAN/RS232/LoRa/센서 등 확장용으로 GPIO를 추가로 노출한다는 점은 3개 독립 출처에서 확인했습니다:
- [CNX Software: "6-channel ESP32-S3-based WiFi relay module... supports Raspberry Pi Pico HATs"](https://www.cnx-software.com/2024/04/02/6-channel-esp32-s3-wifi-relay-module-rs485-raspberry-pi-pico-hat/)
- [Waveshare 제품 페이지](https://www.waveshare.com/esp32-s3-relay-6ch.htm) — "Onboard RS485 / Pico HAT interfaces"
- [Spotpear 사용자 가이드](https://spotpear.com/wiki/ESP32-S3-WROOM-1U-N8-WIFI-RS485-Bluetooth-Industrial-6-Channel-Relay-IOT.html) — 동일 문구 확인, 모듈이 **ESP32-S3-WROOM-1U-N8(PSRAM 없음)**임을 확인(→ GPIO33~37이 PSRAM용으로 예약되어 있지 않을 가능성이 높다는 간접 근거는 있으나, 이 헤더에 실제로 어떤 GPIO가 배선되어 있는지의 **정확한 핀맵 표는 어느 출처에도 없었음**)

즉 "여유 GPIO를 노출하는 커넥터가 존재한다"는 확인했지만, **그 커넥터의 정확한 핀 배치(어느 헤더 위치가 어느 GPIO인지)는 찾지 못했습니다.** Waveshare 공식 위키(`waveshare.com/wiki/ESP32-S3-Relay-6CH`)가 스크래핑 차단(HTTP 403)으로 직접 확인이 안 됐고, 회로도 PDF도 접근하지 못했습니다.

**사용자가 확인해야 할 것:**
1. 보드 실물에서 "Pico HAT 호환 40핀 헤더" 2개의 실크스크린 라벨 사진(핀 번호/GPIO 번호가 보드에 인쇄되어 있을 가능성이 높음)
2. Waveshare 공식 위키의 회로도(schematic) PDF 다운로드 링크 — `waveshare.com/wiki/ESP32-S3-Relay-6CH` 페이지 하단 "Resource" 섹션에서 확인
3. (대안) 실물 보드에서 미사용 핀에 멀티미터로 직접 연속성 테스트를 하거나, 간단한 테스트 스케치로 각 후보 GPIO에 `pinMode(OUTPUT)` + 토글 후 오실로스코프/LED로 실제 그 핀이 헤더의 어느 위치에 나오는지 역추적

**소프트웨어 설계 방침**: 위 확인 전까지는 GPIO 번호를 하드코딩하지 않고 `config.h`의 `FLOW_PULSE_PIN` 설정값으로 분리했습니다(기본값 미정의 — 확인 후 사용자가 직접 채워 넣어야 컴파일되도록 `#error`로 막아둠). 후보로 참고할 만한(★ 미확정) 값: 이미 사용 중인 GPIO(1,2,17,18,21,38,41,42,45,46)와 부트/스트래핑(0,3,45,46은 이미 릴레이로 사용 중이라 제외), USB(19,20), UART0(43,44)를 제외하면 GPIO4~16, 33~37, 39, 40, 47, 48이 이론적으로는 비어있으나 — **이 중 실제로 Pico HAT 헤더에 물리적으로 나와 있는 핀은 사진/회로도 확인 전까지 알 수 없습니다.**

## 3. 확정 사항 요약

- Modbus RTU 방향 제어: **메인/팔 노드 모두 RS485 하드웨어 자동방향 제어** — 펌웨어에서 DE/RE GPIO 토글 코드 불필요.
- RS485 기본 통신: 9600 baud, 8N1 (작업지시서 5번 기본값 그대로 채택).
- 메인 노드 릴레이 출력(RO1~8)은 TCA9554 I2C 확장기 경유 — 1차 버전에서 미사용이므로 이번 펌웨어에 구현하지 않음(예비로 문서만 남김).
- 팔 노드 릴레이는 직접 GPIO, active-high.
