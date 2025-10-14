<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$success = '';
$error = '';

// 문의 제출 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = DatabaseConfig::getConnection();

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $error = '필수 항목을 모두 입력해주세요.';
        } else {
            $sql = "INSERT INTO inquiries (name, email, phone, subject, category, message, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $email, $phone, $subject, $category, $message]);

            $success = '문의가 성공적으로 접수되었습니다. 빠른 시일 내에 답변 드리겠습니다.';

            // 폼 초기화
            $_POST = [];
        }
    } catch (Exception $e) {
        $error = '문의 접수 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>1:1 문의 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .page-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 20px;
            text-align: center;
        }
        .page-hero h1 {
            font-size: 2.5em;
            margin-bottom: 20px;
        }
        .inquiry-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 0 20px;
        }
        .inquiry-form {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #2c3e50;
        }
        .form-group label .required {
            color: #e74c3c;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
            font-family: inherit;
        }
        .form-group textarea {
            min-height: 200px;
            resize: vertical;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .info-section {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .info-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .info-item {
            text-align: center;
        }
        .info-item .icon {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .info-item h4 {
            color: #34495e;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="page-hero">
        <h1>💬 1:1 문의</h1>
        <p>궁금하신 점이나 도움이 필요하신 부분을 알려주세요</p>
    </div>

    <div class="inquiry-container">
        <div class="info-section">
            <h3>📞 빠른 문의</h3>
            <p>급한 문의사항은 전화나 이메일로 직접 연락주시면 더욱 빠른 답변을 받으실 수 있습니다.</p>
            <div class="info-grid">
                <div class="info-item">
                    <div class="icon">📞</div>
                    <h4>전화 문의</h4>
                    <p>010-7183-9876</p>
                    <p style="font-size: 0.9em; color: #7f8c8d;">평일 09:00 - 18:00</p>
                </div>
                <div class="info-item">
                    <div class="icon">📧</div>
                    <h4>이메일</h4>
                    <p>korea_tansaeng@naver.com</p>
                    <p style="font-size: 0.9em; color: #7f8c8d;">24시간 접수</p>
                </div>
                <div class="info-item">
                    <div class="icon">⏱️</div>
                    <h4>답변 시간</h4>
                    <p>영업일 기준</p>
                    <p style="font-size: 0.9em; color: #7f8c8d;">1~2일 이내</p>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="inquiry-form">
            <h2 style="margin-bottom: 30px; color: #2c3e50;">문의 작성</h2>

            <div class="form-group">
                <label for="name">이름 <span class="required">*</span></label>
                <input type="text" id="name" name="name"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                       required placeholder="홍길동">
            </div>

            <div class="form-group">
                <label for="email">이메일 <span class="required">*</span></label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required placeholder="example@email.com">
            </div>

            <div class="form-group">
                <label for="phone">연락처</label>
                <input type="tel" id="phone" name="phone"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                       placeholder="010-1234-5678">
            </div>

            <div class="form-group">
                <label for="category">문의 분류 <span class="required">*</span></label>
                <select id="category" name="category" required>
                    <option value="">선택해주세요</option>
                    <option value="product" <?= (($_POST['category'] ?? '') === 'product') ? 'selected' : '' ?>>제품 문의</option>
                    <option value="order" <?= (($_POST['category'] ?? '') === 'order') ? 'selected' : '' ?>>주문/배송</option>
                    <option value="technical" <?= (($_POST['category'] ?? '') === 'technical') ? 'selected' : '' ?>>기술 지원</option>
                    <option value="partnership" <?= (($_POST['category'] ?? '') === 'partnership') ? 'selected' : '' ?>>제휴 문의</option>
                    <option value="etc" <?= (($_POST['category'] ?? '') === 'etc') ? 'selected' : '' ?>>기타</option>
                </select>
            </div>

            <div class="form-group">
                <label for="subject">제목 <span class="required">*</span></label>
                <input type="text" id="subject" name="subject"
                       value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                       required placeholder="문의 제목을 입력해주세요">
            </div>

            <div class="form-group">
                <label for="message">문의 내용 <span class="required">*</span></label>
                <textarea id="message" name="message" required
                          placeholder="문의하실 내용을 자세히 작성해주시면 더욱 정확한 답변을 드릴 수 있습니다.&#10;&#10;- 제품 문의: 작물 종류, 재배 규모 등&#10;- 기술 지원: 현재 사용 중인 배지, 재배 환경, 문제 상황 등&#10;- 주문 문의: 수량, 배송 지역 등"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            </div>

            <div style="margin-bottom: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px;">
                <p style="margin: 0; font-size: 0.95em; color: #856404;">
                    <strong>개인정보 수집 및 이용 안내</strong><br>
                    수집 항목: 이름, 이메일, 연락처<br>
                    이용 목적: 문의 답변 및 고객 상담<br>
                    보유 기간: 문의 처리 후 1년<br>
                    위 내용에 동의하시고 문의를 접수하시려면 제출 버튼을 눌러주세요.
                </p>
            </div>

            <button type="submit" class="btn-submit">문의하기</button>
        </form>

        <div style="margin-top: 30px; padding: 20px; background: #e3f2fd; border-radius: 10px;">
            <h4 style="color: #1976d2; margin-bottom: 10px;">💡 문의 전 확인해주세요</h4>
            <ul style="line-height: 1.8; color: #424242;">
                <li><a href="/pages/support/faq.php" style="color: #1976d2;">FAQ</a>에서 자주 묻는 질문에 대한 답변을 먼저 확인해보세요</li>
                <li><a href="/pages/support/technical.php" style="color: #1976d2;">기술지원</a> 페이지에서 기술 가이드를 참고하실 수 있습니다</li>
                <li>제품 정보는 <a href="/pages/products/" style="color: #1976d2;">제품 페이지</a>에서 확인하실 수 있습니다</li>
            </ul>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
