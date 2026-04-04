<?php
/**
 * 나이스페이먼츠 결제 처리 클래스
 */

require_once __DIR__ . '/../config/env.php';

class Payment {
    private $clientId;
    private $secretKey;
    private $merchantId;
    private $apiUrl;

    public function __construct() {
        $this->clientId = env('NICEPAY_CLIENT_ID');
        $this->secretKey = env('NICEPAY_SECRET_KEY');
        $this->merchantId = env('NICEPAY_MERCHANT_ID');
        $this->apiUrl = env('NICEPAY_API_URL', 'https://api.nicepay.co.kr');
    }

    /**
     * 결제 승인 요청
     *
     * @param string $tid 거래 ID (나이스페이먼츠에서 발급)
     * @param int $amount 결제 금액
     * @return array 승인 결과
     */
    public function approve($tid, $amount) {
        try {
            $url = $this->apiUrl . '/v1/payments/' . $tid;

            // 인증 토큰 생성
            $authString = $this->clientId . ':' . $this->secretKey;
            $authToken = base64_encode($authString);

            $data = [
                'amount' => $amount
            ];

            $response = $this->sendRequest($url, 'POST', $data, $authToken);

            return [
                'success' => $response['resultCode'] === '0000',
                'data' => $response,
                'message' => $response['resultMsg'] ?? '결제 승인 완료'
            ];

        } catch (Exception $e) {
            error_log('Payment approve error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '결제 승인 중 오류가 발생했습니다.',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 결제 취소 요청
     *
     * @param string $tid 거래 ID
     * @param int $amount 취소 금액
     * @param string $reason 취소 사유
     * @return array 취소 결과
     */
    public function cancel($tid, $amount, $reason = '고객 요청') {
        try {
            $url = $this->apiUrl . '/v1/payments/' . $tid . '/cancel';

            $authString = $this->clientId . ':' . $this->secretKey;
            $authToken = base64_encode($authString);

            $data = [
                'amount' => $amount,
                'reason' => $reason,
                'orderId' => $tid
            ];

            $response = $this->sendRequest($url, 'POST', $data, $authToken);

            return [
                'success' => $response['resultCode'] === '0000',
                'data' => $response,
                'message' => $response['resultMsg'] ?? '결제 취소 완료'
            ];

        } catch (Exception $e) {
            error_log('Payment cancel error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '결제 취소 중 오류가 발생했습니다.',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 결제 내역 조회
     *
     * @param string $tid 거래 ID
     * @return array 조회 결과
     */
    public function getPaymentInfo($tid) {
        try {
            $url = $this->apiUrl . '/v1/payments/' . $tid;

            $authString = $this->clientId . ':' . $this->secretKey;
            $authToken = base64_encode($authString);

            $response = $this->sendRequest($url, 'GET', null, $authToken);

            return [
                'success' => true,
                'data' => $response
            ];

        } catch (Exception $e) {
            error_log('Payment info error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '결제 정보 조회 중 오류가 발생했습니다.',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * HTTP 요청 전송
     *
     * @param string $url 요청 URL
     * @param string $method HTTP 메서드 (GET, POST)
     * @param array|null $data 요청 데이터
     * @param string $authToken 인증 토큰
     * @return array 응답 데이터
     */
    private function sendRequest($url, $method = 'POST', $data = null, $authToken = null) {
        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'Authorization: Basic ' . $authToken
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('cURL Error: ' . $error);
        }

        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode !== 200 && $httpCode !== 201) {
            throw new Exception('API Error: HTTP ' . $httpCode . ' - ' . ($result['message'] ?? 'Unknown error'));
        }

        return $result;
    }

    /**
     * 결제 정보 검증 (금액 일치 여부 등)
     *
     * @param string $tid 거래 ID
     * @param int $expectedAmount 예상 금액
     * @return bool 검증 결과
     */
    public function verifyPayment($tid, $expectedAmount) {
        $result = $this->getPaymentInfo($tid);

        if (!$result['success']) {
            return false;
        }

        $actualAmount = $result['data']['amount'] ?? 0;
        return $actualAmount == $expectedAmount;
    }

    /**
     * 나이스페이먼츠 클라이언트 키 반환 (프론트엔드용)
     *
     * @return string 클라이언트 키
     */
    public function getClientId() {
        return $this->clientId;
    }
}
