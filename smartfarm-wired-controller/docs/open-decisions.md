# 미확정 사항 및 결정 근거

## 1. 로컬 자동운전 범위 (확정됨 — 대화로 결정)

작업지시서 8번은 "완전한 로컬 AUTO 상태머신"을 요구했으나, 기존 서버 코드(`daemons/smartfarm_mist_daemon.cjs`)를 검색한 결과 **현재 `ctlr-0004`용 로컬 스케줄 설정 토픽은 존재하지 않고, 구역A/포깅 스케줄은 100% 서버(데몬)가 계산**하는 구조였습니다(기존 ESP32는 순수 명령 실행기).

또한 `ctlr-heat-001`(장치제어실)에서 ESP32 자체에 온보드 AUTO 판단 로직을 뒀다가, 서버가 보내는 명령을 무시하고 하드코딩 기준값으로 오작동한 실제 사고가 2026-08-18에 있었습니다(같은 코드베이스 내 실제 사례).

**결정**: 완전한 온보드 스케줄러는 만들지 않습니다. 대신:
- 메인 노드는 평상시 순수 명령 실행기(서버 cmd 그대로 실행)
- 구역A(valve1)만, 서버가 발행하는 최신 단순 스케줄을 캐싱해뒀다가 **확정 오프라인 상태에서만** 재생(`docs/mqtt-topics.md`의 `localReplay` 참고)
- 포깅(valve2)은 습도조건 로직이 있어 로컬재생 제외, 오프라인 시 안전 정지
- 서버 재연결 시 항상 서버 명령이 즉시 우선(로컬재생 즉각 중단)

이유: 관수 공백(작업지시서 요구)과 이중권한 오동작 위험(실제 사고 전례) 사이의 절충점.

## 2. 로컬재생 "재생 시작 지점" — 정밀 위상복원 안 함 (확정됨)

확정 오프라인 진입 시, 정지 상태였는지 분무 중이었는지 정밀하게 이어서 재생하려면 서버-ESP32 시각 동기화(NTP)와 상태전이 시각 추적이 필요해 복잡도가 크게 늘어납니다.

**결정 (1차 버전)**: 로컬재생 진입 시 **항상 정지(CLOSE) 상태부터 새로 사이클을 시작**합니다. 분무가 조금 늦게 재개될 뿐, 겹쳐 열리거나 과다 개방되는 사고 가능성이 없습니다. 정밀 위상복원은 필요성이 확인되면 2차 버전에서 검토합니다.

## 3. 확정 오프라인 판단 기준 시간

**결정 (1차 버전)**: 팔 노드 안전타임아웃(90초)과 겹치지 않도록 **3~5분**(정확한 값은 실물 테스트 후 확정, 기본 240초로 코드에 상수화하고 설정 가능하게 함). 짧은 순간 끊김에는 반응하지 않도록 함.

## 4. 메인 노드 릴레이 출력(RO1~8) 미사용

작업지시서 3.1번 지침대로 1차 버전에서는 구현하지 않고 예비로 둡니다. TCA9554 I2C 확장기 초기화는 하되(부팅 시 전부 OFF), 실제 제어 로직은 연결하지 않습니다.

## 5. NEEDS_HARDWARE_TEST / NEEDS_HARDWARE_CONFIRMATION 항목 (실물 없이는 확정 불가)

- `docs/hardware-verification.md`에 표시한 W5500 RESET 핀(GPIO39) — "Not exposed on this board"라는 원저자 주석의 정확한 의미(자동 파워온리셋으로 추정) 실물 확인
- 사용자 보드가 정확히 `ESP32-S3-ETH-8DI-8RO`(RS485, `-C` 아님)인지 실물 라벨 확인
- **[2026-08-23, GPIO 확정됨]** `FLOW_PULSE_PIN`(팔 노드 유량계 입력) = **GPIO4, 확장 헤더 H1 15번** — Waveshare 공식 회로도 PDF 넷리스트로 확정(`docs/hardware-verification.md` "2-1"절, 11번 항목). 남은 것은 GPIO 번호가 아니라 **H1 15번 핀의 물리적 방향(실크스크린 사진 대조)** 뿐 — 이 부분만 `NEEDS_HARDWARE_CONFIRMATION`으로 유지
- **[2026-08-23]** 유량계(YF-B10-S) 실제 공급전압/오픈컬렉터 극성/엣지방향 — 검색 근거는 확보했으나 이 현장의 실제 배선은 실측 필요. `docs/wiring.md` "유량계 배선"절 참고
- Relay-6CH 릴레이 활성화 레벨(active-high로 추정) 실물 확인
- RS485 A/B 극성 실제 배선 시 반전 여부(뒤바뀌어도 대개 무응답으로 나타나며 손상은 없음 — 테스트로 확인)

