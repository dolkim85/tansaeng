<?php
/**
 * 네이버페이 결제 클래스
 * 네이버페이 결제형 API 연동
 */

require_once __DIR__ . '/../config/env.php';

class NaverPay {
    private $client_id;
    private $client_secret;
    private $mode; // 'production' or 'development'
    private $api_url;

    public function __construct() {
        $this->client_id = env('NAVERPAY_CLIENT_ID', '');
        $this->client_secret = env('NAVERPAY_CLIENT_SECRET', '');
        $this->mode = env('NAVERPAY_MODE', 'development');

        // API URL 설정
        if ($this->mode === 'production') {
            $this->api_url = 'https://pay.naver.com/o2o/api/payment';
        } else {
            $this->api_url = 'https://dev.pay.naver.com/o2o/api/payment';
        }
    }

    /**
     * 결제 요청
     * @param array $data 주문 정보
     * @return array 결제 URL 및 결과
     */
    public function requestPayment($data) {
        try {
            // 주문번호 생성
            $merchantPayKey = 'ORD_' . date('YmdHis') . '_' . rand(1000, 9999);

            // 상품명 생성
            $productName = $data['items'][0]['name'];
            if (count($data['items']) > 1) {
                $productName .= ' 외 ' . (count($data['items']) - 1) . '건';
            }

            // 총 금액 계산
            $totalPayAmount = 0;
            $items = [];

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
                $items[] = [
                    'categoryType' => 'PRODUCT',
                    'categoryId' => 'GENERAL',
                    'uid' => (string)$item['product_id'],
                    'name' => $item['name'],
                    'payReferrer' => 'NAVER_SEARCH',
                    'count' => $item['quantity']
                ];
            }

            // 결제 요청 데이터 구성
            $paymentData = [
                'merchantPayKey' => $merchantPayKey,
                'productName' => $productName,
                'totalPayAmount' => $totalPayAmount,
                'returnUrl' => 'https://www.tansaeng.com/api/payment/naverpay_callback.php',
                'productCount' => count($data['items']),
                'items' => $items
            ];

            // 실제 API 연동 (현재는 개발 모드이므로 시뮬레이션)
            if ($this->mode === 'development') {
                // 개발 모드: 테스트용 URL 반환
                return [
                    'success' => true,
                    'payment_url' => '/api/payment/naverpay_test.php?merchant_pay_key=' . $merchantPayKey . '&amount=' . $totalPayAmount,
                    'merchant_pay_key' => $merchantPayKey,
                    'payment_data' => $paymentData
                ];
            }

            // 프로덕션 모드: 실제 네이버페이 API 호출
            $response = $this->sendRequest('POST', $this->api_url . '/reserve', $paymentData);

            if (isset($response['code']) && $response['code'] === 'Success') {
                return [
                    'success' => true,
                    'payment_url' => $response['body']['reserveId'], // 네이버페이 결제창 URL
                    'merchant_pay_key' => $merchantPayKey,
                    'reserve_id' => $response['body']['reserveId']
                ];
            }

            return [
                'success' => false,
                'message' => $response['message'] ?? '결제 요청 실패'
            ];

        } catch (Exception $e) {
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
            $response = $this->sendRequest('POST', $this->api_url . '/complete', [
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
                'message' => $response['message'] ?? '결제 승인 실패'
            ];

        } catch (Exception $e) {
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
            $response = $this->sendRequest('GET', $this->api_url . '/info?paymentId=' . $paymentId);

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
     * HTTP 요청 전송
     * @param string $method HTTP 메소드
     * @param string $url 요청 URL
     * @param array $data 요청 데이터
     * @return array 응답 데이터
     */
    private function sendRequest($method, $url, $data = null) {
        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'X-Naver-Client-Id: ' . $this->client_id,
            'X-Naver-Client-Secret: ' . $this->client_secret
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 개발 환경에서만 사용

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('cURL Error: ' . $error);
        }

        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode !== 200) {
            throw new Exception('HTTP Error: ' . $httpCode . ' - ' . ($result['message'] ?? 'Unknown error'));
        }

        return $result;
    }
}
