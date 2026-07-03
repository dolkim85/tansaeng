#!/bin/bash
# tansaeng 데몬 재시작 일일 리포트 → 텔레그램 (매일 cron)
CONF=/var/www/html/scripts/alert_config.json
TOKEN=$(python3 -c "import json;print(json.load(open('$CONF'))['telegram']['bot_token'])" 2>/dev/null)
CHAT=$(python3 -c "import json;print(json.load(open('$CONF'))['telegram']['chat_id'])" 2>/dev/null)
[ -z "$TOKEN" ] && exit 0

cnt() { journalctl -u "$1" --since '24 hours ago' 2>/dev/null | grep -c "Started $1" || true; }
AC=$(cnt tansaeng-autocontrol); MQ=$(cnt tansaeng-mqtt); MI=$(cnt tansaeng-mist)
WD=$(awk -v d="$(date -d '24 hours ago' '+%Y-%m-%d %H:%M:%S')" '$0 >= d' /var/log/tansaeng-mqtt-watchdog.log 2>/dev/null | grep -c 재시작 || true)

if [ "${MQ:-0}" -le 3 ]; then MFLAG='✅ 안정'; else MFLAG='⚠️ 잦음'; fi

MSG="📊 <b>탄생농원 데몬 재시작 리포트 (최근 24시간)</b>
• autocontrol: ${AC}회
• mqtt: <b>${MQ}회</b> (${MFLAG}, 워치독 ${WD}회)
• mist: ${MI}회
🕐 $(date '+%Y-%m-%d %H:%M')"

curl -s -m 15 "https://api.telegram.org/bot${TOKEN}/sendMessage"   -d chat_id="${CHAT}" -d parse_mode=HTML   --data-urlencode "text=${MSG}" > /dev/null && echo '리포트 발송됨'
