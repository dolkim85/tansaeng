# MQTT 토픽 정의

브로커: HiveMQ Cloud (기존과 동일), TLS 8883. `CONTROLLER_ID = ctlr-0004` (기존과 동일 — 서버/UI는 무선이든 유선이든 이 ID로만 인식하므로 코드 변경 불필요).

## 기존 토픽 (그대로 유지 — 값/의미 변경 금지)

| 토픽 | 방향 | 값 |
|---|---|---|
| `tansaeng/ctlr-0004/valve1/cmd` | 서버→메인 | `ON`/`OPEN`, `OFF`/`CLOSE` |
| `tansaeng/ctlr-0004/valve1/state` | 메인→서버 (retain) | `OPEN`/`CLOSE` |
| `tansaeng/ctlr-0004/valve2/cmd` | 서버→메인 | 상동 (포깅) |
| `tansaeng/ctlr-0004/valve2/state` | 메인→서버 (retain) | 상동 |
| `tansaeng/ctlr-0004/valve3/cmd` | 서버→메인 | 상동 (바이패스) |
| `tansaeng/ctlr-0004/valve3/state` | 메인→서버 (retain) | 상동 |
| `tansaeng/ctlr-0004/flow1/rate` | 메인→서버 (retain) | L/min, 소수 |
| `tansaeng/ctlr-0004/flow1/total` | 메인→서버 (retain) | 누적 L, 소수 |
| `tansaeng/ctlr-0004/flow1/pulses` | 메인→서버 (비-retain) | 진단용 초당 펄스수 |
| `tansaeng/ctlr-0004/flow1/pinLevel` | 메인→서버 (비-retain) | 진단용 `HIGH`/`LOW` — DI1 순간 레벨 |
| `tansaeng/ctlr-0004/status` | 메인→서버 (retain, LWT) | `online`/`offline` |
| `tansaeng/ctlr-0004/restart` | 서버→메인 | `restart` |

## 신규 진단 토픽 (작업지시서 4번 권장 목록)

| 토픽 | 방향 | 값 | 설명 |
|---|---|---|---|
| `tansaeng/ctlr-0004/network/ethernet` | 메인→서버 (retain) | `up`/`down` | W5500 링크 상태 |
| `tansaeng/ctlr-0004/network/mqtt` | 메인→서버 (retain) | `connected`/`disconnected` | (참고용 — LWT `status`가 사실상 같은 정보라 중복이지만, 재연결 카운트 등 세부정보 확장 여지로 분리) |
| `tansaeng/ctlr-0004/rs485/node1/status` | 메인→서버 (retain) | `ok`/`fault`/`timeout` | 팔 노드1(Relay-6CH) RS485 통신 상태 |
| `tansaeng/ctlr-0004/rs485/node1/lastSeen` | 메인→서버 (retain) | epoch ms | 마지막 정상 응답 시각 |
| `tansaeng/ctlr-0004/fault` | 메인→서버 (비-retain) | JSON `{code, message}` | 안전타임아웃 발동, CRC 연속실패 등 |
| `tansaeng/ctlr-0004/system/uptime` | 메인→서버 (retain) | 초 | |

## 신규: 구역A 로컬재생용 스케줄 캐시 (데몬 → 메인 노드)

**배경**: 인터넷/MQTT가 장시간 끊겨도 구역A(메인밸브) 관수가 공백 없이 이어지도록, 데몬이 마지막으로 적용 중이던 단순 ON/OFF 주기를 retain 토픽으로 발행하고 메인 노드가 이를 캐싱해뒀다가, **"확정 오프라인"(연결 끊김이 일정 시간 이상 지속) 상태에서만** 자체 재생합니다. 포깅(valve2)은 습도조건 로직이 있어 로컬재생 대상에서 제외 — 끊기면 안전하게 정지(과다침수 방지 우선).

| 토픽 | 방향 | 페이로드 |
|---|---|---|
| `tansaeng/ctlr-0004/valve1/localReplay` | 데몬→메인 (retain) | `{"enabled":true,"sprayDurationSeconds":25,"stopDurationSeconds":79,"bypassActive":false,"updatedAt":1786...}` |

필드 설명:
- `enabled`: `false`면 로컬재생 자체를 하지 않음(구역A가 정지 상태이거나 AUTO가 아닐 때 데몬이 즉시 이 값으로 재발행)
- `sprayDurationSeconds` / `stopDurationSeconds`: 데몬이 지금 실제 적용 중인 단순 주기(주야간전환·습도조건 등 복잡한 판단은 담지 않음 — 온보드가 서버 로직을 통째로 복제하면 `ctlr-heat-001` 사고 유형이 재발할 위험이 있으므로 의도적으로 단순화)
- `bypassActive`: `true`면 로컬재생 대상이 valve1이 아니라 **valve3**(바이패스)
- `updatedAt`: 데몬 발행 시각(epoch ms, 참고용 — 정밀한 위상 복원에는 사용하지 않음. `docs/open-decisions.md`의 "재생 시작 지점" 결정 참고)

**우선순위 규칙(반드시 지킬 것)**: MQTT가 정상 연결되어 있는 동안 메인 노드는 `valve1/cmd`, `valve3/cmd`(서버가 직접 보내는 살아있는 명령)만 실행하고 `localReplay`는 절대 참고하지 않습니다. `localReplay`는 오직 "확정 오프라인" 상태에서만 활성화되고, 서버가 재연결되는 즉시(첫 유효 cmd 수신 즉시) 로컬재생을 중단하고 서버 명령을 우선합니다 — `ctlr-heat-001` 사고(온보드 판단이 서버 명령을 무시)와 동일한 유형의 이중권한 문제를 만들지 않기 위한 핵심 규칙입니다.

## 명령 값 호환성

기존과 동일하게 대소문자 무관, `ON`/`OPEN` = 켜기, `OFF`/`CLOSE` = 끄기, `restart` = 재시작. 알 수 없는 값은 무시하고 로그만 남김.
