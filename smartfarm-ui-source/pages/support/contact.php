<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
$dbConnected = false;
$contactSettings = null;

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    require_once __DIR__ . '/../../classes/ContactSettings.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $contactSettings = ContactSettings::getInstance();
    $dbConnected = true;
} catch (Exception $e) {
    // 데이터베이스 연결 실패시 계속 진행
    error_log("Database connection failed: " . $e->getMessage());
}

$message = '';
$messageType = '';

// 문의 제출 처리
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $dbConnected) {
    try {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $subject = $_POST['subject'] ?? '';
        $messageContent = $_POST['message'] ?? '';
        $inquiryType = $_POST['inquiry_type'] ?? 'general';
        
        // 간단한 유효성 검사
        if (empty($name) || empty($email) || empty($subject) || empty($messageContent)) {
            throw new Exception('모든 필수 항목을 입력해주세요.');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('올바른 이메일 주소를 입력해주세요.');
        }
        
        // 데이터베이스에 문의 저장
        $db->insert('contact_inquiries', [
            'user_id' => $currentUser ? $currentUser['id'] : null,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'inquiry_type' => $inquiryType,
            'subject' => $subject,
            'message' => $messageContent,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
        $message = '문의가 성공적으로 접수되었습니다. 2-3일 내에 답변드리겠습니다.';
        $messageType = 'success';
        
        // 폼 초기화를 위해 POST 데이터 클리어
        $_POST = [];
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>문의하기 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main >
        <div class="container">
            <div class="page-header">
                <h1>문의하기</h1>
                <p>궁금한 사항이 있으시면 언제든지 연락주세요. 신속하고 정확하게 답변드리겠습니다.</p>
            </div>

            <!-- Contact Methods -->
            <section class="contact-methods">
                <h2>연락처 안내</h2>
                <div class="methods-grid">
                    <div class="method-card">
                        <div class="method-icon">📞</div>
                        <h3>전화 문의</h3>
                        <p><?= $contactSettings ? htmlspecialchars($contactSettings->get('phone_number', '02-0000-0000')) : '02-0000-0000' ?></p>
                        <small><?= $contactSettings ? htmlspecialchars($contactSettings->get('phone_hours', '평일 09:00-18:00 (점심시간 12:00-13:00)')) : '평일 09:00-18:00 (점심시간 12:00-13:00)' ?></small>
                    </div>
                    <div class="method-card">
                        <div class="method-icon">✉️</div>
                        <h3>이메일 문의</h3>
                        <p><?= $contactSettings ? htmlspecialchars($contactSettings->get('email_address', 'support@tangsaeng.com')) : 'support@tangsaeng.com' ?></p>
                        <small><?= $contactSettings ? htmlspecialchars($contactSettings->get('email_response_time', '24시간 접수, 2-3일 내 답변')) : '24시간 접수, 2-3일 내 답변' ?></small>
                    </div>
                    <div class="method-card">
                        <div class="method-icon">💬</div>
                        <h3>온라인 문의</h3>
                        <p><?= $contactSettings ? htmlspecialchars($contactSettings->get('online_inquiry_desc', '아래 문의 양식 작성')) : '아래 문의 양식 작성' ?></p>
                        <small><?= $contactSettings ? htmlspecialchars($contactSettings->get('online_inquiry_note', '실시간 접수, 빠른 답변')) : '실시간 접수, 빠른 답변' ?></small>
                    </div>
                    <div class="method-card">
                        <div class="method-icon">📍</div>
                        <h3>방문 상담</h3>
                        <p><?= $contactSettings ? htmlspecialchars($contactSettings->get('visit_address', '서울특별시 강남구 테헤란로 123')) : '서울특별시 강남구 테헤란로 123' ?></p>
                        <small><?= $contactSettings ? htmlspecialchars($contactSettings->get('visit_note', '사전 예약 후 방문 (전화 예약 필수)')) : '사전 예약 후 방문 (전화 예약 필수)' ?></small>
                    </div>
                </div>
            </section>

            <!-- Contact Form -->
            <section class="contact-form-section">
                <h2>온라인 문의 양식</h2>
                
                <?php if (!empty($message)): ?>
                    <div class="message <?= $messageType ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="contact-form" id="contactForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">이름 *</label>
                            <input type="text" id="name" name="name" required 
                                   value="<?= $currentUser ? htmlspecialchars($currentUser['name']) : (htmlspecialchars($_POST['name'] ?? '')) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">이메일 *</label>
                            <input type="email" id="email" name="email" required
                                   value="<?= $currentUser ? htmlspecialchars($currentUser['email']) : (htmlspecialchars($_POST['email'] ?? '')) ?>">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="phone">연락처</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="inquiry_type">문의 유형 *</label>
                            <select id="inquiry_type" name="inquiry_type" required>
                                <option value="general" <?= ($_POST['inquiry_type'] ?? '') == 'general' ? 'selected' : '' ?>>일반 문의</option>
                                <option value="product" <?= ($_POST['inquiry_type'] ?? '') == 'product' ? 'selected' : '' ?>>제품 문의</option>
                                <option value="technical" <?= ($_POST['inquiry_type'] ?? '') == 'technical' ? 'selected' : '' ?>>기술 지원</option>
                                <option value="order" <?= ($_POST['inquiry_type'] ?? '') == 'order' ? 'selected' : '' ?>>주문/배송</option>
                                <option value="plant_analysis" <?= ($_POST['inquiry_type'] ?? '') == 'plant_analysis' ? 'selected' : '' ?>>식물분석 권한 신청</option>
                                <option value="partnership" <?= ($_POST['inquiry_type'] ?? '') == 'partnership' ? 'selected' : '' ?>>제휴 문의</option>
                                <option value="complaint" <?= ($_POST['inquiry_type'] ?? '') == 'complaint' ? 'selected' : '' ?>>불만/건의</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="subject">제목 *</label>
                        <input type="text" id="subject" name="subject" required 
                               value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                               placeholder="문의 제목을 입력하세요">
                    </div>

                    <div class="form-group">
                        <label for="message">문의 내용 *</label>
                        <textarea id="message" name="message" rows="8" required 
                                  placeholder="궁금한 사항을 자세히 작성해주세요. 제품명, 구체적인 상황 등을 포함하면 더 정확한 답변을 받으실 수 있습니다."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="submit-btn">문의 접수</button>
                        <button type="reset" class="reset-btn">다시 작성</button>
                    </div>
                </form>
            </section>

            <!-- FAQ Quick Links -->
            <section class="faq-quick">
                <h2>자주 묻는 질문</h2>
                <p>문의하기 전에 자주 묻는 질문을 확인해보세요. 더 빠른 답변을 받으실 수 있습니다.</p>
                <div class="faq-links">
                    <a href="/pages/support/faq.php" class="faq-link">
                        <span class="faq-icon">❓</span>
                        <span>전체 FAQ 보기</span>
                    </a>
                    <a href="/pages/support/faq.php" class="faq-link" onclick="searchFAQCategory('product')">
                        <span class="faq-icon">📦</span>
                        <span>제품 관련 FAQ</span>
                    </a>
                    <a href="/pages/support/faq.php" class="faq-link" onclick="searchFAQCategory('technical')">
                        <span class="faq-icon">🔧</span>
                        <span>기술 지원 FAQ</span>
                    </a>
                    <a href="/pages/support/faq.php" class="faq-link" onclick="searchFAQCategory('order')">
                        <span class="faq-icon">🚚</span>
                        <span>주문/배송 FAQ</span>
                    </a>
                </div>
            </section>

            <!-- Business Hours -->
            <section class="business-hours">
                <h2>운영 시간 안내</h2>
                <div class="hours-grid">
                    <div class="hours-card">
                        <h3>📞 전화 상담</h3>
                        <div class="hours-info">
                            <div class="hours-item">
                                <span class="day">평일</span>
                                <span class="time"><?= $contactSettings ? htmlspecialchars($contactSettings->get('business_hours_weekday', '09:00 - 18:00')) : '09:00 - 18:00' ?></span>
                            </div>
                            <div class="hours-item">
                                <span class="day">점심시간</span>
                                <span class="time"><?= $contactSettings ? htmlspecialchars($contactSettings->get('business_hours_lunch', '12:00 - 13:00')) : '12:00 - 13:00' ?></span>
                            </div>
                            <div class="hours-item">
                                <span class="day">주말/공휴일</span>
                                <span class="time"><?= $contactSettings ? htmlspecialchars($contactSettings->get('business_hours_weekend', '휴무')) : '휴무' ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="hours-card">
                        <h3>✉️ 이메일/온라인 문의</h3>
                        <div class="hours-info">
                            <div class="hours-item">
                                <span class="day">접수</span>
                                <span class="time"><?= $contactSettings ? htmlspecialchars($contactSettings->get('email_hours_reception', '24시간')) : '24시간' ?></span>
                            </div>
                            <div class="hours-item">
                                <span class="day">답변</span>
                                <span class="time"><?= $contactSettings ? htmlspecialchars($contactSettings->get('email_hours_response', '평일 기준 2-3일')) : '평일 기준 2-3일' ?></span>
                            </div>
                            <div class="hours-item">
                                <span class="day">긴급 문의</span>
                                <span class="time"><?= $contactSettings ? htmlspecialchars($contactSettings->get('email_hours_urgent', '전화 상담 권장')) : '전화 상담 권장' ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Office Location -->
            <section class="office-location">
                <h2>오시는 길</h2>
                <div class="location-content">
                    <div class="location-info">
                        <h3>📍 본사 위치</h3>
                        <div class="address-info">
                            <p><strong>주소:</strong> 서울특별시 강남구 테헤란로 123</p>
                            <p><strong>우편번호:</strong> 06124</p>
                            <p><strong>건물:</strong> 탄생빌딩 5-7층</p>
                        </div>
                        
                        <h3>🚇 교통편</h3>
                        <div class="transport-info">
                            <div class="transport-item">
                                <span class="transport-type">지하철</span>
                                <span>2호선 강남역 2번 출구 (도보 5분)</span>
                            </div>
                            <div class="transport-item">
                                <span class="transport-type">버스</span>
                                <span>146, 360, 740, 3412 (강남역 하차)</span>
                            </div>
                            <div class="transport-item">
                                <span class="transport-type">자가용</span>
                                <span>지하 주차장 이용 가능 (방문객 2시간 무료)</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="map-container">
                        <div class="map-placeholder">
                            <div class="map-content">
                                <p>🗺️ 지도</p>
                                <small>실제 구현시 Google Maps API 등으로 교체</small>
                                <div class="map-actions">
                                    <button class="map-btn">길찾기</button>
                                    <button class="map-btn">큰 지도</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
    <script>
        function searchFAQCategory(category) {
            // FAQ 페이지로 이동하면서 카테고리 필터 적용
            localStorage.setItem('faqCategory', category);
        }
        
        // 폼 제출 시 간단한 유효성 검사
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const subject = document.getElementById('subject').value.trim();
            const message = document.getElementById('message').value.trim();
            
            if (!name || !email || !subject || !message) {
                e.preventDefault();
                alert('모든 필수 항목을 입력해주세요.');
                return;
            }
            
            if (!isValidEmail(email)) {
                e.preventDefault();
                alert('올바른 이메일 주소를 입력해주세요.');
                return;
            }
        });
        
        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
    </script>
</body>
</html>

<style>
.contact-main {
    padding: 2rem 0;
}

.page-header {
    text-align: center;
    margin-bottom: 3rem;
    padding: 3rem 0;
    background: linear-gradient(135deg, #E8F5E8 0%, #C8E6C9 100%);
    border-radius: 12px;
}

.page-header h1 {
    font-size: 2.5rem;
    color: #2E7D32;
    margin-bottom: 1rem;
}

section {
    margin-bottom: 4rem;
}

section h2 {
    color: #2E7D32;
    font-size: 2rem;
    margin-bottom: 2rem;
    text-align: center;
}

.methods-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
}

.method-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    text-align: center;
    transition: transform 0.3s ease;
}

.method-card:hover {
    transform: translateY(-5px);
}

.method-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.method-card h3 {
    color: #2E7D32;
    margin-bottom: 0.5rem;
}

.method-card p {
    color: #4CAF50;
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.method-card small {
    color: #666;
    font-size: 0.9rem;
}

.contact-form-section {
    background: white;
    padding: 3rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.message {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    text-align: center;
}

.message.success {
    background: #E8F5E8;
    color: #2E7D32;
    border: 1px solid #4CAF50;
}

.message.error {
    background: #FFEBEE;
    color: #C62828;
    border: 1px solid #F44336;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #2E7D32;
    font-weight: 600;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.8rem;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #4CAF50;
}

.form-group textarea {
    resize: vertical;
    min-height: 120px;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.submit-btn,
.reset-btn {
    padding: 1rem 2rem;
    border: none;
    border-radius: 6px;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.submit-btn {
    background: #4CAF50;
    color: white;
}

.submit-btn:hover {
    background: #45a049;
}

.reset-btn {
    background: #f8f9fa;
    color: #666;
    border: 2px solid #e0e0e0;
}

.reset-btn:hover {
    background: #e9ecef;
}

.faq-quick {
    background: #f8f9fa;
    padding: 3rem;
    border-radius: 12px;
}

.faq-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.faq-link {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: white;
    border-radius: 8px;
    text-decoration: none;
    color: #333;
    transition: all 0.3s ease;
}

.faq-link:hover {
    background: #4CAF50;
    color: white;
    transform: translateY(-2px);
}

.faq-icon {
    margin-right: 0.5rem;
}

.hours-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.hours-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.hours-card h3 {
    color: #2E7D32;
    margin-bottom: 1.5rem;
}

.hours-item {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.hours-item:last-child {
    border-bottom: none;
}

.day {
    color: #666;
}

.time {
    color: #4CAF50;
    font-weight: 600;
}

.location-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
}

.location-info h3 {
    color: #2E7D32;
    margin-bottom: 1rem;
    margin-top: 2rem;
}

.location-info h3:first-child {
    margin-top: 0;
}

.address-info p,
.transport-item {
    margin-bottom: 0.5rem;
    color: #333;
}

.transport-item {
    display: flex;
    gap: 1rem;
}

.transport-type {
    background: #f0f0f0;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.9rem;
    color: #666;
    min-width: 60px;
    text-align: center;
}

.map-container {
    background: #f8f9fa;
    border-radius: 8px;
    overflow: hidden;
}

.map-placeholder {
    height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.map-content {
    text-align: center;
    color: #666;
}

.map-content p {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.map-actions {
    margin-top: 1rem;
}

.map-btn {
    padding: 0.5rem 1rem;
    margin: 0 0.5rem;
    background: #4CAF50;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.map-btn:hover {
    background: #45a049;
}

@media (max-width: 768px) {
    .page-header {
        height: 100px !important;
        padding: 1.5rem 1rem !important;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .form-grid,
    .hours-grid,
    .location-content {
        grid-template-columns: 1fr;
    }

    .form-actions {
        flex-direction: column;
    }

    .faq-links {
        grid-template-columns: 1fr;
    }
}
</style>