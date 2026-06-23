# 스마트팜 환경제어 시스템 - 변경 이력

---

## 2026-06-23 — 측창 시간기준 오작동 수정 (moveDevice 스푸리어스 CLOSE 펄스)

> 증상: 측창을 시간기준 AUTO로 켜도 제대로 작동 안 함 → 사용자가 수동으로 변경.

- **진단**: 측창 제어 로직은 천창과 동일해 정상. 그러나 `moveDevice`에서 이미 목표 위치(차이<1%)인데 `forceMove`가 발동하면 `difference>0`이 false라 **0초짜리 CLOSE 펄스**를 발행. 측창 `lastTarget`이 잦은 설정/autoActive 재발행으로 계속 리셋돼 매 사이클 forceMove → **CLOSE 0.0초 펄스가 12초마다 측창 ESP32로 반복 발행**(6/20 로그 확인) → 스크린이 조금씩 닫히거나 오작동.
- **수정**: 차이<1%면 forceMove여도 명령 미발행(0초 이동은 드리프트 보정도 못 하고 펄스만 유발). 천창 공용 함수라 천창에도 안전.
- **부수**: 데몬 재시작으로 `publishSky/Side/FanConfigRetain`(6/12 코드)이 비로소 실행 → 자주 유실되던 천창/측창/팬 설정 retain이 시작 시 자동 복원되도록 활성화. 측창 autoType/timePoints retain도 복원.
- **커밋**: `2026-06-23_2118`

---


## 2026-06-18 — 접속 시 "수동/OFF 깜빡임" 제거 (모드 기본값 AUTO)

> 증상: 접속하니 팬·히트펌프가 잠깐 수동/OFF로 보임. (사용자가 다시 주야간으로 바꿈)

- **진단**: 데몬은 7일 연속 AUTO로 정상 제어 중(재시작 0, 로그에 MANUAL 수신 0, retain·파일 모두 AUTO/true). **실제 제어는 안 바뀜.** UI가 접속 시 retain 로드 전 하드코딩 기본값(mode "MANUAL", autoActive false)을 잠깐 표시한 것뿐. **표시 ≠ 명령** — mode/autoActive는 버튼 onClick에서만 발행되므로, 화면에 수동으로 보여도 명령은 안 나감(데몬은 계속 AUTO).
- **수정**: 팬·HP·천창·측창의 `mode` 기본값 → **AUTO**, `autoActive` 기본값 → **true** (정상 운영상태와 일치). modeRef도 동일. 자동발행 없음 확인 → 표시 전용, 제어 영향 없음. retain 있으면 retain 우선.
- **커밋**: `2026-06-18_2239`

---

## 2026-06-15 — UI 하드코딩 기본값을 실사용값과 일치 (retain 미로드 시 오표시 방지)

> 증상: retain 로드 실패 시 천창/팬 화면이 실제값이 아닌 옛 임의 기본값을 표시. (제어는 데몬이 정상 수행, 표시만 틀림)

- **천창 timePoints 기본값**: `08:00→30/12:00→80/18:00→0` → **실사용값 `12:00→0/14:00→100`**. tempPoints 기본값은 이미 일치.
- **팬 주야간(defaultFanDNConfig)**: `enabled:false`+빈범위 → **실사용값**(주간 온도범위 fan_front 22~50 등, 야간 습도범위 fan_front 80~100 등). dayNightConfig는 버튼에서만 발행되어 안전.
- **팬 일반범위(fanDeviceRanges)**: `{}` → **실사용값**(fan_front 24~50, fan_top 19~50, fan_ground -10~-3 등).
  - ⚠️ **주의**: `fanDeviceRanges`는 dayNightConfig/timePoints와 달리 **변경 시 자동발행 useEffect**가 있음. 비어있지 않은 기본값을 그냥 두면 첫 렌더에 retain을 덮어씀 → `fanRangesFirstRunRef`(첫 렌더 발행 방지) 가드 추가로 해결.
- 공통: **retain 있으면 retain 우선**이므로 제어엔 영향 없음(표시 fallback만 개선). 검증: ranges retain 불변 확인.
- **커밋**: `2026-06-15_1528`(천창), `_1539`(팬 주야간), `_1605`(팬 일반범위+가드)

---

## 2026-06-13 — ESP32 연결 초록불(내부팬 뒤) 오표시 + mqtt 데몬 워치독

> 증상: 내부팬 뒤(ctlr-0002) ESP32가 실제로는 온도·팬 정상인데 UI 연결 초록불만 꺼짐.

- **원인**: UI 초록불은 `device_status` DB(`is_online`) 기준인데, 그 DB를 갱신하는 **`tansaeng-mqtt`(mqtt_daemon.php)가 6/12 22:38부터 "연결됨 상태로 내부 정지"**(크래시 164회 후 connecting에서 멈춤, 프로세스는 살아있어 systemd가 못 잡음) → 약 10시간 DB 동결. 동결 시점에 ctlr-0002가 offline이라 그대로 굳음.
  - ESP32는 정상(broker에 status=online, 온도 실시간 발행). UI 온도는 autocontrol의 realtime_sensor.json에서 와서 무관하게 정상이었음.
