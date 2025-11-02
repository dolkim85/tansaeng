<?php
/**
 * Order Management Class
 * Handles order processing, payment, and management
 */

require_once __DIR__ . '/../config/database.php';

class Order {
    private $db;

    public function __construct() {
        $this->db = DatabaseConfig::getConnection();
    }

    /**
     * Create new order
     */
    public function createOrder($orderData) {
        try {
            $this->db->beginTransaction();

            // Generate order number
            $orderNumber = $this->generateOrderNumber();

            // Insert order
            $stmt = $this->db->prepare("
                INSERT INTO orders (
                    user_id, order_number, status, total_amount, shipping_cost, tax_amount,
                    shipping_name, shipping_phone, shipping_address,
                    billing_name, billing_phone, billing_address,
                    payment_method, payment_status, notes
                ) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");

            $result = $stmt->execute([
                $orderData['user_id'],
                $orderNumber,
                $orderData['total_amount'],
                $orderData['shipping_cost'] ?? 0,
                $orderData['tax_amount'] ?? 0,
                $orderData['shipping_name'],
                $orderData['shipping_phone'],
                $orderData['shipping_address'],
                $orderData['billing_name'] ?? $orderData['shipping_name'],
                $orderData['billing_phone'] ?? $orderData['shipping_phone'],
                $orderData['billing_address'] ?? $orderData['shipping_address'],
                $orderData['payment_method'],
                $orderData['notes'] ?? null
            ]);

            if (!$result) {
                throw new Exception('주문 생성에 실패했습니다.');
            }

            $orderId = $this->db->lastInsertId();

            // Insert order items
            if (!empty($orderData['items'])) {
                foreach ($orderData['items'] as $item) {
                    $this->addOrderItem($orderId, $item);
                }
            }

            $this->db->commit();
            return ['success' => true, 'order_id' => $orderId, 'order_number' => $orderNumber];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Add item to order
     */
    private function addOrderItem($orderId, $item) {
        $stmt = $this->db->prepare("
            INSERT INTO order_items (
                order_id, product_id, product_name, product_sku,
                quantity, unit_price, total_price
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $orderId,
            $item['product_id'],
            $item['product_name'],
            $item['product_sku'] ?? null,
            $item['quantity'],
            $item['unit_price'],
            $item['total_price']
        ]);
    }

    /**
     * Generate unique order number
     */
    private function generateOrderNumber() {
        $prefix = date('Ymd');
        $suffix = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        return $prefix . $suffix;
    }

    /**
     * Get order by ID
     */
    public function getOrder($orderId) {
        try {
            $stmt = $this->db->prepare("
                SELECT o.*, u.username, u.email
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.id = ?
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if ($order) {
                // Get order items
                $order['items'] = $this->getOrderItems($orderId);
            }

            return $order;

        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Get order by order number
     */
    public function getOrderByNumber($orderNumber) {
        try {
            $stmt = $this->db->prepare("
                SELECT o.*, u.username, u.email
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.order_number = ?
            ");
            $stmt->execute([$orderNumber]);
            $order = $stmt->fetch();

            if ($order) {
                $order['items'] = $this->getOrderItems($order['id']);
            }

            return $order;

        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Get order items
     */
    public function getOrderItems($orderId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt->execute([$orderId]);
            return $stmt->fetchAll();

        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get orders by user
     */
    public function getUserOrders($userId, $limit = 20, $offset = 0) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM orders
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$userId, $limit, $offset]);
            return $stmt->fetchAll();

        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Update order status
     */
    public function updateStatus($orderId, $status, $notes = null) {
        try {
            $validStatuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

            if (!in_array($status, $validStatuses)) {
                return ['success' => false, 'message' => '유효하지 않은 상태입니다.'];
            }

            $sql = "UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP";
            $params = [$status];

            if ($notes !== null) {
                $sql .= ", notes = ?";
                $params[] = $notes;
            }

            $sql .= " WHERE id = ?";
            $params[] = $orderId;

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);

            if ($result) {
                return ['success' => true, 'message' => '주문 상태가 업데이트되었습니다.'];
            } else {
                return ['success' => false, 'message' => '상태 업데이트에 실패했습니다.'];
            }

        } catch (PDOException $e) {
            return ['success' => false, 'message' => '상태 업데이트 중 오류가 발생했습니다.'];
        }
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus($orderId, $paymentStatus) {
        try {
            $validStatuses = ['pending', 'paid', 'failed', 'refunded'];

            if (!in_array($paymentStatus, $validStatuses)) {
                return ['success' => false, 'message' => '유효하지 않은 결제 상태입니다.'];
            }

            $stmt = $this->db->prepare("
                UPDATE orders
                SET payment_status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $result = $stmt->execute([$paymentStatus, $orderId]);

            if ($result) {
                // If payment is successful, update order status to paid
                if ($paymentStatus === 'paid') {
                    $this->updateStatus($orderId, 'paid');
                }

                return ['success' => true, 'message' => '결제 상태가 업데이트되었습니다.'];
            } else {
                return ['success' => false, 'message' => '결제 상태 업데이트에 실패했습니다.'];
            }

        } catch (PDOException $e) {
            return ['success' => false, 'message' => '결제 상태 업데이트 중 오류가 발생했습니다.'];
        }
    }

    /**
     * Cancel order
     */
    public function cancelOrder($orderId, $reason = null) {
        try {
            $order = $this->getOrder($orderId);
            if (!$order) {
                return ['success' => false, 'message' => '주문을 찾을 수 없습니다.'];
            }

            // Only allow cancellation for pending or paid orders
            if (!in_array($order['status'], ['pending', 'paid'])) {
                return ['success' => false, 'message' => '취소할 수 없는 주문 상태입니다.'];
            }

            $notes = $order['notes'] ? $order['notes'] . "\n" : '';
            $notes .= "취소 사유: " . ($reason ?: '사용자 요청');

            $result = $this->updateStatus($orderId, 'cancelled', $notes);

            if ($result['success']) {
                // Restore stock quantities
                $this->restoreStock($orderId);
            }

            return $result;

        } catch (Exception $e) {
            return ['success' => false, 'message' => '주문 취소 중 오류가 발생했습니다.'];
        }
    }

    /**
     * Restore stock quantities
     */
    private function restoreStock($orderId) {
        try {
            $items = $this->getOrderItems($orderId);

            foreach ($items as $item) {
                if ($item['product_id']) {
                    $stmt = $this->db->prepare("
                        UPDATE products
                        SET stock_quantity = stock_quantity + ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$item['quantity'], $item['product_id']]);
                }
            }

        } catch (PDOException $e) {
            // Log error but don't throw exception
        }
    }

    /**
     * Get order statistics
     */
    public function getStatistics($startDate = null, $endDate = null) {
        try {
            $whereClause = "";
            $params = [];

            if ($startDate && $endDate) {
                $whereClause = "WHERE created_at BETWEEN ? AND ?";
                $params = [$startDate, $endDate];
            }

            // Total orders
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM orders $whereClause");
            $stmt->execute($params);
            $totalOrders = $stmt->fetch()['total'];

            // Total revenue
            $stmt = $this->db->prepare("
                SELECT SUM(total_amount) as revenue
                FROM orders
                WHERE payment_status = 'paid' $whereClause
            ");
            $stmt->execute($params);
            $totalRevenue = $stmt->fetch()['revenue'] ?: 0;

            // Status breakdown
            $stmt = $this->db->prepare("
                SELECT status, COUNT(*) as count
                FROM orders
                $whereClause
                GROUP BY status
            ");
            $stmt->execute($params);
            $statusBreakdown = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            return [
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'status_breakdown' => $statusBreakdown
            ];

        } catch (PDOException $e) {
            return [
                'total_orders' => 0,
                'total_revenue' => 0,
                'status_breakdown' => []
            ];
        }
    }

    /**
     * Get all orders with pagination
     */
    public function getAllOrders($limit = 20, $offset = 0, $filters = []) {
        try {
            $whereClause = "";
            $params = [];

            // Build WHERE clause based on filters
            if (!empty($filters['status'])) {
                $whereClause .= " WHERE status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['payment_status'])) {
                $whereClause .= empty($whereClause) ? " WHERE" : " AND";
                $whereClause .= " payment_status = ?";
                $params[] = $filters['payment_status'];
            }

            if (!empty($filters['search'])) {
                $whereClause .= empty($whereClause) ? " WHERE" : " AND";
                $whereClause .= " (order_number LIKE ? OR shipping_name LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $sql = "
                SELECT o.*, u.username, u.email
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                $whereClause
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();

        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * 결제 정보 저장 (나이스페이먼츠)
     *
     * @param int $orderId 주문 ID
     * @param array $paymentData 결제 데이터
     * @return array 저장 결과
     */
    public function savePayment($orderId, $paymentData) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO payments (
                    order_id, tid, method, amount, status,
                    result_code, result_message, card_company, card_number,
                    installment, approve_no, paid_at, pg_raw_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $result = $stmt->execute([
                $orderId,
                $paymentData['tid'] ?? null,
                $paymentData['method'] ?? null,
                $paymentData['amount'],
                $paymentData['status'],
                $paymentData['result_code'] ?? null,
                $paymentData['result_message'] ?? null,
                $paymentData['card_company'] ?? null,
                $paymentData['card_number'] ?? null,
                $paymentData['installment'] ?? 0,
                $paymentData['approve_no'] ?? null,
                $paymentData['paid_at'] ?? date('Y-m-d H:i:s'),
                $paymentData['pg_raw_data'] ?? null
            ]);

            if ($result) {
                return ['success' => true, 'payment_id' => $this->db->lastInsertId()];
            } else {
                return ['success' => false, 'message' => '결제 정보 저장에 실패했습니다.'];
            }

        } catch (PDOException $e) {
            error_log('Save payment error: ' . $e->getMessage());
            return ['success' => false, 'message' => '결제 정보 저장 중 오류가 발생했습니다.'];
        }
    }

    /**
     * 결제 정보 조회
     *
     * @param int $orderId 주문 ID
     * @return array|null 결제 정보
     */
    public function getPayment($orderId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM payments
                WHERE order_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$orderId]);
            return $stmt->fetch();

        } catch (PDOException $e) {
            error_log('Get payment error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 결제 취소 정보 저장
     *
     * @param int $paymentId 결제 ID
     * @param string $cancelReason 취소 사유
     * @return bool 성공 여부
     */
    public function cancelPayment($paymentId, $cancelReason = '고객 요청') {
        try {
            $stmt = $this->db->prepare("
                UPDATE payments
                SET status = 'cancelled',
                    cancelled_at = NOW(),
                    cancel_reason = ?
                WHERE id = ?
            ");
            return $stmt->execute([$cancelReason, $paymentId]);

        } catch (PDOException $e) {
            error_log('Cancel payment error: ' . $e->getMessage());
            return false;
        }
    }
}
?>