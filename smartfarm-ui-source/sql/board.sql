-- 게시판 테이블 생성
CREATE TABLE IF NOT EXISTS board_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    author VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    views INT DEFAULT 0,
    status ENUM('active', 'deleted') DEFAULT 'active',
    post_type ENUM('general', 'review') DEFAULT 'general',
    is_notice BOOLEAN DEFAULT FALSE
);

-- 게시판 첨부파일 테이블
CREATE TABLE IF NOT EXISTS board_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES board_posts(id) ON DELETE CASCADE
);

-- 관리자 계정 테이블 (기존 users 테이블 확장)
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin BOOLEAN DEFAULT FALSE;

-- 기본 관리자 계정 생성
INSERT IGNORE INTO users (username, email, password, name, phone, is_admin, user_level) 
VALUES ('admin', 'admin@tangsaeng.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '관리자', '02-0000-0000', TRUE, 9);

-- 인덱스 생성
CREATE INDEX idx_board_created_at ON board_posts(created_at);
CREATE INDEX idx_board_status ON board_posts(status);
CREATE INDEX idx_board_type ON board_posts(post_type);