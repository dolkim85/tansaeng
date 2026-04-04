# 탄생(Tansaeng) 스마트팜 웹사이트 완전 재구축 요구사항

## 📋 프로젝트 개요
**회사명**: 탄생 (Tansaeng)  
**업종**: 스마트팜 배지 제조회사  
**목표**: 수경재배용 배지 판매 및 AI 식물분석 서비스를 제공하는 종합 웹플랫폼 구축

---

## 🎯 1단계: 프로젝트 초기 설정 및 환경 구성

### 1.1 기본 환경 설정
```bash
# 프로젝트 디렉토리 구조 생성
mkdir -p /var/www/html/{assets/{css,js,images},classes,config,includes,admin,api,pages,uploads}

# 권한 설정 (모든 파일에 대한 완전한 읽기/쓰기 권한)
sudo chown -R spinmoll:spinmoll /var/www/html
sudo chmod -R 755 /var/www/html
```

### 1.2 데이터베이스 설계
#### 필수 테이블 구조:
- **users**: 사용자 정보 (일반회원, 관리자)
- **products**: 제품 정보 (배지 종류, 가격, 재고)
- **categories**: 제품 카테고리
- **orders**: 주문 정보
- **order_items**: 주문 상세 항목
- **board_categories**: 게시판 카테고리
- **boards**: 게시글
- **board_comments**: 댓글
- **reviews**: 상품 리뷰
- **plant_analysis**: AI 식물분석 데이터
- **notifications**: 알림 시스템
- **settings**: 사이트 설정

---

## 🏠 2단계: 메인 웹사이트 구축

### 2.1 메인 페이지 (index.php)
#### 레이아웃 구성:
1. **헤더 섹션**
   - 로고: "탄생" 브랜딩
   - 네비게이션 메뉴:
     - 데스크톱: 홈, 기업소개, 배지설명, 스토어, 게시판, 고객지원, 식물분석
     - 모바일: 🏠 홈, 🏢 회사, 🌱 제품, 🛒 스토어, 📋 게시판, 💬 지원, 🔬 분석
   - 로그인/회원가입 버튼

2. **메인 콘텐츠**
   - 히어로 섹션: 스마트팜 배지 소개
   - 주요 제품 캐러셀
   - 회사 소개 요약
   - 최신 공지사항
   - AI 식물분석 서비스 소개

3. **푸터**
   - 회사 정보
   - 연락처
   - 소셜미디어 링크

### 2.2 반응형 CSS 설계
```css
/* 데스크톱 우선 디자인 (768px 이상) */
.nav-menu > li > a {
    /* 상위 메뉴 스타일 */
}

.dropdown-menu li a {
    /* 드롭다운 서브메뉴 스타일 */
}

/* 모바일 대응 (768px 이하) */
@media (max-width: 768px) {
    /* 모바일 전용 스타일 */
}
```

### 2.3 핵심 페이지 구성
#### A. 기업소개 (/company/)
- 회사개요 (about.php)
- 연혁 (history.php)
- 오시는길 (location.php)

#### B. 제품소개 (/products/)
- 코코피트 배지 (coco.php)
- 펄라이트 배지 (perlite.php)
- 혼합 배지 (mixed.php)
- 제품 비교표 (compare.php)

#### C. 온라인 스토어 (/store/)
- 제품 목록 (index.php)
- 제품 상세 (product.php?id=)
- 장바구니 (cart.php)
- 주문결제 (checkout.php)
- 주문완료 (complete.php)

#### D. 고객지원 (/support/)
- FAQ (faq.php)
- 1:1 문의 (inquiry.php)
- 기술지원 (technical.php)
- 다운로드 (downloads.php)

---

## 📋 3단계: 게시판 시스템 구축

### 3.1 게시판 카테고리
1. **공지사항** (notices)
2. **제품 Q&A** (product_qa)
3. **기술지원** (technical)
4. **자유게시판** (free)
5. **고객후기** (reviews)

