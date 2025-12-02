#!/bin/bash

# 탄생(Tansaeng) 클라우드 자동 배포 스크립트 v12.0
# 사용법: ./deploy_to_cloud.sh

echo "🚀 탄생 웹사이트 클라우드 배포 시작 (Version: v3.6.0-백그라운드데몬)..."

# 변수 설정
CLOUD_SERVER="1.201.17.34"
CLOUD_USER="root"
CLOUD_PASSWORD="qjawns3445"
CLOUD_PATH="/var/www/html"
REPO_URL="https://github.com/dolkim85/tansaeng.git"
DEPLOY_TAG="v3.6.0"
DOMAIN="www.tansaeng.com"

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

# 2. 클라우드 서버에 배포
echo "☁️ 클라우드 서버에 배포 중..."

# sshpass를 통해 클라우드 서버에서 명령 실행
sshpass -p "$CLOUD_PASSWORD" ssh -o StrictHostKeyChecking=no "$CLOUD_USER@$CLOUD_SERVER" << 'EOF'
    echo "🔄 클라우드 서버에서 최신 코드 가져오는 중..."

    # 웹 디렉토리로 이동
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

        # 📦 배포 전 자동 백업 (2단계)
        echo "💾 블록 스토리지 백업 중..."
        BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
        if [ -d "/mnt/block-storage/uploads" ]; then
            sudo mkdir -p /var/backups/tansaeng
            sudo cp -r /mnt/block-storage/uploads /var/backups/tansaeng/uploads_$BACKUP_DATE
            echo "✅ 백업 완료: /var/backups/tansaeng/uploads_$BACKUP_DATE"
        fi

        sudo git fetch origin --tags
        sudo git reset --hard HEAD

        # 🛡️ Git clean에서 중요 파일 제외 (1단계)
        sudo git clean -fd -e uploads -e .env -e uploads_backup_* -e config/env.php -e vendor

        sudo git checkout main
        sudo git pull origin main
    fi

    echo "✅ Version v3.6.0 체크아웃 완료"

    # 권한 설정
    echo "🔐 파일 권한 설정 중..."
    sudo chmod -R 755 /var/www/html/

    # 🔗 uploads 심볼릭 링크 복원 (배포 시 삭제될 수 있음)
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

    # 환경별 설정 확인
    echo "🔧 환경별 설정 확인 중..."
    if [ -f "/var/www/html/config/environment.php" ]; then
        echo "✅ 환경별 설정 시스템 배포됨"
    else
        echo "❌ 환경별 설정 파일이 없습니다"
    fi

    # 데이터베이스 연결 테스트 및 테이블 생성
    echo "🔌 데이터베이스 연결 테스트 중..."
    php -r "
        require_once '/var/www/html/config/database.php';
        try {
            \$db = DatabaseConfig::getConnection();
            echo '✅ 데이터베이스 연결 성공\n';

            // site_settings 테이블 존재 확인 및 생성
            \$sql = \"SHOW TABLES LIKE 'site_settings'\";
            \$result = \$db->query(\$sql);
            if (\$result->rowCount() == 0) {
                echo '📝 site_settings 테이블 생성 중...\n';
                \$createSql = \"CREATE TABLE site_settings (
                    id int AUTO_INCREMENT PRIMARY KEY,
                    setting_key varchar(255) NOT NULL UNIQUE,
                    setting_value text,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )\";
                \$db->exec(\$createSql);
                echo '✅ site_settings 테이블 생성 완료\n';
            } else {
                echo '✅ site_settings 테이블 존재 확인됨\n';
            }
        } catch (Exception \$e) {
            echo '❌ 데이터베이스 연결 실패: ' . \$e->getMessage() . '\n';
        }
    "

    # 네이버페이 테스트 계정 생성
    echo "👤 네이버페이 테스트 계정 생성 중..."
    if [ -f "/var/www/html/create_naverpay_test_account.php" ]; then
        php /var/www/html/create_naverpay_test_account.php
    else
        echo "⚠️  테스트 계정 생성 스크립트를 찾을 수 없습니다."
    fi

    # 🏭 스마트팜 React 앱 빌드
    if [ -d "/var/www/html/smartfarm-ui" ]; then
        echo "🏭 스마트팜 React 앱 빌드 중..."
        cd /var/www/html/smartfarm-ui

        # .env 파일 생성 (HiveMQ Cloud + Tapo 카메라 설정)
        echo "📝 HiveMQ Cloud 및 Tapo 카메라 설정 중..."
        sudo bash -c 'cat > .env << '\''ENVEOF'\''
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
ENVEOF'

        # Node.js 및 npm 설치 확인
        if ! command -v npm &> /dev/null; then
            echo "📦 Node.js 및 npm 설치 중..."
            sudo apt-get update -qq
            sudo apt-get install -y nodejs npm 2>&1 | grep -E "Setting up|unpacking" || true
            echo "✅ Node.js 설치 완료"
        fi

        # Node.js 의존성 설치 및 빌드
        if [ -f "package.json" ]; then
            echo "📦 npm 의존성 설치 중..."
            npm install
            echo "✅ npm install 완료"
            echo "📋 node_modules 확인:"
            ls -la node_modules/.bin/ | head -10

            echo "🔨 React 앱 빌드 중..."
            BUILD_OUTPUT=$(npm run build 2>&1)
            BUILD_EXIT_CODE=$?

            echo "$BUILD_OUTPUT" | grep -E "built|error|warning|Error|Failed" || echo "빌드 진행 중..."

            if [ $BUILD_EXIT_CODE -ne 0 ]; then
                echo "❌ 빌드 에러 발생:"
                echo "$BUILD_OUTPUT" | tail -20
            fi

            # dist 폴더 권한 설정
            if [ -d "dist" ]; then
                sudo chown -R www-data:www-data dist/
                sudo chmod -R 755 dist/
                echo "✅ 스마트팜 React 앱 빌드 완료!"
            else
                echo "❌ 빌드 실패: dist 폴더가 생성되지 않았습니다."
            fi
        else
            echo "⚠️  package.json이 없습니다. 빌드를 건너뜁니다."
        fi

        cd /var/www/html
    else
        echo "⚠️  smartfarm-ui 디렉토리가 없습니다. 스마트팜 빌드를 건너뜁니다."
    fi

    # Apache Alias 설정 (smartfarm-admin -> smartfarm-ui/dist)
    echo "🔧 Apache Alias 설정 확인 중..."
    if ! grep -q "Alias /smartfarm-admin" /etc/apache2/sites-enabled/tansaeng.conf; then
        echo "📝 Apache에 /smartfarm-admin Alias 추가 중..."
        sudo sed -i '/<\/VirtualHost>/i \    # React 스마트팜 Alias\n    Alias /smartfarm-admin /var/www/html/smartfarm-ui/dist\n\n    <Directory /var/www/html/smartfarm-ui/dist>\n        Options -Indexes +FollowSymLinks\n        AllowOverride None\n        Require all granted\n    </Directory>\n' /etc/apache2/sites-enabled/tansaeng.conf
        echo "✅ Alias 추가 완료"
    else
        echo "✅ Alias 이미 존재함"
    fi

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
        COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader
        echo "✅ Composer 의존성 설치 완료"
    fi

    # MQTT 데몬 서비스 설치 및 재시작
    echo "🔄 MQTT 데몬 서비스 설정 중..."
    if [ -f "/var/www/html/scripts/tansaeng-mqtt.service" ]; then
        sudo cp /var/www/html/scripts/tansaeng-mqtt.service /etc/systemd/system/
        sudo systemctl daemon-reload
        sudo systemctl enable tansaeng-mqtt
        sudo systemctl restart tansaeng-mqtt
        echo "✅ MQTT 데몬 서비스 재시작 완료"
    fi

    # 웹서버 재시작
    echo "🔄 웹서버 재시작 중..."
    sudo systemctl reload apache2
    sudo systemctl restart apache2

    echo "✅ 클라우드 서버 배포 완료!"
EOF

echo ""
echo "🎉 배포가 완료되었습니다!"
echo "🌐 웹사이트: https://$DOMAIN"
echo "👨‍💼 관리자: https://$DOMAIN/admin"
echo "📊 서버 IP: $CLOUD_SERVER"
echo "🏷️  버전: v3.6.0 - 백그라운드 MQTT 데몬 및 서버 기반 실시간 모니터링"
echo ""
echo "⚠️  배포 후 확인사항:"
echo "1. 웹사이트 접속 확인"
echo "2. 데이터베이스 연결 확인"
echo "3. 관리자 페이지 접속 확인"
echo "4. 주요 기능 동작 테스트"
echo ""