- **조치**: `tansaeng-mqtt` 재시작 → DB 갱신 재개, 모든 활동 장치 online 정상 복귀(0006/0011은 ~4개월 전부터 진짜 오프라인).
- **재발 방지(워치독)**: `scripts/mqtt_watchdog.sh` + cron(1분) 추가. mqtt 로그가 120초 이상 멈추면(=내부 정지) 자동 `systemctl restart`. 쿨다운 600초로 정전 시 무한 재시작 방지. 브로커 장애 크래시루프는 로그가 계속 찍혀 오작동 안 함.

---

## 2026-06-12 — 천창/측창 포인트 retain 유실 → 하드코딩 복귀 수정

> 증상: 천창 화면이 하드코딩 기본값(timePoints 08:00→30/12:00→80/18:00→0)으로 보임.

- **원인**: 천창 `timePoints`/`tempPoints`의 MQTT retain이 브로커에서 유실됨(mode/autoActive/autoType는 남아있음). 데몬은 파일(`daemon_settings.json`)에 사용자 실제 포인트(`timePoints 12:00→0/14:00→100`)를 영속·제어 중이었으나, 데몬이 sky 포인트를 발행하지 않고 구독만 해서 retain이 한번 사라지면 복구 주체가 없었음. → **제어는 정상, UI 표시만 하드코딩**.
- **수정(데몬)**: 시작 시 파일에서 복원한 sky/side 포인트를 retain 재발행하는 `publishSkyConfigRetain`/`publishSideConfigRetain` 추가(팬 `publishFanConfigRetain`과 동일 패턴). `tempPoints`/`timePoints`/`humPoints`/`dayNightConfig`/`fullTimeSeconds`만 발행 — 스크린 이동 없는 표시용이라 안전(mode/autoActive는 제외).
- **즉시 복구**: 현재값을 retain 발행해 데몬 무중단으로 화면 복구. 코드는 다음 재시작부터 자동 보장.
- **검증**: sky retain에 timePoints/tempPoints 생성 확인, 천창 미이동(`[SKIP] 히스테리시스`).
- **커밋**: `2026-06-12_0814`

---

## 2026-06-11 (밤) — 분무 밸브 닫기 보장 + 카운트 동기화 안전 재도입

> 롤백 후, 사고의 진짜 원인이었던 "닫기 보장 버그"를 고치고 카운트 동기화를 안전한 방식으로 다시 넣음.

### 🔧 1. 분무 데몬 — 밸브 닫기 보장 (과분무 버그 근본 수정)
- **중복 사이클 시작 차단**(skip-if-cycling): 재연결/재시작/retain 재수신으로 `startZoneCycle`이 중복 호출돼 닫기 타이머가 유실되던 문제 차단. 이미 사이클 중이면 재시작하지 않음.
- **닫기 보장**: 사이클 중단·습도차단·스케줄없음·정지 등 "분무 안 하는" 모든 경로에서 열린 밸브를 강제 CLOSE (`valveOpen` 추적 + `closeZoneValveIfOpen`).
- **doStop 강화**: `cycling=false`여도 밸브는 항상 CLOSE (다음 분무 예약만 cycling일 때).
- **안전 워치독(60초)**: 비활성인데 열린 밸브 강제 닫기 + 죽은 사이클 재시작. 이상시에만 동작(주기 발행 없음).
- **스케줄 변경 감지**: 실제 변경 시에만 즉시 재시작 (retain 재수신으로 인한 churn 방지).
- **검증**: 재시작 후 중복 "사이클 시작" 사라짐, OPEN→정확히 N초 후 CLOSE, 밸브 정상 닫힘.

### 🔧 2. 분무 카운트 — timerState 구독 (안전판, 1초 발행 없음)
- 데몬이 **전환 시에만** 발행하는 `timerState`(저빈도, retain)를 UI가 구독해 카운트 기준점으로 사용.
- 표시 상태(OPEN/CLOSE)는 **실측 valve/state 유지**, timerState는 timestamp만 제공. state 불일치(stale) 시 로컬시각 fallback → 지난 "stale-OPEN 오표시" 방지.
- **1초 발행이 없으므로 재연결 폭주 위험 없음** (재발 방지). 시계 오차는 NTP 수준(무시 가능).
- **검증**: 복원 후 재연결 0회, `elapsed` 토픽 없음, timerState 저빈도 발행 정상.

### 🏷️ 커밋
- `2026-06-11_2154` 분무 데몬 밸브 닫기 보장
- `2026-06-11_2155` 분무 카운트 timerState 구독(안전판)

---

## 2026-06-11 (저녁) — 🚨 분무 카운트 변경 롤백 (밸브 과분무 사고)

