<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
$dbConnected = false;

try {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = Auth::getInstance();
    
    // 로그인 확인
    if (!$auth->isLoggedIn()) {
        header('Location: /pages/auth/login.php');
        exit;
    }
    
    $currentUser = $auth->getCurrentUser();
    $db = Database::getInstance();
    $dbConnected = true;
    
} catch (Exception $e) {
    // 데이터베이스 연결 실패시 로그인 페이지로 리다이렉트
    error_log("Database connection failed: " . $e->getMessage());
    header('Location: /pages/auth/login.php');
    exit;
}

$message = '';
$messageType = '';

// 프로필 업데이트 처리
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profile' && $dbConnected) {
    try {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        // 간단한 유효성 검사
        if (empty($name)) {
            throw new Exception('이름은 필수 항목입니다.');
        }

        // 데이터베이스 업데이트
        $db->update('users', [
            'name' => $name,
            'phone' => $phone,
            'address' => $address
        ], 'id = :user_id', ['user_id' => $currentUser['id']]);

        // 세션 업데이트
        $currentUser = $auth->getCurrentUser(); // 새로운 정보로 다시 로드

        $message = '프로필이 성공적으로 업데이트되었습니다.';
        $messageType = 'success';

    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// 비밀번호 변경 처리
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'change_password' && $dbConnected) {
    try {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // 유효성 검사
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            throw new Exception('모든 필드를 입력해주세요.');
        }

        if ($newPassword !== $confirmPassword) {
            throw new Exception('새 비밀번호가 일치하지 않습니다.');
        }

        if (strlen($newPassword) < 8) {
            throw new Exception('비밀번호는 8자 이상이어야 합니다.');
        }

        // 데이터베이스에서 현재 비밀번호 해시 가져오기
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$currentUser['id']]);
        $userPassword = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userPassword || !password_verify($currentPassword, $userPassword['password'])) {
            throw new Exception('현재 비밀번호가 일치하지 않습니다.');
        }

        // 새 비밀번호 해시화 및 업데이트
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->update('users', [
            'password' => $hashedPassword
        ], 'id = :user_id', ['user_id' => $currentUser['id']]);

        $message = '비밀번호가 성공적으로 변경되었습니다.';
        $messageType = 'success';

    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// 주문 내역 가져오기
