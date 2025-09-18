-- 게시판 답글 테이블 생성
CREATE TABLE IF NOT EXISTS board_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    content TEXT NOT NULL,
    author VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    is_private BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'deleted') DEFAULT 'active',
    FOREIGN KEY (post_id) REFERENCES board_posts(id) ON DELETE CASCADE,
    INDEX idx_replies_post_id (post_id),
    INDEX idx_replies_created_at (created_at),
    INDEX idx_replies_status (status)
);