> **사고**: 분무수경 경과초 1초 발행 도입 후, MQTT가 ~2분마다 재연결되는 폭주 발생.
> 재연결이 사이클을 끊어 **포깅·구역A 밸브가 자동으로 닫히지 않고 ~10분 이상 열린 채 분무** (사용자가 수동 중지).
> - **원인 확정**: 경과초 `setInterval` 1초 발행이 HiveMQ 연결을 불안정하게 만듦(복원 후 재연결 완전히 멈춤으로 확인).
> - **부가 원인(기존 버그)**: 재연결/재시작 시 `startZoneCycle`이 중복 호출되며 닫기 타이머가 유실 → `doStop`의 `if(!cycling) return` 가드 + 습도 차단(humBlocked)이 겹치면 밸브를 안 닫고 끝남.
>
> **조치 (롤백)**:
> - 분무 데몬 → 경과초 추가 직전 백업(`bak_20260611_211651`)으로 복원 + 재시작 → 1초 발행 제거.
> - `MistControl.tsx` → 분무 변경 직전(커밋 6400240)으로 복원 (timerState/elapsed 구독 제거, 기존 valve/state 로컬시각 방식).
> - 전체 분무 밸브 강제 CLOSE로 즉시 분무 정지.
> - **검증**: 재연결 멈춤 ✓ / 포깅 OPEN→10초후 CLOSE 정상 ✓ / 밸브 CLOSE ✓.
>
> **롤백된 커밋**: `2026-06-11_2105`(timerState), `2026-06-11_2122`(경과초). 팬 AUTO LED·dayNightConfig 보강은 영향 없어 그대로 유지.
> **재시도 시 주의**: 카운트 동기화를 다시 한다면 1초 발행 금지 → (a) timestamp 방식만 쓰되 표시 state는 valve/state(실측) 우선, 또는 (b) 발행 주기 10초 이상 + 기존 닫기 타이머 유실 버그부터 수정.

---

## 2026-06-11 — 데몬 정리 & 팬 AUTO LED/retain 보강

> 발단: "오늘 데몬이 재부팅됐는지 로그 확인" 요청 → 점검 중 구버전 서비스 crash 루프 발견,
> 이어서 "팬 자동모드 LED 미점등" + "재시작 후 주야간 수동 재설정 필요" 문제 해결.

### 🚨 장애/이상 점검
- **데몬 재시작 확인**: 핵심 데몬 3종(autocontrol/mqtt/mist)이 6/11 06:37:37 동시 재시작됨.
  - 서버 전체 재부팅 아님 (시스템 부팅은 6/6, uptime 유지). `systemctl restart` 성격의 서비스 재시작.
- **구버전 서비스 2종 crash 루프 발견·정리**:
  - `tansaeng-command-processor`(+`.timer`): 실행파일 `scripts/command_processor.php` 없음. 마지막 정상동작 2025-12-14. 1초마다 재시작 실패, 에러로그 42MB.
  - `tansaeng-heartbeat`: 실행파일 `daemons/mqtt_heartbeat_daemon.cjs` 없음(구버전 .php만 잔존). 13,585회 재시작 실패, 에러로그 61MB.
  - 둘 다 기능이 `tansaeng-mqtt`(장치 감시) / UI MQTT 직접발행으로 대체된 **구버전 잔재** → `stop` + `disable`(+timer) + 에러로그 103MB 정리.
  - ⚠️ 향후 이 두 서비스 로그가 다시 보이면 = 누군가 enable한 것. 다시 `disable`하면 됨.

### 🔥 버그 수정 — 팬 AUTO "작동중 LED" 미점등
- **원인**: UI는 `BROWSER_AUTO_CONTROL = false`(데몬 단일제어)라 AUTO 모드에서 브라우저측 제어 useEffect가 첫 줄 return → `deviceState[fanId].power` 미갱신. 데몬은 팬 cmd를 retain 없이·변경 시에만 발행하고 UI는 이를 구독하지 않아, 데몬이 실제로 팬을 돌려도 UI LED가 항상 "정지"로 표시됨.
- **수정 (데몬 `smartfarm_auto_control_daemon.cjs`)**:
  - `runFanAutoControl`에서 팬 실제 상태를 `tansaeng/fan-control/autoStates`(JSON `{fan_id:"on"/"off"}`, **retain**) 발행.
  - AUTO 정지/MANUAL 전환 시 `clearFanAutoStates()`로 전체 off 발행.
- **수정 (UI `DevicesControl.tsx`)**:
  - `fanAutoStates` state 추가 + `tansaeng/fan-control/autoStates` 구독.
  - AUTO 화면 팬 카드 LED 판정을 `deviceState.power` → `fanAutoStates[fanId]`로 변경.
