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

PC에서 8883 TCP 포트가 열려도(방화벽/서버는 문제없음), ESP32의 DNS 조회(UDP 53, `Ethernet.dnsServerIP()`가 가리키는 서버로 질의)가 실패하면 TLS 단계에 도달하기도 전에 조용히 rc=-2가 났던 것. 사용자가 시도한 `W5500_WORKAROUND`는 TLS handshake 재시도 루프에만 영향을 주는 매크로라 이 단계와는 무관하다고 판단했었음.

> ⚠️ **[2026-08-23 정정]** 위 "`_W5500_H_`가 이미 자동 정의되어 사실상 중복"이라는 문장은 **틀린 추측**이었습니다. 실제로는 `_W5500_H_`도 `W5500_WORKAROUND`도 어디에서도 정의되지 않아, W5500 handshake 재시도 우회 코드 자체가 한 번도 컴파일된 적이 없었습니다. `arduino-cli --verbose`로 실제 컴파일 커맨드라인을 확인해 반증했습니다 — 상세는 "15. W5500_WORKAROUND 미적용 확정 및 로컬 vendoring 수정" 항목 참고.

**수정**: DNS를 명시적으로 먼저 조회해 로그로 남기고, 이후 MQTT 연결은 해석된 IP로 직접(호스트명 재조회 없이) 시도. 상세: `firmware/main_eth_8di_8ro/mqtt_manager.h` 상단 주석, `docs/mqtt-topics.md` "MQTT 연결 진단 로그" 절.

**부가 수정**: MQTT 네트워크 clientId가 기존에 `CONTROLLER_ID`("ctlr-0004")를 그대로 썼던 버그도 발견해 수정 — 기존 WiFi ctlr-0004와 clientId가 겹쳐서 브로커가 한쪽을 끊어버릴 수 있는 잠재적 사고였음(전환/롤백 테스트 중 두 장치가 동시에 켜지는 상황에서 발현). MAC 기반 접미사로 분리.

> ⚠️ **[2026-08-23] 이 진단은 불완전했음이 현장에서 드러남 — "13. MQTT rc=-2 2차 진단" 항목 참고.** 위 수정(DNS 사전조회 + IP로 직접 연결)을 실제 현장에 올려본 결과 DNS/TCP는 정상인데도 rc=-2가 계속 재현됐고, 진짜 원인은 "IP를 SSLClient에 직접 넘기면 TLS SNI가 깨진다"는 것이었다.

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

## 13. MQTT rc=-2 2차 진단 — IP 직접 연결이 TLS SNI를 깨뜨림 (확정됨 — 2026-08-23)

7번 항목의 1차 수정(DNS 사전조회 + 해석된 IP로 직접 연결)을 실제 메인 노드에 올려 확인한 결과, 현장 로그상 `[DNS] MQTT host resolved: ... -> IP`와 `[TCP] IP:8883 connected`까지는 정상이었는데도 `[MQTT] CONNECT rejected: rc=-2`가 계속 반복됐습니다. 즉 DNS/TCP는 실제로 문제가 아니었고, 1차 진단은 **불완전**했습니다.

**근본원인**: govorox/SSLClient 1.3.2 소스를 직접 추적:

```
SSLClient::connect(IPAddress ip, port)
  → connect(ip.toString().c_str(), port, ...)          // IP를 문자열로 변환해 "host"로 사용
    → ssl__client.cpp: start_ssl_client(..., host, ...)
        Step1 init_tcp_connection(ssl_client, host, port)     // TCP 연결에 host 사용
        Step5 set_hostname_for_tls(ssl_client, host)          // ★ 같은 host를 TLS SNI로도 사용
            → mbedtls_ssl_set_hostname(&ssl_ctx, host)
```

해석된 IP를 `PubSubClient::setServer(IPAddress, port)`로 넘기면, 이 IP 문자열이 TLS ClientHello의 SNI(Server Name Indication)로 그대로 전송됩니다. HiveMQ Cloud는 여러 클러스터가 같은 로드밸런서/포트를 공유하는 SNI 기반 TLS 종단이라, SNI가 실제 호스트명이 아니라 IP 문자열이면 handshake 자체가 깊은 단계(mbedTLS 내부)에서 실패합니다. `setInsecure()`로 인증서 검증을 꺼도 SNI 전송 자체는 영향받지 않으므로 이 문제와 무관합니다.

