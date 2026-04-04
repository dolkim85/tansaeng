-- Tangsaeng Smart Farm Database Installation Script
-- Create database and tables

CREATE DATABASE IF NOT EXISTS tangsaeng_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tangsaeng_db;

-- Company Information Table
CREATE TABLE company_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    images JSON,
    videos JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Users Table (Main user management)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    age_range VARCHAR(20) NULL COMMENT '연령대 (10대, 20대, 30대, 40대, 50대, 60대 이상)',
    gender VARCHAR(10) NULL COMMENT '성별 (남성, 여성, 기타)',
    user_level TINYINT DEFAULT 1 COMMENT '1: General, 2: Plant Analysis, 9: Admin',
    plant_analysis_permission BOOLEAN DEFAULT FALSE,
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(100),
    reset_token VARCHAR(100),
    reset_token_expires DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_user_level (user_level),
    INDEX idx_plant_permission (plant_analysis_permission)
);

-- User Login Logs
CREATE TABLE user_login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    success BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_login_time (login_time)
);

-- User Permissions History
CREATE TABLE user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission_type VARCHAR(50) NOT NULL,
    granted_by INT,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    revoked_at TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_permission_type (permission_type)
);

-- Admin Users (separate from regular users)
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Product Categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    parent_id INT NULL,
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_parent_id (parent_id),
    INDEX idx_status (status)
);

-- Products
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stock INT DEFAULT 0,
    images JSON,
    specifications JSON,
    status ENUM('active', 'inactive', 'out_of_stock') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category_id (category_id),
    INDEX idx_status (status),
    INDEX idx_price (price)
);

-- Orders
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    shipping_address TEXT,
    payment_method VARCHAR(50),
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_customer_id (customer_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Order Items
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id)
);

-- Shopping Cart
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id)
);

-- Plant Images (Raspberry Pi captured images)
CREATE TABLE plant_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255),
    file_size INT,
    captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    analysis_status ENUM('pending', 'analyzing', 'completed', 'failed') DEFAULT 'pending',
    raspberry_id VARCHAR(50),
    metadata JSON,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_captured_at (captured_at),
    INDEX idx_analysis_status (analysis_status)
);

-- Plant Analysis Results
CREATE TABLE plant_analysis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_id INT NOT NULL,
    user_id INT NOT NULL,
    species VARCHAR(200),
    health_status VARCHAR(100),
    recommendations TEXT,
    confidence DECIMAL(5, 2),
    analysis_data JSON,
    analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (image_id) REFERENCES plant_images(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_image_id (image_id),
    INDEX idx_user_id (user_id)
);

-- Sensor Readings
CREATE TABLE sensor_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    raspberry_id VARCHAR(50),
    temperature DECIMAL(5, 2),
    humidity DECIMAL(5, 2),
    light_intensity DECIMAL(8, 2),
    ph_value DECIMAL(4, 2),
    ec_value DECIMAL(8, 2),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metadata JSON,
    INDEX idx_raspberry_id (raspberry_id),
    INDEX idx_recorded_at (recorded_at)
);

-- Plant Analysis Activity Logs
CREATE TABLE plant_analysis_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Raspberry Pi Devices
CREATE TABLE raspberry_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_name VARCHAR(100) NOT NULL,
    device_id VARCHAR(50) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    status ENUM('online', 'offline', 'maintenance') DEFAULT 'offline',
    last_contact TIMESTAMP,
    configuration JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_device_id (device_id),
    INDEX idx_status (status)
);

-- Support/FAQ
CREATE TABLE faq (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50),
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Contact Messages
CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200),
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied', 'closed') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Notices
CREATE TABLE notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    author_id INT,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- Insert default admin user (password: admin2025)
INSERT INTO admin_users (username, password, email, role) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@tangsaeng.com', 'super_admin');

