# 2026-06-07 작업 기록

## 1. 텔레그램 알림 복구 — config/alert_config.json 누락

**커밋:** `e6b139a` (2026-06-07_0900)

**현상:** 6월 4일 이후 텔레그램 알림이 전송되지 않음.

**원인:** 6/4 stable-2026-04-27 서버 복구 시 rsync --delete로 배포하면서
`config/alert_config.json`이 삭제됨. 세 데몬 모두 이 파일에서 텔레그램
설정을 읽으므로 알림 전송 불가 상태가 됨.

**복구:** `scripts/alert_config.json`(원본) → `config/alert_config.json`으로 복사.
텔레그램 전송 정상 확인.

**재발 방지:** CLAUDE.md 배포 exclude 목록에 `config/alert_config.json` 추가.

---

## 2. 데몬 재시작 시 예전 하드코딩 기본값으로 돌아가는 버그 수정

**커밋:** `db4de80` (2026-06-07_2105) — main  
**커밋:** `700d818` (2026-06-07_2130) — main

**현상:** 천창 스크린이 데몬 재시작 후 `08:00→30%, 12:00→80%, 18:00→0%`로 돌아감.

**원인:** `daemon_settings.json` 없는 상황(서버 복구 후 등)에서 MQTT retain이
initDelay(10초) 내 도착 못하면 하드코딩 기본값으로 첫 제어 실행됨.

**수정 3가지:**
1. 하드코딩 기본 timePoints → `[{time:'00:00', rate:0}]` (안전값, 스크린 안 움직임)
2. initDelay: 파일 있을 때 3초→8초, 없을 때 10초→20초 (retain 수신 시간 확보)
3. SIGTERM/SIGINT 종료 시 설정 즉시 저장 (debounce 우회, 항상 최신값 보존)

---

## 3. 저장 버튼 옆 마지막 저장값 표시 UI

**커밋:** `d3e6ef9` (2026-06-07_2130) — master (smartfarm-ui)

**내용:** 장치 제어 탭의 저장 버튼에 마지막 저장값 스냅샷 표시.

| 위치 | 표시 내용 |
|---|---|
| 천창 포인트 저장 버튼 아래 | `✅ 09:30 저장 — 00:00→0%, 14:00→100%` |
| 측창 포인트 저장 버튼 아래 | `✅ 09:30 저장 — 20°C→10%, 23°C→30%` |
| 팬/HP 주간야간 저장 버튼 옆 | `✅ 저장됨 09:30` / `미저장` 안내 |

---

## 4. 메인페이지 슬라이더 수정

**커밋:** `ee3cfa3`, `5ea98df`, `f4b26b5`, `050d759` (2026-06-07_2200~2300)

### 4-1. 이미지 잘림 수정
- DB `hero_media_list`에 없는 파일 참조 → 기존 slider_ 이미지로 교체
- `object-fit: cover` → `contain` → 다시 `cover` (aspect-ratio 적용으로 해결)
- `hero-image`: `min-height` 제거 → `aspect-ratio: 870/727` (이미지 실제 비율)

### 4-2. 풀너비 슬라이더 레이아웃으로 변경
- 기존: 좌측 텍스트 + 우측 슬라이더 2단 분할
- 변경: 미디어 관리 미리보기와 동일한 풀너비 슬라이더 + 텍스트 오버레이
- `hero-overlay`: 하단 그라디언트 + 제목/자막/버튼 표시
- 모바일 높이 280px, 텍스트 크기 축소 대응

### 4-3. 슬라이더 업로드 오류 수정
- **원인 1:** `uploads/` 소유자 `ubuntu` → PHP(`www-data`) 쓰기 불가
- **수정:** `chown -R www-data:www-data /var/www/html/uploads/`
- **원인 2:** `upload_max_filesize: 2MB` 제한
- **수정:** `10MB`로 상향, `post_max_size: 20MB`

### 4-4. AI 식물분석 링크 경로 수정
- `/pages/plant-analysis/`(하이픈) → `/pages/plant_analysis/`(언더스코어)
- index.php 내 2곳 수정

---

## 5. uploads/ 폴더 배포 exclude 규칙 추가

**파일:** `~/.claude/CLAUDE.md`

6/4 서버 복구 시 `uploads/` 폴더가 rsync --delete로 삭제되어
슬라이더 이미지가 모두 삭제된 사고 재발 방지.
전체 배포 시 필수 exclude 목록에 `uploads/` 추가.

---

## 버전 태그

| 태그 | 브랜치 | 내용 |
|---|---|---|
| `stable-2026-06-07` | main | 텔레그램 복구 + 데몬 기본값 수정 + 슬라이더 전면 개선 |
| `smartfarm-ui-2026-06-07` | master | 저장값 표시 UI |