- 📌 HP 장치는 이미 `tansaeng/ctlr-heat-001/{pump,heater,fan}/state` 구독으로 LED를 켜고 있었음(같은 원리). **새 장치 AUTO LED 만들 때 데몬 retain 상태 발행/구독을 반드시 같이 구현.**

### 🔧 보강 — 팬 dayNightConfig retain (다른 기기 동기화)
- **현상**: 데몬 재시작 후 주야간 모드를 수동으로 다시 켜야 했음 + 새 브라우저 접속 시 주야간이 OFF로 표시.
- **진단**:
  - 데몬 재시작 후 주야간 **자동복원은 이미 동작** (`config/daemon_settings.json` 파일 영속: `loadSettings`/`saveSettings`가 `fan.dayNight` 저장·복원). 06:37 당시엔 파일에 enabled=true가 저장되기 전이라 수동 재설정 필요했던 것.
  - 단 브로커에 `dayNightConfig`/`humRanges`/`autoSensor` **retain이 없어** 새 기기 접속 시 UI가 기본값(주야간 OFF)으로 떠 데몬과 어긋남.
- **수정 (데몬)**: `startupComplete` 직후 `publishFanConfigRetain()`으로 파일에서 복원한 위 3개 토픽을 **retain 재발행**. UI는 셋 다 구독 중이라 자동 동기화.
- **검증**: 브로커 fan-control retain 5개 → 8개(`dayNightConfig` 548B 등 추가) 확인.

### 🔧 분무수경 카운트 — 데몬 timerState 구독 (기기간 동기화)
- **현상**: 카운트가 브라우저를 사이클 도중 열면 0부터 시작, 다른 기기마다 값이 다름(드리프트).
- **원인**: UI가 `valve/state`(OPEN/CLOSE)를 **받은 순간의 로컬 `Date.now()`**로 경과시간을 계산(`MistControl.tsx`). 데몬은 단독으로 정확히 카운트 중이지만 UI가 그걸 안 읽음.
- **수정**: 데몬이 전환마다 발행하는 `tansaeng/mist-control/{zone}/timerState`(`{state,timestamp}`, retain, 절대 epoch ms)를 UI가 구독 → `elapsed = now - timestamp`. 언제·어느 기기로 접속해도 동일·정확. timerState 못 받은 구역만 기존 로컬시각 fallback(구버전 데몬 대비).
- 📌 데몬은 원래부터 timerState retain을 발행하고 있었음(설계는 됐는데 UI 연결만 빠진 상태) → **데몬 수정 없이 UI만 수정**.

### 🔧 분무수경 카운트 — 데몬 경과초 직접 발행 (시계 오차 무관)
- **배경**: timerState(절대 timestamp) 방식은 서버-브라우저 **시계(NTP) 오차**에 영향받음.
- **수정**: 데몬이 **서버 시계로 계산한 경과초**를 1초 주기로 `tansaeng/mist-control/{zone}/elapsed`(`{state,elapsed,duration}`, qos0, 활성 구역만) 발행 → UI는 그 값을 그대로 표시(수신 후 경과분만 브라우저 로컬 델타로 보간하여 부드럽게). 양쪽 시각 비교가 사라져 **시계 오차와 무관**.
- **fallback**: elapsed 5초 이상 끊기면(구역 정지/구버전 데몬) timerState/로컬시각 방식으로 자동 전환. 3단계 안전망(elapsed → timerState → 로컬).
- **검증**: zone_a `{state:OPEN, elapsed:11→12→13→14→15, duration:30}` 매초 정상 수신.

### 🏷️ 커밋 (master)
- `2026-06-11_0847` 팬 AUTO 작동중 LED 수정 (autoStates retain)
- `2026-06-11_0900` 팬 dayNightConfig retain 보강 (publishFanConfigRetain)
- `2026-06-11_2105` 분무수경 카운트 timerState 구독 (기기간 동기화)

### ⚠️ 다음 작업 시 참고
- **데몬 = 단일 source of truth.** 서버 `/var/www/html/daemons/smartfarm_auto_control_daemon.cjs`가 실행 기준이며, 로컬 사본(`tansaeng_new/daemons/`, repo `daemons/`)과 다를 수 있으니 수정 전 서버 파일을 받아서 작업할 것.
- **설정 영속 2중화**: ① `daemon_settings.json` 파일(재시작 생존) ② MQTT retain(기기간 동기화). 새 설정 추가 시 둘 다 보장해야 함 — `saveSettings`/`loadSettings`에 필드 추가 + 시작 시 retain 재발행 고려.
- **AUTO 모드 장치 상태 표시**는 데몬이 retain으로 알려줘야 UI가 안다 (`autoStates` 패턴).
- 미적용(필요 시): 천창/측창(side) `dayNightConfig` 등 다른 장치도 동일한 retain 재발행 보강이 필요할 수 있음 (이번엔 팬만 적용).

---

## smartfarm-ui-2026-06-06 (2026-06-06)