-- Insert default user with admin privileges (username: admin, password: admin2025)
INSERT INTO users (name, email, password, user_level, plant_analysis_permission, created_at) 
VALUES ('관리자', 'admin@tangsaeng.com', '$2y$10$E4gF8VqmV7LR3X4Yr0bOIuOhGyR6B/m5kFv5T2xO7cPmLB8xYZv0u', 9, 1, NOW());

-- Insert some sample data
INSERT INTO categories (name, description, parent_id, sort_order) VALUES
('배지', '수경재배용 고급 배지', NULL, 1),
('양액', '작물별 맞춤형 양액', NULL, 2),
('장비', 'IoT 센서 및 모니터링 장비', NULL, 3),
('부자재', '재배에 필요한 각종 부자재', NULL, 4);

INSERT INTO products (name, category_id, description, price, stock, status) VALUES
('탄생 프리미엄 배지', 1, '최고급 코코피트와 펄라이트의 완벽한 조합으로 제작된 프리미엄 배지입니다.', 25000, 50, 'active'),
('토마토 전용 양액', 2, '토마토 재배에 최적화된 전용 양액으로 높은 수확량을 보장합니다.', 35000, 30, 'active'),
('IoT 센서 키트', 3, '온도, 습도, pH, EC를 실시간으로 모니터링할 수 있는 통합 센서 키트입니다.', 150000, 15, 'active'),
('오이 전용 양액', 2, '오이 재배에 최적화된 전용 양액', 32000, 25, 'active'),
('상추 전용 양액', 2, '상추류 재배에 특화된 양액', 28000, 40, 'active'),
('수경재배 키트', 3, '초보자를 위한 완전한 수경재배 키트', 89000, 12, 'active'),
('LED 식물 조명', 3, '식물 성장에 최적화된 전문 LED 조명', 120000, 8, 'active'),
('pH 측정기', 3, '정확한 pH 측정을 위한 디지털 측정기', 45000, 20, 'active');

INSERT INTO notices (title, content, status, published_at, created_at) VALUES
('탄생 스마트팜 오픈 안내', '새로운 AI 기반 스마트팜 시스템이 오픈되었습니다. 최신 IoT 기술과 식물분석 AI를 통해 더욱 스마트한 농업을 경험해보세요.', 'published', NOW(), NOW()),
('신규 회원 혜택 안내', '신규 회원 가입시 10% 할인 쿠폰과 무료배송 혜택을 제공합니다. 지금 가입하고 혜택을 받아보세요!', 'published', NOW(), NOW()),
('고객센터 운영 안내', '고객센터는 평일 오전 9시부터 오후 6시까지 운영됩니다. 궁금한 사항은 언제든지 문의해주세요.', 'published', NOW(), NOW());

-- Insert subcategories for 배지
INSERT INTO categories (name, description, parent_id) VALUES 
('코코피트 배지', '코코넛 껍질 기반 배지', 1),
('펄라이트 배지', '펄라이트 기반 배지', 1),
('혼합 배지', '여러 재료가 혼합된 배지', 1),
('유기농 배지', '유기농 인증 배지', 1);

-- Insert subcategories for 부자재
INSERT INTO categories (name, description, parent_id) VALUES 
('화분 및 용기', '각종 화분과 재배 용기', 4),
('관수 시설', '물주기 관련 장비', 4),
('지지대 및 끈', '식물 지지용 자재', 4),
('온도 조절 용품', '온도 관리 장비', 4);

-- Insert subcategories for 양액
INSERT INTO categories (name, description, parent_id) VALUES 
('기본 양액', '범용 양액', 2),
('전용 양액', '작물별 전용 양액', 2),
('pH 조정제', 'pH 조절용 용액', 2),
('EC 조정제', '전기전도도 조절용', 2);