**부가 확인**: `start_ssl_client()`는 실패 원인이 무엇이든 최종 반환값을 항상 정확히 `0`으로 뭉갭니다(`ssl__client.cpp`: 성공이 아니면 `handle_error(ret)` 호출 후 무조건 `return 0`). 그래서 `SSLClient::lastError()`도 실패 시 대부분 `0`을 반환합니다 — **`0`을 "에러 없음"으로 오해하면 안 됩니다.** 또한 `PubSubClient::connect()` 소스 확인 결과, 하위 전송(`_client->connect(...)`)이 1이 아니면 MQTT CONNECT 패킷 자체를 보내지 않고 곧바로 `_state=MQTT_CONNECT_FAILED(-2)`로 설정합니다 — 즉 rc=-2는 "브로커가 CONNECT를 거절"한 게 아니라 "CONNECT를 보내지도 못했다"는 뜻입니다. 브로커가 실제로 CONNACK을 보내고 거절한 경우에만 상태값이 1~5가 됩니다.

**수정**: `resolveHost_()`(DNS 사전조회)와 `tcpPreTest_()`(순수 TCP 도달성 확인, 실제 SSLClient와 별도의 임시 `EthernetClient` 사용)는 **진단 목적으로만** 유지합니다. **실제 TLS/MQTT 연결은 반드시 원래 호스트명 문자열로** 시도합니다(`mqttClient_.setServer(host_, port_)` — `PubSubClient::setServer(const char*, port)` 오버로드, 내부적으로 `_client->connect(domain, port)` 경로를 타 SNI가 유지됨). 이 경로는 `EthernetClient`가 내부적으로 DNS를 한 번 더 조회하게 되지만(7번 항목에서 다뤘던 그 경로), 현장에서 이미 DNS가 정상 동작함을 확인했으므로 이 비용을 감수하는 쪽이 안전합니다. 상세: `firmware/main_eth_8di_8ro/mqtt_manager.h`/`.cpp`, `docs/mqtt-topics.md` "MQTT 연결 진단 로그" 절.

**로그 분류도 함께 수정**: `[MQTT] CONNECT rejected: rc=N`은 브로커가 실제로 응답한 경우(상태값 1~5)에만 출력하고, 그 외(-1~-4)는 `[TLS] handshake/secure transport failed` + `[MQTT] CONNECT not sent`로 구분합니다. 실패 시 `sslClient_.lastError()`도 `[TLS] lastError code=N, detail=...`로 출력하되, `code=0`이 "성공"을 뜻하지 않는다는 것을 로그 문구에도 명시합니다.

**블로킹 시간 재검토**: 현장 로그상 연결 시도 1회에 약 2.3초가 걸렸습니다 — 이전 보고의 "완전 논블로킹"은 부정확했습니다(실제로는 "재시도 간격만 논블로킹"). `sslClient_.setHandshakeTimeout()`을 8초→5초, `mqttClient_.setSocketTimeout()`을 10초→5초로 낮춰 최악의 경우에도 팔 노드의 `RS485_COMM_TIMEOUT_MS`(10초)에 뚜렷한 여유를 두도록 했습니다. eModbus는 별도 FreeRTOS 태스크에서 RS485 UART 송수신을 하므로 `loop()`가 블로킹되는 동안에도 이미 큐에 들어간 요청은 계속 처리되고, `rs485.update()`의 폴링 트리거는 `millis()` 기반이라 지연 후 즉시 따라잡습니다 — 다만 이번에 타임아웃을 줄여 최악의 시나리오에서도 안전 마진을 명시적으로 확보했습니다. 연결 시도마다 소요시간을 로그로 남겨(`[MQTT] 연결 시도 소요시간: Nms` / 3초 이상이면 경고) 향후 실측으로 계속 확인할 수 있게 했습니다.

**재시도 빈도 제한**: 실패가 반복되면 재연결 간격을 5초→최대 60초까지 지수적으로 늘리는 제한된 backoff를 적용했습니다(성공 시 5초로 리셋) — 브로커를 무제한 5초 간격으로 계속 두드리지 않도록.

