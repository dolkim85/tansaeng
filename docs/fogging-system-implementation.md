# 포깅 시스템 구현 문서

**작성일:** 2026-05-06  
**작업 범위:** ESP32 펌웨어 + 분무수경 UI + 서버 데몬

---

## 1. 개요

기존 분무수경 시스템(Zone A, valve1)에 포깅용 전자밸브(valve2)를 추가하고,  
분무수경 탭에 **포깅** 존을 신규 추가했습니다.  
포깅은 습도가 부족할 때 작동하는 시스템으로, **시간 스케줄 AND 습도 조건**을 모두 만족할 때만 분무합니다.

---

## 2. 하드웨어 구성

| 항목 | 내용 |
|---|---|
| 컨트롤러 | ESP32 (`ctlr-0004`) |
| 분무 밸브 | `valve1` — 핀 18 — BERMAD 24VAC |
| 포깅 밸브 | `valve2` — 핀 19 — BERMAD 24VAC (실제 배선 핀 번호 확인 후 수정) |
| 통신 | HiveMQ Cloud (TLS 8883) |

---

## 3. MQTT 토픽 구조

### 분무 밸브 (기존)
| 토픽 | 방향 | 내용 |
|---|---|---|
| `tansaeng/ctlr-0004/valve1/cmd` | 서버 → ESP32 | `OPEN` / `CLOSE` |
| `tansaeng/ctlr-0004/valve1/state` | ESP32 → 서버 | `OPEN` / `CLOSE` (retain) |

### 포깅 밸브 (신규)
| 토픽 | 방향 | 내용 |
|---|---|---|
| `tansaeng/ctlr-0004/valve2/cmd` | 서버 → ESP32 | `OPEN` / `CLOSE` |
| `tansaeng/ctlr-0004/valve2/state` | ESP32 → 서버 | `OPEN` / `CLOSE` (retain) |

### 포깅 제어 설정
| 토픽 | 내용 |
|---|---|
| `tansaeng/mist-control/fogging/isRunning` | `"true"` / `"false"` (retain) |
| `tansaeng/mist-control/fogging/schedule` | JSON (mode, daySchedule, nightSchedule) (retain) |
| `tansaeng/mist-control/fogging/humidityConfig` | JSON `{ enabled, threshold }` (retain) |

### 공통
| 토픽 | 내용 |
|---|---|
| `tansaeng/ctlr-0004/status` | `"online"` / `"offline"` (heartbeat 60초) |

---

## 4. ESP32 펌웨어 (`ctlr-0004`)

### 주요 변경 사항

기존 단일 밸브 코드에서 **2채널 밸브** 제어로 확장.

```cpp
// 핀 설정
const int VALVE1_PIN = 18;   // 분무 밸브
const int VALVE2_PIN = 19;   // 포깅 밸브 (실제 배선 확인 후 수정)
```

### 동작 방식
- MQTT 연결 시 `valve1/cmd`, `valve2/cmd` 동시 구독
- 각 밸브 독립 제어 (`ON`/`OFF` 또는 `OPEN`/`CLOSE` 모두 인식)
- 상태 변경 시 `valve1/state`, `valve2/state` retain 발행
- 60초마다 `status` heartbeat 전송
- LWT 설정으로 연결 끊김 시 `"offline"` 자동 발행

---

## 5. 서버 데몬 (`smartfarm_mist_daemon.cjs`)

### Zone 정의

```js
const ZONES = {
  zone_a:  { name: 'Zone A', controllerId: 'ctlr-0004', deviceId: 'valve1' },
  zone_b:  { name: 'Zone B', controllerId: 'ctlr-0005', deviceId: 'valve1' },
  zone_c:  { name: 'Zone C', controllerId: 'ctlr-0006', deviceId: 'valve1' },
  zone_d:  { name: 'Zone D', controllerId: 'ctlr-0007', deviceId: 'valve1' },
  zone_e:  { name: 'Zone E', controllerId: 'ctlr-0008', deviceId: 'valve1' },
  fogging: { name: '포깅',   controllerId: 'ctlr-0004', deviceId: 'valve2' },
};
```

### 습도 센서 구독 (신규)

```js
// 팜 3개 위치 습도 MQTT 구독
'tansaeng/ctlr-0001/+/humidity'  // front
'tansaeng/ctlr-0002/+/humidity'  // back
'tansaeng/ctlr-0003/+/humidity'  // top
```

front/back/top 평균값을 `avgHumidity`로 유지.

### 습도 조건 제어 로직 (`doSpray()` 내부)