### 🔥 버그 수정
- **히트펌프 주간/야간 모드**
  - 주간/야간 ON 시 일반 범위 슬라이더 중복 표시 버그 수정 (숨김 처리)
  - 냉각기(hp_heater) 기준 센서를 팜평균온도 → 물온도로 수정
  - 주간/야간 범위 슬라이더 자동발행 useEffect 제거 → 저장 버튼 클릭 시에만 retain 발행
- **팬 주간/야간 모드**
  - 범위 저장 버튼 없던 문제 수정 → 데몬에 저장 버튼 추가
  - 동일한 자동발행 useEffect 제거
- **PHP mqtt 데몬 팬 제어 충돌**
  - autocontrol Node.js 데몬과 PHP 데몬이 동시에 팬 명령 발행하던 충돌 해소
  - PHP 데몬 팬 제어 코드 `if(false &&)` 비활성화

### ✨ 새로운 기능
- **ESP32 원격 재시작 버튼**
  - 장치 제어 탭 ESP32 연결 상태 카드에 🔄 재시작 버튼 추가
  - `tansaeng/{ctrlId}/restart` 토픽으로 `"restart"` 발행 → ESP32 재시작
  - confirm 다이얼로그로 실수 방지

### 🏷️ 태그
- `smartfarm-ui-2026-06-06` (master 브랜치)

---

## stable-2026-06-06 (2026-06-06) — 서버 & 데몬

### 🚨 장애 대응
- **서버 자동 재부팅** (Ubuntu 커널 6.8.0-110 → 6.8.0-124 자동 업그레이드)
  - 재부팅 후 데몬 3개 정상 재시작 확인
  - 분무수경 retain 충돌로 isRunning 루프 발생 → 강제 false 발행으로 해소
- **ctlr-0004 (분무밸브 ESP32) 오프라인**
  - WiFi 끊김 시 재연결 로직 없는 펌웨어 버그 확인
  - 수정된 ESP32 펌웨어 제공 (WiFi 재연결 + retain=true + 원격 재시작 토픽)
- **www.tansaeng.com 접속 불가** (index.php 무한 리다이렉트)
  - stable-2026-04-27 태그 기준으로 서버 전체 복구
  - PHP require 경로 오류(`/../config/`) 근본 원인 해결

### 🔧 데몬 수정
- **분무수경 로그 zone name 한글화**
  - `Zone A~E` → `구역A~E`
  - `fogging` → `포깅` (기존 유지)
  - `smartfarm_mist_daemon.cjs` 수정 후 재시작

### ⚙️ 운영
- **logrotate 설정** (`/etc/logrotate.d/tansaeng`)
  - 대상: tansaeng 데몬 로그 10종
  - 규칙: 100MB 초과 시 자동 회전, 5개 보관, gzip, copytruncate
  - 매시간 자동 점검 (`/etc/cron.d/tansaeng-logrotate`)
  - 즉시 효과: /var/log 7.2G → 4.9G 회수

### 🏷️ 태그
- `stable-2026-06-06` (main 브랜치)

---

## v3.4.0 (2025-11-26) 📹 **Tapo 카메라 전용 페이지 추가**

### ✨ 새로운 기능
- **독립된 /camera 페이지 추가**:
  - React Router 도입으로 페이지 라우팅 지원
  - TP-Link Tapo 카메라 4대 전용 모니터링 페이지
  - 기존 스마트팜 UI와 완전히 분리된 독립 페이지

- **Tapo 카메라 스트리밍**:
  - HLS 스트림 직접 재생 (Nginx/SRS 서버)
  - 4대의 카메라 2x2 그리드 레이아웃
  - 반응형 디자인 (모바일 1열, 데스크톱 2열)
  - 저지연 모드 라이브 스트리밍

- **새로운 컴포넌트**:
  - `TapoCameraView`: HLS 재생 전용 컴포넌트
  - `CameraPage`: 카메라 모니터링 페이지
  - 브라우저 호환성 자동 처리 (hls.js + Safari native)

### 🔧 기술 구현
- **React Router 추가**:
  - `/` - 기존 스마트팜 환경제어 앱
  - `/camera` - 새로운 Tapo 카메라 페이지
  - BrowserRouter 기반 라우팅

- **환경변수 설정**:
  - `VITE_TAPO_CAM1_HLS_URL` ~ `VITE_TAPO_CAM4_HLS_URL`
  - TypeScript 타입 정의 추가 (vite-env.d.ts)
  - .env 파일로 HLS URL 관리

- **HLS 스트리밍 최적화**:
  - liveBackBufferLength: 0 (지연 최소화)
  - lowLatencyMode: true
  - 네트워크 오류 자동 재시도
  - 로딩/에러 상태 표시

### 📦 빌드 정보
- **JavaScript**: 1,163.43KB (gzip: 357.80KB)
- **CSS**: 24.76KB (gzip: 5.41KB)
- **빌드 파일**: `index-C56F581z.js`, `index-DrwB0a3n.css`
- **새 의존성**: react-router-dom@^7.6.3

