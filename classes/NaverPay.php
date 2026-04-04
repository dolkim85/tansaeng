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
    private $api_domain;
    private $service_domain;

    public function __construct() {
        $this->partner_id = env('NAVERPAY_PARTNER_ID', '');
        $this->client_id = env('NAVERPAY_CLIENT_ID', '');
        $this->client_secret = env('NAVERPAY_CLIENT_SECRET', '');
        $this->chain_id = env('NAVERPAY_CHAIN_ID', '');
        $this->mode = env('NAVERPAY_MODE', 'development');

        // API 도메인 설정 (공식 문서 기준)
        if ($this->mode === 'production') {
            $this->api_domain = 'https://pay.paygate.naver.com';
            $this->service_domain = 'https://m.pay.naver.com';
        } else {
            $this->api_domain = 'https://dev-pay.paygate.naver.com';
            $this->service_domain = 'https://test-m.pay.naver.com';
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
                    'count' => (int)$item['quantity']
                ];
            }

            // 결제 요청 데이터 구성 (공식 문서 v2 reserve API)
            $paymentData = [
                'modelVersion' => '2',
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

            // API 호출 (v2 reserve endpoint, JSON)
            $reserveUrl = $this->api_domain . '/naverpay-partner/naverpay/payments/v2/reserve';
            $response = $this->sendRequest('POST', $reserveUrl, $paymentData, 'json');

            error_log("NaverPay Response: " . json_encode($response, JSON_UNESCAPED_UNICODE));

            if (isset($response['code']) && $response['code'] === 'Success') {
                $reserveId = $response['body']['reserveId'];
                // 결제 페이지 URL 구성 (공식 문서 기준)
                $paymentPageUrl = $this->service_domain . '/payments/' . $reserveId;

                return [
                    'success' => true,
                    'payment_url' => $paymentPageUrl,
                    'merchant_pay_key' => $merchantPayKey,
                    'reserve_id' => $reserveId,
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
            // v2.2 apply/payment endpoint, form-urlencoded (공식 문서 기준)
            $approveUrl = $this->api_domain . '/naverpay-partner/naverpay/payments/v2.2/apply/payment';
            $response = $this->sendRequest('POST', $approveUrl, [
                'paymentId' => $paymentId
            ], 'form');

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
            $inquiryUrl = $this->api_domain . '/naverpay-partner/naverpay/payments/v2.2/at/inquiry';
            $response = $this->sendRequest('POST', $inquiryUrl, [
                'paymentId' => $paymentId
            ], 'form');

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
            // v1 cancel endpoint, form-urlencoded (공식 문서 기준)
            $cancelUrl = $this->api_domain . '/naverpay-partner/naverpay/payments/v1/cancel';
            $response = $this->sendRequest('POST', $cancelUrl, [
                'paymentId' => $paymentId,
                'cancelAmount' => (int)$cancelAmount,
                'cancelReason' => $cancelReason,
                'cancelRequester' => '2',
                'taxScopeAmount' => (int)$cancelAmount,
                'taxExScopeAmount' => 0
            ], 'form');

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
     * @param string $method HTTP 메소드
     * @param string $url 요청 URL
     * @param array|null $data 요청 데이터
     * @param string $contentType 'json' 또는 'form'
     */
    private function sendRequest($method, $url, $data = null, $contentType = 'json') {
        $ch = curl_init();

        $headers = [
            'X-Naver-Client-Id: ' . $this->client_id,
            'X-Naver-Client-Secret: ' . $this->client_secret,
            'X-NaverPay-Chain-Id: ' . $this->chain_id
        ];

        if ($contentType === 'json') {
            $headers[] = 'Content-Type: application/json';
        } else {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                if ($contentType === 'json') {
                    $postData = json_encode($data, JSON_UNESCAPED_UNICODE);
                } else {
                    $postData = http_build_query($data);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                error_log("NaverPay Request URL: " . $url);
                error_log("NaverPay Request Body: " . $postData);
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
            'api_domain' => $this->api_domain,
            'service_domain' => $this->service_domain
        ];
    }
}
