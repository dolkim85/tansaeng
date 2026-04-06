-- ================================================================
-- 서버 문제 수정 SQL
-- ================================================================

-- 1. mist_logs 테이블 생성 (없으면)
CREATE TABLE IF NOT EXISTS mist_logs (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    zone_id     VARCHAR(20)  NOT NULL COMMENT '존 ID (zone_a ~ zone_e)',
    zone_name   VARCHAR(50)  NOT NULL COMMENT '존 이름',
    event_type  ENUM('start','stop') NOT NULL COMMENT '이벤트 유형',
    mode        VARCHAR(20)  NOT NULL DEFAULT 'AUTO' COMMENT '모드 (AUTO/MANUAL)',
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone_date  (zone_id, created_at),
    INDEX idx_date       (created_at),
    INDEX idx_event_date (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='분무수경 작동/정지 이벤트 로그';

-- 2. sensor_data 오래된 데이터 즉시 정리 (30일 이상)
DELETE FROM sensor_data WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- 3. MySQL 이벤트 스케줄러 활성화 (자동 정리용)
SET GLOBAL event_scheduler = ON;

-- 4. sensor_data 자동 정리 이벤트 (없으면 생성)
DROP EVENT IF EXISTS cleanup_old_sensor_data;
CREATE EVENT cleanup_old_sensor_data
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
    DELETE FROM sensor_data WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- 5. mist_logs 자동 정리 이벤트 (90일 보관)
DROP EVENT IF EXISTS cleanup_old_mist_logs;
CREATE EVENT cleanup_old_mist_logs
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
    DELETE FROM mist_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- 확인
SHOW TABLES LIKE 'mist_logs';
SELECT COUNT(*) as sensor_rows FROM sensor_data;
SHOW VARIABLES LIKE 'event_scheduler';
