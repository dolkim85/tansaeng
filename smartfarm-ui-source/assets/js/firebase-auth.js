// Firebase Authentication 설정 및 구글 로그인 구현
import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
import { 
    getAuth, 
    signInWithPopup, 
    GoogleAuthProvider,
    signOut,
    onAuthStateChanged 
} from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js';

// Firebase 설정
const firebaseConfig = {
    apiKey: "AIzaSyAz2KBntJtG1DwD-Xju2ZsyjOeLLVkLw0g",
    authDomain: "tansaeng-users.firebaseapp.com",
    projectId: "tansaeng-users"
};

// Google OAuth Client ID 설정
const GOOGLE_CLIENT_ID = "983535094803-v0ljlvpqhl4f5oiagv1v5sgu7ab6jsdj.apps.googleusercontent.com";

// Firebase 초기화
const app = initializeApp(firebaseConfig);
const auth = getAuth(app);

class FirebaseAuth {
    constructor() {
        this.auth = auth;
        this.googleProvider = new GoogleAuthProvider();
        
        // Google Provider 추가 설정
        this.googleProvider.addScope('profile');
        this.googleProvider.addScope('email');
        this.googleProvider.setCustomParameters({
            'prompt': 'select_account'
        });
        
        this.currentUser = null;
        
        // 인증 상태 모니터링
        onAuthStateChanged(this.auth, (user) => {
            this.currentUser = user;
            this.handleAuthStateChange(user);
        });
    }
    
    // 구글 로그인
    async signInWithGoogle() {
        try {
            const result = await signInWithPopup(this.auth, this.googleProvider);
            const user = result.user;
            
            // 서버에 사용자 정보 전송
            await this.sendUserToServer(user);
            
            return {
                success: true,
                user: user,
                message: '구글 로그인 성공'
            };
        } catch (error) {
            console.error('Google sign-in error:', error);
            let errorMessage = '구글 로그인 실패';
            
            // 구체적인 에러 메시지 제공
            if (error.code === 'auth/configuration-not-found') {
                errorMessage = 'Firebase 설정을 찾을 수 없습니다. 관리자에게 문의하세요.';
            } else if (error.code === 'auth/popup-blocked') {
                errorMessage = '팝업이 차단되었습니다. 팝업 차단을 해제하고 다시 시도하세요.';
            } else if (error.code === 'auth/popup-closed-by-user') {
                errorMessage = '로그인이 취소되었습니다.';
            } else if (error.code === 'auth/unauthorized-domain') {
                errorMessage = '승인되지 않은 도메인입니다. 관리자에게 문의하세요.';
            } else if (error.message) {
                errorMessage = `구글 로그인 실패: ${error.message}`;
            }
            
            return {
                success: false,
                error: error.message,
                code: error.code,
                message: errorMessage
            };
        }
    }
    
    // 로그아웃
    async signOut() {
        try {
            await signOut(this.auth);
            
            // 서버에 로그아웃 알림
            await fetch('/pages/auth/firebase_logout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'logout' })
            });
            
            return {
                success: true,
                message: '로그아웃 성공'
            };
        } catch (error) {
            console.error('Sign-out error:', error);
            return {
                success: false,
                error: error.message,
                message: '로그아웃 실패'
            };
        }
    }
    
    // 서버에 사용자 정보 전송
    async sendUserToServer(user) {
        try {
            const idToken = await user.getIdToken();
            
            const userData = {
                uid: user.uid,
                email: user.email,
                displayName: user.displayName,
                photoURL: user.photoURL,
                idToken: idToken
            };
            
            // 테스트 모드에서는 테스트 콜백 사용
            const callbackUrl = window.location.hostname === 'localhost' ? 
                '/test_firebase_callback.php' : '/pages/auth/firebase_callback.php';
            
            const response = await fetch(callbackUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(userData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                // 로그인 성공 후 리디렉션
                const redirectUrl = localStorage.getItem('redirect_after_login') || '/';
                localStorage.removeItem('redirect_after_login');
                window.location.href = redirectUrl;
            } else {
                throw new Error(result.message || '서버 인증 실패');
            }
        } catch (error) {
            console.error('Server authentication error:', error);
            throw error;
        }
    }
    
    // 인증 상태 변경 처리
    handleAuthStateChange(user) {
        const loginButtons = document.querySelectorAll('.firebase-google-login');
        const logoutButtons = document.querySelectorAll('.firebase-logout');
        const userInfo = document.querySelectorAll('.firebase-user-info');
        
        if (user) {
            // 로그인 상태
            loginButtons.forEach(btn => btn.style.display = 'none');
            logoutButtons.forEach(btn => btn.style.display = 'block');
            userInfo.forEach(info => {
                info.style.display = 'block';
                info.innerHTML = `
                    <div class="user-profile">
                        <img src="${user.photoURL || '/assets/images/default-avatar.png'}" alt="프로필" class="user-avatar">
                        <div class="user-details">
                            <span class="user-name">${user.displayName || '사용자'}</span>
                            <span class="user-email">${user.email}</span>
                        </div>
                    </div>
                `;
            });
        } else {
            // 로그아웃 상태
            loginButtons.forEach(btn => btn.style.display = 'block');
            logoutButtons.forEach(btn => btn.style.display = 'none');
            userInfo.forEach(info => info.style.display = 'none');
        }
    }
    
    // 현재 사용자 확인
    getCurrentUser() {
        return this.currentUser;
    }
    
    // 인증 토큰 가져오기
    async getIdToken() {
        if (this.currentUser) {
            return await this.currentUser.getIdToken();
        }
        return null;
    }
}

// 전역 FirebaseAuth 인스턴스
window.firebaseAuth = new FirebaseAuth();

// DOM이 로드된 후 이벤트 리스너 등록
document.addEventListener('DOMContentLoaded', function() {
    // 구글 로그인 버튼
    document.querySelectorAll('.firebase-google-login').forEach(button => {
        button.addEventListener('click', async function(e) {
            e.preventDefault();
            
            // 리디렉션 URL 저장
            const redirectUrl = this.dataset.redirect || window.location.search.get('redirect') || '/';
            localStorage.setItem('redirect_after_login', redirectUrl);
            
            button.disabled = true;
            button.innerHTML = '<span class="loading-spinner"></span> 로그인 중...';
            
            const result = await window.firebaseAuth.signInWithGoogle();
            
            if (!result.success) {
                showAlert(result.message, 'success');
                button.disabled = false;
                button.innerHTML = '<span class="social-icon">G</span> Google로 로그인';
            }
        });
    });
    
    // 로그아웃 버튼
    document.querySelectorAll('.firebase-logout').forEach(button => {
        button.addEventListener('click', async function(e) {
            e.preventDefault();
            
            button.disabled = true;
            button.innerHTML = '로그아웃 중...';
            
            const result = await window.firebaseAuth.signOut();
            
            if (result.success) {
                window.location.href = '/pages/auth/login.php';
            } else {
                showAlert(result.message, 'success');
                button.disabled = false;
                button.innerHTML = '로그아웃';
            }
        });
    });
});