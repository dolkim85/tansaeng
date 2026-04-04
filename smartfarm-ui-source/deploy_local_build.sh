#!/bin/bash

# 탄생(Tansaeng) 로컬 빌드 + 원격 배포 스크립트 v1.0
# 사용법: ./deploy_local_build.sh

echo "🚀 탄생 웹사이트 로컬 빌드 + 클라우드 배포 시작..."

# 변수 설정
CLOUD_SERVER="1.201.17.34"
CLOUD_USER="root"
CLOUD_PASSWORD="qjawns3445"
CLOUD_PATH="/var/www/html"
REPO_URL="https://github.com/dolkim85/tansaeng.git"
DOMAIN="www.tansaeng.com"
LOCAL_SOURCE_DIR="/home/spinmoll/tansaeng_new/smartfarm-ui-source"

# Git 상태 확인
echo "📊 Git 상태 확인 중..."
if ! git diff-index --quiet HEAD --; then
    echo "⚠️  커밋되지 않은 변경사항이 있습니다."
    echo "먼저 변경사항을 커밋해주세요."
    exit 1
fi

echo "✅ 모든 변경사항이 커밋되었습니다."
echo "📤 최신 태그 푸시 확인 중..."
git push origin main --tags 2>/dev/null || echo "이미 최신 상태입니다."

# 🏗️ 로컬에서 React 앱 빌드
echo ""
echo "🏗️ 로컬에서 스마트팜 React 앱 빌드 중..."
cd "$LOCAL_SOURCE_DIR"

# .env 파일 생성
echo "📝 환경 변수 설정 중..."
cat > .env << 'ENVEOF'
# HiveMQ Cloud WebSocket Configuration
VITE_MQTT_HOST=22ada06fd6cf4059bd700ddbf6004d68.s1.eu.hivemq.cloud
VITE_MQTT_WS_PORT=8884
VITE_MQTT_USERNAME=esp32-client-01
VITE_MQTT_PASSWORD=Qjawns3445

# Tapo 카메라 HLS 스트림 URL (Nginx/SRS 서버에서 제공)
VITE_TAPO_CAM1_HLS_URL=https://www.tansaeng.com/live/tapo1.m3u8
VITE_TAPO_CAM2_HLS_URL=https://www.tansaeng.com/live/tapo2.m3u8
VITE_TAPO_CAM3_HLS_URL=https://www.tansaeng.com/live/tapo3.m3u8
VITE_TAPO_CAM4_HLS_URL=https://www.tansaeng.com/live/tapo4.m3u8
ENVEOF

# npm 의존성 확인
if [ ! -d "node_modules" ]; then
    echo "📦 npm 의존성 설치 중..."
    npm install
fi

# 빌드 실행
echo "🔨 React 앱 빌드 중..."
npm run build

if [ ! -d "dist" ]; then
    echo "❌ 빌드 실패: dist 폴더가 생성되지 않았습니다."
    exit 1
fi

echo "✅ 로컬 빌드 완료!"

# 📤 클라우드 서버에 배포
echo ""
echo "📤 클라우드 서버에 배포 중..."

# dist 폴더를 tar로 압축
echo "📦 dist 폴더 압축 중..."
cd "$LOCAL_SOURCE_DIR"
tar -czf /tmp/smartfarm-ui-dist.tar.gz dist/