### 3.2 게시판 CRUD 기능
#### 파일 구조:
```
/board/
├── index.php          # 게시판 목록
├── list.php           # 카테고리별 게시글 목록
├── view.php           # 게시글 상세보기
├── write.php          # 게시글 작성
├── edit.php           # 게시글 수정
├── delete.php         # 게시글 삭제
├── comment.php        # 댓글 처리
└── search.php         # 게시글 검색
```

#### 필수 기능:
- 글쓰기/수정/삭제 (로그인 필수)
- 댓글 시스템
- 파일 첨부 (이미지, 문서)
- 페이징 처리
- 검색 기능 (제목, 내용, 작성자)
- 조회수 카운팅
- 게시글 상태 관리 (공개/비공개/삭제)

---

## 🛠️ 4단계: 관리자 시스템 구축

### 4.1 관리자 로그인 (/admin/login.php)
- 보안 강화된 로그인 시스템
- 관리자 권한 검증
- 세션 관리

### 4.2 관리자 대시보드 (/admin/index.php)
#### 메인 대시보드:
- 사이트 통계 (방문자, 주문, 매출)
- 최근 주문 현황
- 신규 회원 현황
- 게시판 활동 현황
- 시스템 상태 모니터링

### 4.3 관리 메뉴 구성
#### A. 회원 관리 (/admin/users/)
```
├── index.php          # 회원 목록
├── view.php           # 회원 상세정보
├── edit.php           # 회원 정보 수정
├── delete.php         # 회원 삭제
└── export.php         # 회원 데이터 내보내기
```

#### B. 제품 관리 (/admin/products/)
```
├── index.php          # 제품 목록
├── add.php            # 제품 등록
├── edit.php           # 제품 수정
├── delete.php         # 제품 삭제
├── categories.php     # 카테고리 관리
└── inventory.php      # 재고 관리
```

#### C. 주문 관리 (/admin/orders/)
```
├── index.php          # 주문 목록
├── view.php           # 주문 상세
├── status.php         # 주문 상태 변경
├── shipping.php       # 배송 관리
└── statistics.php     # 주문 통계
```

#### D. 게시판 관리 (/admin/board/)
```
├── index.php          # 게시글 관리
├── categories.php     # 카테고리 관리
├── comments.php       # 댓글 관리
├── files.php          # 첨부파일 관리
└── reports.php        # 신고 관리
```

#### E. 식물분석 관리 (/admin/analysis/)
```
├── index.php          # 분석 요청 목록
├── view.php           # 분석 상세보기
├── process.php        # 분석 처리
└── results.php        # 분석 결과 관리
```

#### F. 시스템 설정 (/admin/settings/)
```
├── site.php           # 사이트 기본설정
├── seo.php            # SEO 설정
├── email.php          # 이메일 설정
├── payment.php        # 결제 설정
├── backup.php         # 백업 관리
└── logs.php           # 로그 관리
```

---

## 🔧 5단계: 핵심 기능 구현

### 5.1 사용자 인증 시스템
#### 파일: /classes/Auth.php
```php
class Auth {
    - 로그인/로그아웃
    - 회원가입
    - 비밀번호 재설정
    - 권한 검증
    - 세션 관리
    - 소셜 로그인 (구글, 네이버, 카카오)
}
```

### 5.2 장바구니 시스템
#### 파일: /classes/Cart.php
```php
class Cart {
    - 상품 추가/제거
    - 수량 변경
    - 장바구니 비우기
    - 총액 계산
    - 쿠폰 적용
}
```

### 5.3 주문 처리 시스템
#### 파일: /classes/Order.php
```php
class Order {
    - 주문 생성
    - 결제 처리
    - 주문 상태 관리
    - 배송 정보 관리
    - 주문 취소/환불
}
```

