<?php
/**
 * Shopping Cart Class
 * Handles shopping cart operations with database storage for logged-in users
 * and session storage for guest users
 */

require_once __DIR__ . '/../config/database.php';

class Cart {
    private $db;
    private $userId;
    private $isLoggedIn;

    public function __construct() {
        // 세션 시작
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->db = DatabaseConfig::getConnection();

        // 사용자 로그인 상태 확인
        $this->userId = $_SESSION['user_id'] ?? null;
        $this->isLoggedIn = !empty($this->userId);

        // 세션 기반 장바구니 초기화 (게스트 사용자용)
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        // 로그인 사용자의 경우 데이터베이스에서 장바구니 동기화
        if ($this->isLoggedIn) {
            $this->syncCartFromDatabase();
        }
    }

    /**
     * 데이터베이스에서 장바구니를 세션으로 동기화
     */
    private function syncCartFromDatabase() {
        if (!$this->isLoggedIn) {
            return; // 비로그인 사용자는 동기화하지 않음
        }

        try {
            // 세션 장바구니 백업
            $sessionBackup = $_SESSION['cart'] ?? [];

            $stmt = $this->db->prepare("
                SELECT c.product_id, c.quantity, p.name, p.price,
                       p.images, p.discount_percentage, c.created_at, c.updated_at
                FROM cart c
                JOIN products p ON c.product_id = p.id
                WHERE c.user_id = ? AND p.status = 'active'
                ORDER BY c.updated_at DESC
            ");
            $stmt->execute([$this->userId]);
            $dbCart = $stmt->fetchAll();

            // 세션 장바구니 초기화
            $_SESSION['cart'] = [];

            foreach ($dbCart as $item) {
                $cartKey = 'product_' . $item['product_id'];

                // 이미지 처리
                $imageUrl = '';
                if (!empty($item['images'])) {
                    $images = json_decode($item['images'], true);
                    $imageUrl = is_array($images) ? ($images[0] ?? '') : '';
                }

                // 할인 가격 계산
                $originalPrice = (float)$item['price'];
                $discountPercentage = (int)($item['discount_percentage'] ?? 0);
                $salePrice = $discountPercentage > 0
                    ? $originalPrice * (1 - $discountPercentage / 100)
                    : $originalPrice;

                $_SESSION['cart'][$cartKey] = [
                    'product_id' => $item['product_id'],
                    'name' => $item['name'],
                    'price' => $salePrice,
                    'original_price' => $originalPrice,
                    'quantity' => $item['quantity'],
                    'image' => $imageUrl,
                    'sku' => ''
                ];
            }
        } catch (PDOException $e) {
            error_log("Cart syncCartFromDatabase Error: " . $e->getMessage());
        }
    }

    /**
     * 세션 장바구니를 데이터베이스에 저장
     */
    private function saveCartToDatabase() {
        if (!$this->isLoggedIn) {
            return;
        }

        try {
            // 트랜잭션 시작
            $this->db->beginTransaction();

            // 기존 장바구니 삭제
            $stmt = $this->db->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$this->userId]);

            // 현재 세션 장바구니가 비어있지 않은 경우에만 저장
            if (!empty($_SESSION['cart'])) {
                // UPSERT 방식으로 저장 (중복 방지)
                $insertStmt = $this->db->prepare("
                    INSERT INTO cart (user_id, product_id, quantity)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    quantity = VALUES(quantity),
                    updated_at = CURRENT_TIMESTAMP
                ");

                foreach ($_SESSION['cart'] as $item) {
                    if (isset($item['product_id'], $item['quantity']) && $item['quantity'] > 0) {
                        $insertStmt->execute([
                            $this->userId,
                            $item['product_id'],
                            $item['quantity']
                        ]);
                    }
                }
            }

            // 트랜잭션 커밋
            $this->db->commit();

        } catch (PDOException $e) {
            // 트랜잭션 롤백
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("Cart saveCartToDatabase Error: " . $e->getMessage());
            error_log("Cart saveCartToDatabase Trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Add item to cart
     */
    public function addItem($productId, $quantity = 1) {
        try {
            // Get product details
            $stmt = $this->db->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product) {
                return ['success' => false, 'message' => '상품을 찾을 수 없습니다.'];
            }

            // Check stock
            if ($product['stock'] < $quantity) {
                return ['success' => false, 'message' => '재고가 부족합니다.'];
            }

            // Add to cart session
            $cartKey = 'product_' . $productId;

            if (isset($_SESSION['cart'][$cartKey])) {
                $_SESSION['cart'][$cartKey]['quantity'] += $quantity;
            } else {
                // 이미지 처리
                $imageUrl = '';
                if (!empty($product['images'])) {
                    $images = json_decode($product['images'], true);
                    $imageUrl = is_array($images) ? ($images[0] ?? '') : '';
                }

                // 할인 가격 계산
                $originalPrice = (float)$product['price'];
                $discountPercentage = (int)($product['discount_percentage'] ?? 0);
                $salePrice = $discountPercentage > 0
                    ? $originalPrice * (1 - $discountPercentage / 100)
                    : $originalPrice;

                $_SESSION['cart'][$cartKey] = [
                    'product_id' => $productId,
                    'name' => $product['name'],
                    'price' => $salePrice,
                    'original_price' => $originalPrice,
                    'quantity' => $quantity,
                    'image' => $imageUrl,
                    'sku' => ''
                ];
            }

            // 로그인 사용자의 경우 데이터베이스에 저장
            if ($this->isLoggedIn) {
                $this->saveCartToDatabase();
            }

            return ['success' => true, 'message' => '장바구니에 추가되었습니다.'];

        } catch (PDOException $e) {
            error_log("Cart addItem PDO Error: " . $e->getMessage());
            return ['success' => false, 'message' => '장바구니 추가 중 오류가 발생했습니다: ' . $e->getMessage()];
        } catch (Exception $e) {
            error_log("Cart addItem General Error: " . $e->getMessage());
            return ['success' => false, 'message' => '장바구니 추가 중 오류가 발생했습니다: ' . $e->getMessage()];
        }
    }

    /**
     * Update item quantity
     */
    public function updateQuantity($productId, $quantity) {
        $cartKey = 'product_' . $productId;

        if (!isset($_SESSION['cart'][$cartKey])) {
            return ['success' => false, 'message' => '상품이 장바구니에 없습니다.'];
        }

        if ($quantity <= 0) {
            return $this->removeItem($productId);
        }

        try {
            // Check stock
            $stmt = $this->db->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product || $product['stock'] < $quantity) {
                return ['success' => false, 'message' => '재고가 부족합니다.'];
            }

            $_SESSION['cart'][$cartKey]['quantity'] = $quantity;

            // 로그인 사용자의 경우 데이터베이스에 저장
            if ($this->isLoggedIn) {
                $this->saveCartToDatabase();
            }

            return ['success' => true, 'message' => '수량이 변경되었습니다.'];

        } catch (PDOException $e) {
            return ['success' => false, 'message' => '수량 변경 중 오류가 발생했습니다.'];
        }
    }

    /**
     * Remove item from cart
     */
    public function removeItem($productId) {
        $cartKey = 'product_' . $productId;

        if (isset($_SESSION['cart'][$cartKey])) {
            unset($_SESSION['cart'][$cartKey]);

            // 로그인 사용자의 경우 데이터베이스에서도 삭제
            if ($this->isLoggedIn) {
                try {
                    $stmt = $this->db->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                    $stmt->execute([$this->userId, $productId]);
                } catch (PDOException $e) {
                    error_log("Cart removeItem Database Error: " . $e->getMessage());
                }
            }

            return ['success' => true, 'message' => '상품이 장바구니에서 제거되었습니다.'];
        }

        return ['success' => false, 'message' => '상품이 장바구니에 없습니다.'];
    }

    /**
     * Clear cart
     */
    public function clearCart() {
        $_SESSION['cart'] = [];

        // 로그인 사용자의 경우 데이터베이스에서도 삭제
        if ($this->isLoggedIn) {
            try {
                $stmt = $this->db->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$this->userId]);
            } catch (PDOException $e) {
                error_log("Cart clearCart Database Error: " . $e->getMessage());
            }
        }

