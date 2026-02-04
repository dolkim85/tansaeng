<?php
/**
 * 네이버페이 결제 클래스
 * 네이버페이 결제형 API 연동 (샌드박스/운영)
 */

require_once __DIR__ . '/../config/env.php';

class NaverPay {
    private $partner_id;
    private $client_id;
    private $client_secret;
    private $chain_id;
    private $mode;
    private $api_url;

    public function __construct() {
        $this->partner_id = env('NAVERPAY_PARTNER_ID', '');
        $this->client_id = env('NAVERPAY_CLIENT_ID', '');
        $this->client_secret = env('NAVERPAY_CLIENT_SECRET', '');
        $this->chain_id = env('NAVERPAY_CHAIN_ID', '');
        $this->mode = env('NAVERPAY_MODE', 'development');

        // API URL 설정 (샌드박스 vs 운영)
        if ($this->mode === 'production') {
            $this->api_url = 'https://apis.naver.com/naverpay-partner/naverpay/payments/v2.2';
        } else {
            $this->api_url = 'https://sandbox-apis.naver.com/naverpay-partner/naverpay/payments/v2.2';
        }
    }

    /**
     * 결제 요청 (결제 예약)
     * @param array $data 주문 정보
     * @return array 결제 URL 및 결과
     */
    public function requestPayment($data) {
        try {
            // 주문번호 생성
            $merchantPayKey = 'TS_' . date('YmdHis') . '_' . rand(1000, 9999);

            // 상품명 생성
            $productName = $data['items'][0]['name'];
            if (count($data['items']) > 1) {
                $productName .= ' 외 ' . (count($data['items']) - 1) . '건';
            }

            // 총 금액 계산
            $totalPayAmount = 0;
            $productItems = [];

            foreach ($data['items'] as $item) {
                $subtotal = $item['price'] * $item['quantity'];
                $totalPayAmount += $subtotal;

                // 배송비 계산
                $shippingCost = $item['shipping_cost'] ?? 0;
                $shippingUnitCount = $item['shipping_unit_count'] ?? 1;
                if ($shippingCost > 0 && $shippingUnitCount > 0) {
                    $shippingTimes = ceil($item['quantity'] / $shippingUnitCount);
                    $totalPayAmount += $shippingCost * $shippingTimes;
                }

                // 네이버페이 상품 정보 포맷
                $productItems[] = [
                    'categoryType' => 'PRODUCT',
                    'categoryId' => 'GENERAL',
                    'uid' => (string)$item['product_id'],
                    'name' => $item['name'],
                    'payReferrer' => 'ETC',
                    'sellerId' => $this->partner_id,
                    'count' => (int)$item['quantity']
                ];
            }

            // 결제 요청 데이터 구성
            $paymentData = [
                'merchantPayKey' => $merchantPayKey,
                'merchantUserKey' => $data['user_id'] ?? 'guest_' . time(),
                'productName' => mb_substr($productName, 0, 128),
                'productCount' => count($data['items']),
                'totalPayAmount' => (int)$totalPayAmount,
                'taxScopeAmount' => (int)$totalPayAmount,
                'taxExScopeAmount' => 0,
                'returnUrl' => 'https://www.tansaeng.com/api/payment/naverpay_callback.php',
                'productItems' => $productItems
            ];

            error_log("NaverPay Request Data: " . json_encode($paymentData, JSON_UNESCAPED_UNICODE));

            // API 호출
            $response = $this->sendRequest('POST', $this->api_url . '/reserve', $paymentData);

            error_log("NaverPay Response: " . json_encode($response, JSON_UNESCAPED_UNICODE));

            if (isset($response['code']) && $response['code'] === 'Success') {
                return [
                    'success' => true,
                    'payment_url' => $response['body']['reserveId'],
                    'merchant_pay_key' => $merchantPayKey,
                    'reserve_id' => $response['body']['reserveId'],
                    'payment_data' => $paymentData
                ];
            }

            // 에러 응답 처리
            $errorMessage = $response['message'] ?? '결제 요청 실패';
            if (isset($response['body']['message'])) {
                $errorMessage = $response['body']['message'];
            }

            return [
                'success' => false,
                'message' => $errorMessage,
                'response' => $response
            ];

        } catch (Exception $e) {
            error_log("NaverPay Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 결제 승인
     * @param string $paymentId 결제 ID
     * @return array 승인 결과
     */
    public function approvePayment($paymentId) {
        try {
            $response = $this->sendRequest('POST', $this->api_url . '/apply/payment', [
                'paymentId' => $paymentId
            ]);

            error_log("NaverPay Approve Response: " . json_encode($response, JSON_UNESCAPED_UNICODE));

            if (isset($response['code']) && $response['code'] === 'Success') {
                return [
                    'success' => true,
                    'data' => $response['body']
                ];
            }

            return [
                'success' => false,
                'message' => $response['message'] ?? $response['body']['message'] ?? '결제 승인 실패',
                'response' => $response
            ];

        } catch (Exception $e) {
            error_log("NaverPay Approve Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 결제 정보 조회
     * @param string $paymentId 결제 ID
     * @return array 결제 정보
     */
    public function getPaymentInfo($paymentId) {
        try {
            $response = $this->sendRequest('POST', $this->api_url . '/at/inquiry', [
                'paymentId' => $paymentId
            ]);

            if (isset($response['code']) && $response['code'] === 'Success') {
                return [
                    'success' => true,
                    'data' => $response['body']
                ];
            }

            return [
                'success' => false,
                'message' => $response['message'] ?? '결제 정보 조회 실패'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 결제 취소
     * @param string $paymentId 결제 ID
     * @param int $cancelAmount 취소 금액
     * @param string $cancelReason 취소 사유
     * @return array 취소 결과
     */
    public function cancelPayment($paymentId, $cancelAmount, $cancelReason = '고객 요청') {
        try {
            $response = $this->sendRequest('POST', $this->api_url . '/cancel', [
                'paymentId' => $paymentId,
                'cancelAmount' => (int)$cancelAmount,
                'cancelReason' => $cancelReason,
                'cancelRequester' => '2'
            ]);

            if (isset($response['code']) && $response['code'] === 'Success') {
                return [
                    'success' => true,
                    'data' => $response['body']
                ];
            }

            return [
                'success' => false,
                'message' => $response['message'] ?? '결제 취소 실패'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * HTTP 요청 전송
     */
    private function sendRequest($method, $url, $data = null) {
        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'X-Naver-Client-Id: ' . $this->client_id,
            'X-Naver-Client-Secret: ' . $this->client_secret,
            'X-NaverPay-Chain-Id: ' . $this->chain_id
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                error_log("NaverPay Request URL: " . $url);
                error_log("NaverPay Request Body: " . $jsonData);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        error_log("NaverPay HTTP Code: " . $httpCode);
        error_log("NaverPay Raw Response: " . $response);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('cURL Error: ' . $error);
        }

        curl_close($ch);

        $result = json_decode($response, true);

        if ($result === null && !empty($response)) {
            throw new Exception('Invalid JSON response: ' . substr($response, 0, 200));
        }

        return $result ?? ['code' => 'Error', 'message' => 'Empty response'];
    }

    /**
     * 설정 정보 확인 (디버깅용)
     */
    public function getConfig() {
        return [
            'partner_id' => $this->partner_id,
            'client_id' => substr($this->client_id, 0, 10) . '...',
            'chain_id' => $this->chain_id,
            'mode' => $this->mode,
            'api_url' => $this->api_url
        ];
    }
}
