<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>기술지원 - 탄생</title>
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
        .support-content {
            max-width: 1200px;
            margin: 50px auto;
            padding: 0 20px;
        }
        .support-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .support-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .support-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .support-card h3 {
            font-size: 1.8em;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        .support-card .icon {
            font-size: 3em;
            margin-bottom: 20px;
        }
        .technical-section {
            margin-top: 60px;
            background: #f8f9fa;
            padding: 50px 30px;
            border-radius: 10px;
        }
        .technical-section h2 {
            font-size: 2em;
            margin-bottom: 30px;
            color: #2c3e50;
            text-align: center;
        }
        .guide-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .guide-item {
            background: white;
            padding: 25px;
            border-radius: 8px;
            border-left: 5px solid #3498db;
        }
        .guide-item h4 {
            color: #3498db;
            font-size: 1.3em;
            margin-bottom: 10px;
        }
        .contact-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 50px;
            border-radius: 10px;
            margin-top: 60px;
            text-align: center;
        }
        .contact-section h2 {
            font-size: 2em;
            margin-bottom: 20px;
        }
        .contact-info {
            display: flex;
            justify-content: center;
            gap: 50px;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        .contact-item {
            text-align: center;
        }
        .contact-item .icon {
            font-size: 3em;
            margin-bottom: 10px;
        }
        .contact-item h4 {
            font-size: 1.2em;
            margin-bottom: 10px;
        }
        .btn-primary {
            display: inline-block;
            padding: 15px 40px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1.1em;
            margin-top: 30px;
            transition: transform 0.3s;
            font-weight: bold;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="page-hero">
        <h1>🛠️ 기술지원</h1>
        <p>배지 사용과 재배 관련 기술적인 도움이 필요하신가요?</p>
    </div>

    <div class="support-content">
        <h2 style="text-align: center; font-size: 2em; margin-bottom: 30px;">제공 서비스</h2>

        <div class="support-grid">
            <div class="support-card">
                <div class="icon">📞</div>
                <h3>전화 상담</h3>
                <p>배지 선택, 사용법, 재배 관련 궁금한 점을 전화로 바로 상담받으실 수 있습니다.</p>
                <p style="margin-top: 15px; font-weight: bold; color: #2ecc71;">평일 09:00 - 18:00</p>
                <p style="font-size: 1.3em; font-weight: bold; color: #3498db; margin-top: 10px;">010-7183-9876</p>
            </div>

            <div class="support-card">
                <div class="icon">📧</div>
                <h3>이메일 문의</h3>
                <p>상세한 재배 환경 정보와 함께 이메일로 문의하시면 전문가가 맞춤 솔루션을 제안해 드립니다.</p>
                <p style="margin-top: 15px; font-weight: bold; color: #2ecc71;">24시간 접수</p>
                <p style="font-size: 1.1em; font-weight: bold; color: #3498db; margin-top: 10px;">korea_tansaeng@naver.com</p>
            </div>

            <div class="support-card">
                <div class="icon">🏢</div>
                <h3>현장 방문 상담</h3>
                <p>농장이나 재배 시설을 직접 방문하여 현장 맞춤형 기술 지원을 제공합니다.</p>
                <p style="margin-top: 15px; font-weight: bold; color: #e74c3c;">사전 예약 필수</p>
                <p style="margin-top: 10px; color: #7f8c8d;">대량 구매 고객 대상</p>
            </div>

            <div class="support-card">
                <div class="icon">📹</div>
                <h3>화상 컨설팅</h3>
                <p>원격지에서도 화상 통화를 통해 실시간 기술 지원을 받으실 수 있습니다.</p>
                <p style="margin-top: 15px; font-weight: bold; color: #2ecc71;">예약제 운영</p>
                <p style="margin-top: 10px; color: #7f8c8d;">Zoom, Google Meet 지원</p>
            </div>

            <div class="support-card">
                <div class="icon">📚</div>
                <h3>교육 프로그램</h3>
                <p>배지 활용법과 수경재배 기술에 대한 정기 교육 프로그램을 운영합니다.</p>
                <p style="margin-top: 15px; font-weight: bold; color: #2ecc71;">월 1회 개최</p>
                <p style="margin-top: 10px; color: #7f8c8d;">온/오프라인 병행</p>
            </div>

            <div class="support-card">
                <div class="icon">🔬</div>
                <h3>수질/배지 분석</h3>
                <p>재배 환경의 수질과 배지 상태를 과학적으로 분석하여 최적화 방안을 제시합니다.</p>
                <p style="margin-top: 15px; font-weight: bold; color: #e74c3c;">유료 서비스</p>
                <p style="margin-top: 10px; color: #7f8c8d;">별도 문의 필요</p>
            </div>
        </div>

        <div class="technical-section">
            <h2>🎓 기술 가이드</h2>
            <p style="text-align: center; margin-bottom: 30px;">배지 사용과 관련된 주요 기술 정보를 제공합니다</p>

            <div class="guide-list">
                <div class="guide-item">
                    <h4>배지 선택 가이드</h4>
                    <p>작물 특성과 재배 환경에 맞는 최적의 배지 선택 방법</p>
                </div>

                <div class="guide-item">
                    <h4>EC/pH 관리</h4>
                    <p>양액의 전기전도도와 산도를 적정 수준으로 유지하는 방법</p>
                </div>

                <div class="guide-item">
                    <h4>관수 관리</h4>
                    <p>작물별, 생육 단계별 최적의 관수 주기와 양 설정</p>
                </div>

                <div class="guide-item">
                    <h4>배지 전처리</h4>
                    <p>새 배지 사용 전 수행해야 할 준비 작업</p>
                </div>

                <div class="guide-item">
                    <h4>배지 재생</h4>
                    <p>사용한 배지를 세척하고 재사용하는 방법</p>
                </div>

                <div class="guide-item">
                    <h4>병해충 예방</h4>
                    <p>배지를 통한 병해충 감염 예방 및 대처 방법</p>
                </div>

                <div class="guide-item">
                    <h4>염류 집적 관리</h4>
                    <p>배지 내 염류 축적 방지 및 제거 방법</p>
                </div>

                <div class="guide-item">
                    <h4>트러블슈팅</h4>
                    <p>재배 중 발생하는 일반적인 문제와 해결 방법</p>
                </div>

                <div class="guide-item">
                    <h4>계절별 관리</h4>
                    <p>여름/겨울 등 계절에 따른 배지 관리 포인트</p>
                </div>

                <div class="guide-item">
                    <h4>양액 조제</h4>
                    <p>작물별 최적의 양액 농도와 조제 방법</p>
                </div>

                <div class="guide-item">
                    <h4>배지 혼합 비율</h4>
                    <p>작물 특성에 맞는 배지 혼합 레시피</p>
                </div>

                <div class="guide-item">
                    <h4>수확 후 관리</h4>
                    <p>수확 완료 후 배지 처리 및 보관 방법</p>
                </div>
            </div>
        </div>

        <div class="technical-section" style="background: white;">
            <h2>📋 자주 발생하는 문제</h2>

            <div style="max-width: 800px; margin: 0 auto;">
                <div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <h4 style="color: #e74c3c; margin-bottom: 10px;">Q. 배지에서 이끼나 곰팡이가 발생해요</h4>
                    <p><strong>A.</strong> 과습과 통기 부족이 원인입니다. 관수 간격을 늘리고 배수를 개선하세요. 이미 발생한 경우 과산화수소(H₂O₂) 희석액으로 처리하고, 환기를 강화하세요.</p>
                </div>

                <div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <h4 style="color: #e74c3c; margin-bottom: 10px;">Q. 배지가 너무 빨리 마릅니다</h4>
                    <p><strong>A.</strong> 펄라이트 비율이 높거나 온도가 높은 경우 발생합니다. 코코피트 비율을 높이거나 관수 횟수를 늘리세요. 멀칭으로 증발을 줄이는 것도 효과적입니다.</p>
                </div>

                <div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <h4 style="color: #e74c3c; margin-bottom: 10px;">Q. 식물 생육이 정체되거나 잎이 노랗게 변해요</h4>
                    <p><strong>A.</strong> EC가 너무 높거나 낮을 수 있습니다. EC 측정 후 적정 범위(1.5-2.5 mS/cm)로 조정하세요. pH도 함께 확인하여 5.5-6.5로 맞추세요.</p>
                </div>

                <div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <h4 style="color: #e74c3c; margin-bottom: 10px;">Q. 뿌리가 갈색으로 변하고 무른 느낌이 납니다</h4>
                    <p><strong>A.</strong> 뿌리 부패(Root Rot)입니다. 즉시 과습 상태를 해소하고 배수를 개선하세요. 심한 경우 건강한 부분만 남기고 이식하며, 배지는 교체하는 것이 좋습니다.</p>
                </div>

                <div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <h4 style="color: #e74c3c; margin-bottom: 10px;">Q. 배지 표면에 하얀 결정이 생겼어요</h4>
                    <p><strong>A.</strong> 염류 집적 현상입니다. 양액 농도가 너무 높거나 배수가 불량할 때 발생합니다. 깨끗한 물로 충분히 플러싱(flushing)하여 염류를 제거하고, 양액 농도를 낮추세요.</p>
                </div>
            </div>
        </div>

        <div class="contact-section">
            <h2>💬 기술 지원 문의</h2>
            <p>전문가의 도움이 필요하신가요? 언제든지 연락주세요!</p>

            <div class="contact-info">
                <div class="contact-item">
                    <div class="icon">📞</div>
                    <h4>전화 문의</h4>
                    <p>010-7183-9876</p>
                    <p style="font-size: 0.9em; margin-top: 5px;">평일 09:00 - 18:00</p>
                </div>

                <div class="contact-item">
                    <div class="icon">📧</div>
                    <h4>이메일 문의</h4>
                    <p>korea_tansaeng@naver.com</p>
                    <p style="font-size: 0.9em; margin-top: 5px;">24시간 접수 가능</p>
                </div>

                <div class="contact-item">
                    <div class="icon">💬</div>
                    <h4>1:1 문의</h4>
                    <p>온라인 문의하기</p>
                    <a href="/pages/support/contact.php" class="btn-primary" style="display: inline-block; margin-top: 15px; padding: 10px 30px;">문의하기</a>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