## 14. MQTT rc=-2 3차 현장 재현 — SNI 수정 이후에도 handshake 실패 지속, 원인 미확정 (진행 중 — 2026-08-23)

13번 항목의 SNI 수정(호스트명 문자열로 연결)을 실제 메인 노드에 올려 재현한 결과:

```
[DNS] MQTT host resolved: ...hivemq.cloud -> 46.137.47.218
[TCP] 46.137.47.218:8883 connected
[MQTT] 연결 시도 소요시간: 2748ms
[TLS] lastError code=0, detail=(상세 없음 - SSLClient가 0으로 축약함, 실패 자체는 확실함)
[TLS] handshake/secure transport failed
[MQTT] CONNECT not sent
```

DNS/TCP는 정상이고, 실제 연결 시도가 2.7초간 진행된 뒤 실패했습니다(IP를 직접 넘기던 이전 버전과 비슷한 소요시간 — 즉 SNI 문제였다면 훨씬 더 빨리 실패했을 가능성도 있어 완전히 배제할 수 없지만, hostname 경로로 실제 handshake를 시도한 흔적은 있음). **SNI 수정 자체는 근거가 명확하고(라이브러리 소스로 직접 추적) 필요한 수정이었지만, 이번 재현으로 볼 때 그것만으로는 충분하지 않았을 가능성이 있습니다.**

**문제**: `SSLClient::lastError()`가 실패 시 항상 `0`으로 뭉개진다는 구조적 한계(13번 항목) 때문에, 지금 시리얼 로그만으로는 TCP-재연결/hostname설정/handshake/인증서검증 중 정확히 어느 단계에서 실패하는지 알 수 없습니다.

**추가한 진단(코드 수정, 라이브러리는 건드리지 않음)**:
- `mqtt_manager.cpp`에 handshake 시도 전/후 `ESP.getFreeHeap()`을 로그로 남겨(`[TLS] 힙 여유: 시도전=..., 시도후=... bytes`) 메모리 부족(TLS 세션 컨텍스트는 수십 KB를 요구할 수 있음)이 원인일 가능성을 별도로 확인할 수 있게 함
- 실패 로그에 "Arduino IDE Tools > Core Debug Level을 Verbose로 설정 후 재빌드/재업로드"하라는 안내를 추가 — `ssl__client.cpp`의 `log_e()`/`log_v()` 호출이 `CORE_DEBUG_LEVEL` 컴파일 옵션에 따라 컴파일 시점에 활성/비활성되므로(런타임에 바꿀 수 없음), 라이브러리를 수정하지 않고 실제 mbedTLS 단계/에러코드를 볼 수 있는 유일한 방법

**다음 단계(사용자 조치 필요)**: Arduino IDE Tools 메뉴에서 Core Debug Level을 "Verbose"(정보량 최대) 또는 최소 "Error"로 바꾼 뒤 재업로드하고, 같은 실패를 재현해 시리얼 로그 전체(특히 `E (...)` 또는 `[ssl__client.cpp:NNN]` 형태로 찍히는 줄)를 확인. 이 정보 없이는 다음 수정 방향(예: mbedTLS 버퍼 크기 부족, 힙 부족, 실제 handshake 프로토콜 문제, cleanup 시점 문제 등)을 근거 없이 추측하지 않기로 함 — 이번 턴에서는 "고쳤다"고 주장하지 않고 진단을 더 좁히는 것까지만 진행.

> ⚠️ **[2026-08-23 후속]** 사용자가 실제로 Core Debug Level을 Verbose로 바꿔 재현했지만 `ssl__client.cpp` 내부 로그가 **전혀** 출력되지 않았습니다. 이 증상 자체가 다음 항목(15번)에서 밝혀진 "라이브러리가 스케치와 별도 translation unit이라 스케치의 매크로가 전달되지 않는다"는 문제와 같은 계열의 원인(라이브러리 코드가 우리 예상과 다르게 컴파일/링크되고 있었음)일 가능성이 있습니다.

## 15. W5500_WORKAROUND 미적용 확정 및 로컬 vendoring 수정 (확정됨 — 2026-08-23)