### 5.4 AI 식물분석 시스템
#### 파일: /classes/PlantAnalysis.php
```php
class PlantAnalysis {
    - 이미지 업로드
    - AI 분석 요청
    - 결과 저장
    - 분석 이력 관리
    - 추천 배지 제안
}
```

---

## 📱 6단계: API 시스템 구축

### 6.1 REST API 엔드포인트
#### 파일 구조: /api/
```
├── index.php          # API 라우팅
├── auth.php           # 인증 API
├── products.php       # 제품 API
├── cart.php           # 장바구니 API
├── orders.php         # 주문 API
├── board.php          # 게시판 API
├── analysis.php       # 식물분석 API
└── raspberry/         # 라즈베리파이 전용 API
    ├── sensor_data.php
    └── image_upload.php
```

### 6.2 API 응답 형식
```json
{
    "success": true,
    "data": {},
    "message": "성공",
    "timestamp": "2025-09-14T02:15:00Z"
}
```

---

## 🎨 7단계: 프론트엔드 최적화

### 7.1 CSS 프레임워크
- 반응형 디자인 (모바일 우선)
- CSS Grid/Flexbox 활용
- 다크모드 지원
- 애니메이션 효과

### 7.2 JavaScript 기능
```javascript
// 주요 기능
- 장바구니 AJAX 처리
- 실시간 검색
- 이미지 슬라이더
- 폼 유효성 검사
- 무한 스크롤
- 푸시 알림
```

---

## 🚀 8단계: 배포 및 최적화

### 8.1 성능 최적화
- 이미지 압축 및 WebP 변환
- CSS/JS 압축
- 캐싱 전략 수립
- CDN 연동

### 8.2 보안 강화
- SQL Injection 방지
- XSS 공격 방지
- CSRF 토큰 적용
- 파일 업로드 보안
- SSL 인증서 적용

### 8.3 SEO 최적화
- 메타태그 최적화
- 구조화된 데이터 마크업
- XML 사이트맵 생성
- robots.txt 설정

---

## 📊 9단계: 모니터링 및 유지보수

### 9.1 로그 시스템
- 접속 로그
- 에러 로그
- 사용자 행동 로그
- 시스템 성능 로그

### 9.2 백업 시스템
- 데이터베이스 자동 백업
- 파일 시스템 백업
- 복구 절차 수립

---

## ✅ 구현 순서 및 체크리스트

### Phase 1: 기본 구조 (1-2일)
- [ ] 프로젝트 구조 생성
- [ ] 데이터베이스 설계 및 구축
- [ ] 기본 클래스 구현
- [ ] 메인 페이지 레이아웃

### Phase 2: 사용자 영역 (3-5일)
- [ ] 인증 시스템 구현
- [ ] 제품 페이지 구현
- [ ] 장바구니/주문 시스템
- [ ] 게시판 CRUD 구현

### Phase 3: 관리자 영역 (2-3일)
- [ ] 관리자 대시보드
- [ ] 각종 관리 기능 구현
- [ ] 통계 및 리포트 기능

### Phase 4: 고급 기능 (2-3일)
- [ ] AI 식물분석 시스템
- [ ] API 시스템 구축
- [ ] 모바일 최적화

### Phase 5: 최적화 및 배포 (1-2일)
- [ ] 성능 최적화
- [ ] 보안 점검
- [ ] 최종 테스트 및 배포

---

## 🛡️ 중요 보안 사항

1. **모든 파일 권한**: `chmod 755`
2. **사용자/그룹**: `spinmoll:spinmoll`
3. **입력 데이터 검증**: 모든 사용자 입력 검증
4. **SQL 쿼리**: Prepared Statement 사용
5. **파일 업로드**: 확장자/크기 제한
6. **세션 보안**: 안전한 세션 관리

---

이 요구사항서를 기반으로 단계별로 구현하면 완전한 스마트팜 웹사이트를 구축할 수 있습니다. 각 단계는 독립적으로 개발 가능하며, 점진적으로 기능을 확장할 수 있습니다.