# 클라우드 서버로 업로드 및 배포
sshpass -p "$CLOUD_PASSWORD" ssh -o StrictHostKeyChecking=no "$CLOUD_USER@$CLOUD_SERVER" << 'EOF'
    echo "🔄 클라우드 서버에서 최신 코드 가져오는 중..."

    cd /var/www/html

    # Git 저장소가 없으면 클론, 있으면 풀
    if [ ! -d ".git" ]; then
        echo "📥 저장소 클론 중..."
        sudo rm -rf *
        sudo git clone https://github.com/dolkim85/tansaeng.git .
        sudo git fetch --tags
        sudo git checkout main
    else
        echo "🔄 최신 변경사항 가져오는 중..."

        # 배포 전 자동 백업
        echo "💾 블록 스토리지 백업 중..."
        BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
        if [ -d "/mnt/block-storage/uploads" ]; then
            sudo mkdir -p /var/backups/tansaeng
            sudo cp -r /mnt/block-storage/uploads /var/backups/tansaeng/uploads_$BACKUP_DATE
            echo "✅ 백업 완료: /var/backups/tansaeng/uploads_$BACKUP_DATE"
        fi

        sudo git fetch origin --tags
        sudo git reset --hard HEAD
        sudo git clean -fd -e uploads -e .env -e uploads_backup_* -e config/env.php -e vendor
        sudo git checkout main
        sudo git pull origin main
    fi

    # 권한 설정
    echo "🔐 파일 권한 설정 중..."
    sudo chmod -R 755 /var/www/html/

    # uploads 심볼릭 링크 복원
    if [ -d "/var/www/html/uploads" ] && [ ! -L "/var/www/html/uploads" ]; then
        echo "📁 실제 uploads 디렉토리 발견, 제거 후 심볼릭 링크 생성..."
        sudo rm -rf /var/www/html/uploads
    fi

    if [ ! -L "/var/www/html/uploads" ]; then
        echo "🔗 uploads 심볼릭 링크 생성 중..."
        sudo ln -sf /mnt/block-storage/uploads /var/www/html/uploads
        echo "✅ 심볼릭 링크 생성 완료"
    fi

    # 블록 스토리지 권한 설정
    if [ -d "/mnt/block-storage/uploads" ]; then
        sudo chown -R www-data:www-data /mnt/block-storage/uploads
        sudo chmod -R 755 /mnt/block-storage/uploads
        echo "✅ 블록 스토리지 uploads 권한 설정 완료"
    fi

    sudo chown -R www-data:www-data /var/www/html/

    # 데이터베이스 연결 테스트
    echo "🔌 데이터베이스 연결 테스트 중..."
    php -r "
        require_once '/var/www/html/config/database.php';
        try {
            \$db = DatabaseConfig::getConnection();
            echo '✅ 데이터베이스 연결 성공\n';
        } catch (Exception \$e) {
            echo '❌ 데이터베이스 연결 실패: ' . \$e->getMessage() . '\n';
        }
    "

    # Composer 설치 및 의존성 설치
    echo "📦 Composer 의존성 설치 중..."
    cd /var/www/html
    if [ -f "composer.json" ]; then
        if ! command -v composer &> /dev/null; then
            echo "📥 Composer 설치 중..."
            curl -sS https://getcomposer.org/installer | php
            sudo mv composer.phar /usr/local/bin/composer
            sudo chmod +x /usr/local/bin/composer
        fi
        COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader 2>&1 | tail -5
        echo "✅ Composer 의존성 설치 완료"
    fi

    # MQTT 데몬 서비스 재시작
    echo "🔄 MQTT 데몬 서비스 설정 중..."
    if [ -f "/var/www/html/scripts/tansaeng-mqtt.service" ]; then
        sudo cp /var/www/html/scripts/tansaeng-mqtt.service /etc/systemd/system/
        sudo systemctl daemon-reload
        sudo systemctl enable tansaeng-mqtt 2>&1 | grep -v "Created symlink" || true
        sudo systemctl restart tansaeng-mqtt
        echo "✅ MQTT 데몬 서비스 재시작 완료"
    fi

    echo "✅ 기본 배포 완료"
EOF

# dist 파일 업로드
echo "📤 빌드된 dist 파일 업로드 중..."
sshpass -p "$CLOUD_PASSWORD" scp -o StrictHostKeyChecking=no /tmp/smartfarm-ui-dist.tar.gz "$CLOUD_USER@$CLOUD_SERVER:/tmp/"

