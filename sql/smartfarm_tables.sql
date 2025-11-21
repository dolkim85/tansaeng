-- Smart Farm System Tables
-- 스마트팜 제어 및 모니터링 시스템을 위한 테이블

-- 1. 제어 명령 로그 테이블
CREATE TABLE IF NOT EXISTS smartfarm_controls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_name VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL,
    value VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_device (user_id, device_name),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. 디바이스 현재 상태 테이블
CREATE TABLE IF NOT EXISTS smartfarm_device_states (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_name VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL,
    value VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_device (user_id, device_name),
    INDEX idx_updated_at (updated_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. 스케줄 설정 테이블
CREATE TABLE IF NOT EXISTS smartfarm_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_name VARCHAR(100) NOT NULL,
    mode VARCHAR(20) NOT NULL COMMENT 'day, night, both, custom',
    start_time TIME NULL,
    end_time TIME NULL,
    duration INT NOT NULL COMMENT '작동 시간 (초)',
    interval_time INT NOT NULL COMMENT '쉬는 시간 (초)',
    enabled TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_device (user_id, device_name),
    INDEX idx_enabled (enabled),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. MQTT 브로커 설정 테이블
CREATE TABLE IF NOT EXISTS smartfarm_mqtt_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    broker_url VARCHAR(255) NOT NULL,
    broker_port INT NOT NULL DEFAULT 8883,
    username VARCHAR(100) NULL,
    password VARCHAR(255) NULL COMMENT 'Encrypted',
    use_tls TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. 센서 데이터 저장 테이블 (기존 sensor_readings 확장)
CREATE TABLE IF NOT EXISTS smartfarm_sensor_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    temperature DECIMAL(5,2) NULL,
    humidity DECIMAL(5,2) NULL,
    light_intensity DECIMAL(10,2) NULL,
    ph_value DECIMAL(4,2) NULL,
    ec_value DECIMAL(5,2) NULL,
    co2_level DECIMAL(10,2) NULL,
    soil_moisture DECIMAL(5,2) NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_time (user_id, recorded_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. 카메라 설정 테이블
CREATE TABLE IF NOT EXISTS smartfarm_cameras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    camera_id VARCHAR(50) NOT NULL,
    camera_name VARCHAR(100) NOT NULL,
    stream_url VARCHAR(500) NULL,
    stream_type VARCHAR(20) DEFAULT 'mjpeg' COMMENT 'mjpeg, rtsp, webrtc',
    enabled TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_camera (user_id, camera_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. 알림/경고 로그 테이블
CREATE TABLE IF NOT EXISTS smartfarm_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    alert_type VARCHAR(20) NOT NULL COMMENT 'warning, critical, info',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. 디바이스 등록 테이블
CREATE TABLE IF NOT EXISTS smartfarm_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_id VARCHAR(100) NOT NULL COMMENT 'ESP32 MAC 주소 또는 고유 ID',
    device_name VARCHAR(100) NOT NULL,
    device_type VARCHAR(50) NOT NULL COMMENT 'esp32, raspberry_pi',
    firmware_version VARCHAR(50) NULL,
    last_online TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_device_id (device_id),
    INDEX idx_user_active (user_id, is_active),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 샘플 데이터 삽입 (테스트용)
-- INSERT INTO smartfarm_device_states (user_id, device_name, status, value) VALUES
-- (1, 'fan_front', 'off', NULL),
-- (1, 'fan_rear', 'off', NULL),
-- (1, 'fan_ceiling', 'off', NULL),
-- (1, 'side_left', 'position', '0'),
-- (1, 'side_right', 'position', '0'),
-- (1, 'roof_left', 'position', '0'),
-- (1, 'roof_right', 'position', '0'),
-- (1, 'pump_nutrient', 'off', NULL),
-- (1, 'pump_curtain', 'off', NULL),
-- (1, 'pump_heating', 'off', NULL),
-- (1, 'mist_valve', 'off', NULL);
