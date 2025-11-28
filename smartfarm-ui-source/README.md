# 탄생농원 스마트팜 환경제어 시스템

React + TypeScript + HiveMQ Cloud 기반의 프로덕션급 스마트팜 환경제어 시스템입니다.

## 🎯 주요 기능

### 1. 장치 제어 (Devices Control)
- **팬 제어**: 내부팬 앞/뒤, 천장팬 ON/OFF 제어
- **개폐기 제어**: 측창/천창개폐기 0~100% 슬라이더 제어
- **펌프 제어**: 양액탱크, 수막펌프, 히팅탱크 급수펌프 제어
- 모든 장치 상태는 localStorage에 자동 저장 (새로고침 후에도 유지)

### 2. 분무수경 설정 (Mist Control)
- 3개 Zone (상층/하층/테스트베드) 독립 제어
- 운전 모드: OFF / MANUAL / AUTO
- AUTO 모드: 분무 주기, 분무 시간, 운전 시간대, 야간 운전 설정

### 3. 환경 모니터링 (Environment)
- 실시간 센서 데이터: 공기/근권 온도/습도, EC, pH, 탱크 수위, CO₂, PPFD

### 4. 카메라 (Cameras)
- RTSP/HTTP 스트림 카메라 추가/삭제

### 5. 설정 (Settings)
- MQTT 연결 정보 확인
- 디바이스 레지스트리 확인
- 농장 기본 정보 설정

## 🚀 시작하기

### 1. 의존성 설치
```bash
npm install
```

### 2. 환경 변수 설정
.env 파일을 편집하여 HiveMQ Cloud 정보를 입력하세요.

### 3. 개발 서버 실행
```bash
npm run dev
```

### 4. 프로덕션 빌드
```bash
npm run build
```

## 📂 프로젝트 위치
/var/www/html/smartfarm-ui/

