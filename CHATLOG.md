# 작업 채팅 로그

세션 단위로 "무엇을 왜 요청했고 어떻게 처리했는지"를 짧게 남깁니다.
코드 변경의 상세 diff/원인 분석은 `CHANGELOG.md`를 참고하세요.

---

## 2026-07-31 — 구역A 메인밸브 유량계(YF-B10-S) 연동 + 자동 바이패스 전환

**대화 흐름**
1. 기존 ctlr-0004 밸브 제어 ESP32 코드에 YF-B10-S 유량센서(NPN 펄스, 2~50L/min, ≈595pulse/L, DC3.5~24V) 연결 가능 여부 질문 → 가능, GPIO4 인터럽트 + (권장)레벨시프팅으로 답변.
2. 실제 연결 코드 요청 → 기존 밸브(valve1/2/3) 코드에 펄스카운트 ISR + MQTT 발행(`flow1/rate` L/min, `flow1/total` 누적 L) 추가한 전체 코드 작성.
3. "분무수경 탭 기상청 API 위젯 바로 밑에 유량계 UI 배치, 메인밸브 작동중 5초 무유량이면 텔레그램 알림 + 자동 바이패스, retain으로 브라우저 꺼도 계속되는지" 질문
   → 브라우저만으로는 안 되고 Node.js 분무수경 데몬(`smartfarm_mist_daemon.cjs`)이 처리해야 함을 설명 후 구현:
   - UI: 유량 표시 카드 (`MistControl.tsx`, 기상청 위젯 바로 아래)
   - 데몬: `valve1/state` + `flow1/rate` 구독, 5초 연속 무유량 시 기존 수동 바이패스 버튼과 동일한 retain 토픽(`zone_a/bypass`)을 자동 발행 → valve1→valve3 전환, 텔레그램 알림(재알림 쿨다운 30분)
4. "유량센서에 의한 밸브 판단여부도 on/off 달아줘"
   → `zone_a/flowGuard` retain 토글 추가, **기본 OFF**(유량계 미배선 상태에서 밸브 열자마자 오작동 바이패스되는 것 방지). UI 스위치 + 데몬 게이팅.
5. "문제없다면 빌드하고 서버에 배포해줘"
   → `npm run build` → `dist/` rsync(`/var/www/html/smartfarm-ui-source/dist/`) → `smartfarm_mist_daemon.cjs` 개별 rsync → `tansaeng-mist.service` 재시작(정상 재연결·기존 AUTO 사이클 이어짐 확인) → apache2 reload → git 커밋 2건(`2026-07-31_1034` 기능, `2026-07-31_1045` dist/CHANGELOG/펌웨어 소스 동기화) 푸시.
6. "채팅로그, md 업데이트 및 git 롤백 버전 저장" → 본 파일 작성 + `stable-2026-07-31` 태그.

**변경 파일**
- `firmware/ctlr-0004_valve_main_fog_bypass.ino` — 유량계 펄스카운트/발행 추가 (레포를 실제 배포본과 동기화)
- `src/tabs/MistControl.tsx` — 유량 표시 카드, flowGuard on/off 스위치
- `daemons/smartfarm_mist_daemon.cjs` — 유량 감시, 자동 바이패스, 텔레그램 알림
- `dist/` — 빌드 산출물
- `CHANGELOG.md` — 상세 변경 기록 추가

**아직 필요한 수동 작업**
- ESP32 ctlr-0004에 실제로 유량센서를 GPIO4에 배선하고 펌웨어(`firmware/ctlr-0004_valve_main_fog_bypass.ino`)를 업로드해야 `flow1/rate` 값이 실제로 들어옴.
- 배선/업로드 확인 후 분무수경 탭에서 "유량 기반 밸브고장 자동판단" 스위치를 ON으로 전환.
- `config/alert_config.json`(서버 전용, git 미포함)에 `telegram.enabled/bot_token/chat_id`가 설정돼 있어야 실제 알림 발송됨 — 값 확인 필요.
- 자동 복귀(바이패스→메인 재전환)는 구현하지 않음(의도적) — 고장 원인 미확인 상태에서 자동으로 메인밸브에 되돌리는 것은 위험하다고 판단, 수동 "메인으로 복귀" 버튼으로만 복귀.

**커밋**: `2026-07-31_1034`, `2026-07-31_1045`
**롤백 태그**: `stable-2026-07-31`