### 🔧 파일 변경사항
- **신규 파일**:
  - `src/vite-env.d.ts` - Vite 환경변수 TypeScript 타입 정의
  - `src/components/camera/TapoCameraView.tsx` - HLS 카메라 재생 컴포넌트
  - `src/pages/CameraPage.tsx` - 카메라 모니터링 페이지

- **수정 파일**:
  - `src/main.tsx` - React Router 추가 및 라우팅 설정
  - `package.json` - react-router-dom 의존성 추가

### ⚙️ 기능 보존
- ✅ 기존 스마트팜 UI 100% 유지 (루트 경로)
- ✅ MQTT 연결 및 제어 기능 변경 없음
- ✅ 기존 카메라 탭 정상 작동
- ✅ 센서 모니터링 및 장치 제어 유지
- ✅ PHP 백엔드 변경 없음

### 🚀 사용 방법
- 기존 앱: `https://www.tansaeng.com/`
- Tapo 카메라: `https://www.tansaeng.com/camera`

---

## v3.3.1 (2025-11-26) 🎯 **카메라 UI 개선: 각 카메라별 직접 편집 기능**

### ✨ 주요 개선사항
- **각 카메라 카드에서 직접 편집**:
  - 각 카메라 영상 아래에 이름/URL 표시 및 편집 폼 통합
  - "수정" 버튼 클릭 시 즉시 해당 카메라만 편집 모드로 전환
  - 입력 폼에서 이름과 스트림 URL 직접 수정 가능
  - "저장" 버튼으로 즉시 반영, "취소" 버튼으로 되돌리기

- **개선된 사용성**:
  - 별도의 모달 없이 카드 내에서 모든 편집 완료
  - 여러 카메라를 동시에 편집 가능
  - 각 카메라의 현재 설정을 항상 확인 가능
  - 활성/비활성 상태를 토글 버튼으로 간편하게 전환

### 🐛 버그 수정
- **초기 로드 문제 해결**:
  - 렌더링 중 state 변경으로 인한 React 경고 제거
  - `useEffect`를 사용한 안전한 초기 카메라 설정
  - "등록된 카메라가 없습니다" 오류 완전 해결

### 🎨 UI 개선
- 각 카메라 카드 구성:
  - 상단: 라이브 스트림 영상 (HLS)
  - 하단: 설정 폼 (이름/URL 표시 또는 편집)
  - 버튼: 수정 / 활성-비활성 / 삭제
- 비활성 카메라: 📵 아이콘과 함께 "비활성화된 카메라" 메시지 표시
- 편집 모드: 녹색 "저장" 버튼으로 명확한 액션 표시

### 📦 빌드 정보
- **JavaScript**: 1,126.94KB (gzip: 345.68KB)
- **CSS**: 24.54KB (gzip: 5.34KB)
- **빌드 파일**: `index-DPjA7egP.js`, `index-DJMRFuIr.css`

### 🔧 기술 개선
- 개별 카메라 편집 상태를 별도 state로 관리
- 편집 모드와 일반 모드 명확하게 분리
- TypeScript 타입 안전성 강화

---

## v3.3.0 (2025-11-26) 📹 **HLS 카메라 라이브 스트리밍 추가**

### ✨ 새로운 기능
- **HLS 라이브 스트리밍 지원**:
  - hls.js 라이브러리 통합 (1.x 버전)
  - CameraLive 컴포넌트 신규 추가
  - 4개의 기본 카메라 (cam1, cam2, cam3, cam4)
  - 2x2 반응형 그리드 레이아웃

- **카메라 관리 기능**:
  - 카메라 추가/수정/삭제
  - 카메라 활성화/비활성화
  - 실시간 스트림 URL 표시

- **라즈베리파이 IP 설정**:
  - .env 파일에서 기본 URL 설정 (VITE_RPI_BASE_URL)
  - UI를 통한 RPI IP 수정 기능
  - localStorage에 설정값 저장
  - cam1, cam2, cam3의 URL 자동 업데이트

### 🎨 기술 구현
- **브라우저 호환성**:
  - HLS.js 지원 브라우저 (Chrome, Firefox 등)
  - Safari 네이티브 HLS 지원
  - 브라우저별 자동 감지 및 처리

- **실시간 스트리밍 최적화**:
  - liveBackBufferLength: 0 (지연 최소화)
  - lowLatencyMode: true
  - 자동 재생 (음소거 상태)
  - 네트워크 오류 시 자동 재시도

- **에러 처리**:
  - 로딩 상태 표시
  - 네트워크/미디어 오류 처리
  - 사용자 친화적 오류 메시지

### 📦 빌드 정보
- **JavaScript**: 1,127.74KB (gzip: 345.67KB)
- **CSS**: 23.31KB (gzip: 5.16KB)
- **빌드 파일**: `index-ppgGEWTZ.js`, `index-xrcrOTKL.css`
- **새 의존성**: hls.js