**의혹 제기**: 사용자가 GovoroxSSLClient 1.3.2 소스를 직접 재검토해, `mqtt_manager.h`의 `#define W5500_WORKAROUND` 후 `#include <SSLClient.h>`가 실제로는 `ssl__client.cpp`(라이브러리의 별도 `.cpp` 파일)의 컴파일에 전혀 영향을 주지 못한다는 가설을 제기함 — C/C++의 분리 컴파일 원칙상 한 translation unit의 `#define`은 다른 translation unit에 전달되지 않기 때문.

**검증(추측이 아니라 실제 컴파일 커맨드라인으로 확인)**: `arduino-cli compile --verbose`로 `ssl__client.cpp`를 컴파일하는 실제 gcc 커맨드라인을 확보해 `-D` 플래그를 전수 확인:
```
-DARDUINO ... -DARDUINO_VARIANT ... -DCORE_DEBUG_LEVEL=0 ... -DF_CPU=240000000L
```
`-DW5500_WORKAROUND`는 **없음**. 또한 저장소/모든 설치된 라이브러리 전체를 `_W5500_H_`로 검색한 결과 실제로 이 매크로를 정의(`#define`)하는 헤더는 어디에도 없음(`platformio.ini`의 주석 처리된 예시, `ssl__client.cpp`의 `#if defined(...)` 검사 자체, 그리고 우리 문서의 설명문에만 문자열로 등장). **결론: 두 매크로 모두 정의되지 않은 채로 빌드돼, `ssl__client.cpp`의 W5500 handshake 재시도 우회 코드(`perform_ssl_handshake()`의 `#if defined(_W5500_H_) || defined(W5500_WORKAROUND)` 블록)가 실제 바이너리에서 한 번도 컴파일된 적이 없었습니다.** 7번 항목에 적었던 "`_W5500_H_`가 이미 자동 정의되어 있다"는 설명도 이번에 틀렸음이 확인되어 정정했습니다.

**해결 구조 선택 — vendoring(옵션 A)**: 전역 컴파일 플래그(옵션 B, 예: `build_opt.h`나 `platform.local.txt` 전역 수정)는 사용자 PC마다 수동 설정이 필요하거나 Arduino IDE 설정 파일을 직접 건드려야 해서 "재현 가능하고 저장소에 보존" 요구조건과 맞지 않았습니다. 대신 GovoroxSSLClient 1.3.2 소스 전체를 `firmware/main_eth_8di_8ro/src/SSLClient/`에 그대로 복사(vendoring)하고, 그 로컬 사본에서만 4가지를 수정했습니다:
1. `ssl__client.cpp` 최상단에서 `W5500_WORKAROUND`를 무조건 활성화
2. `start_ssl_client()` 진입 시 `Serial.println("[TLS] W5500_WORKAROUND active")`를 무조건 출력(ESP-IDF `log_*` 매크로가 아니라 순수 `Serial` — Core Debug Level 설정과 무관하게 항상 보임. Core Debug Level을 Verbose로 올려도 아무 로그가 안 보였던 위 후속 문제도 이 방식이면 우회됨)
3. 각 TLS 초기화 단계 실패 시 단계 이름을 기록해 `outFailedStep` 출력 파라미터로 반환(`ssl__client.h`/`.cpp`)
4. 실패 시 항상 `return 0;`으로 실제 코드를 지우던 것을 `return ret;`(진짜 mbedTLS/내부 코드)로 변경, `SSLClient.h`/`.cpp`에 `_lastFailedStep`/`lastFailedStep()` 추가해 `mqtt_manager.cpp`가 `[TLS] 실패 단계=...` / `[TLS] 실제 mbedTLS 오류번호=...` / `[TLS] 오류 문자열=...`을 출력할 수 있게 함

Arduino 빌드 규칙상 `<스케치폴더>/src/`의 `.c/.cpp` 파일은 스케치 자신의 소스로 자동 컴파일되므로, 별도 라이브러리 설치나 빌드 플래그 없이 `main_eth_8di_8ro.ino`를 열어 컴파일 버튼만 누르면 이 사본이 사용됩니다. `mqtt_manager.h`의 `#include <SSLClient.h>`(각괄호, 전역 라이브러리 탐색)를 `#include "src/SSLClient/SSLClient.h"`(스케치 상대경로, 파일시스템으로 직접 지정)로 바꿔 전역 Library Manager의 `SSLClient`가 이 프로젝트 빌드에 전혀 관여하지 않게 했습니다 — 중복 심볼 위험이 없습니다(전역 라이브러리 폴더 자체는 건드리지 않았고, 다른 프로젝트에는 영향 없음).

