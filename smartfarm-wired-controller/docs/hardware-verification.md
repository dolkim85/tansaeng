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

## 2-1. ESP32-S3-Relay-6CH — 유량계 입력용 여유 GPIO (2026-08-23 조사, 2026-08-23 회로도로 확정)

**결론: `FLOW_PULSE_PIN = GPIO4` (확장 헤더 H1 15번 핀) — 공식 회로도로 확정.** 남은 것은 "GPIO 번호"가 아니라 **H1 15번 핀의 물리적 방향(실크스크린 대조)** 뿐입니다.

### 확정 근거 — Waveshare 공식 회로도 PDF 직접 확인

이전 조사(아래 "최초 조사 기록" 참고)에서는 위키 페이지 스크래핑이 막혀 회로도를 못 봤으나, 공식 회로도 PDF 직접 링크(`https://files.waveshare.com/wiki/ESP32-S3-Relay-6CH/ESP32-S3-Relay-6CH.pdf`)는 접근 가능했고, PDF 내부 텍스트 레이어(넷리스트)에서 넷 이름과 핀 번호가 그대로 추출됩니다. 아래는 실제 추출된 넷리스트 원문 발췌입니다(변형 없음):

```
PIH1015
PIU404 NLGPIO4
```

`PIH1015` = 커넥터 `H1`의 15번 핀(`H1 Header 20`), `PIU404` = `U4`(모듈 자체, 아래 확인) 4번 핀, `NLGPIO4` = 이 둘을 연결하는 넷 이름이 "GPIO4"라는 뜻입니다. `U4`가 `ESP32-S3-WROOM-1U` 모듈이라는 것은 같은 PDF의 모듈 핀 목록에서 확인됩니다:

```
GND 1  3V3 2  EN 3  IO4 4  IO5 5  ...
U4 ESP32-S3-WROOM-1U
```

즉 모듈의 4번 핀(`IO4`=GPIO4)이 그대로 `H1`의 15번 핀 하나에만 연결되어 있습니다. `NLGPIO4` 넷은 PDF 전체에서 이 두 핀(`PIH1015`, `PIU404`) 외에 다른 어떤 부품과도 연결되어 있지 않습니다 — 즉 CH1~6 릴레이, RS485, 부저, RGB, USB와 완전히 분리된 전용 헤더 핀입니다.

### 요청하신 8개 항목 재확인 (동일 PDF 넷리스트 기준)

| # | 확인 항목 | 결과 |
|---|---|---|
| 1 | GPIO4가 H1 15번 핀으로 노출되는가 | **예** — `PIH1015`/`PIU404`/`NLGPIO4` 넷으로 직접 확인 |
| 2 | CH1~CH6 릴레이 GPIO와 충돌하는가 | **충돌 없음** — 회로도 "Control IO" 블록: CH1=GPIO1, CH2=GPIO2, CH3=GPIO41, CH4=GPIO42, CH5=GPIO45, CH6=GPIO46 (기존 `board_pins.h`와 완전 일치, 이번 회로도로 재확인됨). `NLGPIO4` 넷에는 이 중 어느 것도 없음 |
| 3 | RS485 GPIO17/18과 충돌하는가 | **충돌 없음** — 넷리스트: `NLTXD1 NLGPIO17`(U7 74HC04D, U8 RS485 트랜시버 관련 핀에 연결), `NLRXD1 NLGPIO18` — GPIO4와 무관한 별도 넷 |
| 4 | 부저 GPIO21 / RGB GPIO38 / USB GPIO19,20과 충돌하는가 | **충돌 없음** — `NLGPIO21`(부저 트랜지스터 T1 R11 경유), `NLGPIO38`(RGB_CTRL, WS2812B LED4), `NLGPIO19`/`NLGPIO20`(USB 커넥터 J2의 D-/D+에 직결, `NLD0N`/`NLD0P`) — 전부 GPIO4와 다른 독립 넷 |
| 5 | ESP32-S3 부트 스트래핑 핀인가 | **아니오** — ESP32-S3 스트래핑 핀은 GPIO0/3/45/46뿐(이 보드에서 46은 CH6, 45는 CH5로 이미 사용 중이라 문서화됨). GPIO4는 스트래핑 핀이 아님 |
| 6 | Flash/PSRAM과 충돌하는가 | **충돌 없음** — 모듈은 회로도 상 `ESP32-S3-WROOM-1U`(PSRAM 없는 -N8 계열, 기존 조사와 일치), PSRAM용 GPIO33~37 대역과 GPIO4는 무관. 내장 Flash SPI 핀은 모듈 내부에만 있고 애초에 외부 핀으로 노출되지 않음 |
| 7 | 인터럽트/내부 풀업 사용 가능한가 | **가능** — ESP32-S3의 GPIO4는 입력 전용이 아닌 범용 디지털 GPIO로, `attachInterrupt()`와 `pinMode(INPUT_PULLUP)`을 모두 지원(고전 ESP32의 GPIO34~39류 입력전용 제약이 ESP32-S3에는 해당 없음). 팔 노드는 WiFi/BT를 쓰지 않으므로 ADC2 관련 제약도 무관 |
| 8 | 저장소 전체에서 GPIO4를 다른 용도로 이미 쓰고 있는가 | **아니오** — `firmware/arm_relay_6ch/board_pins.h` 전수 확인 결과 CH1~6(1,2,41,42,45,46)/RS485(17,18)/부저(21)/RGB(38) 중 GPIO4 없음. 메인 노드(`main_eth_8di_8ro`)가 과거 DI1=GPIO4를 썼던 것은 **완전히 다른 물리 보드(ESP32-S3-ETH-8DI-8RO)**라 충돌 대상이 아님 |

