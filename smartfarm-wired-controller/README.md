# 탄생농원 메인밸브 유선 제어시스템 (RS485 + Ethernet)

기존 WiFi ESP32(`ctlr-0004`)가 노이즈/끊김 문제를 겪던 메인밸브·포깅·바이패스·유량계 제어를, **유선 이더넷 + RS485** 2단 구조로 바꾸는 프로젝트입니다.

## ⚠️ 시작하기 전에 반드시 읽으세요

1. **인증정보 경고**: 기존 코드(`붙여넣은 코드.cpp`)에 있던 WiFi 비밀번호와 MQTT 비밀번호는 평문 노출된 상태입니다. 이 프로젝트를 실사용하기 전에 **반드시 새 비밀번호로 교체**하는 것을 권장합니다. 이 저장소에는 실제 인증정보를 커밋하지 않습니다(`firmware/*/config.h`는 `.gitignore`에 등록, `config.example.h`만 커밋).
2. **제품명 확인**: `ESP32-S3-ETH-8DI-8RO`(RS485)와 `ESP32-S3-ETH-8DI-8RO-C`(CAN, 다른 제품)를 혼동하지 마세요. 실물 보드 실크스크린과 구매 옵션을 확인하세요. `docs/hardware-verification.md` 참고.
3. **먼저 벤치 테스트, 나중에 실제 밸브**: 반드시 `docs/wiring.md`의 "현장 전환 및 롤백 절차"를 따라 단계적으로 진행하세요. 기존 WiFi ESP32를 버리지 말고 롤백용으로 남겨두세요.

## 문서 구성

| 문서 | 내용 |
|---|---|
| [`docs/architecture.md`](docs/architecture.md) | 전체 구조, 권한(authority) 원칙, 장애별 대응 |
| [`docs/hardware-verification.md`](docs/hardware-verification.md) | 핀맵 근거 및 출처(공식 위키 접근 불가로 교차검증한 내역) |
| [`docs/wiring.md`](docs/wiring.md) | 배선표, 점검표, **현장 전환/롤백 절차** |
| [`docs/modbus-map.md`](docs/modbus-map.md) | Modbus RTU 레지스터 맵, 라이브러리 선정 근거 |
| [`docs/mqtt-topics.md`](docs/mqtt-topics.md) | MQTT 토픽 정의(기존 호환 + 신규 진단/로컬재생) |
| [`docs/open-decisions.md`](docs/open-decisions.md) | 확정된 설계 결정과 근거, 실물 확인 필요 항목 |
| [`docs/test-procedure.md`](docs/test-procedure.md) | 단계별 시험 절차 및 체크리스트 |

## 폴더 구조

```text
smartfarm-wired-controller/
├─ README.md                     (이 파일)
├─ docs/                         (위 표 참고)
├─ firmware/
│  ├─ main_eth_8di_8ro/          메인 노드 (ESP32-S3-ETH-8DI-8RO)
│  └─ arm_relay_6ch/             팔 노드 (ESP32-S3-Relay-6CH)
├─ shared/                       두 펌웨어가 함께 쓰는 프로토콜 정의
└─ test/
   └─ flow_pulse_generator/      유량계 펄스 시뮬레이터(다른 ESP32로 실행)
```

## 필요한 라이브러리

