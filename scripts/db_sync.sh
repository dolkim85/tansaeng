#!/bin/bash

# 데이터베이스 동기화 스크립트
# 사용법: ./db_sync.sh [cloud-to-local|local-to-cloud|backup]

CLOUD_SERVER="1.201.17.34"
CLOUD_USER="ubuntu"
SSH_KEY="/home/spinmoll/.ssh/tansaeng.pem"
CLOUD_DB_PASS="qjawns3445"
LOCAL_DB_PASS=""
DB_NAME="tansaeng_db"

function backup_cloud_db() {
    echo "☁️ 클라우드 서버 데이터베이스 백업 중..."

    BACKUP_FILE="/tmp/tansaeng_db_backup_$(date +%Y%m%d_%H%M%S).sql"

    ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$CLOUD_USER@$CLOUD_SERVER" \
        "sudo mysqldump -u root -p$CLOUD_DB_PASS --single-transaction --routines --triggers $DB_NAME" > "$BACKUP_FILE"

    if [ $? -eq 0 ]; then
        echo "✅ 백업 완료: $BACKUP_FILE"
        echo "$BACKUP_FILE"
    else
        echo "❌ 백업 실패"
        exit 1
    fi
}

function sync_cloud_to_local() {
    echo "⬇️ 클라우드 → 로컬 데이터베이스 동기화 시작..."

    BACKUP_FILE=$(backup_cloud_db)

    echo "📥 로컬 데이터베이스에 적용 중..."
    mysql -u root -e "DROP DATABASE IF EXISTS $DB_NAME; CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -u root "$DB_NAME" < "$BACKUP_FILE"

    if [ $? -eq 0 ]; then
        echo "✅ 클라우드 → 로컬 동기화 완료"
        rm "$BACKUP_FILE"
    else
        echo "❌ 동기화 실패"
        exit 1
    fi
}

function sync_local_to_cloud() {
    echo "⬆️ 로컬 → 클라우드 데이터베이스 동기화 시작..."

    LOCAL_BACKUP="/tmp/local_db_backup_$(date +%Y%m%d_%H%M%S).sql"

    echo "📤 로컬 데이터베이스 백업 중..."
    mysqldump -u root --single-transaction --routines --triggers "$DB_NAME" > "$LOCAL_BACKUP"

    echo "☁️ 클라우드 서버에 적용 중..."
    scp -i "$SSH_KEY" -o StrictHostKeyChecking=no "$LOCAL_BACKUP" "$CLOUD_USER@$CLOUD_SERVER:/tmp/"

    ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no "$CLOUD_USER@$CLOUD_SERVER" \
        "sudo mysql -u root -p$CLOUD_DB_PASS -e 'DROP DATABASE IF EXISTS $DB_NAME; CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;' && \
         sudo mysql -u root -p$CLOUD_DB_PASS $DB_NAME < /tmp/$(basename $LOCAL_BACKUP) && \
         rm /tmp/$(basename $LOCAL_BACKUP)"

    if [ $? -eq 0 ]; then
        echo "✅ 로컬 → 클라우드 동기화 완료"
        rm "$LOCAL_BACKUP"
    else
        echo "❌ 동기화 실패"
        exit 1
    fi
}

case "$1" in
    cloud-to-local)
        sync_cloud_to_local
        ;;
    local-to-cloud)
        sync_local_to_cloud
        ;;
    backup)
        backup_cloud_db
        ;;
    *)
        echo "사용법: $0 [cloud-to-local|local-to-cloud|backup]"
        echo "  cloud-to-local : 클라우드 DB를 로컬로 동기화"
        echo "  local-to-cloud : 로컬 DB를 클라우드로 동기화"
        echo "  backup         : 클라우드 DB 백업만 생성"
        exit 1
        ;;
esac