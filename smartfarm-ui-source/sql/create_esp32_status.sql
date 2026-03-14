-- ESP32 장치 연결 상태 테이블
CREATE TABLE IF NOT EXISTS esp32_status (
  id INT AUTO_INCREMENT PRIMARY KEY,
  controller_id VARCHAR(50) NOT NULL UNIQUE,
  controller_name VARCHAR(100) NOT NULL,
  is_connected BOOLEAN DEFAULT FALSE,
  last_heartbeat TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_controller_id (controller_id),
  INDEX idx_last_heartbeat (last_heartbeat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 기존 ESP32 장치들 초기화
INSERT INTO esp32_status (controller_id, controller_name, is_connected) VALUES
('ctlr-0001', '내부팬 앞', FALSE),
('ctlr-0002', '내부팬 뒤', FALSE),
('ctlr-0003', '천장 환기', FALSE),
('ctlr-0004', '메인 밸브', FALSE),
('ctlr-0005', '라인 밸브', FALSE),
('ctlr-0006', '가압 펌프', FALSE),
('ctlr-0007', '주입 펌프', FALSE),
('ctlr-0008', '히트펌프 밸브', FALSE),
('ctlr-0009', '칠러 펌프', FALSE),
('ctlr-0010', '칠러/히트 통합 펌프', FALSE),
('ctlr-0011', '천창 스크린', FALSE),
('ctlr-0012', '측창 스크린', FALSE),
('ctlr-0013', '천창 칠러라인 펌프 밸브', FALSE)
ON DUPLICATE KEY UPDATE controller_name = VALUES(controller_name);
