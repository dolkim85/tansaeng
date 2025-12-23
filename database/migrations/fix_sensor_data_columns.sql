-- sensor_data 테이블에 누락된 컬럼 추가
-- MQTT 데몬이 센서 데이터를 저장할 수 있도록 필요한 컬럼들을 추가합니다

-- controller_id 컬럼이 없으면 추가
ALTER TABLE sensor_data
ADD COLUMN IF NOT EXISTS controller_id VARCHAR(50) AFTER id,
ADD INDEX IF NOT EXISTS idx_controller_id (controller_id);

-- sensor_type 컬럼이 없으면 추가
ALTER TABLE sensor_data
ADD COLUMN IF NOT EXISTS sensor_type VARCHAR(50) AFTER controller_id;

-- sensor_location 컬럼이 없으면 추가
ALTER TABLE sensor_data
ADD COLUMN IF NOT EXISTS sensor_location VARCHAR(50) AFTER sensor_type,
ADD INDEX IF NOT EXISTS idx_sensor_location (sensor_location);
