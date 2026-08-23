# 이 폴더는 GovoroxSSLClient 1.3.2의 로컬 사본입니다

## 원본 출처

- 프로젝트명: `GovoroxSSLClient` (Arduino Library Manager상의 이름)
- 라이브러리 버전: 1.3.2 (`library.properties`의 `version=1.3.2`)
- 저장소: https://github.com/govorox/SSLClient.git
- 가져온 커밋: `7dd830679412d4d81384f178843fd38edff2c259`
- 라이선스: GNU GPLv3 (`LICENSE` 파일 그대로 동봉, 원본 수정 없음)
- 원 저작권자: Evandro Copercini(2017, Apache 2.0 원 코드 기반), Vadim Govorovski(2019 추가분)

## 왜 여기 있는가 (vendoring 이유)

`mqtt_manager.h`가 `#define W5500_WORKAROUND` 후 `#include <SSLClient.h>`를 했지만,
Arduino 빌드에서 이 매크로는 **스케치 자신의 translation unit에만 적용되고, 별도로
컴파일되는 라이브러리의 `.cpp` 파일(`ssl__client.cpp`)에는 전달되지 않습니다.**
실측(`arduino-cli compile --verbose`로 확인한 실제 gcc 커맨드라인)으로 `ssl__client.cpp`
컴파일 시 `-DW5500_WORKAROUND`도 `-D_W5500_H_`도 전혀 존재하지 않음을 확인했습니다
— 즉 원본 Arduino Library Manager 설치본에서는 이 프로젝트가 필요로 하는
W5500 handshake 재시도 우회 코드(`ssl__client.cpp`의 `#if defined(_W5500_H_) ||
defined(W5500_WORKAROUND)` 블록)가 **바이너리에서 통째로 빠져 있었습니다.**

전역 라이브러리 폴더(`~/Arduino/libraries/SSLClient/`, Windows는
`Documents/Arduino/libraries/SSLClient/`)를 직접 고치면 Arduino IDE/Library
Manager가 라이브러리를 업데이트할 때 되돌아가고, 다른 프로젝트에도 영향을 주며,
git으로 재현/보존할 수 없습니다. 그래서 이 프로젝트 전용으로 `firmware/
main_eth_8di_8ro/src/SSLClient/`에 소스를 통째로 복사해 넣고 이 사본만 수정했습니다.
Arduino의 스케치 빌드 규칙상 `<스케치폴더>/src/` 아래의 `.c/.cpp` 파일은 스케치
자신의 소스로 자동 컴파일되므로, 별도 설정 없이 `main_eth_8di_8ro.ino`를 열어
컴파일 버튼만 눌러도 이 사본이 사용됩니다.

## 이 사본에서 변경한 것 (원본 대비 diff 요약)

1. **`ssl__client.cpp` 최상단**: `#define W5500_WORKAROUND`를 무조건 활성화(원본은
   외부에서 정의해주길 기대했으나 그 경로가 실제로 동작하지 않았음).
2. **`ssl__client.cpp` `start_ssl_client()`**:
   - 함수 진입 시 `Serial.println("[TLS] W5500_WORKAROUND active")`를 무조건 출력
     (ESP-IDF `log_*` 매크로가 아니라 순수 `Serial.println`이라 `Core Debug Level`
     설정과 무관하게 항상 보임 — 이 로그가 실제로 뜨는지 자체가 "이 사본이 진짜
     쓰이고 있다"는 증거가 됨).
   - 각 단계(TCP 연결/난수생성/TLS 기본설정/CA·PSK인증/클라이언트인증서/SNI
     설정/IO콜백·타임아웃/handshake/인증서검증) 실패 시 어느 단계였는지를
     `const char* stepName`에 기록.
   - 원본은 실패 시 항상 `return 0;`으로 실제 에러코드를 버렸으나, 이 사본은
     `return ret;`로 실제 mbedTLS/내부 에러코드를 그대로 반환하고, 새로 추가한
     `outFailedStep` 출력 파라미터로 실패 단계 이름도 함께 돌려줌.
3. **`ssl__client.h`**: `start_ssl_client()` 시그니처에 `const char **outFailedStep
   = nullptr` 파라미터 추가(기본값 있어 기존 호출부와 호환).
4. **`SSLClient.h`/`SSLClient.cpp`**: `_lastFailedStep` 멤버와
   `const char* lastFailedStep() const` 공개 메서드 추가. `connect(const char*,...)`
   가 `start_ssl_client()`에 `&_lastFailedStep`을 넘겨 받아온 값을 저장.
   `_lastError`에는 이제 (원본처럼 뭉개진 0이 아니라) 실제 실패 코드가 담김.

그 외 로직/동작은 원본과 동일합니다(암묵적 TLS 정책, 인증서 처리, 소켓 처리 등
변경 없음). SNI(hostname) 전달, `setInsecure()` 동작, 평문 폴백 없음 등은 원본
그대로입니다.

## 업스트림 갱신 시 주의사항

향후 GovoroxSSLClient가 새 버전을 내면, 이 폴더 전체를 새 버전으로 교체한 뒤
위 4가지 변경사항을 다시 적용해야 합니다(자동 병합 스크립트 없음 — 파일 수가
적어 수동 diff로 충분).
