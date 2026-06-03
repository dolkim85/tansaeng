# 2026-06-03 작업 기록

## 1. MQTT 브로커 장애 원인 분석

**현상:** 2026-06-02 오후 7시 44분에 `tansaeng-mqtt` (PHP) 데몬이 재시작됨.

**원인:** HiveMQ Cloud 브로커와의 MQTT keepalive 핑 응답 타임아웃
- 로그 마지막 줄: `[ERROR] [66] Transferring data over socket failed: No ping response received in time. The connection is dead.`
- PHP 데몬만 exit code 1로 종료 → systemd 자동 재시작 (30초)
- autocontrol/mist 데몬은 자동 재연결 (약 17초 공백)

**배제 원인:** 서버 리부트 없음, OOM 없음, MariaDB 재시작 없음

**재시작 후 부작용:**
- PHP mqtt 데몬이 재시작 시 `deviceCycleState` 초기화 → `device_settings.json` 기반으로 팬 명령 즉시 재발행
- autocontrol Node.js 데몬도 동시에 브로커 연결 끊김/재연결 → 설정은 retain으로 3초 내 복원

---

## 2. PHP mqtt 데몬 팬 제어 코드 비활성화

**파일:** `scripts/mqtt_daemon.php`
**커밋:** `98de69f` (2026-06-03_1622)

**문제:** PHP 데몬이 `device_settings.json` 기반으로 팬 명령을 독립적으로 발행하여 autocontrol Node.js 데몬과 충돌.
- PHP 데몬 재시작 시마다 팬 명령 중복 발행
- 두 데몬이 동일 ESP32 명령 토픽에 publish → 마지막 발행 쪽이 이김

**수정:** 팬 자동 제어 섹션을 `if (false && ...)` 로 비활성화 (분무수경 섹션과 동일 방식)
- 팬 AUTO 제어: autocontrol Node.js 데몬 전담
- 팬 MANUAL 제어: UI가 직접 ESP32에 publish → 영향 없음

---

## 3. 로그 로테이션 설정

**서버 설정 파일:** `/etc/logrotate.d/tansaeng`
**자동 실행:** `/etc/cron.d/tansaeng-logrotate` (매시간 17분)

**규칙:** 100MB 초과 시 회전, 5개 보관, gzip 압축, `copytruncate` (데몬 재시작 불필요)

**대상 로그 10종:**
- tansaeng-autocontrol.log / error
- tansaeng-mqtt.log / error
- tansaeng-command-processor.log / error
- tansaeng-heartbeat.log / error
- tansaeng-mist.log / error
- mqtt_daemon.log

**즉시 효과:** tansaeng-mqtt.log 1.5GB → 4KB, /var/log 7.2G → 4.9G 회수

---

## 4. 히트펌프 주간/야간 모드 UI 버그 수정

**파일:** `src/tabs/DevicesControl.tsx`
**커밋:** `8e4aadb` (2026-06-03_1640)

### 버그 1 — 일반 범위 슬라이더 미숨김
- **증상:** 주간/야간 ON 시 daynight 범위 설정 아래에 일반 자동제어 범위 슬라이더(hpDeviceRanges)가 계속 표시
- **원인:** `gaugeItems.map(...)` 섹션에 조건 없이 항상 렌더링
- **수정:** `{!hpDayNightConfig.enabled && gaugeItems.map(...)}` 조건 추가
- 작동시작/멈춤 버튼은 항상 표시 유지

### 버그 2 — 냉각기 기준 센서 오류
- **증상:** 주간/야간 범위 슬라이더에서 냉각기(hp_heater)가 팜평균온도(avgTemp) 기준으로 마커 표시
- **원인:** daynight 섹션 `gaugeItems.map`에서 `sensorVal`을 구조분해하지 않고 `avgTemp` 하드코딩
- **수정:** `{ key, label, icon, sensorVal }` 구조분해 추가 → `markerPct={toMarkerPct(sensorVal)}`로 교체
- 냉각기는 waterTemp, 나머지는 avgTemp로 각각 올바르게 표시
- **데몬은 이미 올바름:** `smartfarm_auto_control_daemon.cjs`에서 hp_heater는 항상 waterTemp 사용

---

## 5. www.tansaeng.com 접속 불가 복구

**현상:** 브라우저에서 `chrome-error://chromewebdata/` → "Unsafe attempt to load URL" 에러

**원인:** `/var/www/html/index.php`가 Vercel API 스텁으로 교체돼 자기 자신으로 무한 리다이렉트
```php
header('Location: /index.php');  // 181바이트 스텁
```

**복구:**
- 깨진 파일 → `/var/www/html/index.php.bak_20260603` 백업
- 로컬 저장소(`tansaeng_fresh/index.php`, 560줄) → 서버 복구
- 결과: `HTTP/1.1 200 OK` 정상 응답 확인

---

## 아키텍처 메모 (이번 분석에서 확인된 사항)

### 팬/히트펌프 이중 제어 구조 (해소됨)

| 데몬 | 제어 방식 | 상태 |
|---|---|---|
| autocontrol Node.js | fan-control/hp-control retain 구독 → 온도 기반 AUTO | 활성 |
| mqtt PHP | device_settings.json → 팬 명령 발행 | **비활성화 완료** |

### MQTT 브로커 장애 시 데몬별 동작

| 데몬 | 브로커 끊김 시 | 복구 방식 |
|---|---|---|
| tansaeng-autocontrol (Node.js) | 자동 재연결, retain으로 설정 3초 내 복원 | 정상 |
| tansaeng-mqtt (PHP) | exit code 1 종료 | systemd 30초 후 재시작 |
| tansaeng-mist (Node.js) | 자동 재연결 | 정상 |
