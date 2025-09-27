-- Firebase 지원을 위한 users 테이블 컬럼 추가

ALTER TABLE users 
ADD COLUMN firebase_uid VARCHAR(128) NULL UNIQUE COMMENT 'Firebase UID',
ADD COLUMN avatar_url VARCHAR(500) NULL COMMENT '프로필 이미지 URL';

-- Firebase UID 인덱스 추가
CREATE INDEX idx_firebase_uid ON users(firebase_uid);

-- 기존 데이터 확인
SELECT COUNT(*) as total_users FROM users;

-- Firebase 사용자 확인 (추가 후)
SELECT COUNT(*) as firebase_users FROM users WHERE firebase_uid IS NOT NULL;