### 🔧 파일 변경사항
- **신규 파일**:
  - `src/components/CameraLive.tsx` - HLS 비디오 플레이어 컴포넌트

- **수정 파일**:
  - `src/tabs/Cameras.tsx` - 완전히 재작성 (카메라 관리 UI)
  - `.env` - VITE_RPI_BASE_URL 추가
  - `package.json` - hls.js 의존성 추가

### ⚙️ 기능 보존
- ✅ 기존 ESP32 제어 로직 100% 유지
- ✅ MQTT 연결 및 통신 정상 작동
- ✅ 온습도 센서 모니터링 유지
- ✅ 팬/분무기 제어 기능 유지
- ✅ 모든 기존 탭 정상 작동

---

## v3.2.0 (2025-11-25) 🔗 **헤더 링크 추가 및 폰트 색상 개선**

### ✨ 새로운 기능
- **헤더 네비게이션 링크 추가**:
  - 메인페이지 바로가기 버튼
  - 관리자 페이지 바로가기 버튼
  - 새 탭에서 열림 (target="_blank")

### 🎨 디자인 개선
- **모든 탭 폰트를 검은색으로 변경**:
  - 제목: `text-gray-900` (진한 검정)
  - 설명/부제: `text-gray-800` (중간 검정)
  - 가독성 대폭 향상

- **헤더 스타일 통일**:
  - 그라데이션 배경 제거
  - 단색 `bg-farm-500` 배경 사용
  - ESP32 연결 상태 배지 스타일 개선

### 📋 적용 범위
- 장치 제어 (DevicesControl)
- 환경 모니터링 (Environment)
- 분무수경 (MistControl)
- 카메라 (Cameras)
- 설정 (Settings)

### 📦 빌드 정보
- **JavaScript**: 599.87KB (gzip: 181.22KB)
- **CSS**: 22.41KB (gzip: 5.00KB)
- **빌드 파일**: `index-CAuylD13.js`, `index-Cjy4a5Lm.css`

---

## v3.1.0 (2025-11-25) 📱 **모바일 UI 개선 및 헤더 디자인 변경**

### 🎨 UI/UX 개선
- **모바일 탭 메뉴 완전 표시**: 모든 탭이 스크롤로 접근 가능
- **반응형 탭 네비게이션**: 모바일에서는 아이콘만, 태블릿 이상에서는 텍스트 표시
- **헤더 디자인 변경**:
  - 배경색: 그라데이션 → 단색 그린 (`bg-farm-500`)
  - 텍스트: 흰색 → 검정색 (`text-gray-900`, `text-gray-800`)
  - MQTT 연결 상태 배지는 기존 색상 유지

### 🐛 버그 수정
- 모바일에서 "장치 제어" 탭이 안 보이던 문제 해결
- 탭 메뉴 가로 스크롤 시 스크롤바 숨김 처리
- 작은 화면에서 탭 버튼 잘림 현상 해결

### 📦 빌드 정보
- **JavaScript**: 599.63KB (gzip: 181.13KB)
- **CSS**: 22.08KB (gzip: 4.95KB)
- **빌드 파일**: `index-boPuUzTG.js`, `index-DSVfF9oc.css`

---

## v3.0.0 (2025-11-25) 🎨 **MAJOR UPDATE: Tailwind CSS 전환**

### 🎉 주요 변경사항
- **100% Tailwind CSS 전환**: 모든 인라인 스타일을 Tailwind 유틸리티 클래스로 변환
- **탄생 홈페이지 디자인 스타일 반영**: 깔끔한 카드 레이아웃, 호버 효과, 그림자
- **디자인 시스템 통일**: 일관된 색상 팔레트와 스타일링

