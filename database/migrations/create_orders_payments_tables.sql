-- 주문(orders) 테이블 생성
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_number VARCHAR(50) NOT NULL UNIQUE COMMENT '주문 번호',
    total_amount INT NOT NULL COMMENT '총 주문 금액',
    status ENUM('pending', 'paid', 'cancelled', 'refunded') DEFAULT 'pending' COMMENT '주문 상태',
    customer_name VARCHAR(100) NOT NULL COMMENT '주문자 이름',
    customer_email VARCHAR(255) NOT NULL COMMENT '주문자 이메일',
    customer_phone VARCHAR(20) COMMENT '주문자 연락처',
    shipping_address TEXT COMMENT '배송지 주소',
    order_memo TEXT COMMENT '주문 메모',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_order_number (order_number),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='주문 정보';

-- 주문 상품(order_items) 테이블 생성
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT COMMENT '상품 ID',
    product_name VARCHAR(255) NOT NULL COMMENT '상품명',
    product_price INT NOT NULL COMMENT '상품 가격',
    quantity INT NOT NULL DEFAULT 1 COMMENT '수량',
    subtotal INT NOT NULL COMMENT '소계',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    INDEX idx_product_id (product_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='주문 상품 정보';

-- 결제(payments) 테이블 생성
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    tid VARCHAR(100) COMMENT '나이스페이먼츠 거래 ID',
    method VARCHAR(50) COMMENT '결제 수단 (CARD, BANK, VBANK, CELLPHONE)',
    amount INT NOT NULL COMMENT '결제 금액',
    status ENUM('pending', 'approved', 'cancelled', 'failed') DEFAULT 'pending' COMMENT '결제 상태',
    result_code VARCHAR(20) COMMENT '결제 결과 코드',
    result_message TEXT COMMENT '결제 결과 메시지',
    card_company VARCHAR(50) COMMENT '카드사',
    card_number VARCHAR(50) COMMENT '카드번호 (마스킹)',
    installment INT DEFAULT 0 COMMENT '할부 개월 수',
    approve_no VARCHAR(50) COMMENT '승인 번호',
    paid_at DATETIME COMMENT '결제 완료 시간',
    cancelled_at DATETIME COMMENT '결제 취소 시간',
    cancel_reason TEXT COMMENT '취소 사유',
    pg_raw_data TEXT COMMENT 'PG사 원본 응답 데이터 (JSON)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    INDEX idx_tid (tid),
    INDEX idx_status (status),
    INDEX idx_paid_at (paid_at),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='결제 정보';

-- 주문 번호 생성을 위한 시퀀스 테이블
CREATE TABLE IF NOT EXISTS order_sequence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='주문번호 시퀀스';
