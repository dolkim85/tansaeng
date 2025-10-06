<?php
/**
 * 새로운 장바구니 클래스 - 깔끔하고 간단한 구조
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
        $this->userId = $_SESSION['user_id'] ?? null;
        $this->isLoggedIn = !empty($this->userId);

        // 세션 기반 장바구니 초기화
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        // 로그인 사용자의 경우 데이터베이스에서 장바구니 동기화
        if ($this->isLoggedIn) {
            $this->syncFromDatabase();
        }
    }

    /**
     * 데이터베이스에서 세션으로 동기화
     */
    private function syncFromDatabase() {
        try {
            $stmt = $this->db->prepare("
                SELECT c.product_id, c.quantity, p.name, p.price, p.images, p.image_url, p.discount_percentage
                FROM cart c
                JOIN products p ON c.product_id = p.id
                WHERE c.user_id = ? AND p.status = 'active'
            ");
            $stmt->execute([$this->userId]);
            $dbCart = $stmt->fetchAll();

            // 세션 장바구니 재구성
            $_SESSION['cart'] = [];
            foreach ($dbCart as $item) {
                $cartKey = 'product_' . $item['product_id'];

                // 할인 가격 계산
                $originalPrice = (float)$item['price'];
                $discountPercentage = (int)($item['discount_percentage'] ?? 0);
                $salePrice = $discountPercentage > 0
                    ? $originalPrice * (1 - $discountPercentage / 100)
                    : $originalPrice;

                // 이미지 처리
                $imageUrl = '';
                if (!empty($item['images'])) {
                    $images = json_decode($item['images'], true);
                    $imageUrl = is_array($images) ? ($images[0] ?? '') : '';
                }
                // images가 없으면 image_url 사용
                if (empty($imageUrl) && !empty($item['image_url'])) {
                    $imageUrl = $item['image_url'];
                }

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
            error_log("Cart sync error: " . $e->getMessage());
        }
    }

    /**
     * 세션에서 데이터베이스로 저장
     */
    private function saveToDatabase() {
        if (!$this->isLoggedIn) {
            return;
        }

        try {
            $this->db->beginTransaction();

            // 기존 장바구니 삭제
            $stmt = $this->db->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$this->userId]);

            // 현재 세션 장바구니 저장
            if (!empty($_SESSION['cart'])) {
                $insertStmt = $this->db->prepare("
                    INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)
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

            $this->db->commit();
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log("Cart save error: " . $e->getMessage());
        }
    }

    /**
     * 상품 추가
     */
    public function addItem($productId, $quantity = 1) {
        try {
            // 상품 정보 확인
            $stmt = $this->db->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product) {
                return ['success' => false, 'message' => '상품을 찾을 수 없습니다.'];
            }

            // 재고 확인
            $stockQuantity = $product['stock_quantity'] ?? $product['stock'] ?? 0;
            if ($stockQuantity < $quantity) {
                return ['success' => false, 'message' => '재고가 부족합니다.'];
            }

            $cartKey = 'product_' . $productId;

            // 기존 상품이 있으면 수량 추가
            if (isset($_SESSION['cart'][$cartKey])) {
                $_SESSION['cart'][$cartKey]['quantity'] += $quantity;
            } else {
                // 새 상품 추가
                $originalPrice = (float)$product['price'];
                $discountPercentage = (int)($product['discount_percentage'] ?? 0);
                $salePrice = $discountPercentage > 0
                    ? $originalPrice * (1 - $discountPercentage / 100)
                    : $originalPrice;

                // 이미지 처리
                $imageUrl = '';
                if (!empty($product['images'])) {
                    $images = json_decode($product['images'], true);
                    $imageUrl = is_array($images) ? ($images[0] ?? '') : '';
                }
                // images가 없으면 image_url 사용
                if (empty($imageUrl) && !empty($product['image_url'])) {
                    $imageUrl = $product['image_url'];
                }

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

            // 재고 차감 (임시 예약)
            $stockColumn = isset($product['stock_quantity']) ? 'stock_quantity' : 'stock';
            $updateStmt = $this->db->prepare("UPDATE products SET {$stockColumn} = {$stockColumn} - ? WHERE id = ?");
            $updateStmt->execute([$quantity, $productId]);

            // 로그인 사용자는 데이터베이스에도 저장
            if ($this->isLoggedIn) {
                $this->saveToDatabase();
            }

            return ['success' => true, 'message' => '장바구니에 추가되었습니다.'];

        } catch (PDOException $e) {
            error_log("Cart addItem error: " . $e->getMessage());
            return ['success' => false, 'message' => '상품 추가 중 오류가 발생했습니다.'];
        }
    }

    /**
     * 수량 변경
     */
    public function updateQuantity($productId, $quantity) {
        $cartKey = 'product_' . $productId;

        if (!isset($_SESSION['cart'][$cartKey])) {
            return ['success' => false, 'message' => '장바구니에 해당 상품이 없습니다.'];
        }

        if ($quantity < 1) {
            return ['success' => false, 'message' => '수량은 1개 이상이어야 합니다.'];
        }

        try {
            // 재고 확인
            $stmt = $this->db->prepare("SELECT stock_quantity, stock FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            $stockQuantity = $product['stock_quantity'] ?? $product['stock'] ?? 0;
            if (!$product || $stockQuantity < $quantity) {
                return ['success' => false, 'message' => '재고가 부족합니다.'];
            }

            $_SESSION['cart'][$cartKey]['quantity'] = $quantity;

            // 로그인 사용자는 데이터베이스에도 저장
            if ($this->isLoggedIn) {
                $this->saveToDatabase();
            }

            return ['success' => true, 'message' => '수량이 변경되었습니다.'];

        } catch (PDOException $e) {
            error_log("Cart updateQuantity error: " . $e->getMessage());
            return ['success' => false, 'message' => '수량 변경 중 오류가 발생했습니다.'];
        }
    }

    /**
     * 상품 제거
     */
    public function removeItem($productId) {
        $cartKey = 'product_' . $productId;

        if (isset($_SESSION['cart'][$cartKey])) {
            $removedQuantity = $_SESSION['cart'][$cartKey]['quantity'];
            unset($_SESSION['cart'][$cartKey]);

            // 재고 복원
            try {
                $stmt = $this->db->prepare("SELECT stock_quantity, stock FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                $product = $stmt->fetch();

                if ($product) {
                    $stockColumn = isset($product['stock_quantity']) ? 'stock_quantity' : 'stock';
                    $updateStmt = $this->db->prepare("UPDATE products SET {$stockColumn} = {$stockColumn} + ? WHERE id = ?");
                    $updateStmt->execute([$removedQuantity, $productId]);
                }
            } catch (PDOException $e) {
                error_log("Cart removeItem stock restore error: " . $e->getMessage());
            }

            // 로그인 사용자는 데이터베이스에서도 삭제
            if ($this->isLoggedIn) {
                try {
                    $stmt = $this->db->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                    $stmt->execute([$this->userId, $productId]);
                } catch (PDOException $e) {
                    error_log("Cart removeItem DB error: " . $e->getMessage());
                }
            }

            return ['success' => true, 'message' => '상품이 장바구니에서 제거되었습니다.'];
        }

        return ['success' => false, 'message' => '장바구니에 해당 상품이 없습니다.'];
    }

    /**
     * 장바구니 비우기
     */
    public function clearCart() {
        $_SESSION['cart'] = [];

        // 로그인 사용자는 데이터베이스에서도 삭제
        if ($this->isLoggedIn) {
            try {
                $stmt = $this->db->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$this->userId]);
            } catch (PDOException $e) {
                error_log("Cart clearCart DB error: " . $e->getMessage());
            }
        }

        return ['success' => true, 'message' => '장바구니가 비워졌습니다.'];
    }

    /**
     * 장바구니 항목 조회
     */
    public function getItems() {
        return $_SESSION['cart'] ?? [];
    }

    /**
     * 장바구니 개수 조회
     */
    public function getItemCount() {
        $count = 0;
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
        }
        return $count;
    }

    /**
     * 장바구니 요약 정보
     */
    public function getSummary() {
        $items = $_SESSION['cart'] ?? [];
        $itemCount = 0;
        $subtotal = 0;

        foreach ($items as $item) {
            $itemCount += $item['quantity'];
            $subtotal += $item['price'] * $item['quantity'];
        }

        // 배송비 계산 (5만원 이상 무료배송)
        $shippingCost = $subtotal >= 50000 ? 0 : 3000;
        $finalTotal = $subtotal + $shippingCost;

        return [
            'items' => $items,
            'item_count' => $itemCount,
            'subtotal' => $subtotal,
            'discount' => 0,
            'total' => $subtotal,
            'shipping_cost' => $shippingCost,
            'final_total' => $finalTotal
        ];
    }
}
?>