#!/bin/bash

# 빠른 배포 스크립트
# 사용법: ./quick_deploy.sh "커밋 메시지"

cd /var/www/html

# 커밋 메시지 설정
if [ -z "$1" ]; then
    COMMIT_MSG="Quick update $(date '+%Y-%m-%d %H:%M:%S')"
else
    COMMIT_MSG="$1"
fi

echo "🚀 빠른 배포 시작..."

# Git 작업
echo "📝 로컬 변경사항 커밋 중..."
git add .
git commit -m "$COMMIT_MSG"

echo "📤 GitHub에 푸시 중..."
git push origin main

# 클라우드 서버 배포
echo "☁️ 클라우드 서버에 배포 중..."
ssh -i "/home/spinmoll/.ssh/tansaeng.pem" -o StrictHostKeyChecking=no ubuntu@1.201.17.34 << 'EOF'
    cd /var/www/html

    # 웹 디렉토리 확인 및 생성
    echo "📁 웹 디렉토리 설정 중..."
    sudo mkdir -p /var/www/html
    cd /var/www/html

    # Git 저장소 확인 및 업데이트
    if [ ! -d ".git" ]; then
        echo "📥 저장소 클론 중..."
        sudo rm -rf /var/www/html/*
        sudo git clone https://github.com/dolkim85/tansaeng.git /tmp/tansaeng
        sudo cp -r /tmp/tansaeng/* /var/www/html/
        sudo rm -rf /tmp/tansaeng
    else
        echo "🔄 최신 변경사항 가져오는 중..."
        sudo git pull origin main
    fi

    # Apache 및 PHP 설치 및 설정
    echo "🔧 Apache 및 PHP 설치 및 설정 중..."
    sudo apt update -y
    sudo apt install -y apache2 php libapache2-mod-php php-mysql php-curl php-json php-mbstring
    sudo systemctl enable apache2
    sudo systemctl start apache2

    # PHP 모듈 활성화
    sudo a2enmod php8.3
    sudo a2enmod rewrite
    sudo a2enmod ssl
    sudo a2enmod headers

    # SSL 인증서 설정
    echo "🔐 SSL 인증서 설정 중..."
    sudo mkdir -p /etc/ssl/tansaeng
    sudo cp /var/www/html/ssl/www.tansaeng.com.crt /etc/ssl/tansaeng/
    sudo cp /var/www/html/ssl/www.tansaeng.com.key /etc/ssl/tansaeng/
    sudo chmod 600 /etc/ssl/tansaeng/www.tansaeng.com.key
    sudo chmod 644 /etc/ssl/tansaeng/www.tansaeng.com.crt

    # Apache 가상호스트 설정
    echo "⚙️ Apache 가상호스트 설정 중..."
    sudo cp /var/www/html/ssl/www.tansaeng.com.conf /etc/apache2/sites-available/
    sudo a2ensite www.tansaeng.com.conf
    sudo a2enmod ssl rewrite headers
    sudo a2dissite 000-default.conf

    # 권한 설정
    sudo chmod -R 755 /var/www/html/
    sudo chmod -R 777 /var/www/html/uploads/
    sudo chown -R www-data:www-data /var/www/html/

    # Apache 재시작
    echo "🔄 Apache 재시작 중..."
    sudo systemctl restart apache2

    echo "✅ 배포 완료!"
EOF

echo ""
echo "🎉 SSL 배포가 완료되었습니다!"
echo "🌐 웹사이트: https://www.tansaeng.com"
echo "🔗 HTTP 리다이렉트: http://www.tansaeng.com"
echo "👨‍💼 관리자: https://www.tansaeng.com/admin"