# dist 파일 압축 해제 및 배치
sshpass -p "$CLOUD_PASSWORD" ssh -o StrictHostKeyChecking=no "$CLOUD_USER@$CLOUD_SERVER" << 'EOF'
    echo "📦 업로드된 dist 압축 해제 중..."
    cd /var/www/html/smartfarm-ui-source

    # 기존 dist 백업
    if [ -d "dist" ]; then
        sudo mv dist dist.backup.$(date +%Y%m%d_%H%M%S)
    fi

    # 새 dist 압축 해제
    sudo tar -xzf /tmp/smartfarm-ui-dist.tar.gz
    sudo chown -R www-data:www-data dist/
    sudo chmod -R 755 dist/

    # 임시 파일 삭제
    rm /tmp/smartfarm-ui-dist.tar.gz

    echo "✅ dist 파일 배치 완료"

    # Apache 설정 수정
    echo "🔧 Apache 설정 수정 중..."

    # default-ssl.conf 비활성화
    if [ -L "/etc/apache2/sites-enabled/default-ssl.conf" ]; then
        echo "  - default-ssl.conf 비활성화 중..."
        sudo a2dissite default-ssl.conf 2>&1 | grep -v "Site default-ssl disabled" || true
    fi

    # Apache 설정 파일 생성
    echo "  - Apache 설정 파일 업데이트 중..."
    sudo tee /etc/apache2/sites-enabled/www.tansaeng.com.conf > /dev/null << 'APACHECONF'
<VirtualHost *:80>
    ServerName www.tansaeng.com
    ServerAlias tansaeng.com
    DocumentRoot /var/www/html

    # HTTP to HTTPS redirect
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName www.tansaeng.com
    ServerAlias tansaeng.com

    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/ssl/tansaeng/www.tansaeng.com.crt
    SSLCertificateKeyFile /etc/ssl/tansaeng/www.tansaeng.com.key

    # Security Headers
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    Header always set X-Frame-Options DENY
    Header always set X-Content-Type-Options nosniff
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    # React 스마트팜 Alias - MUST BE BEFORE DocumentRoot
    Alias /smartfarm-ui /var/www/html/smartfarm-ui-source/dist

    <Directory /var/www/html/smartfarm-ui-source/dist>
        # Force correct MIME types
        AddType text/html .html
        AddType text/css .css
        AddType application/javascript .js
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
        DirectoryIndex index.html

        # Disable caching for development
        <IfModule mod_headers.c>
            Header set Cache-Control "no-cache, no-store, must-revalidate"
            Header set Pragma "no-cache"
            Header set Expires 0
        </IfModule>
    </Directory>

    DocumentRoot /var/www/html

    # Directory Settings
    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # PHP Configuration
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php8.3-fpm.sock|fcgi://localhost"
    </FilesMatch>

    # Error and Access Logs
    ErrorLog ${APACHE_LOG_DIR}/tansaeng_error.log
    CustomLog ${APACHE_LOG_DIR}/tansaeng_access.log combined
</VirtualHost>
APACHECONF

    # Apache 설정 테스트
    echo "  - Apache 설정 테스트 중..."
    if sudo apache2ctl configtest 2>&1 | grep -q "Syntax OK"; then
        echo "  ✅ Apache 설정 검증 완료"
    else
        echo "  ❌ Apache 설정 오류 발생"
        sudo apache2ctl configtest
        exit 1
    fi

    # 웹서버 재시작
    echo "🔄 웹서버 재시작 중..."
    sudo systemctl reload apache2
    sudo systemctl restart apache2

    # Apache 상태 확인
    if systemctl is-active --quiet apache2; then
        echo "✅ Apache 정상 작동 중"
    else
        echo "❌ Apache 재시작 실패"
        systemctl status apache2
        exit 1
    fi

    echo "✅ 클라우드 서버 배포 완료!"
EOF

# 로컬 임시 파일 정리
rm /tmp/smartfarm-ui-dist.tar.gz

echo ""
echo "🎉 배포가 완료되었습니다!"
echo "🌐 웹사이트: https://$DOMAIN"
echo "👨‍💼 관리자: https://$DOMAIN/admin"
echo "🏭 스마트팜: https://$DOMAIN/admin/smartfarm/"
echo "📊 서버 IP: $CLOUD_SERVER"
echo ""
echo "⚠️  배포 후 확인사항:"
echo "1. 웹사이트 접속 확인"
echo "2. 스마트팜 UI 정상 작동 확인 (Ctrl+F5로 새로고침)"
echo "3. MQTT 연결 확인"
echo "4. 주요 기능 동작 테스트"
echo ""