### 남은 확인 사항 — 물리적 방향(실크스크린 대조)

회로도 넷리스트는 "H1의 15번 핀이 전기적으로 GPIO4"라는 것만 증명하며, **그 15번 핀이 실물 보드 위에서 정확히 어느 위치(좌/우, 몇 번째 구멍)인지는 실크스크린 라벨로만 확정 가능**합니다. 배선 전 반드시:
1. H1 커넥터 옆 실크스크린에 인쇄된 핀 번호(대개 "1"과 홀수/짝수 방향 화살표 또는 사각 패드로 1번 핀 표시)를 사진으로 확인
2. 가능하면 멀티미터 연속성 테스트로 "실크스크린이 가리키는 15번 위치"와 "GPIO4"가 실제로 이어지는지 최종 검증(모듈 자체의 IO4 핀 또는 이미 알려진 다른 GPIO4 노출점이 있다면 그쪽과 비교)

이 문서/코드에서는 이제 GPIO 번호 자체는 확정값으로 다루되, 위 물리적 대조가 끝나기 전까지는 실제 배선(통전)을 하지 않습니다.

### 최초 조사 기록 (2026-08-23, 회로도 확보 전 — 참고용으로 보존)

당시 조사에서는 이 보드에 **라즈베리파이 Pico HAT 호환 40핀 헤더가 내부에 2개 존재**하며 확장용 GPIO를 노출한다는 점만 3개 독립 출처(간접 정보)로 확인했었습니다:
- [CNX Software: "6-channel ESP32-S3-based WiFi relay module... supports Raspberry Pi Pico HATs"](https://www.cnx-software.com/2024/04/02/6-channel-esp32-s3-wifi-relay-module-rs485-raspberry-pi-pico-hat/)
- [Waveshare 제품 페이지](https://www.waveshare.com/esp32-s3-relay-6ch.htm) — "Onboard RS485 / Pico HAT interfaces"
- [Spotpear 사용자 가이드](https://spotpear.com/wiki/ESP32-S3-WROOM-1U-N8-WIFI-RS485-Bluetooth-Industrial-6-Channel-Relay-IOT.html) — 모듈이 ESP32-S3-WROOM-1U-N8(PSRAM 없음)임을 간접 확인

당시엔 Waveshare 위키 페이지(`waveshare.com/wiki/...`) 스크래핑이 막혀(HTTP 403) 회로도 PDF 원문을 확인하지 못했으나, **PDF 직접 다운로드 링크는 스크래핑 차단 대상이 아니어서 이번에 정상적으로 확보·분석했습니다.**

## 3. 확정 사항 요약

- Modbus RTU 방향 제어: **메인/팔 노드 모두 RS485 하드웨어 자동방향 제어** — 펌웨어에서 DE/RE GPIO 토글 코드 불필요.
- RS485 기본 통신: 9600 baud, 8N1 (작업지시서 5번 기본값 그대로 채택).
- 메인 노드 릴레이 출력(RO1~8)은 TCA9554 I2C 확장기 경유 — 1차 버전에서 미사용이므로 이번 펌웨어에 구현하지 않음(예비로 문서만 남김).
- 팔 노드 릴레이는 직접 GPIO, active-high.
- 팔 노드 유량계 입력: `FLOW_PULSE_PIN = GPIO4`(확장 헤더 H1 15번), Waveshare 공식 회로도 넷리스트로 확정 — "2-1"절 참고. 물리적 방향(실크스크린 대조)만 배선 전 확인 필요.
