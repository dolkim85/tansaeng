#!/usr/bin/env bash
# shared/protocol_version.h(기준 원장)를 두 스케치 폴더의 사본으로 동기화한다.
# 이 스크립트가 필요한 이유: Arduino IDE(특히 Windows)가 스케치 폴더 밖을
# 가리키는 "../../shared/..." include를 안정적으로 찾지 못해, 각 스케치
# 폴더 안에 protocol_version.h를 그대로 복사해 두는 방식으로 바꿨다
# (2026-08-23, docs/open-decisions.md 참고).
#
# 사용법:
#   scripts/sync_protocol_version.sh          — 복사 실행(기준 원장 → 두 사본)
#   scripts/sync_protocol_version.sh --check  — 세 파일이 동일한지만 검사(복사 안 함),
#                                                다르면 0이 아닌 코드로 종료
#
# shared/protocol_version.h를 고친 뒤에는 반드시 이 스크립트를(옵션 없이) 실행해
# 두 사본을 갱신하세요.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

CANONICAL="$ROOT_DIR/shared/protocol_version.h"
COPIES=(
  "$ROOT_DIR/firmware/arm_relay_6ch/protocol_version.h"
  "$ROOT_DIR/firmware/main_eth_8di_8ro/protocol_version.h"
)

if [[ ! -f "$CANONICAL" ]]; then
  echo "[FAIL] 기준 원장을 찾을 수 없음: $CANONICAL" >&2
  exit 1
fi

if [[ "${1:-}" == "--check" ]]; then
  status=0
  for copy in "${COPIES[@]}"; do
    if [[ ! -f "$copy" ]]; then
      echo "[FAIL] 사본이 없음: $copy" >&2
      status=1
      continue
    fi
    if ! diff -q "$CANONICAL" "$copy" >/dev/null 2>&1; then
      echo "[FAIL] 불일치: $copy 가 $CANONICAL 와 다름 (scripts/sync_protocol_version.sh 를 옵션 없이 실행해 동기화하세요)" >&2
      status=1
    else
      echo "[OK] $copy"
    fi
  done
  exit $status
fi

for copy in "${COPIES[@]}"; do
  cp "$CANONICAL" "$copy"
  echo "[OK] 동기화: $copy"
done

echo "[OK] 모든 사본이 기준 원장과 동일합니다."