**라이선스/출처 보존**: `src/SSLClient/LICENSE`(원본 GPLv3 그대로), `src/SSLClient/VENDORED_FROM.md`(원본 저장소 URL, 가져온 커밋 해시, 버전, 변경사항 4가지 diff 요약 기록).

**안전장치**: `start_ssl_client()`의 새 반환값(`ret`)이 이론상 정확히 `1`이 될 수 있는 경우(인증서 검증 플래그가 `MBEDTLS_X509_BADCERT_EXPIRED` 하나만 켜졌을 때, `flags==1`)에는 성공 신호(`1`)와 혼동되지 않도록 `-1`로 치환해 반환합니다.

## 16. W5500 no-data(-1)가 recv 콜백에서 실제 오류로 잘못 반환되던 문제 수정 (확정됨 — 2026-08-23)

15번 항목의 vendoring 수정을 실물에 올려 재현한 결과, `[TLS] 실패 단계=perform_ssl_handshake(TLS handshake)`, `실제 mbedTLS 오류번호=-1`, `오류 문자열=ERROR - Generic error`로 여전히 실패했습니다. `-1`이 진짜 mbedTLS 프로토콜 에러가 아니라는 점(mbedTLS 에러코드는 보통 큰 음수 hex값)에 착안해 recv 콜백을 재검토했습니다.

**확인된 구조**:
- `mbedtls_ssl_set_bio()`가 실제로 연결하는 recv 콜백은 `client_net_recv_timeout()`이며, `client_net_recv()`(non-timeout)는 f_recv 슬롯에 `NULL`이 들어가 있어 **호출되지 않는 죽은 코드**였습니다(원본의 `-Wunused-function` 경고와 일치).
- W5500 `EthernetClient::read()`는 연결이 살아있어도 아직 수신 데이터가 없으면 `-1`을 반환할 수 있습니다(Arduino `Stream` 관례상 "데이터 없음"과 "에러"가 `-1`로 뭉뚱그려짐).
- 원본 `client_net_recv_timeout()`은 `result==0`만 `MBEDTLS_ERR_SSL_WANT_READ`로 변환했고, `result==-1`은 그대로 반환했습니다. mbedTLS는 이 `-1`을 `WANT_READ` 상수와 다른 실제 오류로 취급해 handshake를 즉시 실패시켰습니다.
- 기존 `perform_ssl_handshake()`의 W5500_WORKAROUND는 "`ret==-1`이면 지연 없이 최대 200회 반복"하는 임시방편이라, 서버 TLS 응답이 도착하기 전에 200회를 소진할 수 있는 구조적 결함이 있었습니다. 횟수를 늘리는 것(2000/20000)도 같은 종류의 임시방편이라 채택하지 않았습니다.

**수정**(모두 `firmware/main_eth_8di_8ro/src/SSLClient/`의 vendored 사본에서만):
1. `client_net_recv_timeout()`(실사용)과 `client_net_recv()`(죽은 코드지만 일관성 유지)에서 `read()==-1`일 때 `client->connected()`로 재확인 → 연결이 살아있으면 `MBEDTLS_ERR_SSL_WANT_READ`로 변환, 끊긴 상태면 그대로 실제 오류로 전달
2. `perform_ssl_handshake()`의 "ret==-1이면 200회 무지연 반복" 임시방편 루프 제거 — 근본원인을 recv 콜백에서 고쳤으므로 표준 WANT_READ/WANT_WRITE 재시도 루프(매 반복 handshake_timeout 검사 + `vTaskDelay(10ms)`)만으로 충분
3. 파일 스코프 카운터(`g_w5500NoDataToWantReadCount`)로 변환 횟수를 집계, 연결 시도마다 리셋, handshake 시도 종료 시 `[TLS] W5500 no-data converted to WANT_READ, count=N` 요약 1줄만 출력(매 반복 로그 금지)

상세 diff: `src/SSLClient/VENDORED_FROM.md` "2차 수정" 절.
