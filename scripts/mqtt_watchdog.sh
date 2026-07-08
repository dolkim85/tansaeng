#!/bin/bash
# tansaeng-mqtt 헬스 워치독
# 데몬이 60초마다 자체 하트비트 로그를 남기므로(7/8 추가), 정상 상태면 로그가 MAX_STALE을 넘겨 멈추지 않음.
# 그보다 오래 멈추면 '프로세스는 살아있으나 내부 정지(소켓 블록 등)'로 보고 재시작.
# 단 COOLDOWN 내 중복 재시작은 방지(정전 등 무한루프 차단).
LOG=/var/log/tansaeng-mqtt.log
WLOG=/var/log/tansaeng-mqtt-watchdog.log
STAMP=/run/tansaeng-mqtt-watchdog.last
MAX_STALE=90
COOLDOWN=600

[ -f "$LOG" ] || exit 0
age=$(( $(date +%s) - $(stat -c %Y "$LOG") ))
[ "$age" -le "$MAX_STALE" ] && exit 0

# 쿨다운: 최근 재시작 후 COOLDOWN초 안이면 건너뜀
if [ -f "$STAMP" ]; then
  since=$(( $(date +%s) - $(cat "$STAMP" 2>/dev/null || echo 0) ))
  [ "$since" -lt "$COOLDOWN" ] && exit 0
fi

echo "$(date '+%F %T') [WATCHDOG] mqtt 로그 ${age}s 정지 → tansaeng-mqtt 재시작" >> "$WLOG"
systemctl restart tansaeng-mqtt
date +%s > "$STAMP"
