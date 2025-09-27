#!/bin/bash

# 탄생(Tangsaeng) 웹사이트 배포 스크립트
echo "🚀 탄생 웹사이트 배포 시작..."

# 최신 코드 가져오기
echo "📥 최신 코드 다운로드 중..."
git pull origin main

# 권한 설정
echo "🔐 파일 권한 설정 중..."
chmod -R 755 /var/www/html/
chmod -R 777 /var/www/html/uploads/
chown -R www-data:www-data /var/www/html/

# 환경 설정 파일 확인
echo "⚙️ 환경 설정 확인 중..."
if [ ! -f "config/database.php" ]; then
    echo "❌ config/database.php 파일이 없습니다. config/database.php.example을 복사하여 설정해주세요."
    exit 1
fi

# Apache/Nginx 재시작
echo "🔄 웹서버 재시작 중..."
if command -v systemctl &> /dev/null; then
    sudo systemctl reload apache2 2>/dev/null || sudo systemctl reload nginx 2>/dev/null
fi

echo "✅ 배포 완료!"
echo "🌐 웹사이트: http://localhost"
echo "👨‍💼 관리자: http://localhost/admin"