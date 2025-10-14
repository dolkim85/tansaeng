-- 소셜 로그인을 위한 컬럼 추가
-- 실행 방법: mysql -u [사용자명] -p tansaeng_new < add_oauth_columns.sql

-- oauth_provider 컬럼 추가 (이미 있으면 에러 무시)
ALTER TABLE users
ADD COLUMN oauth_provider VARCHAR(20) DEFAULT 'email'
COMMENT '로그인 제공자: email, kakao, google, naver';

-- oauth_id 컬럼 추가 (이미 있으면 에러 무시)
ALTER TABLE users
ADD COLUMN oauth_id VARCHAR(100) NULL
COMMENT '소셜 로그인 고유 ID';

-- oauth_id에 인덱스 추가 (빠른 검색을 위해)
ALTER TABLE users
ADD INDEX idx_oauth (oauth_provider, oauth_id);

-- 기존 사용자는 oauth_provider를 'email'로 설정
UPDATE users
SET oauth_provider = 'email'
WHERE oauth_provider IS NULL OR oauth_provider = '';
