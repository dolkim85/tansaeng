-- ESP32 장치 연결 상태 테이블
CREATE TABLE IF NOT EXISTS device_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    controller_id VARCHAR(50) NOT NULL UNIQUE,
    status ENUM('online', 'offline') DEFAULT 'offline',
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_controller_id (controller_id),
    INDEX idx_status (status),
    INDEX idx_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 초기 장치 등록 (4개 컨트롤러)
INSERT INTO device_status (controller_id, status, last_seen)
VALUES
    ('ctlr-0001', 'offline', NOW()),
    ('ctlr-0002', 'offline', NOW()),
    ('ctlr-0003', 'offline', NOW()),
    ('ctlr-0004', 'offline', NOW())
ON DUPLICATE KEY UPDATE
    updated_at = NOW();
