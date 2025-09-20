#!/bin/bash

# 탄생(Tansaeng) 클라우드 자동 배포 스크립트
# 사용법: ./deploy_to_cloud.sh

echo "🚀 탄생 웹사이트 클라우드 배포 시작..."

# 변수 설정
CLOUD_SERVER="1.201.17.34"
CLOUD_USER="ubuntu"
SSH_KEY="/home/spinmoll/.ssh/tansaeng.pem"
CLOUD_PATH="/var/www/html"
REPO_URL="https://github.com/dolkim85/tansaeng.git"

# 1. 로컬 변경사항 커밋 및 푸시
echo "📝 로컬 변경사항 커밋 중..."
git add .
read -p "커밋 메시지를 입력하세요: " commit_message
if [ -z "$commit_message" ]; then
    commit_message="Website update $(date '+%Y-%m-%d %H:%M:%S')"
fi
git commit -m "$commit_message"

echo "📤 GitHub에 푸시 중..."
CURRENT_BRANCH=$(git branch --show-current)
git push origin $CURRENT_BRANCH

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
        sudo git checkout uploaded-board-store_v2
    else
        echo "🔄 최신 변경사항 가져오는 중..."
        sudo git fetch origin
        sudo git checkout uploaded-board-store_v2
        sudo git pull origin uploaded-board-store_v2
    fi

    # 권한 설정
    echo "🔐 파일 권한 설정 중..."
    sudo chmod -R 755 /var/www/html/
    sudo chmod -R 777 /var/www/html/uploads/
    sudo chown -R www-data:www-data /var/www/html/

    # 웹서버 재시작
    echo "🔄 웹서버 재시작 중..."
    sudo systemctl reload apache2 2>/dev/null || sudo systemctl reload nginx 2>/dev/null

    echo "✅ 클라우드 서버 배포 완료!"
EOF

echo ""
echo "🎉 배포가 완료되었습니다!"
echo "🌐 웹사이트: http://$CLOUD_SERVER"
echo "👨‍💼 관리자: http://$CLOUD_SERVER/admin"
echo ""