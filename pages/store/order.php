<?php
// 데이터베이스 연결 및 사용자 인증
$currentUser = null;

// 세션이 시작되지 않았으면 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    require_once __DIR__ . '/../../classes/Database.php';
    require_once __DIR__ . '/../../config/env.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();

    if (!$currentUser) {
        header('Location: /pages/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
} catch (Exception $e) {
    error_log("Auth failed: " . $e->getMessage());
    header('Location: /pages/auth/login.php');
    exit;
}

// 주문할 상품 정보 가져오기 (세션에서)
$orderItems = $_SESSION['order_items'] ?? [];

if (empty($orderItems)) {
    header('Location: /pages/store/cart.php');
    exit;
}

// 총 금액 계산
$subtotal = 0;
$shippingCost = 0;
foreach ($orderItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $shippingCost += $item['shipping_cost'] ?? 0;
}
$totalAmount = $subtotal + $shippingCost;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>주문/결제 - 탄생</title>
    <link rel="stylesheet" href="../../assets/css/main.css?v=<?= date('YmdHis') ?>">
    <script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
    <!-- 나이스페이먼츠 JavaScript SDK -->
    <script src="https://pay.nicepay.co.kr/v1/js/"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Noto Sans KR', sans-serif; background: #f8f9fa; }

        .container { max-width: 1200px; margin: 0 auto; padding: 15px; }

        .order-header {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
        }

        .order-header h1 {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 10px;
        }

        .order-steps {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
            padding: 10px;
        }

        .step::after {
            content: '';
            position: absolute;
            top: 20px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: #e0e0e0;
            z-index: -1;
        }

        .step:last-child::after {
            display: none;
        }

        .step-number {
            display: inline-block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #999;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .step.active .step-number {
            background: #007bff;
            color: white;
        }

        .step-label {
            font-size: 0.9rem;
            color: #666;
        }

        .step.active .step-label {
            color: #007bff;
            font-weight: 600;
        }

        .order-layout {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }

        .order-main {
            flex: 1;
            min-width: 0;
        }

        .order-sidebar {
            width: 350px;
            flex-shrink: 0;
        }

        .section {
            background: white;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        /* 주문 상품 */
        .order-product {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .order-product:last-child {
            border-bottom: none;
        }

        .product-image {
            width: 70px;
            height: 70px;
            background: #f5f5f5;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            border: 1px solid #ddd;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 6px;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-size: 0.95rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .product-quantity {
            font-size: 0.85rem;
            color: #666;
        }

        .product-price {
            font-size: 1rem;
            font-weight: 700;
            color: #333;
            text-align: right;
        }

        /* 배송지 정보 */
        .address-item {
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .address-item:hover {
            border-color: #007bff;
            background: #f8f9ff;
        }

        .address-item.selected {
            border-color: #007bff;
            background: #f0f7ff;
        }

        .address-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .address-label {
            display: inline-block;
            padding: 3px 8px;
            background: #007bff;
            color: white;
            font-size: 0.75rem;
            border-radius: 3px;
            margin-right: 8px;
        }

        .address-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .address-detail {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.5;
        }

        .address-phone {
            font-size: 0.85rem;
            color: #999;
            margin-top: 5px;
        }

        .btn-add-address {
            width: 100%;
            padding: 12px;
            border: 2px dashed #007bff;
            background: white;
            color: #007bff;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-add-address:hover {
            background: #f0f7ff;
        }

        /* 결제 수단 */
        .payment-method {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .payment-method:hover {
            border-color: #007bff;
            background: #f8f9ff;
        }

        .payment-method.selected {
            border-color: #007bff;
            background: #f0f7ff;
        }

        .payment-method input[type="radio"] {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            cursor: pointer;
            accent-color: #007bff;
        }

        .payment-icon {
            font-size: 1.5rem;
            margin-right: 10px;
        }

        .payment-info {
            flex: 1;
        }

        .payment-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: #333;
        }

        .payment-desc {
            font-size: 0.8rem;
            color: #666;
            margin-top: 3px;
        }

        /* 주문 요약 (사이드바) */
        .order-summary {
            position: sticky;
            top: 100px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 0.95rem;
            color: #666;
        }

        .summary-row span:last-child {
            font-weight: 600;
            color: #333;
        }

        .summary-divider {
            height: 1px;
            background: #e0e0e0;
            margin: 10px 0;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            font-size: 1.15rem;
            font-weight: 700;
            color: #007bff;
            border-top: 2px solid #007bff;
            margin-top: 10px;
        }

        .summary-total span:last-child {
            font-size: 1.3rem;
        }

        .btn-order {
            width: 100%;
            padding: 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 15px;
            transition: background 0.2s;
        }

        .btn-order:hover {
            background: #0056b3;
        }

        .btn-order:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .order-notice {
            background: #fff3cd;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #ffc107;
            margin-top: 15px;
        }

        .order-notice-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: #856404;
            margin-bottom: 5px;
        }

        .order-notice-text {
            font-size: 0.85rem;
            color: #856404;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            font-size: 0.9rem;
            color: #333;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
        }

        .form-input:focus {
            outline: none;
            border-color: #007bff;
        }

        .form-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
            resize: vertical;
            min-height: 80px;
        }

        @media (max-width: 992px) {
            .order-layout {
                flex-direction: column;
            }

            .order-sidebar {
                width: 100%;
            }

            .order-summary {
                position: static;
            }
        }

        /* 모달 스타일 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 25px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
            padding: 0;
            width: 30px;
            height: 30px;
            line-height: 30px;
            text-align: center;
        }

        .modal-close:hover {
            color: #333;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .address-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }

        .btn-edit, .btn-delete {
            padding: 4px 10px;
            font-size: 0.8rem;
            border: 1px solid #ddd;
            background: white;
            color: #666;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-edit:hover {
            border-color: #007bff;
            color: #007bff;
        }

        .btn-delete:hover {
            border-color: #dc3545;
            color: #dc3545;
        }

        @media (max-width: 768px) {
            .order-steps {
                flex-wrap: wrap;
            }

            .step {
                flex-basis: 50%;
                margin-bottom: 10px;
            }

            .step::after {
                display: none;
            }

            .product-image {
                width: 60px;
                height: 60px;
            }

            .modal-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container" style="margin-top: 80px;">
        <!-- 주문 헤더 -->
        <div class="order-header">
            <h1>주문/결제</h1>
            <div class="order-steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-label">장바구니</div>
                </div>
                <div class="step active">
                    <div class="step-number">2</div>
                    <div class="step-label">주문/결제</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-label">주문완료</div>
                </div>
            </div>
        </div>

        <div class="order-layout">
            <!-- 주문 정보 (왼쪽) -->
            <div class="order-main">
                <!-- 주문 상품 -->
                <div class="section">
                    <h2 class="section-title">주문 상품 (<?= count($orderItems) ?>개)</h2>
                    <?php foreach ($orderItems as $item): ?>
                    <div class="order-product">
                        <div class="product-image">
                            <?php if (!empty($item['image'])): ?>
                                <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                            <?php else: ?>
                                📦
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <div class="product-name"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="product-quantity">수량: <?= $item['quantity'] ?>개</div>
                        </div>
                        <div class="product-price">
                            <?= number_format($item['price'] * $item['quantity']) ?>원
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- 배송지 정보 -->
                <div class="section">
                    <h2 class="section-title">배송지</h2>
                    <div id="addressList">
                        <div class="address-item selected" data-address-id="default" onclick="selectAddress(this)">
                            <div class="address-header">
                                <div>
                                    <span class="address-label">기본</span>
                                    <span class="address-name"><?= htmlspecialchars($currentUser['name']) ?></span>
                                </div>
                            </div>
                            <div class="address-detail">
                                <?= htmlspecialchars($currentUser['address'] ?? '서울시 강남구 테헤란로 123') ?>
                            </div>
                            <div class="address-phone">
                                📱 <?= htmlspecialchars($currentUser['phone'] ?? '010-0000-0000') ?>
                            </div>
                            <div class="address-actions">
                                <button class="btn-edit" onclick="event.stopPropagation(); editAddress('default')">수정</button>
                            </div>
                        </div>
                    </div>
                    <button class="btn-add-address" onclick="openAddressModal()">
                        + 새 배송지 추가
                    </button>
                </div>

                <!-- 배송 요청사항 -->
                <div class="section">
                    <h2 class="section-title">배송 요청사항</h2>
                    <div class="form-group">
                        <select class="form-input" id="deliveryRequest" onchange="handleDeliveryRequest()">
                            <option value="">배송 시 요청사항을 선택해주세요</option>
                            <option value="문 앞에 놓아주세요">문 앞에 놓아주세요</option>
                            <option value="경비실에 맡겨주세요">경비실에 맡겨주세요</option>
                            <option value="택배함에 넣어주세요">택배함에 넣어주세요</option>
                            <option value="배송 전 연락주세요">배송 전 연락주세요</option>
                            <option value="direct">직접 입력</option>
                        </select>
                    </div>
                    <div class="form-group" id="directInputGroup" style="display: none;">
                        <textarea class="form-textarea" id="deliveryMemo" placeholder="배송 시 요청사항을 입력해주세요 (100자 이내)" maxlength="100"></textarea>
                    </div>
                </div>

                <!-- 결제 수단 -->
                <div class="section">
                    <h2 class="section-title">결제 수단</h2>
                    <div class="payment-method selected" onclick="selectPayment(this)">
                        <input type="radio" name="payment" value="card" checked>
                        <span class="payment-icon">💳</span>
                        <div class="payment-info">
                            <div class="payment-name">신용/체크카드</div>
                            <div class="payment-desc">일반 결제</div>
                        </div>
                    </div>
                    <div class="payment-method" onclick="selectPayment(this)">
                        <input type="radio" name="payment" value="transfer">
                        <span class="payment-icon">🏦</span>
                        <div class="payment-info">
                            <div class="payment-name">무통장 입금</div>
                            <div class="payment-desc">입금 확인 후 배송</div>
                        </div>
                    </div>
                    <div class="payment-method" onclick="selectPayment(this)">
                        <input type="radio" name="payment" value="kakao">
                        <span class="payment-icon">💬</span>
                        <div class="payment-info">
                            <div class="payment-name">카카오페이</div>
                            <div class="payment-desc">간편 결제</div>
                        </div>
                    </div>
                    <div class="payment-method" onclick="selectPayment(this)">
                        <input type="radio" name="payment" value="naver">
                        <span class="payment-icon">🟢</span>
                        <div class="payment-info">
                            <div class="payment-name">네이버페이</div>
                            <div class="payment-desc">간편 결제</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 주문 요약 (오른쪽) -->
            <div class="order-sidebar">
                <div class="section order-summary">
                    <h2 class="section-title">결제 금액</h2>
                    <div class="summary-row">
                        <span>상품 금액</span>
                        <span><?= number_format($subtotal) ?>원</span>
                    </div>
                    <div class="summary-row">
                        <span>배송비</span>
                        <span><?= number_format($shippingCost) ?>원</span>
                    </div>
                    <div class="summary-row">
                        <span>할인</span>
                        <span style="color: #dc3545;">-0원</span>
                    </div>
                    <div class="summary-total">
                        <span>최종 결제 금액</span>
                        <span><?= number_format($totalAmount) ?>원</span>
                    </div>
                    <button class="btn-order" onclick="processOrder()">
                        <?= number_format($totalAmount) ?>원 결제하기
                    </button>
                    <div class="order-notice">
                        <div class="order-notice-title">💡 주문 전 확인하세요</div>
                        <div class="order-notice-text">
                            • 주문 후 배송지 변경은 불가능합니다<br>
                            • 결제 완료 후 영업일 기준 2-3일 내 배송됩니다
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 배송지 추가/수정 모달 -->
    <div id="addressModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">새 배송지 추가</h3>
                <button class="modal-close" onclick="closeAddressModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="addressForm">
                    <input type="hidden" id="addressId" value="">
                    <div class="form-group">
                        <label class="form-label">받는 사람 <span style="color: #dc3545;">*</span></label>
                        <input type="text" class="form-input" id="recipientName" required placeholder="받는 사람 이름">
                    </div>
                    <div class="form-group">
                        <label class="form-label">연락처 <span style="color: #dc3545;">*</span></label>
                        <input type="tel" class="form-input" id="recipientPhone" required placeholder="010-0000-0000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">주소 <span style="color: #dc3545;">*</span></label>
                        <div style="display: flex; gap: 10px; margin-bottom: 8px;">
                            <input type="text" class="form-input" id="zipCode" readonly placeholder="우편번호" style="flex: 1;">
                            <button type="button" class="btn btn-secondary" onclick="searchAddress()">주소 검색</button>
                        </div>
                        <input type="text" class="form-input" id="address" readonly placeholder="기본 주소" style="margin-bottom: 8px;">
                        <input type="text" class="form-input" id="addressDetail" placeholder="상세 주소 입력">
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" id="setDefault" style="width: auto; margin-right: 8px;">
                            기본 배송지로 설정
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeAddressModal()">취소</button>
                <button class="btn btn-primary" onclick="saveAddress()">저장</button>
            </div>
        </div>
    </div>

    <script>
        function selectAddress(element) {
            document.querySelectorAll('.address-item').forEach(item => {
                item.classList.remove('selected');
            });
            element.classList.add('selected');
        }

        function selectPayment(element) {
            document.querySelectorAll('.payment-method').forEach(item => {
                item.classList.remove('selected');
                item.querySelector('input[type="radio"]').checked = false;
            });
            element.classList.add('selected');
            element.querySelector('input[type="radio"]').checked = true;
        }

        function handleDeliveryRequest() {
            const select = document.getElementById('deliveryRequest');
            const directInputGroup = document.getElementById('directInputGroup');

            if (select.value === 'direct') {
                directInputGroup.style.display = 'block';
            } else {
                directInputGroup.style.display = 'none';
            }
        }

        // 배송지 관련 데이터
        let addresses = [
            {
                id: 'default',
                name: '<?= htmlspecialchars($currentUser['name']) ?>',
                phone: '<?= htmlspecialchars($currentUser['phone'] ?? '010-0000-0000') ?>',
                zipCode: '06234',
                address: '<?= htmlspecialchars($currentUser['address'] ?? '서울시 강남구 테헤란로 123') ?>',
                addressDetail: '4층',
                isDefault: true
            }
        ];

        // 배송지 모달 열기
        function openAddressModal(addressId = null) {
            const modal = document.getElementById('addressModal');
            const form = document.getElementById('addressForm');
            const title = document.getElementById('modalTitle');

            form.reset();

            if (addressId) {
                const address = addresses.find(a => a.id === addressId);
                if (address) {
                    title.textContent = '배송지 수정';
                    document.getElementById('addressId').value = address.id;
                    document.getElementById('recipientName').value = address.name;
                    document.getElementById('recipientPhone').value = address.phone;
                    document.getElementById('zipCode').value = address.zipCode;
                    document.getElementById('address').value = address.address;
                    document.getElementById('addressDetail').value = address.addressDetail || '';
                    document.getElementById('setDefault').checked = address.isDefault;
                }
            } else {
                title.textContent = '새 배송지 추가';
                document.getElementById('addressId').value = '';
            }

            modal.classList.add('active');
        }

        // 배송지 모달 닫기
        function closeAddressModal() {
            const modal = document.getElementById('addressModal');
            modal.classList.remove('active');
        }

        // 배송지 저장
        function saveAddress() {
            const id = document.getElementById('addressId').value;
            const name = document.getElementById('recipientName').value.trim();
            const phone = document.getElementById('recipientPhone').value.trim();
            const zipCode = document.getElementById('zipCode').value.trim();
            const address = document.getElementById('address').value.trim();
            const addressDetail = document.getElementById('addressDetail').value.trim();
            const isDefault = document.getElementById('setDefault').checked;

            if (!name || !phone || !zipCode || !address) {
                alert('필수 항목을 모두 입력해주세요.');
                return;
            }

            const newAddress = {
                id: id || 'addr_' + Date.now(),
                name,
                phone,
                zipCode,
                address,
                addressDetail,
                isDefault
            };

            if (id) {
                // 수정
                const index = addresses.findIndex(a => a.id === id);
                if (index !== -1) {
                    addresses[index] = newAddress;
                }
            } else {
                // 추가
                addresses.push(newAddress);
            }

            // 기본 배송지 설정 시 다른 주소들의 기본 해제
            if (isDefault) {
                addresses.forEach(addr => {
                    if (addr.id !== newAddress.id) {
                        addr.isDefault = false;
                    }
                });
            }

            renderAddressList();
            closeAddressModal();
        }

        // 배송지 수정
        function editAddress(addressId) {
            openAddressModal(addressId);
        }

        // 배송지 삭제
        function deleteAddress(addressId) {
            if (addressId === 'default') {
                alert('기본 배송지는 삭제할 수 없습니다.');
                return;
            }

            if (confirm('이 배송지를 삭제하시겠습니까?')) {
                addresses = addresses.filter(a => a.id !== addressId);
                renderAddressList();
            }
        }

        // 배송지 목록 렌더링
        function renderAddressList() {
            const list = document.getElementById('addressList');
            list.innerHTML = addresses.map(addr => `
                <div class="address-item ${addr.isDefault ? 'selected' : ''}" data-address-id="${addr.id}" onclick="selectAddress(this)">
                    <div class="address-header">
                        <div>
                            ${addr.isDefault ? '<span class="address-label">기본</span>' : ''}
                            <span class="address-name">${addr.name}</span>
                        </div>
                    </div>
                    <div class="address-detail">
                        [${addr.zipCode}] ${addr.address} ${addr.addressDetail || ''}
                    </div>
                    <div class="address-phone">
                        📱 ${addr.phone}
                    </div>
                    <div class="address-actions">
                        <button class="btn-edit" onclick="event.stopPropagation(); editAddress('${addr.id}')">수정</button>
                        ${addr.id !== 'default' ? `<button class="btn-delete" onclick="event.stopPropagation(); deleteAddress('${addr.id}')">삭제</button>` : ''}
                    </div>
                </div>
            `).join('');
        }

        // Daum 우편번호 API
        function searchAddress() {
            new daum.Postcode({
                oncomplete: function(data) {
                    // 도로명 주소 또는 지번 주소 선택
                    var addr = data.userSelectedType === 'R' ? data.roadAddress : data.jibunAddress;

                    // 우편번호와 주소 입력
                    document.getElementById('zipCode').value = data.zonecode;
                    document.getElementById('address').value = addr;

                    // 상세주소 입력 칸으로 포커스 이동
                    document.getElementById('addressDetail').focus();
                }
            }).open();
        }

        // 주문 처리
        async function processOrder() {
            const paymentMethod = document.querySelector('input[name="payment"]:checked');

            if (!paymentMethod) {
                alert('결제 수단을 선택해주세요.');
                return;
            }

            // 선택된 배송지 가져오기
            const selectedAddress = document.querySelector('.address-item.selected');
            if (!selectedAddress) {
                alert('배송지를 선택해주세요.');
                return;
            }

            const addressId = selectedAddress.dataset.addressId;
            const address = addresses.find(a => a.id === addressId);

            const deliveryRequest = document.getElementById('deliveryRequest').value;
            const deliveryMemo = document.getElementById('deliveryMemo').value;

            const orderData = {
                payment_method: paymentMethod.value,
                delivery_address: address,
                delivery_request: deliveryRequest === 'direct' ? deliveryMemo : deliveryRequest,
                total_amount: <?= $totalAmount ?>
            };

            if (!confirm('주문을 진행하시겠습니까?')) {
                return;
            }

            // 1. 먼저 주문 생성 (pending 상태)
            try {
                const response = await fetch('/api/order.php?action=create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(orderData)
                });

                const data = await response.json();

                if (!data.success) {
                    alert('주문 생성 중 오류가 발생했습니다: ' + data.message);
                    return;
                }

                const orderId = data.data.order_id;
                const orderNumber = data.data.order_number;

                // 2. 결제 수단에 따라 처리
                if (paymentMethod.value === 'card') {
                    // 신용카드 - 나이스페이먼츠 결제창 호출
                    requestNicePayment(orderNumber, orderData.total_amount, address);
                } else if (paymentMethod.value === 'transfer') {
                    // 무통장 입금 - 바로 완료 페이지로
                    window.location.href = '/pages/store/order_complete.php?order_id=' + orderId + '&payment=transfer';
                } else if (paymentMethod.value === 'kakao') {
                    alert('카카오페이는 준비 중입니다.');
                } else if (paymentMethod.value === 'naver') {
                    alert('네이버페이는 준비 중입니다.');
                }
            } catch (error) {
                console.error('Order error:', error);
                alert('주문 처리 중 오류가 발생했습니다.');
            }
        }

        // 나이스페이먼츠 결제창 호출
        function requestNicePayment(orderNumber, amount, address) {
            // 나이스페이먼츠 결제창 호출
            NICEPAY.requestPay({
                clientId: '<?= env("NICEPAY_CLIENT_ID") ?>',
                method: 'card',
                orderId: orderNumber,
                amount: amount,
                goodsName: '탄생 스마트팜 상품',
                returnUrl: 'https://www.tansaeng.com/api/payment/nicepay_callback.php',

                // 구매자 정보
                buyerName: address.name,
                buyerTel: address.phone,
                buyerEmail: '<?= $currentUser['email'] ?>',

                // 결제 수단
                payMethod: 'CARD',

                fnError: function(result) {
                    console.error('결제 오류:', result);
                    alert('결제 처리 중 오류가 발생했습니다.\n' + (result.errorMsg || result.msg || '알 수 없는 오류'));
                }
            });
        }

        // 모달 외부 클릭 시 닫기
        document.getElementById('addressModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddressModal();
            }
        });
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