## 6. TLS 인증서 정책

작업지시서 10번은 최종 펌웨어에서 `setInsecure()` 미사용을 요구합니다. **1차 버전은 기존 시스템 전체(다른 모든 ESP32/데몬)와 동일하게 `setInsecure()`를 사용**하기로 잠정 결정했습니다 — 이유: 이 프로젝트의 다른 모든 구성요소가 이미 이 방식이라 여기만 CA 인증서 검증을 추가하면 일관성이 깨지고, NTP 시각동기화 실패 시 TLS 자체가 안 되는 새로운 장애 요인이 생깁니다. HiveMQ Cloud CA 고정은 컴파일 플래그(`USE_TLS_VERIFY`)로 분리해 나중에 전체 시스템을 함께 강화할 때 켤 수 있게 구조만 마련합니다.

## 7. MQTT rc=-2 근본원인 (확정됨 — 2026-08-23)

라이브러리 소스(eModbus 아님, SSLClient+고전 Ethernet.h)를 직접 추적해 확인:

```
PubSubClient::connect() → SSLClient::connect(domain,port)
  → start_ssl_client() → init_tcp_connection() → EthernetClient::connect(host,port)
    → DNSClient::getHostByName() 실패 시 별다른 에러 없이 그냥 0 반환
```

PC에서 8883 TCP 포트가 열려도(방화벽/서버는 문제없음), ESP32의 DNS 조회(UDP 53, `Ethernet.dnsServerIP()`가 가리키는 서버로 질의)가 실패하면 TLS 단계에 도달하기도 전에 조용히 rc=-2가 났던 것. 사용자가 시도한 `W5500_WORKAROUND`는 TLS handshake 재시도 루프에만 영향을 주는 매크로라 이 단계와는 무관했음(게다가 `_W5500_H_`가 이미 자동 정의되어 사실상 중복).

**수정**: DNS를 명시적으로 먼저 조회해 로그로 남기고, 이후 MQTT 연결은 해석된 IP로 직접(호스트명 재조회 없이) 시도. 상세: `firmware/main_eth_8di_8ro/mqtt_manager.h` 상단 주석, `docs/mqtt-topics.md` "MQTT 연결 진단 로그" 절.

**부가 수정**: MQTT 네트워크 clientId가 기존에 `CONTROLLER_ID`("ctlr-0004")를 그대로 썼던 버그도 발견해 수정 — 기존 WiFi ctlr-0004와 clientId가 겹쳐서 브로커가 한쪽을 끊어버릴 수 있는 잠재적 사고였음(전환/롤백 테스트 중 두 장치가 동시에 켜지는 상황에서 발현). MAC 기반 접미사로 분리.

## 8. 유량 누적값 마이그레이션 정책 (확정됨 — 2026-08-23)

이전 버전(메인 노드가 DI1로 직접 측정)에서 NVS에 저장하던 유량 누적값과, 새 팔 노드가 처음부터 새로 쌓는 누적값을 **자동으로 합산하지 않기로** 결정했습니다. 이유: 이전 값이 실제로 정확히 측정된 것인지 검증되지 않은 상태였고(하드웨어 확인 대기 중이었음), 자동 합산은 이중계산/오류 전파 위험이 더 큽니다.

대신 `firmware/arm_relay_6ch/config.h`에 `FLOW_TOTAL_ML_SEED`(mL)를 선택적으로 정의할 수 있게 했습니다 — **팔 노드가 정말 최초 부팅(NVS에 저장된 값이 없음)일 때 한 번만** 이 값에서 시작하고, 이후 부팅부터는 팔 노드 자체 누적값을 그대로 씁니다. 기본은 정의하지 않음(0에서 시작).

## 9. 유량 데이터 원자성 — seqlock 채택 (확정됨 — 2026-08-23)