        return ['success' => true, 'message' => '장바구니가 비워졌습니다.'];
    }

    /**
     * Get cart items
     */
    public function getItems() {
        // 로그인 사용자는 데이터베이스에서 최신 데이터를 가져옴
        if ($this->isLoggedIn) {
            $this->syncCartFromDatabase();
        }
        return $_SESSION['cart'] ?? [];
    }

    /**
     * Get cart item count
     */
    public function getItemCount() {
        $count = 0;
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
        }
        return $count;
    }

    /**
     * Get cart total
     */
    public function getTotal() {
        $total = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        return $total;
    }

    /**
     * Get cart subtotal (before discount)
     */
    public function getSubtotal() {
        $subtotal = 0;
        foreach ($_SESSION['cart'] as $item) {
            $subtotal += $item['original_price'] * $item['quantity'];
        }
        return $subtotal;
    }

    /**
     * Get discount amount
     */
    public function getDiscountAmount() {
        return $this->getSubtotal() - $this->getTotal();
    }

    /**
     * Get shipping cost
     */
    public function getShippingCost() {
        $total = $this->getTotal();
        $freeShippingAmount = 50000; // Default free shipping amount

        // Get from settings if available
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'free_shipping_amount'");
            $stmt->execute();
            $result = $stmt->fetch();
            if ($result) {
                $freeShippingAmount = (int)$result['setting_value'];
            }
        } catch (PDOException $e) {
            // Use default value
        }

        if ($total >= $freeShippingAmount) {
            return 0;
        }

        // Default shipping cost
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'shipping_cost'");
            $stmt->execute();
            $result = $stmt->fetch();
            if ($result) {
                return (int)$result['setting_value'];
            }
        } catch (PDOException $e) {
            // Use default value
        }

        return 3000; // Default shipping cost
    }

    /**
     * Get final total including shipping
     */
    public function getFinalTotal() {
        return $this->getTotal() + $this->getShippingCost();
    }

    /**
     * Validate cart items (check stock, prices, etc.)
     */
    public function validateCart() {
        $errors = [];

        foreach ($_SESSION['cart'] as $key => $item) {
            try {
                $stmt = $this->db->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
                $stmt->execute([$item['product_id']]);
                $product = $stmt->fetch();

                if (!$product) {
                    $errors[] = $item['name'] . ' 상품이 더 이상 판매되지 않습니다.';
                    unset($_SESSION['cart'][$key]);
                    continue;
                }

                // Check stock
                if ($product['stock'] < $item['quantity']) {
                    $errors[] = $item['name'] . ' 상품의 재고가 부족합니다. (재고: ' . $product['stock'] . ')';
                    $_SESSION['cart'][$key]['quantity'] = $product['stock'];
                }

                // Check price changes
                $currentPrice = (float)$product['price'];
                if ($item['original_price'] != $currentPrice) {
                    $errors[] = $item['name'] . ' 상품의 가격이 변경되었습니다.';
                    $_SESSION['cart'][$key]['original_price'] = $currentPrice;
                    $_SESSION['cart'][$key]['price'] = $currentPrice;
                }

            } catch (PDOException $e) {
                $errors[] = '상품 정보 확인 중 오류가 발생했습니다.';
            }
        }

        // 로그인 사용자의 경우 변경사항을 데이터베이스에 저장
        if ($this->isLoggedIn && !empty($errors)) {
            $this->saveCartToDatabase();
        }

        return $errors;
    }

    /**
     * Get cart summary for display
     */
    public function getSummary() {
        return [
            'items' => $this->getItems(),
            'item_count' => $this->getItemCount(),
            'subtotal' => $this->getSubtotal(),
            'discount' => $this->getDiscountAmount(),
            'total' => $this->getTotal(),
            'shipping_cost' => $this->getShippingCost(),
            'final_total' => $this->getFinalTotal()
        ];
    }

    /**
     * 게스트 사용자가 로그인할 때 세션 장바구니를 데이터베이스로 이전
     */
    public function mergeGuestCartToUser($userId) {
        $this->userId = $userId;
        $this->isLoggedIn = true;

        // 기존 세션 장바구니가 있으면 데이터베이스로 이전
        if (!empty($_SESSION['cart'])) {
            try {
                // 기존 사용자 장바구니를 세션으로 로드
                $this->syncCartFromDatabase();

                // 게스트 장바구니와 병합 후 저장
                $this->saveCartToDatabase();
            } catch (Exception $e) {
                error_log("Cart mergeGuestCartToUser Error: " . $e->getMessage());
            }
        } else {
            // 기존 사용자 장바구니만 로드
            $this->syncCartFromDatabase();
        }
    }
}
?>