-- Insert sample sensor data for testing
INSERT INTO sensor_readings (raspberry_id, temperature, humidity, light_intensity, ph_value, ec_value, recorded_at) VALUES
('RASP001', 22.5, 65.0, 450.0, 6.2, 1.8, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
('RASP001', 23.1, 64.2, 470.0, 6.1, 1.9, DATE_SUB(NOW(), INTERVAL 50 MINUTE)),
('RASP001', 22.8, 66.5, 420.0, 6.3, 1.7, DATE_SUB(NOW(), INTERVAL 40 MINUTE)),
('RASP001', 23.5, 63.8, 480.0, 6.0, 2.0, DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
('RASP001', 22.9, 65.5, 460.0, 6.2, 1.8, DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
('RASP001', 23.2, 64.0, 440.0, 6.1, 1.9, DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
('RASP001', 23.0, 65.2, 450.0, 6.2, 1.8, NOW());

-- Insert sample plant images for testing
INSERT INTO plant_images (user_id, filename, original_filename, file_size, captured_at, analysis_status, raspberry_id) VALUES
(1, 'plant_001.jpg', 'tomato_leaf_sample.jpg', 204800, DATE_SUB(NOW(), INTERVAL 2 DAY), 'completed', 'RASP001'),
(1, 'plant_002.jpg', 'lettuce_sample.jpg', 189440, DATE_SUB(NOW(), INTERVAL 1 DAY), 'completed', 'RASP001'),
(1, 'plant_003.jpg', 'cucumber_leaf.jpg', 225280, DATE_SUB(NOW(), INTERVAL 6 HOUR), 'analyzing', 'RASP001');

-- Insert sample plant analysis results
INSERT INTO plant_analysis (image_id, user_id, species, health_status, recommendations, confidence, analysis_data, analyzed_at) VALUES
(1, 1, '토마토 (Solanum lycopersicum)', '건강', '현재 식물 상태가 매우 양호합니다. 적정 수분과 영양분이 공급되고 있습니다.', 94.50, '{"leaf_color": "green", "disease_detected": false, "growth_stage": "flowering"}', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 1, '상추 (Lactuca sativa)', '양호', '잎의 색상이 약간 연한 편입니다. 질소 공급을 조금 늘려보세요.', 89.20, '{"leaf_color": "light_green", "disease_detected": false, "growth_stage": "mature"}', DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Insert sample FAQ data
INSERT INTO faq (category, question, answer, sort_order, status) VALUES
('일반', '스마트팜 시스템은 어떻게 작동하나요?', 'IoT 센서를 통해 온도, 습도, pH 등을 실시간으로 모니터링하고, AI 기술로 식물 상태를 분석합니다.', 1, 'active'),
('기술', '식물분석 AI의 정확도는 얼마나 되나요?', '현재 95% 이상의 높은 정확도를 보이고 있으며, 지속적인 학습을 통해 개선되고 있습니다.', 2, 'active'),
('주문', '주문 후 배송은 얼마나 걸리나요?', '일반적으로 주문 후 2-3일 내에 배송됩니다. 지역에 따라 차이가 있을 수 있습니다.', 3, 'active'),
('기술', 'pH 값이 비정상일 때는 어떻게 해야 하나요?', 'pH 조정제를 사용하여 적정 범위(5.5-6.5)로 조절하시면 됩니다.', 4, 'active');

-- Insert sample raspberry device
INSERT INTO raspberry_devices (device_name, device_id, ip_address, status, last_contact, configuration) VALUES
('메인 온실 모니터링', 'RASP001', '192.168.1.100', 'online', NOW(), '{"sensors": ["temperature", "humidity", "ph", "ec"], "camera": true, "interval": 300}');

-- Insert sample company info
INSERT INTO company_info (title, content, images, videos) VALUES
('회사 소개', '탄생은 최첨단 스마트팜 기술을 통해 지속가능한 농업의 미래를 만들어갑니다.', '["company1.jpg", "company2.jpg"]', '["intro.mp4"]');

-- Installation completed message
SELECT 'Database installation completed successfully!' as message;