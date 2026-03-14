/**
 * 인증 관련 JavaScript
 * 로그인/회원가입 페이지에서 사용
 */

// 폼 유효성 검사
document.addEventListener('DOMContentLoaded', function() {
    // 로그인 폼 처리
    const loginForm = document.querySelector('.auth-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const email = document.getElementById('email');
            const password = document.getElementById('password');

            if (email && !email.value.trim()) {
                e.preventDefault();
                alert('이메일을 입력해주세요.');
                email.focus();
                return false;
            }

            if (password && !password.value) {
                e.preventDefault();
                alert('비밀번호를 입력해주세요.');
                password.focus();
                return false;
            }
        });
    }

    // 소셜 로그인 버튼 처리
    const socialButtons = document.querySelectorAll('.social-btn');
    socialButtons.forEach(button => {
        button.addEventListener('click', function() {
            const btnText = this.textContent.trim();
            console.log('소셜 로그인 버튼 클릭:', btnText);
        });
    });
});