| 라이브러리 | 버전 | 용도 | 설치 |
|---|---|---|---|
| eModbus | v1.7.4.stable | Modbus RTU (Master/Slave 논블로킹) | Arduino: Library Manager에서 "eModbus" 검색. PlatformIO: `lib_deps = eModbus/eModbus@^1.7.4` |
| Ethernet (고전 Arduino Ethernet 라이브러리) | Arduino IDE 기본 제공 `Ethernet.h`/`Dns.h` | W5500 SPI 이더넷 + DNS 조회(`DNSClient`, 별도 설치 불필요 — Ethernet 라이브러리에 포함) | Library Manager에 이미 포함되어 있는 경우가 많음(없으면 "Ethernet" by Arduino 검색) |
| SSLClient | govorox/SSLClient (GitHub `master` 기준, W5500 호환 커밋 포함된 버전) | `EthernetClient`에 TLS(BearSSL)를 씌워 MQTTS 가능하게 함 — ESP32+W5500 호환성 확인됨(changelog에 "Add workaround for W5500 Ethernet failing" 포함) | Arduino: Library Manager에서 "SSLClient" (govorox) 검색 — 없으면 GitHub에서 ZIP 다운로드 후 "라이브러리 추가(.ZIP)". PlatformIO: `lib_deps = https://github.com/govorox/SSLClient.git` |
| PubSubClient | 기존 프로젝트와 동일 버전 | MQTT | Library Manager |
| ArduinoJson | v7.4.x | `localReplay` JSON 페이로드 파싱 | Library Manager 또는 `lib_deps = bblanchon/ArduinoJson@^7.4.3` |
| AsyncTCP | v1.1.4 | eModbus가 TCP/비동기 변형(`ModbusClientTCPasync` 등)도 함께 컴파일하기 때문에 필요(RTU만 쓰지만 라이브러리 구조상 의존성이 걸림) — **실제 컴파일 검증 중 발견됨(2026-08-23)** | Library Manager에서 "AsyncTCP" (dvarrel 또는 ESP32Async 계열) 검색 |
| PCA9554/TCA9554 GPIO 확장 | 필요 시 검색 | 메인 노드 릴레이 확장(1차 버전 미사용, 초기화만 필요하면 나중에 추가) | 1차 버전 미포함 |

### ✅ 실제 컴파일 검증 완료 (2026-08-23)

`arduino-cli` + ESP32 core 3.3.11 + 위 라이브러리 조합으로 **메인/팔 노드 펌웨어 모두 실제로 컴파일 성공**을 확인했습니다(보드: `esp32:esp32:esp32s3`, "ESP32S3 Dev Module"). `--warnings all`로도 경고 0건입니다. 이 과정에서 `ethernet_manager.cpp`의 `esp_mac.h` include 누락 버그와, `board_pins.h`의 `PIN_RGB_LED` 매크로가 ESP32 코어 내장 매크로와 이름이 겹치던 문제(`PIN_WS2812_RGB`로 개명), `flow_sensor.cpp`의 `volatile` 변수 `++` 연산자 폐기예정(C++20) 경고 2건을 발견해 모두 수정했습니다. 상세 결과는 `docs/test-procedure.md` 참고.

## Arduino IDE로 업로드하는 법 (초보자용)

1. Arduino IDE 설치 → 파일 > 환경설정 > "추가 보드 관리자 URL"에 ESP32 보드 URL 추가(이미 다른 ESP32 펌웨어를 올려보셨다면 이미 되어있을 겁니다).
2. 툴 > 보드 매니저에서 "esp32" 검색 후 설치(이미 설치돼 있으면 생략).
3. 위 라이브러리들을 라이브러리 매니저에서 설치.
4. `firmware/main_eth_8di_8ro/config.example.h`를 복사해서 `config.h`로 이름 바꾸고, 실제 WiFi/MQTT 정보 대신 여기서는 **불필요**(메인 노드는 WiFi 안 씀) — MQTT 계정 정보만 입력.
5. `firmware/arm_relay_6ch/config.example.h`도 마찬가지로 `config.h`로 복사 (팔 노드는 RS485 슬레이브 주소만 설정하면 됨, 네트워크 정보 없음).
6. 툴 > 보드에서 "ESP32S3 Dev Module" 선택 (정확한 보드 패키지명은 실물 보드 설명서 참고).
7. USB로 연결 후 업로드.
8. 시리얼 모니터(115200bps)로 부팅 로그 확인.

## PlatformIO로 빌드하는 법 (선택)

각 `firmware/*/` 폴더에 `platformio.ini`를 두어 빌드할 수도 있습니다. eModbus + Ethernet 라이브러리를 함께 쓸 때 링커 문제를 피하려면 `lib_ldf_mode = deep+` 설정을 추가하세요.

## 현재 상태

이 프로젝트는 **로컬에서 작성 중**이며 아직 실물 하드웨어로 검증되지 않았습니다(`NEEDS_HARDWARE_TEST` 표시된 항목들 — `docs/open-decisions.md` 참고). 실제 서버/git에는 아직 반영하지 않았습니다.
