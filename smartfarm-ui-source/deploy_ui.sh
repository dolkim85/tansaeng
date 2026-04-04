#!/bin/bash

echo "======================================="
echo "스마트팜 UI 배포 스크립트"
echo "======================================="
echo ""

# 1. 빌드
echo "[1/5] React 앱 빌드 중..."
cd /home/spinmoll/tansaeng_new/smartfarm-ui-source
npm run build

if [ $? -ne 0 ]; then
    echo "❌ 빌드 실패!"
    exit 1
fi

echo "✓ 빌드 완료"
echo ""

# 2. 버전 체크
echo "[2/5] 배포 파일 확인..."
LATEST_JS=$(ls -t dist/assets/index-*.js | head -1)
echo "최신 JS: $LATEST_JS"
echo ""

# 3. index.php 업데이트
echo "[3/5] index.php 업데이트 중..."
JS_FILE=$(basename $LATEST_JS)
CSS_FILE=$(ls -t dist/assets/index-*.css | head -1 | xargs basename)

echo "JS 파일: $JS_FILE"
echo "CSS 파일: $CSS_FILE"

# index.php 업데이트
sed -i "s|index-[^-]*-v[0-9]*\.js|$JS_FILE|g" /home/spinmoll/tansaeng_new/admin/smartfarm/index.php
sed -i "s|index-[^-]*-v[0-9]*\.css|$CSS_FILE|g" /home/spinmoll/tansaeng_new/admin/smartfarm/index.php

echo "✓ index.php 업데이트 완료"
echo ""

# 4. Apache 리로드
echo "[4/5] Apache 리로드 중..."
echo 'qjawns3445' | sudo -S systemctl reload apache2 2>/dev/null
echo "✓ Apache 리로드 완료"
echo ""

# 5. 배포 완료 메시지
echo "[5/5] 배포 완료!"
echo ""
echo "======================================="
echo "✓ 스마트팜 UI 배포 성공"
echo "======================================="
echo ""
echo "배포된 파일:"
echo "  - $JS_FILE"
echo "  - $CSS_FILE"
echo ""
echo "다음 단계:"
echo "1. 브라우저에서 Ctrl+Shift+Del → 캐시 삭제"
echo "2. 또는 Ctrl+Shift+R로 강제 새로고침"
echo "3. 또는 시크릿 모드로 페이지 열기"
echo ""
echo "배포 시간: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