```js
zones.forEach(zoneId => {
  const hCtrl = zoneState[zoneId].humidityControl;
  if (hCtrl?.enabled && avgH !== null && avgH >= hCtrl.threshold) {
    // 습도 충족 → 이 존만 건너뜀
    log(`[${ZONES[zoneId].name}] 습도 조건 미충족 (${avgH}% >= ${hCtrl.threshold}%) → 건너뜀`);
    return;
  }
  // 밸브 OPEN
  mqttClient.publish(`tansaeng/${ZONES[zoneId].controllerId}/${ZONES[zoneId].deviceId}/cmd`, 'OPEN');
});
```

**핵심 동작:**
- 포깅만 습도 조건 체크 → 다른 존(Zone A~E)에 영향 없음
- 습도가 다시 부족해지면 다음 사이클에 자동 작동 재개
- 습도 센서 오프라인 시(`avgH === null`) 조건 체크 건너뜀 → 시간 스케줄만으로 작동

---

## 6. UI 변경 (`MistControl.tsx`)

### 포깅 존 추가

- Zone A와 동일한 구조 (OFF / MANUAL / AUTO 모드)
- 컨트롤러: `ctlr-0004`, 밸브: `valve2`
- 분무수경 탭 최하단에 표시

### 습도 조건 섹션 (AUTO 모드 전용)

포깅 존의 AUTO 모드에서 **💧 습도 조건** 섹션이 추가됩니다.

| UI 요소 | 설명 |
|---|---|
| 활성화 토글 | 습도 조건 사용 여부 |
| 현재 팜 습도 | API 10초 폴링 (front/back/top 평균) |
| 작동 기준 습도 | 이 값 미만일 때만 분무 (기본값: 70%) |
| 상태 뱃지 | 조건 미충족(빨강) / 습도 충족–대기(초록) |

**작동 조건:** 시간 스케줄 범위 내 **AND** 현재 습도 < 기준치

### 습도 데이터 출처

`/api/smartfarm/get_realtime_sensor_data.php` → `data.data.{front|back|top}.humidity` 평균

---

## 7. 수정된 파일 목록

| 파일 | 변경 내용 |
|---|---|
| `src/types.ts` | `HumidityControl` 인터페이스 추가, `MistZoneConfig`에 `deviceId`, `humidityControl` 필드 추가 |
| `src/store/usePersistedStore.ts` | `fogging` 존 기본값 추가 (`ctlr-0004`, `valve2`, `humidityControl: { enabled: false, threshold: 70 }`) |
| `src/tabs/MistControl.tsx` | 포깅 토픽 구독, 습도 폴링, 습도 조건 UI, `humidityConfig` MQTT retain 발행 |
| `daemons/smartfarm_mist_daemon.cjs` | 포깅 존 추가, `deviceId` 기반 토픽 발행, 습도 구독, `doSpray` 습도 조건 체크 |

---

## 8. 배포 정보

| 항목 | 내용 |
|---|---|
| UI 배포 경로 | `/var/www/html/smartfarm-ui-source/dist/` |
| 데몬 파일 | `/var/www/html/daemons/smartfarm_mist_daemon.cjs` |
| 서비스 재시작 | `systemctl restart tansaeng-mist.service` |
| 로그 확인 | `tail -f /var/log/tansaeng-mist.log` (또는 `journalctl -u tansaeng-mist -f`) |

---

## 9. 검증 체크리스트

- [ ] 포깅 탭 표시 및 OFF/MANUAL/AUTO 모드 전환 확인
- [ ] MANUAL 모드: 분무/중지 버튼으로 `ctlr-0004/valve2/cmd` 발행 확인
- [ ] AUTO 모드: 시간 스케줄 내 작동 시작 확인
- [ ] 습도 조건 활성화 후 현재 습도 수치 표시 확인
- [ ] 현재 습도 >= 기준치일 때 포깅 건너뜀 로그 확인 (`tail -f` 로그)
- [ ] 현재 습도 < 기준치일 때 포깅 밸브 OPEN 확인
- [ ] Zone A는 포깅 습도 조건과 무관하게 정상 작동 확인
- [ ] 브라우저 닫아도 데몬이 제어 유지 확인

---

## 10. 주의사항

- `VALVE2_PIN = 19` 는 실제 릴레이 배선 핀에 맞게 ESP32 펌웨어에서 수정 후 재업로드 필요
- 포깅과 Zone A는 같은 `ctlr-0004`를 공유하므로 ESP32 1대가 두 밸브를 모두 제어
- 습도 센서(`ctlr-0001~0003`)가 오프라인이면 습도 조건 비활성화와 동일하게 동작 (시간 스케줄만 적용)