$orders = [];
if ($dbConnected) {
    try {
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare("
            SELECT o.*,
                   GROUP_CONCAT(CONCAT(oi.product_name, ' x ', oi.quantity) SEPARATOR ', ') as product_names
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.user_id = :user_id
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT 10
        ");
        $stmt->execute(['user_id' => $currentUser['id']]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("주문 내역 로드 실패: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>내 정보 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main style="padding-top: 80px; padding-bottom: 80px;">
        <div class="container">
            <div class="page-header">
                <h1>👤 내 정보</h1>
                <p>회원 정보를 관리하고 계정 설정을 변경하세요</p>
            </div>

            <div class="profile-content">
                <div class="profile-sidebar">
                    <div class="profile-card">
                        <div class="profile-avatar">
                            <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                        </div>
                        <h3><?= htmlspecialchars($currentUser['name']) ?></h3>
                        <p class="profile-email"><?= htmlspecialchars($currentUser['email']) ?></p>
                        <div class="profile-status">
                            <?php if ($currentUser['plant_analysis_permission']): ?>
                                <span class="status-badge active">🌱 식물분석 권한</span>
                            <?php else: ?>
                                <span class="status-badge inactive">식물분석 권한 없음</span>
                            <?php endif; ?>
                            <?php if ($currentUser['user_level'] == 9): ?>
                                <span class="status-badge admin">👑 관리자</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <nav class="profile-nav">
                        <a href="#profile-info" class="nav-link active" onclick="showTab('profile-info', this)">
                            <span class="nav-icon">📝</span>
                            <span>기본 정보</span>
                        </a>
                        <a href="#security" class="nav-link" onclick="showTab('security', this)">
                            <span class="nav-icon">🔒</span>
                            <span>보안 설정</span>
                        </a>
                        <a href="#orders" class="nav-link" onclick="showTab('orders', this)">
                            <span class="nav-icon">📦</span>
                            <span>주문 내역</span>
                        </a>
                        <a href="#plant-analysis" class="nav-link" onclick="showTab('plant-analysis', this)">
                            <span class="nav-icon">🌱</span>
                            <span>식물분석</span>
                        </a>
                        <a href="/pages/auth/logout.php" class="nav-link logout-link" onclick="return confirm('로그아웃하시겠습니까?')">
                            <span class="nav-icon">🚪</span>
                            <span>로그아웃</span>
                        </a>
                    </nav>
                </div>

                <div class="profile-main-content">
                    <?php if (!empty($message)): ?>
                        <div class="message <?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Profile Info Tab -->
                    <div id="profile-info" class="tab-content active">
                        <h2>기본 정보</h2>
                        <form method="POST" class="profile-form">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="name">이름 *</label>
                                    <input type="text" id="name" name="name" required 
                                           value="<?= htmlspecialchars($currentUser['name']) ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">이메일</label>
                                    <input type="email" id="email" value="<?= htmlspecialchars($currentUser['email']) ?>" disabled>
                                    <small>이메일은 변경할 수 없습니다.</small>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="phone">연락처</label>
                                    <input type="tel" id="phone" name="phone"
                                           value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>">
                                </div>

                                <div class="form-group">
                                    <label for="postcode">우편번호</label>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <input type="text" id="postcode" name="postcode" readonly style="flex: 1; background: #f8f9fa;">
                                        <button type="button" onclick="execDaumPostcode()" class="btn btn-outline" style="white-space: nowrap;">주소 찾기</button>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="address">주소</label>
                                <input type="text" id="address" name="address" readonly style="background: #f8f9fa;">
                                <input type="hidden" id="fullAddress" name="fullAddress">
                            </div>

                            <div class="form-group">
                                <label for="detailAddress">상세주소</label>
                                <input type="text" id="detailAddress" name="detailAddress">
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">정보 업데이트</button>
                                <button type="reset" class="btn btn-outline">취소</button>
                            </div>
                        </form>
                    </div>

                    <!-- Security Tab -->
                    <div id="security" class="tab-content">
                        <h2>보안 설정</h2>
                        <div class="security-section">
                            <h3>비밀번호 변경</h3>
                            <form method="POST" class="security-form">
                                <input type="hidden" name="action" value="change_password">
                                <div class="form-group">
                                    <label for="current_password">현재 비밀번호</label>
                                    <input type="password" id="current_password" name="current_password" required>
                                </div>
                                <div class="form-group">
                                    <label for="new_password">새 비밀번호</label>
                                    <input type="password" id="new_password" name="new_password" required>
                                    <small>8자 이상, 영문과 숫자 조합</small>
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">새 비밀번호 확인</label>
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" class="btn btn-primary">비밀번호 변경</button>
                            </form>
                        </div>

                        <div class="security-section">
                            <h3>로그인 기록</h3>
                            <div class="login-history">
                                <div class="history-item">
                                    <div class="history-info">
                                        <span class="device">🖥️ Windows Chrome</span>
                                        <span class="time">2024-01-15 14:30</span>
                                    </div>
                                    <span class="location">서울, 한국</span>
                                </div>
                                <div class="history-item">
                                    <div class="history-info">
                                        <span class="device">📱 Mobile Safari</span>
                                        <span class="time">2024-01-14 09:15</span>
                                    </div>
                                    <span class="location">서울, 한국</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Orders Tab -->
                    <div id="orders" class="tab-content">
                        <h2>주문 내역</h2>
                        <div class="orders-list">
                            <?php if (empty($orders)): ?>
                                <div class="no-orders">
                                    <div class="no-orders-icon">📦</div>
                                    <h3>주문 내역이 없습니다</h3>
                                    <p>첫 주문을 시작해보세요!</p>
                                    <a href="/pages/store/" class="btn btn-primary">스토어 둘러보기</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($orders as $order):
                                    $statusClass = '';
                                    $statusText = '';
                                    switch ($order['order_status']) {
                                        case 'delivered':
                                            $statusClass = 'completed';
                                            $statusText = '배송완료';
                                            break;
                                        case 'shipped':
                                            $statusClass = 'shipping';
                                            $statusText = '배송중';
                                            break;
                                        case 'processing':
                                            $statusClass = 'processing';
                                            $statusText = '처리중';
                                            break;
                                        case 'confirmed':
                                            $statusClass = 'confirmed';
                                            $statusText = '주문확인';
                                            break;
                                        case 'pending':
                                            $statusClass = 'pending';
                                            $statusText = '대기중';
                                            break;
                                        case 'cancelled':
                                            $statusClass = 'cancelled';
                                            $statusText = '취소됨';
                                            break;
                                        default:
                                            $statusClass = 'pending';
                                            $statusText = $order['order_status'];
                                    }
                                ?>
                                <div class="order-item">
                                    <div class="order-header">
                                        <span class="order-number">주문번호: <?= htmlspecialchars($order['order_number']) ?></span>
                                        <span class="order-date"><?= date('Y.m.d', strtotime($order['created_at'])) ?></span>
                                        <span class="order-status <?= $statusClass ?>"><?= $statusText ?></span>
                                    </div>
                                    <div class="order-products">
                                        <div class="product-item">
                                            <span><?= htmlspecialchars($order['product_names'] ?? '상품 정보 없음') ?></span>
                                            <span><?= number_format($order['total_amount']) ?>원</span>
                                        </div>
                                    </div>
                                    <div class="order-actions">
                                        <a href="/pages/store/order_detail.php?id=<?= $order['id'] ?>" class="btn btn-outline btn-sm">상세보기</a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Plant Analysis Tab -->
                    <div id="plant-analysis" class="tab-content">
                        <h2>식물분석 서비스</h2>
                        
                        <?php if ($currentUser['plant_analysis_permission']): ?>
                            <div class="analysis-status">
                                <div class="status-card active">
                                    <h3>✅ 식물분석 권한 보유</h3>
                                    <p>식물분석 서비스를 자유롭게 이용하실 수 있습니다.</p>
                                    <a href="/pages/plant_analysis/" class="btn btn-primary">식물분석 바로가기</a>
                                </div>
                                
                                <div class="analysis-stats">
                                    <h3>이용 현황</h3>
                                    <div class="stats-grid">
                                        <div class="stat-item">
                                            <span class="stat-number">15</span>
                                            <span class="stat-label">분석 횟수</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-number">32</span>
                                            <span class="stat-label">촬영 이미지</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-number">12일</span>
                                            <span class="stat-label">마지막 이용</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="analysis-status">
                                <div class="status-card inactive">
                                    <h3>❌ 식물분석 권한 없음</h3>
                                    <p>식물분석 서비스를 이용하려면 권한 신청이 필요합니다.</p>
                                    <a href="/pages/support/contact.php" class="btn btn-primary">권한 신청하기</a>
                                </div>
                                
                                <div class="permission-info">
                                    <h3>권한 신청 방법</h3>
                                    <ol>
                                        <li>문의하기를 통해 식물분석 권한 신청서 작성</li>
                                        <li>농장 정보 및 사용 목적 기재</li>
                                        <li>관리자 검토 후 2-3일 내 승인 처리</li>
                                        <li>승인 완료 후 서비스 이용 가능</li>
                                    </ol>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
    <script>
        // Daum 우편번호 API
        function execDaumPostcode() {
            new daum.Postcode({
                oncomplete: function(data) {
                    var addr = data.userSelectedType === 'R' ? data.roadAddress : data.jibunAddress;

                    document.getElementById('postcode').value = data.zonecode;
                    document.getElementById('address').value = addr;
                    document.getElementById('detailAddress').focus();
                }
            }).open();
        }

        // 폼 제출 전 주소 합치기
        document.querySelector('#profile-info form').addEventListener('submit', function(e) {
            const postcode = document.getElementById('postcode').value;
            const address = document.getElementById('address').value;
            const detailAddress = document.getElementById('detailAddress').value;

            let fullAddress = '';
            if (postcode) fullAddress += '[' + postcode + '] ';
            if (address) fullAddress += address;
            if (detailAddress) fullAddress += ' ' + detailAddress;

            // 합쳐진 주소를 hidden 필드에 저장
            document.getElementById('fullAddress').value = fullAddress.trim();

            // address 필드의 name을 임시로 제거하고 fullAddress를 address로 설정
            document.getElementById('address').removeAttribute('name');
            document.getElementById('fullAddress').setAttribute('name', 'address');
        });

        // 페이지 로드 시 기존 주소 분리
        window.addEventListener('DOMContentLoaded', function() {
            const currentAddress = "<?= htmlspecialchars($currentUser['address'] ?? '') ?>";
            if (currentAddress) {
                // 주소 파싱: [우편번호] 주소 상세주소
                const postcodeMatch = currentAddress.match(/\[(\d+)\]/);
                if (postcodeMatch) {
                    document.getElementById('postcode').value = postcodeMatch[1];
                    let remainingAddress = currentAddress.replace(/\[\d+\]\s*/, '');

                    // 상세주소는 마지막 공백 이후로 가정
                    const parts = remainingAddress.split(' ');
                    if (parts.length > 3) {
                        const detailStartIndex = remainingAddress.lastIndexOf(' ', remainingAddress.length - 10);
                        if (detailStartIndex > 0) {
                            document.getElementById('address').value = remainingAddress.substring(0, detailStartIndex).trim();
                            document.getElementById('detailAddress').value = remainingAddress.substring(detailStartIndex + 1).trim();
                        } else {
                            document.getElementById('address').value = remainingAddress;
                        }
                    } else {
                        document.getElementById('address').value = remainingAddress;
                    }
                } else {
                    // 우편번호가 없는 경우 전체를 주소로
                    document.getElementById('address').value = currentAddress;
                }
            }

            // URL 해시로 탭 자동 열기 (#orders 등)
            const hash = window.location.hash.replace('#', '');
            if (hash && document.getElementById(hash)) {
                showTab(hash, null);
            }
        });

        function showTab(tabId, element) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all nav links
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            // Show selected tab and mark nav link as active
            const tabEl = document.getElementById(tabId);
            if (tabEl) tabEl.classList.add('active');
            // element가 없으면(해시 직접 진입) 해당 탭 nav-link를 찾아 활성화
            if (element) {
                element.classList.add('active');
            } else {
                const navLink = document.querySelector('.nav-link[href="#' + tabId + '"]');
                if (navLink) navLink.classList.add('active');
            }
        }
        
        // 폼 제출 후 메시지가 있으면 스크롤
        <?php if (!empty($message)): ?>
        window.scrollTo({ top: 0, behavior: 'smooth' });
        <?php endif; ?>
    </script>
</body>
</html>

<style>
.profile-main {
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

.profile-content {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 3rem;
}

.profile-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    text-align: center;
    margin-bottom: 2rem;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    background: #4CAF50;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
    margin: 0 auto 1rem;
}

.profile-card h3 {
    color: #2E7D32;
    margin-bottom: 0.5rem;
}

.profile-email {
    color: #666;
    margin-bottom: 1rem;
}

.status-badge {
    display: block;
    padding: 0.3rem 0.8rem;
    border-radius: 15px;
    font-size: 0.8rem;
    margin-bottom: 0.5rem;
}

.status-badge.active {
    background: #E8F5E8;
    color: #2E7D32;
}

.status-badge.inactive {
    background: #FFEBEE;
    color: #C62828;
}

.status-badge.admin {
    background: #FFF3E0;
    color: #FF6F00;
}

.profile-nav {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    text-decoration: none;
    color: #333;
    border-bottom: 1px solid #f0f0f0;
    transition: all 0.3s ease;
}

.nav-link:last-child {
    border-bottom: none;
}

.nav-link:hover {
    background: #f8f9fa;
}

.nav-link.active {
    background: #E8F5E8;
    color: #2E7D32;
    border-left: 4px solid #4CAF50;
}

.nav-icon {
    margin-right: 0.8rem;
    font-size: 1.2rem;
}

.profile-main-content {
    background: white;
    padding: 2rem;
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

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.tab-content h2 {
    color: #2E7D32;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f0f0f0;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
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
.form-group textarea {
    width: 100%;
    padding: 0.8rem;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #4CAF50;
}

.form-group input:disabled {
    background: #f8f9fa;
    color: #666;
}

.form-group small {
    color: #666;
    font-size: 0.9rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.security-section {
    margin-bottom: 3rem;
}

.security-section h3 {
    color: #2E7D32;
    margin-bottom: 1rem;
}

.login-history {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
}

.history-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.8rem 0;
    border-bottom: 1px solid #e0e0e0;
}

.history-item:last-child {
    border-bottom: none;
}

.history-info {
    display: flex;
    flex-direction: column;
}

.device {
    font-weight: 600;
    margin-bottom: 0.2rem;
}

.time {
    color: #666;
    font-size: 0.9rem;
}

.location {
    color: #4CAF50;
    font-size: 0.9rem;
}

.orders-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.order-item {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 1.5rem;
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.order-number {
    font-weight: 600;
    color: #2E7D32;
}

.order-status {
    padding: 0.3rem 0.8rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
}

.order-status.completed {
    background: #E8F5E8;
    color: #2E7D32;
}

.order-status.shipping {
    background: #E3F2FD;
    color: #1976D2;
}

.order-status.processing {
    background: #FFF3E0;
    color: #F57C00;
}

.order-status.confirmed {
    background: #E8F5E9;
    color: #388E3C;
}

.order-status.pending {
    background: #F5F5F5;
    color: #757575;
}

.order-status.cancelled {
    background: #FFEBEE;
    color: #C62828;
}

.no-orders {
    text-align: center;
    padding: 4rem 2rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.no-orders-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.no-orders h3 {
    color: #2E7D32;
    margin-bottom: 0.5rem;
}

.no-orders p {
    color: #666;
    margin-bottom: 2rem;
}

.product-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.order-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.analysis-status .status-card {
    background: #f8f9fa;
    padding: 2rem;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 2rem;
}

.analysis-status .status-card.active {
    background: #E8F5E8;
    border: 2px solid #4CAF50;
}

.analysis-status .status-card.inactive {
    background: #FFEBEE;
    border: 2px solid #F44336;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-top: 1rem;
}

.stat-item {
    text-align: center;
    padding: 1rem;
    background: white;
    border-radius: 8px;
}

.stat-number {
    display: block;
    font-size: 1.8rem;
    font-weight: bold;
    color: #4CAF50;
    margin-bottom: 0.3rem;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
}

.permission-info {
    background: #f8f9fa;
    padding: 2rem;
    border-radius: 8px;
}

.permission-info h3 {
    color: #2E7D32;
    margin-bottom: 1rem;
}

.permission-info ol {
    color: #333;
    padding-left: 1.5rem;
}

.permission-info li {
    margin-bottom: 0.5rem;
}

@media (max-width: 768px) {
    .page-header {
        height: 100px !important;
        padding: 1.5rem 1rem !important;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .profile-content {
        grid-template-columns: 1fr;
        gap: 2rem;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .order-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .form-actions,
    .order-actions {
        flex-direction: column;
    }
}
</style>