<?php
/**
 * Support Main Page - 고객지원
 * 탄생 스마트팜 고객지원 센터
 */

$currentUser = null;
try {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
}

$pageTitle = "고객지원 - 탄생";
$pageDescription = "탄생 스마트팜 고객지원센터. FAQ, 기술지원, 1:1 문의 서비스를 제공합니다.";
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= $pageDescription ?>">
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .support-hero {
            background: linear-gradient(135deg, #2E7D32 0%, #1B5E20 100%);
            color: white;
            height: 170px;
            text-align: center;
            display: none; /* 기본적으로 숨김 (모바일) */
            align-items: center;
            justify-content: center;
        }

        /* PC에서만 표시 (768px 이상) */
        @media (min-width: 769px) {
            .support-hero {
                display: flex;
            }
        }
        .support-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            padding: 60px 0;
        }
        .support-card {
            background: white;
            border-radius: 12px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .support-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.15);
        }
        .support-icon {
            font-size: 3.5rem;
            margin-bottom: 20px;
        }
        .support-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #2E7D32;
            margin-bottom: 15px;
        }
        .support-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .support-btn {
            background: #2E7D32;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: background 0.3s ease;
        }
        .support-btn:hover {
            background: #1B5E20;
            color: white;
        }
        .contact-info {
            background: #f8f9fa;
            padding: 60px 0;
        }
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            text-align: center;
        }
        .contact-item {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        .contact-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        .faq-preview {
            padding: 60px 0;
        }
        .faq-item {
            background: white;
            border-radius: 10px;
            margin-bottom: 15px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .faq-question {
            background: #f8f9fa;
            padding: 20px;
            font-weight: 600;
            color: #2E7D32;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .faq-answer {
            padding: 20px;
            color: #666;
            line-height: 1.6;
        }
        @media (max-width: 768px) {
            .support-hero {
                height: 100px !important;
                padding: 0rem 0 !important;
            }
            .support-hero .container {
                padding: 1.5rem 1rem !important;
                display: flex;
                flex-direction: column;
                justify-content: center;
                height: 100%;
            }
            .support-grid, .contact-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="support-hero">
        <div class="container">
            <h1>💬 고객지원센터</h1>
            <p>언제든지 도움이 필요하시면 연락주세요</p>
        </div>
    </section>

    <!-- Support Services -->
    <section class="support-grid">
        <div class="container">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px;">

                <!-- FAQ -->
                <div class="support-card">
                    <div class="support-icon">❓</div>
                    <h3 class="support-title">자주 묻는 질문</h3>
                    <p class="support-description">
                        고객님들이 자주 물어보시는 질문들과 답변을 확인하세요. 빠르고 정확한 정보를 제공합니다.
                    </p>
                    <a href="faq.php" class="support-btn">FAQ 보기</a>
                </div>

                <!-- Contact -->
                <div class="support-card">
                    <div class="support-icon">📞</div>
                    <h3 class="support-title">1:1 문의</h3>
                    <p class="support-description">
                        개별적인 문의사항이나 상담이 필요하시면 1:1 문의를 이용해 주세요. 전문 상담원이 도와드립니다.
                    </p>
                    <a href="contact.php" class="support-btn">문의하기</a>
                </div>

                <!-- Technical Support -->
                <div class="support-card">
                    <div class="support-icon">🔧</div>
                    <h3 class="support-title">기술지원</h3>
                    <p class="support-description">
                        제품 사용법, 설치, 문제해결 등 기술적인 도움이 필요하시면 전문 기술팀이 지원해 드립니다.
                    </p>
                    <a href="contact.php?type=technical" class="support-btn">기술지원</a>
                </div>

                <!-- Downloads -->
                <div class="support-card">
                    <div class="support-icon">📁</div>
                    <h3 class="support-title">자료실</h3>
                    <p class="support-description">
                        제품 매뉴얼, 기술 자료, 카탈로그 등 다양한 자료를 다운로드 받으실 수 있습니다.
                    </p>
                    <a href="#downloads" class="support-btn">자료 다운로드</a>
                </div>

                <!-- Notice -->
                <div class="support-card">
                    <div class="support-icon">📢</div>
                    <h3 class="support-title">공지사항</h3>
                    <p class="support-description">
                        제품 업데이트, 서비스 공지, 이벤트 소식 등 중요한 공지사항을 확인하세요.
                    </p>
                    <a href="notice.php" class="support-btn">공지사항</a>
                </div>

                <!-- Plant Analysis Support -->
                <div class="support-card">
                    <div class="support-icon">🌱</div>
                    <h3 class="support-title">식물분석 지원</h3>
                    <p class="support-description">
                        AI 식물분석 서비스 이용 방법과 결과 해석에 대한 전문적인 지원을 받으실 수 있습니다.
                    </p>
                    <a href="/pages/plant_analysis/" class="support-btn">분석 서비스</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Information -->
    <section class="contact-info">
        <div class="container">
            <h2 style="text-align: center; margin-bottom: 40px; color: #2E7D32;">연락처 정보</h2>
            <div class="contact-grid">
                <div class="contact-item">
                    <div class="contact-icon">☎️</div>
                    <h4>전화 상담</h4>
                    <p><strong>1588-0000</strong></p>
                    <p>평일 09:00 - 18:00<br>토요일 09:00 - 12:00</p>
                </div>

                <div class="contact-item">
                    <div class="contact-icon">📧</div>
                    <h4>이메일 문의</h4>
                    <p><strong>contact@tansaeng.com</strong></p>
                    <p>24시간 접수<br>1일 이내 답변</p>
                </div>

                <div class="contact-item">
                    <div class="contact-icon">💬</div>
                    <h4>카카오톡 상담</h4>
                    <p><strong>@탄생스마트팜</strong></p>
                    <p>평일 09:00 - 18:00<br>실시간 상담</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Preview -->
    <section class="faq-preview">
        <div class="container">
            <h2 style="text-align: center; margin-bottom: 40px; color: #2E7D32;">자주 묻는 질문 미리보기</h2>

            <div class="faq-item">
                <div class="faq-question">Q. 배지는 어떻게 선택해야 하나요?</div>
                <div class="faq-answer">A. 재배하실 작물의 종류와 재배환경에 따라 적합한 배지가 달라집니다. 엽채류는 코코피트, 다육식물은 펄라이트, 범용으로는 혼합배지를 추천합니다.</div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Q. 배송은 얼마나 걸리나요?</div>
                <div class="faq-answer">A. 재고 보유 제품은 결제 완료 후 1-2일 내 출고되며, 지역에 따라 1-3일 내 배송됩니다. 대량 주문시 별도 협의가 필요합니다.</div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Q. 반품/교환은 어떻게 하나요?</div>
                <div class="faq-answer">A. 제품 수령 후 7일 이내 미사용 제품에 한하여 반품/교환이 가능합니다. 고객센터로 먼저 연락주시기 바랍니다.</div>
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <a href="faq.php" class="support-btn">전체 FAQ 보기</a>
            </div>
        </div>
    </section>

    <?php include '../../includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
</body>
</html>