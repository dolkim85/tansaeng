<?php
// 데이터베이스 연결을 선택적으로 처리
$currentUser = null;
try {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
} catch (Exception $e) {
    // 데이터베이스 연결 실패시 계속 진행
    error_log("Database connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main >
        <div class="container">
            <div class="page-header">
                <h1>자주 묻는 질문</h1>
                <p>탄생 제품과 서비스에 대해 자주 묻는 질문들을 모았습니다</p>
            </div>

            <!-- Search Bar -->
            <div class="faq-search">
                <div class="search-box">
                    <input type="text" id="faqSearch" placeholder="궁금한 것을 검색해보세요..." onkeyup="searchFAQ()">
                    <span class="search-icon">🔍</span>
                </div>
            </div>

            <!-- FAQ Categories -->
            <div class="faq-categories">
                <button class="category-btn active" onclick="filterFAQ('all')">전체</button>
                <button class="category-btn" onclick="filterFAQ('product')">제품</button>
                <button class="category-btn" onclick="filterFAQ('usage')">사용법</button>
                <button class="category-btn" onclick="filterFAQ('technical')">기술지원</button>
                <button class="category-btn" onclick="filterFAQ('order')">주문/배송</button>
                <button class="category-btn" onclick="filterFAQ('account')">계정</button>
            </div>

            <!-- FAQ List -->
            <div class="faq-list">
                <!-- Product FAQs -->
                <div class="faq-item" data-category="product">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <h3>탄생 배지의 주요 특징은 무엇인가요?</h3>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>탄생 배지는 다음과 같은 특징을 가지고 있습니다:</p>
                        <ul>
                            <li><strong>최적의 보수력과 배수성:</strong> 뿌리 발달에 필요한 적절한 수분과 공기 공급</li>
                            <li><strong>pH 안정성:</strong> 6.0-6.8 범위에서 안정적인 pH 유지</li>
                            <li><strong>무균화:</strong> 고온 스팀 살균으로 99.8% 무균 상태 달성</li>
                            <li><strong>친환경 소재:</strong> 100% 천연 원료 사용</li>
                            <li><strong>작물별 맞춤형:</strong> 다양한 작물에 최적화된 배지 제공</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-item" data-category="product">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <h3>일반 흙과 비교했을 때 어떤 장점이 있나요?</h3>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>탄생 배지는 일반 흙 대비 다음과 같은 장점이 있습니다:</p>
                        <ul>
                            <li><strong>수확량 30% 증대:</strong> 최적화된 뿌리 환경으로 생산성 향상</li>
                            <li><strong>물 절약 50%:</strong> 효율적인 수분 관리로 물 사용량 절감</li>
                            <li><strong>병해충 예방:</strong> 무균 상태로 토양 병해충 차단</li>
                            <li><strong>일정한 품질:</strong> 균일한 생장 조건 제공</li>
                            <li><strong>재사용 가능:</strong> 적절한 처리 후 재활용 가능</li>
                        </ul>
                    </div>
                </div>

                <!-- Usage FAQs -->
                <div class="faq-item" data-category="usage">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <h3>배지를 처음 사용할 때 어떻게 준비해야 하나요?</h3>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>배지 사용 전 준비 과정:</p>
                        <ol>
                            <li><strong>포장 확인:</strong> 손상 여부와 유통기한 확인</li>
                            <li><strong>수분 공급:</strong> 깨끗한 물에 10-15분간 담가서 충분히 적시기</li>
                            <li><strong>물기 조절:</strong> 과도한 물기 제거 (촉촉하지만 물이 뚝뚝 떨어지지 않을 정도)</li>
                            <li><strong>pH 조정:</strong> 필요시 pH 6.0-6.5로 조정</li>
                        </ol>
                        <p>준비된 배지는 즉시 사용하거나 밀폐하여 보관하세요.</p>
                    </div>
                </div>

                <div class="faq-item" data-category="usage">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <h3>얼마나 자주 물을 줘야 하나요?</h3>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>급수 주기는 계절과 환경에 따라 다릅니다:</p>
                        <ul>
                            <li><strong>여름:</strong> 1일 1-2회 (온도가 높고 증발량이 많을 때)</li>
                            <li><strong>봄/가을:</strong> 2-3일에 1회 (적당한 온도)</li>
                            <li><strong>겨울:</strong> 3-4일에 1회 (낮은 온도, 적은 증발량)</li>
                        </ul>
                        <p><strong>급수 시점:</strong> 배지 표면이 약간 마를 때가 적절합니다.</p>
                        <p><strong>급수량:</strong> 배수구에서 물이 약간 나올 때까지 충분히 주세요.</p>
                    </div>
                </div>

                <!-- Technical FAQs -->
                <div class="faq-item" data-category="technical">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <h3>AI 식물분석 시스템은 어떻게 작동하나요?</h3>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>AI 식물분석 시스템의 작동 원리:</p>
                        <ol>
                            <li><strong>영상 촬영:</strong> 라즈베리파이 카메라로 식물 이미지 촬영</li>
                            <li><strong>AI 분석:</strong> 딥러닝 모델이 이미지를 분석하여 건강상태 진단</li>
                            <li><strong>결과 도출:</strong> 질병, 영양상태, 성장 단계 등을 실시간 분석</li>
                            <li><strong>권장사항 제공:</strong> 분석 결과에 따른 관리 방법 제시</li>
                        </ol>
                        <p><strong>정확도:</strong> 95% 이상의 높은 정확도로 분석합니다.</p>
                        <p><strong>지원 작물:</strong> 토마토, 딸기, 오이, 상추 등 15종 지원</p>
                    </div>
                </div>

                <div class="faq-item" data-category="technical">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <h3>식물분석 서비스 이용 권한을 받으려면 어떻게 해야 하나요?</h3>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>식물분석 서비스 권한 신청 절차:</p>
                        <ol>
                            <li><strong>회원가입:</strong> 먼저 회원가입을 완료하세요</li>
                            <li><strong>권한 신청:</strong> '고객지원 > 문의하기'를 통해 권한 신청서 작성</li>
                            <li><strong>정보 제공:</strong> 농장 정보, 재배 작물, 사용 목적 등 기재</li>
                            <li><strong>승인 대기:</strong> 관리자 검토 후 2-3일 내 승인 처리</li>
                            <li><strong>서비스 이용:</strong> 승인 완료 후 식물분석 시스템 이용 가능</li>
                        </ol>
                        <p><strong>승인 기준:</strong> 농업 관련 종사자 또는 연구 목적의 이용자</p>
                    </div>
                </div>

                <!-- Order & Shipping FAQs -->
                <div class="faq-item" data-category="order">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <h3>주문은 어떻게 하나요?</h3>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>주문 방법:</p>
                        <ul>
                            <li><strong>온라인 주문:</strong> '스토어' 메뉴에서 제품 선택 후 주문</li>
                            <li><strong>전화 주문:</strong> 02-0000-0000 (평일 09:00-18:00)</li>
                            <li><strong>이메일 주문:</strong> order@tangsaeng.com</li>
                        </ul>
                        <p><strong>결제 방법:</strong> 신용카드, 계좌이체, 무통장입금</p>
                        <p><strong>최소 주문량:</strong> 제품별로 상이 (일반적으로 10포 이상)</p>
                    </div>
                </div>

                <div class="faq-item" data-category="order">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <h3>배송은 얼마나 걸리나요?</h3>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>배송 안내:</p>
                        <ul>
                            <li><strong>일반 배송:</strong> 주문 후 2-3일 (영업일 기준)</li>
                            <li><strong>대량 주문:</strong> 주문 후 3-5일 (사전 협의 필요)</li>
                            <li><strong>긴급 배송:</strong> 당일 또는 익일 배송 가능 (추가 비용 발생)</li>
                        </ul>
                        <p><strong>배송 지역:</strong> 전국 배송 (도서지역 별도 문의)</p>
                        <p><strong>배송비:</strong> 5만원 이상 주문시 무료배송</p>
                    </div>
                </div>

                <!-- Account FAQs -->
                <div class="faq-item" data-category="account">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <h3>회원가입은 어떻게 하나요?</h3>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>회원가입 절차:</p>
                        <ol>
                            <li>페이지 우상단 '회원가입' 버튼 클릭</li>
                            <li>이메일 주소와 기본 정보 입력</li>
                            <li>이메일 인증 완료</li>
                            <li>약관 동의 후 가입 완료</li>
                        </ol>
                        <p><strong>회원 혜택:</strong> 구매 할인, 전용 상담 서비스, 기술 자료 다운로드</p>
                    </div>
                </div>

                <div class="faq-item" data-category="account">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <h3>비밀번호를 잊어버렸어요.</h3>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>비밀번호 재설정 방법:</p>
                        <ol>
                            <li>로그인 페이지에서 '비밀번호 찾기' 클릭</li>
                            <li>가입시 사용한 이메일 주소 입력</li>
                            <li>이메일로 발송된 재설정 링크 클릭</li>
                            <li>새 비밀번호 설정</li>
                        </ol>
                        <p><strong>문의:</strong> 문제가 지속되면 고객지원팀(02-0000-0000)으로 연락주세요.</p>
                    </div>
                </div>

                <!-- General FAQs -->
                <div class="faq-item" data-category="all">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <h3>환불/교환 정책은 어떻게 되나요?</h3>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>환불/교환 정책:</p>
                        <ul>
                            <li><strong>교환/환불 기간:</strong> 제품 수령 후 7일 이내</li>
                            <li><strong>교환 가능한 경우:</strong> 제품 불량, 배송 오류, 파손</li>
                            <li><strong>교환 불가한 경우:</strong> 사용한 제품, 포장 훼손, 고객 변심 (특수 제품)</li>
                            <li><strong>환불 방법:</strong> 원결제 수단으로 환불 (처리기간 3-5일)</li>
                        </ul>
                        <p><strong>신청 방법:</strong> 고객센터 02-0000-0000 또는 support@tangsaeng.com</p>
                    </div>
                </div>
            </div>

            <!-- Contact Section -->
            <div class="faq-contact">
                <h2>원하는 답변을 찾지 못하셨나요?</h2>
                <p>추가 문의사항이 있으시면 언제든지 연락주세요.</p>
                <div class="contact-options">
                    <a href="/pages/support/contact.php" class="contact-btn">
                        <span class="contact-icon">✉️</span>
                        <span>문의하기</span>
                    </a>
                    <div class="contact-info">
                        <div class="info-item">
                            <span class="info-icon">📞</span>
                            <span>02-0000-0000</span>
                        </div>
                        <div class="info-item">
                            <span class="info-icon">🕒</span>
                            <span>평일 09:00-18:00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
    <script>
        function toggleFAQ(element) {
            const faqItem = element.parentElement;
            const answer = faqItem.querySelector('.faq-answer');
            const toggle = element.querySelector('.faq-toggle');
            
            if (answer.style.display === 'block') {
                answer.style.display = 'none';
                toggle.textContent = '+';
                faqItem.classList.remove('active');
            } else {
                answer.style.display = 'block';
                toggle.textContent = '-';
                faqItem.classList.add('active');
            }
        }
        
        function filterFAQ(category) {
            const items = document.querySelectorAll('.faq-item');
            const buttons = document.querySelectorAll('.category-btn');
            
            // Update button states
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Filter items
            items.forEach(item => {
                if (category === 'all' || item.getAttribute('data-category') === category) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }
        
        function searchFAQ() {
            const searchTerm = document.getElementById('faqSearch').value.toLowerCase();
            const items = document.querySelectorAll('.faq-item');
            
            items.forEach(item => {
                const question = item.querySelector('.faq-question h3').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
                
                if (question.includes(searchTerm) || answer.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>

<style>
.faq-main {
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

.faq-search {
    margin-bottom: 2rem;
}

.search-box {
    position: relative;
    max-width: 500px;
    margin: 0 auto;
}

.search-box input {
    width: 100%;
    padding: 1rem 3rem 1rem 1rem;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.search-box input:focus {
    outline: none;
    border-color: #4CAF50;
}

.search-icon {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #666;
}

.faq-categories {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 3rem;
}

.category-btn {
    padding: 0.8rem 1.5rem;
    border: 2px solid #e0e0e0;
    background: white;
    border-radius: 25px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.category-btn.active {
    background: #4CAF50;
    color: white;
    border-color: #4CAF50;
}

.category-btn:hover {
    border-color: #4CAF50;
}

.faq-list {
    max-width: 800px;
    margin: 0 auto;
}

.faq-item {
    background: white;
    border-radius: 8px;
    margin-bottom: 1rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: all 0.3s ease;
}

.faq-item.active {
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.faq-question {
    padding: 1.5rem;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
    transition: background-color 0.3s ease;
}

.faq-question:hover {
    background: #e9ecef;
}

.faq-question h3 {
    margin: 0;
    color: #2E7D32;
    font-size: 1.1rem;
    font-weight: 600;
}

.faq-toggle {
    font-size: 1.5rem;
    color: #4CAF50;
    font-weight: bold;
    transition: transform 0.3s ease;
}

.faq-item.active .faq-toggle {
    transform: rotate(45deg);
}

.faq-answer {
    display: none;
    padding: 1.5rem;
    border-top: 1px solid #e0e0e0;
}

.faq-answer p {
    color: #333;
    line-height: 1.6;
    margin-bottom: 1rem;
}

.faq-answer ul,
.faq-answer ol {
    padding-left: 1.5rem;
    color: #333;
}

.faq-answer li {
    margin-bottom: 0.5rem;
    line-height: 1.5;
}

.faq-answer strong {
    color: #2E7D32;
}

.faq-contact {
    background: #f8f9fa;
    padding: 3rem;
    border-radius: 12px;
    text-align: center;
    margin-top: 3rem;
}

.faq-contact h2 {
    color: #2E7D32;
    margin-bottom: 1rem;
}

.faq-contact p {
    color: #666;
    margin-bottom: 2rem;
}

.contact-options {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
}

.contact-btn {
    display: flex;
    align-items: center;
    padding: 1rem 2rem;
    background: #4CAF50;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    transition: background-color 0.3s ease;
}

.contact-btn:hover {
    background: #45a049;
}

.contact-icon {
    margin-right: 0.5rem;
}

.contact-info {
    display: flex;
    gap: 1.5rem;
}

.info-item {
    display: flex;
    align-items: center;
    color: #666;
}

.info-icon {
    margin-right: 0.5rem;
}

@media (max-width: 768px) {
    .page-header {
        height: 100px !important;
        padding: 1.5rem 1rem !important;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .faq-categories {
        justify-content: flex-start;
        overflow-x: auto;
        padding-bottom: 0.5rem;
    }

    .category-btn {
        white-space: nowrap;
    }

    .contact-options {
        flex-direction: column;
        gap: 1rem;
    }

    .contact-info {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>