팔 노드의 유량 계산(메인 `loop()` 컨텍스트)과 Modbus 응답 생성(eModbus 백그라운드 FreeRTOS 태스크)이 서로 다른 실행 컨텍스트에서 같은 데이터에 접근합니다. mutex/세마포어 대신 **seqlock**(짝수/홀수 시퀀스 카운터로 쓰기 중임을 표시, 읽는 쪽은 시작/끝 시퀀스가 같고 짝수일 때만 유효한 복사로 인정)을 채택했습니다 — 이유: 쓰는 쪽(유량 계산, 1초 주기)이 읽는 쪽(Modbus 폴링)을 블로킹하지 않아야 밸브 안전제어에 지연이 생기지 않고, ESP32 임베디드 환경에서 흔히 쓰이는 락프리 기법이라 구현/검증이 상대적으로 단순합니다. 상세: `firmware/arm_relay_6ch/flow_sensor.h/.cpp`, `docs/modbus-map.md` "원자성" 절.

## 10. PC817 절연 입력회로 저항값 오류 수정 (확정됨 — 2026-08-23)

최초 설계(9번 항목 이전 버전)는 센서 신호선에 별도 풀업 R1(10kΩ)을 두고, 그 뒤에 LED 전류제한 R2를 직렬로 추가한 구조였습니다. 이 구조는 두 가지 문제가 있었습니다:
1. 센서가 쉬고 있을 때(오픈컬렉터 OFF) 전류가 `Vs → R1 → R2 → LED → GND`로 흘러 R1+R2가 직렬이 되면서 LED 전류가 목표치(10mA)의 1/3~1/30 수준으로 떨어짐(예: 5V 조건에서 약 0.37mA — PC817이 신뢰성 있게 스위칭하기엔 너무 작음)
2. 센서가 펄스를 낼 때는 오히려 R1에서 전압강하가 대부분 발생해 LED가 꺼지는 방향이 되어, **의도한 것과 논리가 반대**로 동작(당시 `FLOW_PULSE_EDGE=FALLING` 기본값과 회로 실제 동작이 맞지 않음)

**수정**: R1을 제거하고 PC817 LED(+R2)가 오픈컬렉터의 풀업 부하 역할을 겸하도록 재설계했습니다(오픈컬렉터를 옵토커플러로 격리하는 표준 방식). 이제 평상시 LED OFF(`FLOW_PULSE_PIN`=HIGH), 펄스 시 LED ON(`FLOW_PULSE_PIN`=LOW)으로 `FLOW_PULSE_EDGE=FALLING` 기본값과 일치합니다. R2 저항값(5V=390Ω/12V=1kΩ/24V=2.2kΩ)은 그대로 유효하지만, 최소 권장 정격(1/4W~1W)을 새로 명시했습니다 — 특히 24V 조건은 1/4W 저항 사용 시 정격의 94%까지 차서 부적합, 1/2W 이상 필요. 상세 계산 근거: `docs/wiring.md` "권장 절연 입력회로" / "저항값(R2) 재계산" 절.

이 오류는 **문서/설계 단계에서만 존재**했고(아직 실물 배선 전), 실제 하드웨어에 손상을 준 사례는 없습니다.

## 11. `FLOW_PULSE_PIN` GPIO 확정 — Waveshare 공식 회로도 넷리스트 (확정됨 — 2026-08-23)

기존 조사(5번 항목)에서는 Waveshare 위키 페이지 스크래핑이 막혀(HTTP 403) Pico HAT 헤더의 실제 GPIO 매핑을 찾지 못해 `NEEDS_HARDWARE_CONFIRMATION`으로 남겨뒀습니다. 위키 페이지가 아닌 **회로도 PDF 직접 링크**(`files.waveshare.com/wiki/ESP32-S3-Relay-6CH/ESP32-S3-Relay-6CH.pdf`)는 스크래핑 차단 대상이 아니어서 접근 가능했고, PDF 내부 텍스트 레이어(넷리스트)에서 핀 연결 관계를 직접 추출했습니다.

**확인 결과**: 넷 `NLGPIO4`가 `PIH1015`(커넥터 H1의 15번 핀)와 `PIU404`(ESP32-S3-WROOM-1U 모듈의 4번 핀=IO4) 두 곳에만 연결되어 있고, 다른 어떤 부품/넷과도 공유되지 않습니다. 같은 회로도로 CH1~6(GPIO1/2/41/42/45/46), RS485(17/18), 부저(21), RGB(38), USB(19/20) 넷도 함께 확인해 GPIO4와 충돌이 없음을 재검증했고, 기존 `firmware/arm_relay_6ch/board_pins.h`의 핀맵과도 완전히 일치함을 확인했습니다(별도 출처로 이미 검증돼 있던 CH1~6 등의 신뢰도도 함께 재확인됨).