### ✨ 새로운 기능
- **커스텀 색상 팔레트**:
  - `primary-*`: 탄생 브랜드 그린 (#2E7D32)
  - `secondary-*`: 탄생 오렌지 (#FFA726)
  - `farm-*`: 스마트팜 그린 (#10B981)

- **향상된 UI/UX**:
  - 카드 호버 효과 (`shadow-card-hover`)
  - 버튼 호버 애니메이션 (`hover:-translate-y-0.5`)
  - 부드러운 그라데이션 배경
  - 반응형 그리드 레이아웃

### 🎨 디자인 개선
- **모든 컴포넌트 리디자인**:
  - App.tsx
  - Header.tsx
  - TabNavigation.tsx
  - DeviceCard.tsx
  - GaugeCard.tsx
  - SensorRow.tsx

- **모든 탭 리디자인**:
  - DevicesControl.tsx (장치 제어)
  - Environment.tsx (환경 모니터링)
  - MistControl.tsx (분무수경)
  - Cameras.tsx (카메라)
  - Settings.tsx (설정)

### 📦 빌드 정보
- **JavaScript**: 599.51KB (gzip: 181.09KB)
- **CSS**: 21.68KB (gzip: 4.89KB) - Tailwind 포함
- **총 파일 크기**: 621.19KB

### 🔧 기술 스택
- Tailwind CSS v4
- React 18
- TypeScript
- Vite 7
- PostCSS

### ⚙️ 기능 보존
- ✅ MQTT 연결 및 제어 기능 100% 유지
- ✅ WebSocket 통신 정상 작동
- ✅ 상태 관리 (localStorage) 유지
- ✅ ESP32 장치 제어 기능 유지
- ✅ 실시간 환경 모니터링 유지
- ✅ 모든 이벤트 핸들러 정상 작동

### 🚀 성능 최적화
- Tailwind JIT 모드로 CSS 크기 최적화
- 불필요한 CSS 제거
- 빌드 시간 단축

---

## v2.9.4 (2025-11-25)

### 🔧 경로 및 파일 정리
- **vite.config.ts**: base 경로를 `/smartfarm-admin/` → `/smartfarm-ui/`로 수정
- **dist/index.html 삭제**: PHP가 직접 경로 지정하므로 불필요한 파일 제거
- **충돌 가능성 완전 제거**: 단일 진실 공급원(PHP index.php)으로 통일

### 📦 빌드 정보
- JavaScript: 606.97KB (gzip: 181.81KB)
- CSS: 1.18KB (gzip: 0.50KB)
- 모든 asset 파일명 유지

---

## v2.9.3 (2025-11-24)

### 🐛 CSS 충돌 제거
- **index.php**: 인라인 `<style>` 태그 완전 삭제
- `body { overflow: hidden }` 충돌 제거
- CSS는 index.css에서만 관리

### 📦 빌드 정보
- JavaScript: 606.97KB (gzip: 181.81KB)
- CSS: 1.13KB (gzip: 0.48KB)

---

## v2.9.2 (2025-11-24)

### 🔧 스크롤 구조 개선
- **index.css**: `#root`, `html`, `body`에 `overflow: hidden` 추가
- 메인 영역만 스크롤되도록 완벽하게 구조화
- Header/Footer 고정, Main만 `overflowY: auto`

### 📦 빌드 정보
- JavaScript: 606.97KB (gzip: 181.81KB)
- CSS: 1.13KB (gzip: 0.48KB)

---

## v2.9.1 (2025-11-24)

### 🔄 캐시 무효화 강화
- **index.php**: 버전 기반 캐시 무효화 로직 추가
- sessionStorage로 APP_VERSION 체크
- ServiceWorker 및 Cache API 강제 클리어

### 📦 빌드 정보
- JavaScript: 606.97KB (gzip: 181.81KB)
- CSS: 1.13KB (gzip: 0.48KB)

---

## v2.9.0 (2025-11-24)

### ✨ 모든 탭 인라인 스타일 완료
- **MistControl.tsx** 완전히 인라인 스타일로 변환
- **Cameras.tsx** 완전히 인라인 스타일로 변환
- **Settings.tsx** 완전히 인라인 스타일로 변환

### 🎨 디자인 개선
- 모든 탭이 장치제어와 동일한 디자인 일관성 확보
- 카드 박스, 헤더, 버튼, 테이블 모두 CSS 충돌 없이 정상 표시
- 페이지 스크롤 모든 탭에서 정상 작동

### 📦 빌드 정보
- JavaScript: 606.97KB (gzip: 181.81KB)
- CSS: 1.13KB (gzip: 0.48KB)

---

## v2.8.0 (2025-11-24)

### ✨ 개선사항
- **환경 모니터링 탭 인라인 스타일 적용**: 모든 Tailwind 클래스 제거
- Environment.tsx 완전히 인라인 스타일로 재작성
- GaugeCard 컴포넌트 인라인 스타일 변환
- SensorRow 컴포넌트 인라인 스타일 변환

### 🎨 디자인
- 장치제어와 동일한 디자인 일관성 확보
- 카드 박스, 헤더, 섹션 모두 CSS 충돌 없이 정상 표시

### 📦 빌드 정보
- JavaScript: 603.07KB (gzip: 181.79KB)
- CSS: 1.13KB (gzip: 0.48KB)

---

## v2.7.3 (2025-11-24)

### 🐛 버그 수정
- **페이지 스크롤 완전 수정**: 외부 컨테이너의 minHeight를 height로 변경
- App.tsx의 최상위 div를 `minHeight: "100vh"` → `height: "100vh"`로 수정
- Flexbox 스크롤이 작동하려면 부모 컨테이너가 고정 높이를 가져야 함

### 🔍 원인 분석
- `minHeight: "100vh"`는 컨테이너가 최소 높이만 가지고 늘어날 수 있어 스크롤이 발생하지 않음
- `height: "100vh"`로 고정하면 컨테이너가 뷰포트 높이로 제한되어 내부 콘텐츠가 넘치면 스크롤 발생
