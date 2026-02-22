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

    # Heartbeat 데몬 서비스 설정
    echo "🔄 Heartbeat 데몬 서비스 설정 중..."
    if [ -f "/var/www/html/scripts/tansaeng-heartbeat.service" ]; then
        sudo cp /var/www/html/scripts/tansaeng-heartbeat.service /etc/systemd/system/
        sudo systemctl daemon-reload
        sudo systemctl enable tansaeng-heartbeat 2>&1 | grep -v "Created symlink" || true
        sudo systemctl restart tansaeng-heartbeat
        echo "✅ Heartbeat 데몬 서비스 재시작 완료"
    fi

    echo "✅ 기본 배포 완료"
EOF

# dist 파일 직접 scp 전송
echo "📤 빌드된 dist 파일 업로드 중..."
sshpass -p "$CLOUD_PASSWORD" ssh -o StrictHostKeyChecking=no "$CLOUD_USER@$CLOUD_SERVER" '
    cd /var/www/html/smartfarm-ui-source
    if [ -d "dist" ]; then
        sudo mv dist dist.backup.$(date +%Y%m%d_%H%M%S)
    fi
'
sshpass -p "$CLOUD_PASSWORD" scp -r -o StrictHostKeyChecking=no "$LOCAL_SOURCE_DIR/dist/" "$CLOUD_USER@$CLOUD_SERVER:/var/www/html/smartfarm-ui-source/dist/"

# 권한 설정 + Apache 리로드
sshpass -p "$CLOUD_PASSWORD" ssh -o StrictHostKeyChecking=no "$CLOUD_USER@$CLOUD_SERVER" '
    sudo chown -R www-data:www-data /var/www/html/smartfarm-ui-source/dist/
    sudo chmod -R 755 /var/www/html/smartfarm-ui-source/dist/
    sudo systemctl reload apache2
    echo "✅ dist 배치 + Apache 리로드 완료"
'

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
