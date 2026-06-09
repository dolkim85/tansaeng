# 스마트팜 환경제어 시스템 - 변경 이력

---

## stable-2026-06-09 (2026-06-09) — 분무수경 데몬 개선 + 메인밸브 진단

### 💧 분무수경 데몬 (공유 사이클 → 구역별 독립 사이클 리팩토링)
- **(a) 텔레그램 오발송 수정** — 습도 높아 건너뛸 때도 "분무 시작" 알림 가던 버그
  → 실제 분무한 구역만 알림 발송
- **(b) 구역별 독립 스케줄 (핵심)** — 기존엔 공유 사이클이 첫 구역 스케줄을
  모든 구역에 적용 → 동시 운영 시 다른 구역이 잘못된 분무 시간으로 작동
  → 각 구역이 자기 스케줄대로 독립 동작 (구역A 30/240s + 포깅 10/60s 동시 검증)
- **(c) 재연결 시 밸브 재동기화** — MQTT 재연결 시 활성 구역 사이클 자동 재시작
- 구조: `sharedCycle`(단일) → `zoneCycle[zoneId]`(구역별),
  isRunning/schedule 핸들러가 해당 구역만 시작/중지 (타구역 무영향)

### 🔧 메인밸브(ctlr-0004) 끊김 진단 + 펌웨어
- 원인: WiFi 신호 −42(최상급)·밴드 정상 → **전원 브라운아웃**이 유력
  (릴레이가 24VAC 솔레노이드 작동 시 전압강하/EMI로 ESP32 리셋)
- 펌웨어: `setKeepAlive(60)` + `WiFi.setSleep(false)` + WiFi 재연결 +
  retain=true + 원격 재시작 토픽 (`ctlr-0004/restart`)
- 하드웨어 권장: ESP32 전원 분리 + 1000µF 캐패시터 + RC 스너버

### 🏷️ 태그
- `stable-2026-06-09` (main 브랜치)

---

## smartfarm-ui-2026-06-08 (2026-06-08) — 히트펌프 명령 무시 버그 수정

### 🔥 버그 수정
- **냉각기/냉각순환펌프 명령이 전달 안 되던 문제**
  - 근본 원인: ESP32 펌웨어가 `if (mode == MANUAL_MODE && systemOn) setPump(...)`로
    게이트 처리하는데, UI가 `system/cmd`(시스템 전원)를 한 번도 발행하지 않음
    → `systemOn=false` 상태에서 pump/heater 명령을 ESP32가 무시
  - 수정: HP AUTO/MANUAL 모드 버튼에 `system/cmd=ON` 발행 추가(retain)
  - 검증: 게이트 닫은 뒤 MANUAL 토글 → system ON → pump/state=ON 확인

### 🏷️ 태그
- `smartfarm-ui-2026-06-08` (master 브랜치)

---

## stable-2026-06-08 (2026-06-08) — 네이버페이 결제 흐름 전면 개선

### 🛒 결제/주문
- **NPay 결제창 팝업 방식** — `window.location.href` → `window.open()` (500×700)
- **결제완료 후 주문 생성 실패 수정 (핵심 버그)**
  - 콜백 INSERT가 실제 테이블에 없는 컬럼명 사용 → `Unknown column 'buyer_name'` 으로 실패
  - orders.payment_method enum에 naverpay/kakao 추가, payment_id 컬럼 추가
  - 콜백 컬럼명 실제 테이블에 맞춤 (buyer_* → customer_*, status → payment_status 등)
  - `$_SESSION['last_order']` 연동 (order_complete.php가 세션에서 읽음)
- **주문상세 페이지(order_detail.php) 신규 생성** — 404 해결, 권한 체크 포함
- **주문완료 페이지 헤더 깨짐/여백 수정** — `.container` 클래스 충돌 분리, padding-top 제거
- **주문내역 버튼 경로 수정** — order.php(체크아웃) → profile.php#orders(실제 주문내역)
  - profile.php가 URL 해시 읽어 주문내역 탭 자동 열기
- **비로그인 장바구니 JS 오류 수정** — checkoutBtn null 체크

### 🏷️ 태그
- `stable-2026-06-08` (main 브랜치) / 상세: docs/changelog-2026-06-08.md

---

## stable-2026-06-07 (2026-06-07) — 데몬 안정화 + 슬라이더 개선

### 🔥 버그 수정
- **텔레그램 알림 복구** — 6/4 복구 시 누락된 config/alert_config.json 복원
- **데몬 재시작 시 예전 하드코딩 기본값으로 돌아가는 버그**
  - 하드코딩 기본 timePoints → 0% 안전값
  - initDelay 증가 (retain 수신 시간 확보), SIGTERM 시 설정 즉시 저장
- **슬라이더 이미지 업로드 권한 오류** — uploads/ 소유권 www-data로 변경
- **PHP upload_max_filesize** 2MB → 10MB
- **AI 식물분석 링크 경로** — plant-analysis → plant_analysis

### ✨ 기능 개선
- 메인페이지 히어로 슬라이더 풀너비 + 텍스트 오버레이 레이아웃
- 저장 버튼 옆 마지막 저장값 표시 UI (천창/측창/팬/HP)

### 🏷️ 태그
- `stable-2026-06-07` (main) / 상세: docs/changelog-2026-06-07.md

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
