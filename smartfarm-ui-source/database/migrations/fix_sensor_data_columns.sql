-- sensor_data 테이블에 누락된 컬럼 추가
-- MQTT 데몬이 센서 데이터를 저장할 수 있도록 필요한 컬럼들을 추가합니다

-- controller_id 컬럼 추가 (이미 존재하면 에러 무시)
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_SCHEMA = 'tansaeng_db'
               AND TABLE_NAME = 'sensor_data'
               AND COLUMN_NAME = 'controller_id');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE sensor_data ADD COLUMN controller_id VARCHAR(50) AFTER id', 'SELECT "Column controller_id already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- sensor_type 컬럼 추가 (이미 존재하면 에러 무시)
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_SCHEMA = 'tansaeng_db'
               AND TABLE_NAME = 'sensor_data'
               AND COLUMN_NAME = 'sensor_type');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE sensor_data ADD COLUMN sensor_type VARCHAR(50) AFTER controller_id', 'SELECT "Column sensor_type already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- sensor_location 컬럼 추가 (이미 존재하면 에러 무시)
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_SCHEMA = 'tansaeng_db'
               AND TABLE_NAME = 'sensor_data'
               AND COLUMN_NAME = 'sensor_location');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE sensor_data ADD COLUMN sensor_location VARCHAR(50) AFTER sensor_type', 'SELECT "Column sensor_location already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 인덱스 추가 (이미 존재하면 에러 무시)
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
               WHERE TABLE_SCHEMA = 'tansaeng_db'
               AND TABLE_NAME = 'sensor_data'
               AND INDEX_NAME = 'idx_controller_id');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE sensor_data ADD INDEX idx_controller_id (controller_id)', 'SELECT "Index idx_controller_id already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
               WHERE TABLE_SCHEMA = 'tansaeng_db'
               AND TABLE_NAME = 'sensor_data'
               AND INDEX_NAME = 'idx_sensor_location');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE sensor_data ADD INDEX idx_sensor_location (sensor_location)', 'SELECT "Index idx_sensor_location already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
