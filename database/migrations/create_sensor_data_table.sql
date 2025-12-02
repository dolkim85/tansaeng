-- 센서 데이터 저장 테이블 (1개월 보관)
CREATE TABLE IF NOT EXISTS sensor_data (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    controller_id VARCHAR(20) NOT NULL COMMENT '제어장치 ID (ctlr-0001, ctlr-0002, ctlr-0003)',
    sensor_type VARCHAR(20) NOT NULL COMMENT '센서 타입 (dht11, dht22)',
    sensor_location VARCHAR(50) NOT NULL COMMENT '센서 위치 (front, back, top)',
    temperature DECIMAL(5,2) NULL COMMENT '온도 (°C)',
    humidity DECIMAL(5,2) NULL COMMENT '습도 (%)',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '기록 시간',
    INDEX idx_controller_date (controller_id, recorded_at),
    INDEX idx_location_date (sensor_location, recorded_at),
    INDEX idx_date (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='스마트팜 센서 데이터 (1개월 보관)';

-- 1개월 이상 오래된 데이터 자동 삭제 이벤트
-- (MySQL 이벤트 스케줄러가 활성화되어 있어야 함)
CREATE EVENT IF NOT EXISTS cleanup_old_sensor_data
ON SCHEDULE EVERY 1 DAY
DO
    DELETE FROM sensor_data
    WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 1 MONTH);