**결정**: `FLOW_PULSE_PIN`을 `GPIO4`(물리 위치: 확장 헤더 H1 15번)로 확정하고, `firmware/arm_relay_6ch/config.example.h`에 `#define FLOW_PULSE_PIN 4`로 반영했습니다. `docs/hardware-verification.md` "2-1"절에 넷리스트 원문 발췌와 8개 충돌 확인 항목을 표로 기록했습니다.

**남은 확인 사항**: 회로도는 전기적 연결만 증명하며, H1 15번 핀이 실물 보드에서 어느 물리적 위치인지는 실크스크린 라벨 사진으로 최종 대조해야 합니다(`docs/wiring.md` "팔 노드 GPIO 확정" 절). 이 대조가 끝나기 전까지는 실제 배선(통전)을 하지 않습니다 — 이 부분만 `NEEDS_HARDWARE_CONFIRMATION`으로 유지합니다.

## 12. protocol_version.h 사본 동기화 — Arduino IDE의 스케치 폴더 밖 include 제약 (확정됨 — 2026-08-23)

**문제**: `shared/protocol_version.h`를 `#include "../../shared/protocol_version.h"`로 참조하는 방식이 Linux `arduino-cli`에서는 컴파일됐지만, **Windows Arduino IDE에서 스케치를 단독으로 열어 컴파일하면 `fatal error: ... No such file or directory`로 실패**했습니다. 원인은 Arduino IDE가 스케치를 빌드할 때 컴파일러에 전달하는 include 검색 경로가 스케치 폴더(및 그 안의 파일들)를 기준으로 하고, 스케치 폴더 **밖**을 가리키는 `../../` 상대경로까지는 플랫폼/버전에 따라 안정적으로 해석하지 못할 수 있기 때문입니다(파일이 실제로 존재하고 경로가 맞아도 실패). `arduino-cli`에서 우연히 동작했던 것은 컴파일러 호출 방식의 차이 때문이며, Windows IDE에서의 실패가 이 구조의 근본적인 불안정성을 드러냈습니다.

**결정**: `shared/protocol_version.h`를 **기준 원장(canonical)** 으로 유지하되, `firmware/arm_relay_6ch/protocol_version.h`와 `firmware/main_eth_8di_8ro/protocol_version.h`에 **byte-for-byte 동일한 사본**을 각 스케치 폴더 안에 둡니다. 모든 `#include`는 스케치 폴더 내부만 가리키는 `#include "protocol_version.h"`로 통일했습니다 — 이는 Arduino 스케치가 자신의 tab(같은 폴더 파일)을 include하는 표준 방식이라 모든 플랫폼(Windows/Mac/Linux, IDE/arduino-cli 무관)에서 안정적으로 동작합니다.

**일관성 보장 방법**:
- `scripts/sync_protocol_version.sh` — 옵션 없이 실행하면 기준 원장을 두 사본에 복사, `--check` 옵션으로 실행하면 세 파일이 byte-for-byte 동일한지만 검사(다르면 실패 종료코드)
- `shared/protocol_version.h` 최상단에 "이 파일이 기준 원장이며, 사본은 직접 수정하지 말 것"이라는 배너 주석을 넣었고, 이 배너가 사본에도 그대로 복사되므로 **어느 파일을 열어도 기준 원장이 어디인지 알 수 있음**
- `shared/protocol_version.h`를 고칠 때마다 `scripts/sync_protocol_version.sh`를 실행해야 함(자동 실행되지 않음 — Arduino IDE 빌드 과정에 훅을 걸 방법이 없어 수동 규칙으로 둠). 잊었을 경우 `--check`가 다음에 실행될 때(예: 다음 세션 시작 시 습관적으로 실행) 바로 걸러짐

**런타임 버전 비교는 그대로 유지됨**: 매크로 이름(`PROTOCOL_VERSION`)과 값은 세 파일 모두 동일하므로, `rs485_master.cpp`가 매 RS485 폴링 주기마다 팔 노드가 보고하는 `IR_PROTOCOL_VERSION`과 메인 노드 자신의 `PROTOCOL_VERSION`을 비교하는 기존 로직(`Rs485Master::isFlowSupported()`)은 파일 구조 변경과 무관하게 동작합니다 — 코드 로직은 손대지 않았습니다.

이 사본들은 자동 생성 파일이지만 **git에 커밋되는 일반 소스 파일**입니다(Arduino IDE가 빌드 시점에 참조해야 하므로 `.gitignore` 대상이 아님). `shared/protocol_version.h`만 편집하고 사본 갱신을 잊는 실수를 막기 위해, 코드 수정 후에는 항상 `scripts/sync_protocol_version.sh`를 실행하는 것을 표준 절차로 합니다.
