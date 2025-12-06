#!/bin/bash
# 스마트팜 UI 자동 배포 스크립트

set -e  # 에러 발생 시 중단

echo "=== 스마트팜 UI 배포 시작 ==="

# 1. 이전 빌드 파일 완전 삭제
echo "[1/6] 이전 빌드 파일 삭제 중..."
rm -rf dist
rm -f ../smartfarm-ui/assets/*.js
rm -f ../smartfarm-ui/assets/*.css
echo "✓ 이전 파일 삭제 완료"

# 2. 새로 빌드
echo "[2/6] React 앱 빌드 중..."
npm run build
echo "✓ 빌드 완료"

# 3. 빌드된 파일명 확인
echo "[3/6] 빌드 파일 확인 중..."
JS_FILE=$(ls dist/assets/*.js | head -1 | xargs basename)
CSS_FILE=$(ls dist/assets/*.css | head -1 | xargs basename)
echo "  JS:  $JS_FILE"
echo "  CSS: $CSS_FILE"

# 4. index.php 자동 업데이트
echo "[4/6] index.php 업데이트 중..."
PHP_FILE="../admin/smartfarm/index.php"

# 기존 script/link 태그 찾아서 교체
sed -i "s|src=\"/smartfarm-admin/assets/index.*\.js|src=\"/smartfarm-admin/assets/$JS_FILE|g" "$PHP_FILE"
sed -i "s|href=\"/smartfarm-admin/assets/index.*\.css|href=\"/smartfarm-admin/assets/$CSS_FILE|g" "$PHP_FILE"
echo "✓ index.php 업데이트 완료"

# 5. Git 커밋 및 푸시
echo "[5/6] Git 커밋 중..."
cd ..
git add -A

# 버전 번호 자동 증가 (v3.12.X 형식)
LAST_TAG=$(git tag | grep -E '^v3\.12\.' | sort -V | tail -1)
if [ -z "$LAST_TAG" ]; then
  NEW_TAG="v3.12.2"
else
  # 마지막 숫자만 증가
  LAST_NUM=$(echo $LAST_TAG | sed 's/v3\.12\.//')
  NEW_NUM=$((LAST_NUM + 1))
  NEW_TAG="v3.12.$NEW_NUM"
fi

git commit -m "deploy: 스마트팜 UI 자동 배포 $NEW_TAG

- 이전 빌드 파일 완전 삭제
- 새 빌드: $JS_FILE, $CSS_FILE
- index.php 자동 업데이트

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"

git tag -a "$NEW_TAG" -m "스마트팜 UI 자동 배포"
git push origin main --tags
echo "✓ Git 푸시 완료 ($NEW_TAG)"

# 6. 완료 메시지
echo ""
echo "=== ✅ 배포 완료! ==="
echo ""
echo "버전: $NEW_TAG"
echo "JS:   /smartfarm-admin/assets/$JS_FILE"
echo "CSS:  /smartfarm-admin/assets/$CSS_FILE"
echo ""
echo "⚠️  브라우저에서 Ctrl+Shift+Delete로 캐시를 삭제하세요!"
echo ""
