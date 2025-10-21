#!/bin/bash

# 탄생(Tansaeng) 클라우드 자동 배포 스크립트 v11.0
# 사용법: ./deploy_to_cloud.sh

echo "🚀 탄생 웹사이트 클라우드 배포 시작 (Version: latest_v11)..."

# 변수 설정
CLOUD_SERVER="1.201.17.34"
CLOUD_USER="ubuntu"
SSH_KEY="/home/spinmoll/.ssh/tansaeng.pem"
CLOUD_PATH="/var/www/html"
REPO_URL="https://github.com/dolkim85/tansaeng.git"
DEPLOY_TAG="latest_v11"
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

# SSH를 통해 클라우드 서버에서 명령 실행
ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$CLOUD_USER@$CLOUD_SERVER" << 'EOF'
    echo "🔄 클라우드 서버에서 최신 코드 가져오는 중..."

    # 웹 디렉토리로 이동
    cd /var/www/html

    # Git 저장소가 없으면 클론, 있으면 풀
    if [ ! -d ".git" ]; then
        echo "📥 저장소 클론 중..."
        sudo rm -rf *
        sudo git clone https://github.com/dolkim85/tansaeng.git .
        sudo git fetch --tags
        sudo git checkout tags/latest_v10
    else
        echo "🔄 최신 변경사항 가져오는 중..."
        sudo git fetch origin --tags
        sudo git reset --hard HEAD
        sudo git clean -fd
        sudo git checkout tags/latest_v10
        sudo git pull origin main
    fi

    echo "✅ Version latest_v10 체크아웃 완료"

    # 권한 설정
    echo "🔐 파일 권한 설정 중..."
    sudo chmod -R 755 /var/www/html/
    sudo mkdir -p /var/www/html/uploads/
    sudo chmod -R 777 /var/www/html/uploads/
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

    # 웹서버 재시작
    echo "🔄 웹서버 재시작 중..."
    sudo systemctl reload apache2 2>/dev/null || sudo systemctl reload nginx 2>/dev/null

    echo "✅ 클라우드 서버 배포 완료!"
EOF

echo ""
echo "🎉 배포가 완료되었습니다!"
echo "🌐 웹사이트: https://$DOMAIN"
echo "👨‍💼 관리자: https://$DOMAIN/admin"
echo "📊 서버 IP: $CLOUD_SERVER"
echo "🏷️  버전: $DEPLOY_TAG"
echo ""
echo "⚠️  배포 후 확인사항:"
echo "1. 웹사이트 접속 확인"
echo "2. 데이터베이스 연결 확인"
echo "3. 관리자 페이지 접속 확인"
echo "4. 주요 기능 동작 테스트"
echo ""