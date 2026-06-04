<?php
/**
 * Shopping Cart Class
 * Handles shopping cart operations
 */

require_once __DIR__ . '/../config/database.php';

class Cart {
    private $db;

    public function __construct() {
        $this->db = DatabaseConfig::getConnection();

        // Initialize cart session
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
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
            if ($product['stock_quantity'] < $quantity) {
                return ['success' => false, 'message' => '재고가 부족합니다.'];
            }

            // Add to cart session
            $cartKey = 'product_' . $productId;

            if (isset($_SESSION['cart'][$cartKey])) {
                $_SESSION['cart'][$cartKey]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$cartKey] = [
                    'product_id' => $productId,
                    'name' => $product['name'],
                    'price' => $product['sale_price'] ?: $product['price'],
                    'original_price' => $product['price'],
                    'quantity' => $quantity,
                    'image' => $product['images'] ? json_decode($product['images'], true)[0] ?? '' : '',
                    'sku' => $product['sku']
                ];
            }

            return ['success' => true, 'message' => '장바구니에 추가되었습니다.'];

        } catch (PDOException $e) {
            return ['success' => false, 'message' => '장바구니 추가 중 오류가 발생했습니다.'];
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
            $stmt = $this->db->prepare("SELECT stock_quantity FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product || $product['stock_quantity'] < $quantity) {
                return ['success' => false, 'message' => '재고가 부족합니다.'];
            }

            $_SESSION['cart'][$cartKey]['quantity'] = $quantity;
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
            return ['success' => true, 'message' => '상품이 장바구니에서 제거되었습니다.'];
        }

        return ['success' => false, 'message' => '상품이 장바구니에 없습니다.'];
    }

    /**
     * Clear cart
     */
    public function clearCart() {
        $_SESSION['cart'] = [];
        return ['success' => true, 'message' => '장바구니가 비워졌습니다.'];
    }

    /**
     * Get cart items
     */
    public function getItems() {
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
     * Apply coupon
     */
    public function applyCoupon($couponCode) {
        // This is a placeholder for coupon functionality
        // You would implement coupon logic here
        return ['success' => false, 'message' => '쿠폰 기능은 추후 구현 예정입니다.'];
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

        // Get shipping cost from settings
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
                if ($product['stock_quantity'] < $item['quantity']) {
                    $errors[] = $item['name'] . ' 상품의 재고가 부족합니다. (재고: ' . $product['stock_quantity'] . ')';
                    $_SESSION['cart'][$key]['quantity'] = $product['stock_quantity'];
                }

                // Check price changes
                $currentPrice = $product['sale_price'] ?: $product['price'];
                if ($item['price'] != $currentPrice) {
                    $errors[] = $item['name'] . ' 상품의 가격이 변경되었습니다.';
                    $_SESSION['cart'][$key]['price'] = $currentPrice;
                }

            } catch (PDOException $e) {
                $errors[] = '상품 정보 확인 중 오류가 발생했습니다.';
            }
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
}
?>