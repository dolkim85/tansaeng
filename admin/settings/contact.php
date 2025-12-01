<?php
session_start();
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$db = Database::getInstance();
$message = '';
$messageType = '';

// 설정 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($_POST as $key => $value) {
            if ($key !== 'submit') {
                $db->query(
                    "UPDATE contact_settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = ?",
                    [$value, $_SESSION['user_id'], $key]
                );
            }
        }
        $message = '설정이 저장되었습니다.';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = '저장 중 오류가 발생했습니다: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// 설정 불러오기
$settings = [];
$results = $db->select("SELECT setting_key, setting_value, setting_group FROM contact_settings ORDER BY setting_group, display_order");
foreach ($results as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>연락처 정보 관리 - 관리자</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .contact-preview {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 2rem;
            margin: 2rem 0;
        }

        .preview-section {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .preview-section h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 1.2rem;
            border-bottom: 2px solid #3498db;
            padding-bottom: 0.5rem;
        }

        .methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .method-card {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .method-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .method-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .method-card h4 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .method-card p {
            color: #3498db;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .method-card small {
            color: #6c757d;
            font-size: 0.875rem;
        }

        .hours-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .hours-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
        }

        .hours-card h4 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .hours-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #dee2e6;
        }

        .hours-item:last-child {
            border-bottom: none;
        }

        .hours-item .day {
            font-weight: 600;
            color: #495057;
        }

        .hours-item .time {
            color: #3498db;
        }

        .form-section {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .form-section h3 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-size: 1.2rem;
            border-bottom: 2px solid #3498db;
            padding-bottom: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #495057;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .message {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .message.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .message.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <main class="admin-main">
        <div class="admin-container">
            <h1>📞 연락처 정보 관리</h1>
            <p class="page-description">고객 문의 페이지에 표시되는 연락처 정보를 설정합니다. 실시간으로 미리보기를 확인할 수 있습니다.</p>

            <?php if ($message): ?>
                <div class="message <?= $messageType ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="contactSettingsForm">
                <!-- 연락처 정보 섹션 -->
                <div class="form-section">
                    <h3>📋 연락처 안내</h3>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="phone_number">📞 전화번호</label>
                            <input type="text" id="phone_number" name="phone_number"
                                   value="<?= htmlspecialchars($settings['phone_number'] ?? '') ?>"
                                   placeholder="02-0000-0000">
                        </div>

                        <div class="form-group">
                            <label for="phone_hours">전화 상담 시간</label>
                            <input type="text" id="phone_hours" name="phone_hours"
                                   value="<?= htmlspecialchars($settings['phone_hours'] ?? '') ?>"
                                   placeholder="평일 09:00-18:00">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="email_address">✉️ 이메일 주소</label>
                            <input type="email" id="email_address" name="email_address"
                                   value="<?= htmlspecialchars($settings['email_address'] ?? '') ?>"
                                   placeholder="support@tangsaeng.com">
                        </div>

                        <div class="form-group">
                            <label for="email_response_time">이메일 응답 시간</label>
                            <input type="text" id="email_response_time" name="email_response_time"
                                   value="<?= htmlspecialchars($settings['email_response_time'] ?? '') ?>"
                                   placeholder="24시간 접수, 2-3일 내 답변">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="visit_address">📍 방문 상담 주소</label>
                            <input type="text" id="visit_address" name="visit_address"
                                   value="<?= htmlspecialchars($settings['visit_address'] ?? '') ?>"
                                   placeholder="서울특별시 강남구 테헤란로 123">
                        </div>

                        <div class="form-group">
                            <label for="visit_note">방문 상담 안내</label>
                            <input type="text" id="visit_note" name="visit_note"
                                   value="<?= htmlspecialchars($settings['visit_note'] ?? '') ?>"
                                   placeholder="사전 예약 후 방문">
                        </div>
                    </div>
                </div>

                <!-- 운영 시간 섹션 -->
                <div class="form-section">
                    <h3>⏰ 운영 시간</h3>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="business_hours_weekday">평일 운영 시간</label>
                            <input type="text" id="business_hours_weekday" name="business_hours_weekday"
                                   value="<?= htmlspecialchars($settings['business_hours_weekday'] ?? '') ?>"
                                   placeholder="09:00 - 18:00">
                        </div>

                        <div class="form-group">
                            <label for="business_hours_lunch">점심 시간</label>
                            <input type="text" id="business_hours_lunch" name="business_hours_lunch"
                                   value="<?= htmlspecialchars($settings['business_hours_lunch'] ?? '') ?>"
                                   placeholder="12:00 - 13:00">
                        </div>

                        <div class="form-group">
                            <label for="business_hours_weekend">주말/공휴일</label>
                            <input type="text" id="business_hours_weekend" name="business_hours_weekend"
                                   value="<?= htmlspecialchars($settings['business_hours_weekend'] ?? '') ?>"
                                   placeholder="휴무">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="email_hours_reception">이메일 접수 시간</label>
                            <input type="text" id="email_hours_reception" name="email_hours_reception"
                                   value="<?= htmlspecialchars($settings['email_hours_reception'] ?? '') ?>"
                                   placeholder="24시간">
                        </div>

                        <div class="form-group">
                            <label for="email_hours_response">이메일 답변 시간</label>
                            <input type="text" id="email_hours_response" name="email_hours_response"
                                   value="<?= htmlspecialchars($settings['email_hours_response'] ?? '') ?>"
                                   placeholder="평일 기준 2-3일">
                        </div>

                        <div class="form-group">
                            <label for="email_hours_urgent">긴급 문의 안내</label>
                            <input type="text" id="email_hours_urgent" name="email_hours_urgent"
                                   value="<?= htmlspecialchars($settings['email_hours_urgent'] ?? '') ?>"
                                   placeholder="전화 상담 권장">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="submit" class="btn btn-primary">💾 설정 저장</button>
                    <a href="/pages/support/contact.php" target="_blank" class="btn btn-secondary">👁️ 실제 페이지 보기</a>
                </div>
            </form>

            <!-- 실시간 미리보기 -->
            <div class="contact-preview">
                <h2>📱 실시간 미리보기</h2>
                <p>현재 입력한 내용이 실제 페이지에 어떻게 표시되는지 확인할 수 있습니다.</p>

                <div class="preview-section">
                    <h3>연락처 안내</h3>
                    <div class="methods-grid">
                        <div class="method-card">
                            <div class="method-icon">📞</div>
                            <h4>전화 문의</h4>
                            <p id="preview_phone"><?= htmlspecialchars($settings['phone_number'] ?? '02-0000-0000') ?></p>
                            <small id="preview_phone_hours"><?= htmlspecialchars($settings['phone_hours'] ?? '평일 09:00-18:00') ?></small>
                        </div>

                        <div class="method-card">
                            <div class="method-icon">✉️</div>
                            <h4>이메일 문의</h4>
                            <p id="preview_email"><?= htmlspecialchars($settings['email_address'] ?? 'support@tangsaeng.com') ?></p>
                            <small id="preview_email_response"><?= htmlspecialchars($settings['email_response_time'] ?? '24시간 접수, 2-3일 내 답변') ?></small>
                        </div>

                        <div class="method-card">
                            <div class="method-icon">💬</div>
                            <h4>온라인 문의</h4>
                            <p>아래 문의 양식 작성</p>
                            <small>실시간 접수, 빠른 답변</small>
                        </div>

                        <div class="method-card">
                            <div class="method-icon">📍</div>
                            <h4>방문 상담</h4>
                            <p id="preview_address"><?= htmlspecialchars($settings['visit_address'] ?? '서울특별시 강남구 테헤란로 123') ?></p>
                            <small id="preview_visit_note"><?= htmlspecialchars($settings['visit_note'] ?? '사전 예약 후 방문') ?></small>
                        </div>
                    </div>
                </div>

                <div class="preview-section">
                    <h3>운영 시간 안내</h3>
                    <div class="hours-grid">
                        <div class="hours-card">
                            <h4>📞 전화 상담</h4>
                            <div class="hours-item">
                                <span class="day">평일</span>
                                <span class="time" id="preview_weekday"><?= htmlspecialchars($settings['business_hours_weekday'] ?? '09:00 - 18:00') ?></span>
                            </div>
                            <div class="hours-item">
                                <span class="day">점심시간</span>
                                <span class="time" id="preview_lunch"><?= htmlspecialchars($settings['business_hours_lunch'] ?? '12:00 - 13:00') ?></span>
                            </div>
                            <div class="hours-item">
                                <span class="day">주말/공휴일</span>
                                <span class="time" id="preview_weekend"><?= htmlspecialchars($settings['business_hours_weekend'] ?? '휴무') ?></span>
                            </div>
                        </div>

                        <div class="hours-card">
                            <h4>✉️ 이메일/온라인 문의</h4>
                            <div class="hours-item">
                                <span class="day">접수</span>
                                <span class="time" id="preview_reception"><?= htmlspecialchars($settings['email_hours_reception'] ?? '24시간') ?></span>
                            </div>
                            <div class="hours-item">
                                <span class="day">답변</span>
                                <span class="time" id="preview_response"><?= htmlspecialchars($settings['email_hours_response'] ?? '평일 기준 2-3일') ?></span>
                            </div>
                            <div class="hours-item">
                                <span class="day">긴급 문의</span>
                                <span class="time" id="preview_urgent"><?= htmlspecialchars($settings['email_hours_urgent'] ?? '전화 상담 권장') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // 실시간 미리보기 업데이트
        const inputs = {
            'phone_number': 'preview_phone',
            'phone_hours': 'preview_phone_hours',
            'email_address': 'preview_email',
            'email_response_time': 'preview_email_response',
            'visit_address': 'preview_address',
            'visit_note': 'preview_visit_note',
            'business_hours_weekday': 'preview_weekday',
            'business_hours_lunch': 'preview_lunch',
            'business_hours_weekend': 'preview_weekend',
            'email_hours_reception': 'preview_reception',
            'email_hours_response': 'preview_response',
            'email_hours_urgent': 'preview_urgent'
        };

        Object.keys(inputs).forEach(inputId => {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(inputs[inputId]);

            if (input && preview) {
                input.addEventListener('input', function() {
                    preview.textContent = this.value || this.placeholder;
                });
            }
        });
    </script>
</body>
</html>
