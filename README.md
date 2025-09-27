# 탄생(Tangsaeng) - 스마트팜 웹사이트

스마트팜 배지 제조 전문회사 탄생의 공식 웹사이트입니다.

## 주요 기능

- **제품 쇼케이스**: 스마트팜 배지 제품 소개
- **AI 식물분석**: 라즈베리파이 카메라를 활용한 식물 건강상태 분석
- **온라인 스토어**: 제품 주문 및 구매 시스템
- **사용자 관리**: 회원가입, 로그인, 소셜 로그인 지원
- **관리자 페이지**: 제품, 사용자, 콘텐츠 관리
- **게시판 시스템**: 공지사항 및 커뮤니티

## 기술 스택

- **백엔드**: PHP 8+
- **데이터베이스**: MySQL
- **프론트엔드**: HTML5, CSS3, JavaScript
- **인증**: Firebase Auth (Google, Kakao, Naver)
- **배포**: Vercel
- **도메인**: www.tansaeng.com

## 🚀 배포 방법

### 1. 저장소 클론
```bash
git clone <repository-url>
cd tangsaeng-website
```

### 2. 환경설정
```bash
# 데이터베이스 설정 파일 생성
cp config/database.php.example config/database.php
# config/database.php 파일을 편집하여 데이터베이스 정보 입력
```

### 3. 자동 배포
```bash
./deploy.sh
```

## 📁 주요 구조
```
/var/www/html/
├── admin/              # 관리자 페이지
├── api/                # REST API
├── assets/             # 정적 파일 (CSS, JS, 이미지)
├── classes/            # PHP 클래스
├── config/             # 환경설정
├── includes/           # 공통 컴포넌트
├── pages/              # 웹 페이지
├── uploads/            # 업로드 파일
└── deploy.sh           # 배포 스크립트
```

## 🔐 보안
- 민감한 설정 파일들은 `.gitignore`에 등록
- 업로드 디렉토리는 Git에서 제외
- 데이터베이스 정보는 별도 관리

## 로컬 개발

```bash
# 웹서버 실행
php -S localhost:8080

# 데이터베이스 설정
# localhost MySQL 서버에 tangsaeng_db 데이터베이스 생성 필요
```

## 라이선스

© 2024 탄생(Tangsaeng). All rights reserved.