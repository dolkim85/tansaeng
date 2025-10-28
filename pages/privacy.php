<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>개인정보처리방침 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
    <style>
        .privacy-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .privacy-container h1 {
            color: #2c3e50;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #3498db;
        }
        .privacy-container h2 {
            color: #34495e;
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }
        .privacy-container h3 {
            color: #555;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
        }
        .privacy-container p {
            line-height: 1.8;
            color: #555;
            margin-bottom: 1rem;
        }
        .privacy-container ul {
            margin: 1rem 0;
            padding-left: 2rem;
        }
        .privacy-container li {
            line-height: 1.8;
            color: #555;
            margin-bottom: 0.5rem;
        }
        .info-box {
            background: #e7f3ff;
            padding: 1.5rem;
            border-radius: 6px;
            margin: 1.5rem 0;
            border-left: 4px solid #3498db;
        }
        .info-box strong {
            color: #2c3e50;
            display: block;
            margin-bottom: 0.5rem;
        }
        .required-items {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
            border-left: 4px solid #ffc107;
        }
        .optional-items {
            background: #d1ecf1;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
            border-left: 4px solid #17a2b8;
        }
        .back-link {
            display: inline-block;
            margin-top: 2rem;
            padding: 0.75rem 1.5rem;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.3s;
        }
        .back-link:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="privacy-container">
        <h1>개인정보처리방침</h1>

        <p>탄생(이하 '회사')은 「개인정보 보호법」 제30조에 따라 정보주체의 개인정보를 보호하고 이와 관련한 고충을 신속하고 원활하게 처리할 수 있도록 하기 위하여 다음과 같이 개인정보 처리방침을 수립·공개합니다.</p>

        <h2>1. 수집하는 개인정보 항목</h2>

        <div class="info-box">
            <strong>회사는 회원가입, 서비스 제공을 위해 다음과 같은 개인정보를 수집합니다.</strong>
        </div>

        <h3>가. 필수 항목</h3>
        <div class="required-items">
            <p><strong>필수 수집 항목 (서비스 이용을 위해 반드시 필요한 정보)</strong></p>
            <ul>
                <li><strong>이름</strong>: 회원 식별 및 서비스 제공</li>
                <li><strong>이메일주소</strong>: 회원 식별, 로그인, 공지사항 전달</li>
                <li><strong>휴대전화번호</strong>: 본인 확인, 주문/배송 안내, 고객 문의 응대</li>
                <li><strong>비밀번호</strong>: 계정 보안 (소셜 로그인 사용자는 제외)</li>
            </ul>
            <p>※ 필수 항목을 제공하지 않을 경우 회원가입 및 서비스 이용이 제한될 수 있습니다.</p>
        </div>

        <h3>나. 선택 항목</h3>
        <div class="optional-items">
            <p><strong>선택 수집 항목 (서비스 개선 및 맞춤형 콘텐츠 제공을 위한 정보)</strong></p>
            <ul>
                <li><strong>연령대</strong>: 연령대별 맞춤 서비스 제공, 통계 분석</li>
                <li><strong>성별</strong>: 성별 맞춤 상품 추천, 마케팅 자료 활용</li>
                <li><strong>주소</strong>: 상품 배송, 지역별 서비스 제공</li>
            </ul>
            <p>※ 선택 항목을 제공하지 않아도 서비스 이용에는 제한이 없으나, 맞춤형 서비스 제공이 어려울 수 있습니다.</p>
            <p>※ 선택 항목 수집에 동의하지 않을 경우, 해당 정보는 수집·저장되지 않습니다.</p>
        </div>

        <h3>다. 소셜 로그인 사용 시</h3>
        <ul>
            <li><strong>구글 로그인</strong>: 이메일, 이름, 프로필 사진</li>
            <li><strong>카카오 로그인</strong>: 이메일, 닉네임, 프로필 사진</li>
            <li><strong>네이버 로그인</strong>: 이메일, 이름, 프로필 사진</li>
        </ul>
        <p>※ 소셜 로그인 시에도 휴대전화번호는 필수로 추가 수집되며, 주소, 연령대, 성별은 선택 사항입니다.</p>

        <h2>2. 개인정보의 수집 및 이용 목적</h2>
        <p>회사는 수집한 개인정보를 다음의 목적을 위해 활용합니다.</p>
        <ul>
            <li>회원 가입 및 관리: 회원 식별, 본인확인, 부정 이용 방지</li>
            <li>서비스 제공: 상품 주문, 배송, 결제, 고객 문의 응대</li>
            <li>마케팅 및 광고: 신규 서비스 안내, 이벤트 정보 제공 (선택 항목 동의 시)</li>
            <li>서비스 개선: 통계 분석, 맞춤형 서비스 제공 (선택 항목 동의 시)</li>
        </ul>

        <h2>3. 개인정보의 보유 및 이용 기간</h2>
        <p>회사는 원칙적으로 개인정보 수집 및 이용목적이 달성된 후에는 해당 정보를 지체 없이 파기합니다. 단, 다음의 정보에 대해서는 아래의 이유로 명시한 기간 동안 보존합니다.</p>

        <h3>가. 회원 탈퇴 시</h3>
        <ul>
            <li>보존 항목: 이메일, 이름, 탈퇴 일시</li>
            <li>보존 근거: 부정 이용 방지, 서비스 이용 분쟁 해결</li>
            <li>보존 기간: 탈퇴 후 30일</li>
        </ul>

        <h3>나. 관련 법령에 의한 정보 보유</h3>
        <ul>
            <li>계약 또는 청약철회 등에 관한 기록: 5년 (전자상거래법)</li>
            <li>대금결제 및 재화 등의 공급에 관한 기록: 5년 (전자상거래법)</li>
            <li>소비자 불만 또는 분쟁처리에 관한 기록: 3년 (전자상거래법)</li>
            <li>접속 로그 기록: 3개월 (통신비밀보호법)</li>
        </ul>

        <h2>4. 개인정보의 제3자 제공</h2>
        <p>회사는 원칙적으로 이용자의 개인정보를 외부에 제공하지 않습니다. 다만, 아래의 경우에는 예외로 합니다.</p>
        <ul>
            <li>이용자가 사전에 동의한 경우</li>
            <li>법령의 규정에 의거하거나, 수사 목적으로 법령에 정해진 절차와 방법에 따라 수사기관의 요구가 있는 경우</li>
        </ul>

        <h2>5. 개인정보 처리의 위탁</h2>
        <p>회사는 서비스 제공을 위해 다음과 같이 개인정보 처리 업무를 외부에 위탁하고 있습니다.</p>
        <ul>
            <li>배송업체: 상품 배송 (수탁자: [배송업체명], 위탁 업무: 배송 업무)</li>
            <li>결제대행사: 결제 처리 (수탁자: [결제대행사명], 위탁 업무: 결제 대행)</li>
        </ul>

        <h2>6. 정보주체의 권리·의무 및 행사 방법</h2>
        <p>정보주체는 회사에 대해 언제든지 다음 각 호의 개인정보 보호 관련 권리를 행사할 수 있습니다.</p>
        <ul>
            <li>개인정보 열람 요구</li>
            <li>오류 등이 있을 경우 정정 요구</li>
            <li>삭제 요구</li>
            <li>처리정지 요구</li>
        </ul>
        <p>권리 행사는 회사에 대해 서면, 전화, 이메일 등을 통하여 하실 수 있으며, 회사는 이에 대해 지체 없이 조치하겠습니다.</p>

        <h2>7. 개인정보의 파기</h2>
        <p>회사는 개인정보 보유기간의 경과, 처리목적 달성 등 개인정보가 불필요하게 되었을 때에는 지체 없이 해당 개인정보를 파기합니다.</p>
        <ul>
            <li>전자적 파일: 복구 및 재생되지 않도록 안전하게 삭제</li>
            <li>종이 문서: 분쇄하거나 소각</li>
        </ul>

        <h2>8. 개인정보 보호책임자</h2>
        <div class="info-box">
            <strong>개인정보 보호책임자</strong>
            <p>성명: [책임자명]<br>
            직책: [직책]<br>
            연락처: [이메일], [전화번호]</p>
        </div>

        <h2>9. 개인정보 처리방침 변경</h2>
        <p>이 개인정보 처리방침은 2025년 1월 1일부터 적용됩니다.</p>
        <p>개인정보 처리방침이 변경될 경우, 웹사이트 공지사항을 통해 고지하겠습니다.</p>

        <div style="text-align: center;">
            <a href="javascript:history.back()" class="back-link">돌아가기</a>
        </div>
    </div>
</body>
</html>
