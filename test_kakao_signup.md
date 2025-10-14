# 카카오 회원가입 테스트 가이드

## ✅ 사전 준비 체크리스트

### 1. 데이터베이스 준비
- [x] users 테이블에 oauth_provider 컬럼 추가됨
- [x] users 테이블에 oauth_id 컬럼 추가됨

### 2. 코드 설정
- [x] REST API 키: fcb1f0af57098d3ce5e3d97e355c159c
- [x] Redirect URI: http://127.0.0.1:8000/api/auth/kakao/callback.php
- [x] 콜백 API: /api/auth/kakao/callback.php 생성됨
- [x] Auth::findOrCreateOAuthUser() 메서드 추가됨

### 3. Kakao Developers 설정 (직접 확인 필요)
- [ ] 카카오 로그인 활성화
- [ ] Redirect URI 등록: http://127.0.0.1:8000/api/auth/kakao/callback.php

---

## 🧪 테스트 시나리오

### Step 1: 회원가입 페이지 접속
```
URL: http://127.0.0.1:8000/pages/auth/register.php
```

**확인 사항:**
- [ ] 페이지가 정상적으로 로드됨
- [ ] "카카오톡으로 시작하기" 버튼이 노란색으로 표시됨
- [ ] 약관 동의 체크박스가 보임

### Step 2: 약관 동의 체크
```
동작: 이용약관 및 개인정보처리방침 체크박스 클릭
```

**확인 사항:**
- [ ] 체크박스가 체크됨

### Step 3: 카카오톡 버튼 클릭
```
동작: "카카오톡으로 시작하기" 버튼 클릭
```

**예상 결과:**
- [ ] 카카오 로그인 페이지로 이동됨
- [ ] URL이 https://kauth.kakao.com/oauth/authorize... 로 시작함

**만약 이동하지 않으면:**
- F12 → Console 탭에서 에러 확인
- "카카오 버튼 클릭됨" 메시지가 표시되는지 확인

### Step 4: 카카오 로그인
```
동작: 카카오 계정으로 로그인
```

**예상 결과:**
- [ ] 카카오 로그인 화면 표시
- [ ] 이메일/비밀번호 입력 후 로그인

**가능한 에러:**
- ❌ "Redirect URI 불일치" 에러
  → Kakao Developers에서 URI 등록 필요
- ❌ "등록되지 않은 앱" 에러
  → 카카오 로그인 활성화 필요

### Step 5: 동의 및 계속하기
```
동작: 카카오에서 정보 제공 동의 화면
```

**예상 결과:**
- [ ] 닉네임, 이메일 제공 동의 요청
- [ ] "동의하고 계속하기" 버튼 클릭

### Step 6: 콜백 처리 및 회원가입
```
자동: http://127.0.0.1:8000/api/auth/kakao/callback.php?code=...
```

**예상 결과:**
- [ ] 자동으로 회원가입 처리됨
- [ ] 메인 페이지로 리다이렉트됨
- [ ] 헤더에 "홍길동님" (카카오 닉네임) 표시됨

**가능한 에러:**
- ❌ 빈 화면 또는 PHP 에러
  → /var/log/apache2/error.log 확인
- ❌ "사용자 생성 실패"
  → 데이터베이스 연결 확인

### Step 7: 데이터베이스 확인
```bash
mysql -u root -p'qjawns3445' tansaeng_db -e "SELECT id, name, email, oauth_provider, oauth_id FROM users WHERE oauth_provider='kakao';"
```

**예상 결과:**
```
id  name      email                    oauth_provider  oauth_id
2   홍길동    hong@kakao.com           kakao          1234567890
```

---

## 🐛 문제 발생 시 디버깅

### 1. 카카오 페이지로 이동하지 않음
```bash
# 콘솔에서 확인
- "카카오 버튼 클릭됨" 메시지가 없으면 → JavaScript 에러
- "약관 동의 필요" 알림이 뜨면 → 체크박스 선택
```

### 2. Redirect URI 에러
```
해결: Kakao Developers에서 다음 URI 등록
http://127.0.0.1:8000/api/auth/kakao/callback.php
```

### 3. 콜백 후 에러 발생
```bash
# 에러 로그 확인
sudo tail -50 /var/log/apache2/error.log

# 또는
tail -50 /var/log/httpd/error_log
```

### 4. 회원가입은 됐는지 확인
```bash
# 데이터베이스 직접 확인
mysql -u root -p'qjawns3445' tansaeng_db -e "SELECT * FROM users ORDER BY id DESC LIMIT 1;"
```

---

## 📊 테스트 결과 기록

### 테스트 일시:
```
날짜: ____________________
시간: ____________________
```

### 결과:
- [ ] ✅ 성공: 카카오 회원가입 완료
- [ ] ❌ 실패: __________________ 단계에서 에러

### 에러 메시지:
```
여기에 에러 메시지 기록
```

### 생성된 사용자 정보:
```
ID: ___
이름: ___________
이메일: ___________________
oauth_provider: kakao
oauth_id: ___________
```

---

## ✅ 테스트 완료 확인

모든 항목이 체크되면 카카오 회원가입 기능이 정상 작동하는 것입니다!

- [ ] 회원가입 페이지에서 카카오 버튼 클릭 가능
- [ ] 카카오 로그인 페이지로 이동됨
- [ ] 카카오 계정으로 로그인 완료
- [ ] 자동으로 회원가입 처리됨
- [ ] 데이터베이스에 카카오 사용자 저장됨
- [ ] 메인 페이지에서 로그인 상태 확인됨
