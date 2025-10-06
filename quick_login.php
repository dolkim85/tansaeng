<?php
session_start();

// 강제 로그인 설정
$_SESSION['user_id'] = 1;
$_SESSION['user_email'] = 'korea_tansaeng@naver.com';
$_SESSION['email'] = 'korea_tansaeng@naver.com';
$_SESSION['user_name'] = '탄생 관리자';
$_SESSION['name'] = '탄생 관리자';
$_SESSION['role'] = 'admin';
$_SESSION['user_level'] = 9;
$_SESSION['plant_analysis_permission'] = 1;
$_SESSION['login_time'] = time();
$_SESSION['last_activity'] = time();

echo "<!DOCTYPE html>";
echo "<html lang='ko'>";
echo "<head><meta charset='UTF-8'><title>빠른 로그인</title></head>";
echo "<body style='font-family: Arial; margin: 20px;'>";

echo "<h1>✅ 빠른 로그인 완료</h1>";

echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>로그인 정보:</h3>";
echo "<p>사용자 ID: {$_SESSION['user_id']}</p>";
echo "<p>이름: {$_SESSION['name']}</p>";
echo "<p>이메일: {$_SESSION['email']}</p>";
echo "<p>권한: {$_SESSION['role']}</p>";
echo "<p>세션 ID: " . session_id() . "</p>";
echo "</div>";

echo "<div style='margin: 20px 0;'>";
echo "<h3>🔗 테스트 링크:</h3>";
echo "<p><a href='/pages/store/' style='display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>스토어 메인</a></p>";
echo "<p><a href='/pages/store/cart.php' style='display: inline-block; background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>장바구니 페이지</a></p>";
echo "<p><a href='/debug_session.php' style='display: inline-block; background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>세션 상태 확인</a></p>";
echo "</div>";

echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>💡 사용 방법:</h3>";
echo "<ol>";
echo "<li>이 페이지에서 로그인을 완료했습니다</li>";
echo "<li>위의 '스토어 메인' 링크를 클릭하세요</li>";
echo "<li>상품의 🛒 버튼을 클릭해보세요</li>";
echo "<li>이제 로그인 오류 없이 장바구니에 추가될 것입니다</li>";
echo "</ol>";
echo "</div>";

echo "</body>";
echo "</html>";
?>