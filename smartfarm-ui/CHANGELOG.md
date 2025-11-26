# 스마트팜 환경제어 시스템 - 변경 이력

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
