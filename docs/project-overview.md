# 탄생 (tansaeng) 프로젝트 전체 문서

**최종 수정일:** 2026-05-06  
**서비스 주소:** https://www.tansaeng.com  
**GitHub:** https://github.com/dolkim85/tansaeng.git

---

## 목차

1. [프로젝트 개요](#1-프로젝트-개요)
2. [서버 및 인프라](#2-서버-및-인프라)
3. [전체 디렉토리 구조](#3-전체-디렉토리-구조)
4. [웹사이트 (PHP)](#4-웹사이트-php)
5. [스마트팜 UI (React/Vite)](#5-스마트팜-ui-reactvite)
6. [서버 데몬 구조](#6-서버-데몬-구조)
7. [ESP32 하드웨어 제어](#7-esp32-하드웨어-제어)
8. [MQTT 토픽 전체 목록](#8-mqtt-토픽-전체-목록)
9. [데이터베이스](#9-데이터베이스)
10. [배포 방법](#10-배포-방법)
11. [주요 API 목록](#11-주요-api-목록)
12. [아키텍처 주의사항 및 알려진 버그 이력](#12-아키텍처-주의사항-및-알려진-버그-이력)

---

## 1. 프로젝트 개요

**탄생(탄소를 생각하는 농원)**은 스마트팜 운영 + 농산물 쇼핑몰이 결합된 웹 서비스입니다.

| 기능 영역 | 설명 |
|---|---|
| 쇼핑몰 | 농산물 판매, 장바구니, 주문, 네이버페이 결제 |
| 스마트팜 UI | 온습도 모니터링, 장치 제어, 분무수경·포깅 자동화 |
| 관리자 | 상품/주문/회원/게시판/스마트팜 설정 관리 |
| IoT | ESP32 + MQTT 기반 밸브·팬·히트펌프·천창·측창 제어 |

---

## 2. 서버 및 인프라

| 항목 | 내용 |
|---|---|
| 서버 | Gabia 클라우드 |
| IP | 1.201.17.34 |
| OS | Ubuntu (Apache/2.4.58) |
| 웹루트 | `/var/www/html` |
| DB | MySQL — `tansaeng_db` |
| Node.js | 데몬 실행용 |
| MQTT 브로커 | HiveMQ Cloud (TLS 8883) |
| SSH 접속 | `ssh -i SSH_KeyPair-250917163334.pem root@1.201.17.34` |

### Apache 주요 설정

```
/var/www/html/                         ← PHP 웹사이트 루트
/var/www/html/smartfarm-ui-source/dist/ ← 스마트팜 React 앱
```

- Apache Alias: `/smartfarm-ui` → `/var/www/html/smartfarm-ui-source/dist`
- Vite base: `/smartfarm-ui/` (변경 금지)

---

## 3. 전체 디렉토리 구조

```
tansaeng_new/                        ← 로컬 개발 루트 (= 서버 /var/www/html/)
├── index.php                        ← 메인 진입점
├── main.php                         ← 메인 페이지
├── config/
│   ├── config.php                   ← 앱 상수 정의
│   ├── database.php                 ← DB 연결
│   ├── device_settings.json         ← 스마트팜 장치 설정 (데몬 참조)
│   ├── realtime_sensor.json         ← 실시간 센서 캐시 (autocontrol 데몬 작성)
│   ├── alert_config.json            ← 텔레그램 알림 설정
│   └── valve_schedule.json          ← 밸브 스케줄 백업
├── classes/                         ← PHP 클래스
│   ├── Auth.php                     ← 인증/세션
│   ├── User.php                     ← 회원 관리
│   ├── Cart.php                     ← 장바구니
│   ├── Order.php                    ← 주문
│   ├── Payment.php                  ← 결제
│   ├── NaverPay.php                 ← 네이버페이 연동
│   ├── PlantAnalysis.php            ← 식물 분석
│   ├── SocialLogin.php              ← 소셜 로그인
│   ├── Database.php                 ← DB 싱글턴
│   ├── Mailer.php                   ← 이메일 발송
│   └── Admin.php                    ← 관리자
├── api/                             ← REST API (PHP)
│   ├── auth/                        ← 로그인/회원가입/소셜
│   ├── products.php                 ← 상품 조회
│   ├── cart.php                     ← 장바구니 CRUD
│   ├── order.php                    ← 주문 처리
│   ├── payment/                     ← 결제 처리
│   ├── device_status.php            ← ESP32 온라인 상태
│   └── smartfarm/                   ← 스마트팜 API
├── pages/                           ← 프론트 PHP 페이지
│   ├── auth/                        ← 로그인·회원가입
│   ├── products/                    ← 상품 목록·상세
│   ├── store/                       ← 스토어
│   ├── board/                       ← 게시판
│   ├── payment/                     ← 결제 페이지
│   ├── support/                     ← 고객지원
│   └── plant_analysis/              ← 식물 분석
├── admin/                           ← 관리자 패널
│   ├── index.php
│   ├── products/                    ← 상품 관리
│   ├── orders/                      ← 주문 관리
│   ├── customers/                   ← 회원 관리
│   ├── board/                       ← 게시판 관리
│   ├── smartfarm/                   ← 스마트팜 설정
│   └── settings/                    ← 사이트 설정
├── daemons/                         ← Node.js 서버 데몬
│   ├── smartfarm_auto_control_daemon.cjs  ← 팬/히트펌프/천창/측창 AUTO
│   └── smartfarm_mist_daemon.cjs          ← 분무수경/포깅 AUTO
├── scripts/
│   └── mqtt_daemon.php              ← PHP MQTT 데몬 (센서 DB 저장)
├── sql/                             ← DB 스키마
├── uploads/                         ← 업로드 파일
├── logs/                            ← 데몬 로그
└── smartfarm-ui-source/
    └── smartfarm-ui-source/         ← React/Vite 스마트팜 UI 소스
        ├── src/
        │   ├── App.tsx
        │   ├── types.ts
        │   ├── tabs/                ← 탭별 컴포넌트
        │   ├── components/
        │   ├── mqtt/mqttClient.ts
        │   ├── api/deviceControl.ts
        │   ├── store/usePersistedStore.ts
        │   └── config/
        └── dist/                    ← 빌드 결과 (배포 대상)
```

---

## 4. 웹사이트 (PHP)

### 기술 스택

- PHP 8.x + Apache
- MySQL (PDO)
- Tailwind CSS (CDN)
- Vanilla JS

### 주요 기능

#### 회원 시스템
- 이메일/비밀번호 가입, 로그인
- 소셜 로그인: 카카오, 네이버, 구글
- 권한 레벨: 일반(1), 식물분석(2), 관리자(9)
- 세션 무제한 유지

#### 쇼핑몰
- 상품 목록/상세/카테고리
- 장바구니 (세션 + DB 병합)
- 주문·결제 (네이버페이 결제형)
- 재고 관리

#### 네이버페이 연동

| 환경 | API 도메인 |
|---|---|
| 개발 | `dev-pay.paygate.naver.com` |
| 운영 | `pay.paygate.naver.com` |
| 결제창(개발) | `test-m.pay.naver.com` |
| 결제창(운영) | `m.pay.naver.com` |

#### 게시판
- 카테고리별 게시판
- 댓글, 파일 첨부

#### 관리자 패널
- 상품 CRUD, 이미지 업로드
- 주문 상태 관리
- 회원 권한 변경
- 스마트팜 설정 (장치 범위, 알림)

### 주요 설정 테이블

```
site_settings   ← 사이트 전반 설정 (contact_settings 아님 — 주의)
```

---

## 5. 스마트팜 UI (React/Vite)

### 접속 주소

`https://www.tansaeng.com/smartfarm-ui/`

### 기술 스택

- React 18 + TypeScript
- Vite (base: `/smartfarm-ui/`)
- Tailwind CSS
- MQTT.js (HiveMQ Cloud WebSocket)

### 탭 구성

| 탭 | 파일 | 주요 기능 |
|---|---|---|
| 대시보드 | `Dashboard.tsx` | 실시간 온습도, 24시간 차트, 장치 상태 요약 |
| 장치제어 | `DevicesControl.tsx` | 팬·히트펌프·천창·측창 MANUAL/AUTO 제어 |
| 분무수경 | `MistControl.tsx` | Zone A~E + 포깅 밸브 제어 (MANUAL/AUTO/습도조건) |
| 환경 | `Environment.tsx` | 센서 데이터 상세 조회 |
| 카메라 | `Cameras.tsx` | Tapo 카메라 라이브 스트림 |
| 설정 | `Settings.tsx` | 팜 정보, 카메라 IP, 알림 설정 |

### 상태 관리

`usePersistedStore.ts` — localStorage 기반 퍼시스턴트 상태

| 스토어 | 내용 |
|---|---|
| `usePersistedMistZones` | 분무수경 Zone 설정 (Zone A~E + 포깅) |
| `usePersistedCameras` | 카메라 IP 설정 |
| `usePersistedFarmSettings` | 팜 이름 등 기본 정보 |

### MQTT 클라이언트

`mqtt/mqttClient.ts`

- HiveMQ Cloud WebSocket (wss://...hivemq.cloud:8884)
- 브라우저에서 직접 구독/발행
- retain 토픽으로 다기기 상태 동기화

### 분무수경 Zone 설정

| Zone | ID | Controller | Valve | 비고 |
|---|---|---|---|---|
| Zone A | `zone_a` | `ctlr-0004` | `valve1` | 운영 중 |
| Zone B | `zone_b` | `ctlr-0005` | `valve1` | 대기 |
| Zone C | `zone_c` | `ctlr-0006` | `valve1` | 대기 |
| Zone D | `zone_d` | `ctlr-0007` | `valve1` | 대기 |
| Zone E | `zone_e` | `ctlr-0008` | `valve1` | 대기 |
| 포깅 | `fogging` | `ctlr-0004` | `valve2` | 운영 중, 습도 조건 지원 |

#### 포깅 습도 조건 로직

```
작동 조건: 시간 스케줄 범위 내  AND  현재 팜 평균 습도 < 기준치(%)
```

- 현재 습도 표시: `/api/smartfarm/get_realtime_sensor_data.php` 10초 폴링
- 기본 기준 습도: 70%
- 습도 충족 시 포깅만 건너뜀 (Zone A~E 영향 없음)

---

## 6. 서버 데몬 구조

서버는 **데몬 방식으로 항상 live 유지** — 브라우저를 닫아도 AUTO 제어 동작

### 서비스 목록

| 서비스명 | 언어 | 파일 | 역할 |
|---|---|---|---|
| `tansaeng-autocontrol` | Node.js | `daemons/smartfarm_auto_control_daemon.cjs` | 팬/히트펌프/천창/측창 AUTO |
| `tansaeng-mqtt` | PHP | `scripts/mqtt_daemon.php` | 센서 DB 저장, 장치 상태 감시, 알림 |
| `tansaeng-mist` | Node.js | `daemons/smartfarm_mist_daemon.cjs` | 분무수경/포깅 AUTO 제어 |
| `tansaeng-heartbeat` | Node.js | `daemons/mqtt_heartbeat_daemon.cjs` | MQTT heartbeat |

### 데몬 관리 명령

```bash
# 상태 확인
systemctl status tansaeng-autocontrol.service
systemctl status tansaeng-mist.service
systemctl status tansaeng-mqtt.service

# 재시작
systemctl restart tansaeng-autocontrol.service
systemctl restart tansaeng-mist.service

# 로그 확인
tail -f /var/log/tansaeng-autocontrol.log
journalctl -u tansaeng-mist -f
```

### autocontrol 데몬 (`smartfarm_auto_control_daemon.cjs`)

제어 대상:

| 장치 | 변수 | 기준 센서 |
|---|---|---|
| `hp_pump` (순환펌프) | `avgTemp` | 팜 평균 온도 (ctlr-0001~0003) |
| `hp_heater` (냉각기) | `waterTemp` | 물온도 (ctlr-heat-001/water) |
| `hp_fan` (장치실 팬) | `roomTemp` | 장치실 공기온도 (ctlr-heat-001/air) |
| 팬 | `avgTemp` | 팜 평균 온도 |
| 천창 | `avgTemp` | 팜 평균 온도 |
| 측창 | `avgTemp` | 팜 평균 온도 |

`realtime_sensor.json` 파일 — **이 데몬만 쓰기**  
(PHP 데몬의 `updateRealtimeSensorCache()` 주석 처리 상태 유지 — race condition 방지)

### mist 데몬 (`smartfarm_mist_daemon.cjs`)

- Zone 단위 분무 사이클 제어 (공유 사이클 — 모든 활성 존 동시 ON/OFF)
- MQTT retain 토픽으로 isRunning / schedule / humidityConfig 수신
- 포깅 존: 습도 조건 체크 후 분무 여부 결정
- 이벤트 발생 시 텔레그램 알림 전송
- DB에 분무 이벤트 로그 저장 (`mist_logs` 테이블)

---

## 7. ESP32 하드웨어 제어

### 컨트롤러 목록

| ID | 위치/역할 | 연결 장치 |
|---|---|---|
| `ctlr-0001` | 팜 front | DHT 온습도 센서 |
| `ctlr-0002` | 팜 back | DHT 온습도 센서 |
| `ctlr-0003` | 팜 top | DHT 온습도 센서 |
| `ctlr-0004` | 분무/포깅 | valve1(분무·핀18), valve2(포깅·핀19) |
| `ctlr-0005` | Zone B 분무 | valve1 |
| `ctlr-0006` | Zone C 분무 | valve1 |
| `ctlr-0007` | Zone D 분무 | valve1 |
| `ctlr-0008` | Zone E 분무 | valve1 |
| `ctlr-heat-001` | 장치실 | 물온도/공기온도 센서 |
| `ctlr-0012` | 팬 제어 | fan1, fan2 ... |
| `ctlr-0021` | 천창/측창 | 개폐 장치 |

### MQTT 연결 설정 (공통)

```cpp
MQTT_BROKER   = "22ada06fd6cf4059bd700ddbf6004d68.s1.eu.hivemq.cloud"
MQTT_PORT     = 8883  // TLS
MQTT_USERNAME = "esp32-client-01"
MQTT_PASSWORD = "Qjawns3445"
```

### ctlr-0004 (분무 + 포깅 2채널)

```cpp
VALVE1_PIN = 18  // 분무 (Zone A)
VALVE2_PIN = 19  // 포깅 (실제 배선 핀 확인 후 수정)
```

### ESP32 표준 패턴

- 60초마다 `{ctlr-id}/status` 에 `"online"` heartbeat
- LWT(Last Will Testament): 연결 끊기면 자동 `"offline"` 발행
- 명령 수신 시 상태 토픽 retain 발행

---

## 8. MQTT 토픽 전체 목록

### 센서 토픽 (ESP32 → 서버)

```
tansaeng/ctlr-0001/{dht*}/temperature
tansaeng/ctlr-0001/{dht*}/humidity
tansaeng/ctlr-0002/{dht*}/temperature
tansaeng/ctlr-0002/{dht*}/humidity
tansaeng/ctlr-0003/{dht*}/temperature
tansaeng/ctlr-0003/{dht*}/humidity
tansaeng/ctlr-heat-001/water/temperature   ← 물온도
tansaeng/ctlr-heat-001/air/temperature     ← 장치실 공기온도
tansaeng/{ctlr-id}/status                  ← online / offline
```

### 분무수경 제어 토픽

```
tansaeng/mist-control/{zone_id}/isRunning      ← true/false (retain)
tansaeng/mist-control/{zone_id}/schedule       ← JSON (retain)
tansaeng/mist-control/{zone_id}/timerState     ← JSON {state, timestamp} (retain)
tansaeng/mist-control/fogging/humidityConfig   ← JSON {enabled, threshold} (retain)

tansaeng/ctlr-0004/valve1/cmd    ← OPEN/CLOSE
tansaeng/ctlr-0004/valve1/state  ← OPEN/CLOSE (retain)
tansaeng/ctlr-0004/valve2/cmd    ← OPEN/CLOSE  (포깅)
tansaeng/ctlr-0004/valve2/state  ← OPEN/CLOSE (retain)
```

### 장치 제어 토픽 (히트펌프, 팬, 천창, 측창)

```
tansaeng/hp-control/mode            ← AUTO/MANUAL (retain)
tansaeng/hp-control/autoActive      ← true/false (retain)
tansaeng/hp-control/ranges          ← JSON (retain)

tansaeng/fan-control/mode           ← AUTO/MANUAL (retain)
tansaeng/fan-control/autoActive     ← true/false (retain)
tansaeng/fan-control/ranges         ← JSON (retain)
tansaeng/fan-control/autoSensor     ← 기준 센서 ID (retain)

tansaeng/side-control/mode          ← AUTO/MANUAL (retain)
tansaeng/side-control/autoActive    ← true/false (retain)
tansaeng/side-control/ranges        ← JSON (retain)
tansaeng/side-control/autoSensor    ← 기준 센서 ID (retain)

tansaeng/{ctlr-id}/{device-id}/cmd   ← ON/OFF 명령
tansaeng/{ctlr-id}/{device-id}/state ← ON/OFF 상태 (retain)
```

---

## 9. 데이터베이스

**DB명:** `tansaeng_db`

| 테이블 | 설명 |
|---|---|
| `users` | 회원 정보, 권한 레벨 |
| `products` | 상품 목록, 재고 |
| `categories` | 상품 카테고리 |
| `cart` | 장바구니 |
| `orders` | 주문 헤더 |
| `order_items` | 주문 상품 상세 |
| `payments` | 결제 정보 |
| `boards` | 게시판 글 |
| `board_categories` | 게시판 카테고리 |
| `board_comments` | 댓글 |
| `sensor_data` | 시계열 온습도 데이터 |
| `device_status` | ESP32 온라인 상태 이력 |
| `device_command_logs` | 장치 명령 로그 |
| `device_command_queue` | 명령 큐 |
| `device_ranges` | 장치별 AUTO 제어 범위 |
| `mist_logs` | 분무 이벤트 로그 |
| `site_settings` | 사이트 설정 (메인 설정 테이블) |
| `smartfarm_screen_settings` | 스마트팜 화면 설정 |
| `plant_analysis_logs` | 식물 분석 요청 이력 |
| `plant_analysis_results` | 식물 분석 결과 |
| `user_login_logs` | 로그인 이력 |
| `contact_inquiries` | 문의 내역 |

> ⚠️ 메인 설정 테이블은 **`site_settings`** — `contact_settings` 아님

---

## 10. 배포 방법

### 스마트팜 UI 배포 (표준 절차)

```bash
# 1. 빌드
cd /home/spinmoll/tansaeng_new/smartfarm-ui-source/smartfarm-ui-source/
npm run build

# 2. 서버 전송 (반드시 이 경로로)
sshpass -p 'qjawns3445' rsync -avz --delete \
  dist/ \
  root@1.201.17.34:/var/www/html/smartfarm-ui-source/dist/

# ⚠️ /var/www/html/ 루트에 배포하면 PHP 사이트 파일이 깨짐

# 3. Apache 리로드
sshpass -p 'qjawns3445' ssh root@1.201.17.34 "systemctl reload apache2"
```

### 데몬 배포

```bash
# 파일 전송
sshpass -p 'qjawns3445' scp daemons/smartfarm_mist_daemon.cjs \
  root@1.201.17.34:/var/www/html/daemons/

# 서비스 재시작 (파일만 바꿔도 메모리에 이전 버전 유지 → 반드시 restart)
sshpass -p 'qjawns3445' ssh root@1.201.17.34 "systemctl restart tansaeng-mist.service"
```

### Git 커밋 규칙

- 커밋 메시지: **날짜+시간** 형식 (`2026-05-06_1030`)
- 작업 후 반드시 `git commit` → `git push`
- `.env`는 git 미포함 → 서버 `/var/www/html/.env` 별도 관리

---

## 11. 주요 API 목록

### 스마트팜 API

| 경로 | 메서드 | 설명 |
|---|---|---|
| `/api/smartfarm/get_realtime_sensor_data.php` | GET | 실시간 온습도 (front/back/top) |
| `/api/smartfarm/save_mist_log.php` | POST | 분무 이벤트 DB 저장 |
| `/api/smartfarm/get_mist_logs.php` | GET | 분무 로그 조회 |
| `/api/device_status.php` | GET | ESP32 온라인 상태 |
| `/api/device_control.php` | POST | 장치 설정 저장 |

### 쇼핑몰 API

| 경로 | 메서드 | 설명 |
|---|---|---|
| `/api/products.php` | GET | 상품 목록/상세 |
| `/api/cart.php` | GET/POST/DELETE | 장바구니 |
| `/api/order.php` | POST | 주문 생성 |
| `/api/payment/` | POST | 결제 처리 |

### 실시간 센서 응답 구조

```json
{
  "success": true,
  "data": {
    "front": { "temperature": 25.0, "humidity": 65.0, "lastUpdate": "2026-05-06 10:00:00" },
    "back":  { "temperature": 24.5, "humidity": 63.0, "lastUpdate": "2026-05-06 10:00:00" },
    "top":   { "temperature": 27.0, "humidity": 60.0, "lastUpdate": "2026-05-06 10:00:00" }
  },
  "timestamp": "2026-05-06 10:00:01"
}
```

---

## 12. 아키텍처 주의사항 및 알려진 버그 이력

### realtime_sensor.json 소유권

- **Node.js autocontrol 데몬만 쓰기** (`writeSensorFile()`)
- PHP 데몬의 `updateRealtimeSensorCache()` 호출 주석 처리 상태 유지
- **이유:** 두 프로세스 동시 read-modify-write → race condition → 온도 null 덮어쓰기 버그 발생

### 히트펌프 기준 센서 혼동 금지

| 장치 | 반드시 사용할 센서 | 사용 금지 |
|---|---|---|
| `hp_heater` (냉각기) | `waterTemp` (물온도) | `avgTemp` 사용 금지 |
| `hp_pump` (순환펌프) | `avgTemp` (팜 평균) | — |
| `hp_fan` (장치실 팬) | `roomTemp` (장치실 공기온도) | — |

`hp_heater`에 `avgTemp` 사용 시 물온도 범위 무관하게 오작동하는 버그 발생 이력 있음

### 모드 전환 시 장치 OFF 필수

- MANUAL 전환, 작동멈춤 버튼 클릭 시 → hp_pump/hp_heater/hp_fan 3개 모두 OFF 명령 전송
- `setHpDeviceStates` 즉시 업데이트 함께 해야 UI 스위치가 OFF로 표시됨
- **이유:** OFF 없으면 AUTO가 켠 장치가 수동 전환 후에도 계속 켜진 채 방치

### 센서값 UI null 깜빡임 방지

올바른 패턴:
```ts
setFarmSensors(prev => ({
  front: result.data.front?.temperature ?? prev.front,
  // null 수신 시 이전 값 유지
}));
```

잘못된 패턴:
```ts
setFarmSensors({ front: result.data.front.temperature });
// null 수신 시 "—" 깜빡임 발생
```

### 데몬 코드 수정 후 반드시 restart

Node.js/PHP는 시작 시 파일을 메모리에 로드.  
파일을 수정하거나 삭제해도 **프로세스는 이전 코드로 계속 실행됨**.  
→ 수정 반영을 위해 반드시 `systemctl restart` 필요

### Vite 빌드 후 index.html 덮어쓰기 주의

- `npm run build` 결과물에 `index.html` 포함
- 서버 루트(`/var/www/html/`)에 직접 배포 시 `index.php` 덮어씀 → PHP 사이트 다운
- 반드시 `/var/www/html/smartfarm-ui-source/dist/` 경로에만 배포

### .env 관리

- `.env`는 git에 포함되지 않음
- 서버: `/var/www/html/.env` 별도 유지
- 로컬: `/home/spinmoll/tansaeng_new/.env`

---

*본 문서는 `docs/fogging-system-implementation.md` 에 포깅 시스템 상세 구현 내용이 별도 기술되어 있습니다.*
