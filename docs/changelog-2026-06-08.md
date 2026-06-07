# 2026-06-08 작업 기록 — 네이버페이 결제 흐름 전면 개선

## 1. NPay 결제창 팝업 방식으로 변경

**커밋:** `a21cedf` (2026-06-08_0001)

**현상:** NPay 버튼 클릭 시 현재 페이지가 통째로 네이버페이로 전환됨.

**수정:**
- product_detail.php / product.php / cart.php:
  `window.location.href` → `window.open(url, 'NaverPay', 'width=500,height=700,...')`
  팝업 차단 시 안내 메시지 표시
- naverpay_callback.php:
  결제 성공/실패 시 `window.opener.location.href`로 메인창 이동 + `window.close()`
  팝업 아닐 때(opener 없음) fallback redirect 유지

---

## 2. 비로그인 장바구니 JS 오류 수정

**커밋:** `4d17806` (2026-06-08_0020)

**현상:** 비로그인 상태로 장바구니 접근 시 "Cannot set properties of null (setting 'disabled')" 오류.

**원인:** 비로그인 시 `checkoutBtn`이 DOM에 없는데 `getElementById('checkoutBtn').disabled` 접근.

**수정:** null 체크 추가.

---

## 3. NPay 결제완료 후 주문 생성 실패 (가장 큰 버그)

**커밋:** `1a15906` (2026-06-08_0040)

**현상:** 결제는 성공하나 주문완료 페이지로 안 넘어가고 장바구니로 에러 복귀.

**근본 원인:** 콜백의 orders INSERT가 실제 테이블에 없는 컬럼명 사용
→ `SQLSTATE[42S22]: Unknown column 'buyer_name'` 으로 INSERT 실패 → 롤백.

**수정:**
1. DB 스키마: `orders.payment_method` enum에 'naverpay','kakao' 추가, `payment_id` 컬럼 추가
2. 콜백 INSERT 컬럼명 실제 테이블에 맞춤:
   - orders: `buyer_*` → `customer_*`, `status` → `payment_status`/`order_status`, `shipping_address` 추가
   - order_items: `price` → `product_price`, `shipping_cost` 제거 → `total_price` 계산
3. `$_SESSION['last_order']` 설정 추가 (order_complete.php가 세션에서 주문정보를 읽음)

---

## 4. 주문완료/주문상세 페이지 정비

**커밋:** `7e2dd0e` (0100), `477fa4c` (0110)

### 4-1. 주문완료 페이지 헤더 깨짐
- order_complete.php 인라인 `.container` 스타일이 헤더 navbar(`class="navbar container"`)와 충돌
  → 메뉴 800px로 좁아지고 글자 세로 깨짐
- `.container` → `.order-complete-wrap` 고유 클래스로 분리

### 4-2. 주문상세 페이지 신규 생성
- profile.php 상세보기 → `order_detail.php?id=N` 링크가 404 (파일 없음)
- order_detail.php 신규 생성: 본인/관리자 권한 체크, 주문상품/배송/결제 정보 표시

### 4-3. 헤더 위 여백 제거
- 인라인 `body { padding-top: 80px }`가 데스크탑(non-fixed navbar)에서 빈 공간 생성
- 제거 (모바일은 main.css가 !important로 70px 처리)

---

## 5. 주문내역 버튼 경로 수정

**커밋:** `21c0f7d` (0120), `02f49a2` (0130)

**현상:** 주문상세 → "주문 내역" 클릭 시 장바구니로 이동.

**원인:**
- `order.php`는 주문내역이 아니라 체크아웃(주문/결제) 페이지
  → session order_items 비어있으면 장바구니로 redirect
- 실제 주문내역 리스트는 `profile.php`의 `#orders` 탭

**수정:**
1. 버튼 경로 `order.php` → `profile.php#orders` (order_detail 3곳, order_complete 1곳)
2. profile.php가 URL 해시를 읽도록 수정:
   - DOMContentLoaded에서 `window.location.hash` 읽어 해당 탭 자동 활성화
   - showTab() element null 허용 (해시 직접 진입 대응)

---

## 결제 전체 흐름 (최종)

```
스토어/장바구니 NPay 클릭
  → 팝업창(500×700)에서 네이버페이 결제
  → 결제 성공:
      콜백이 DB에 주문 생성 + 장바구니 비우기 + last_order 세션 저장
      → 메인창을 order_complete.php로 이동 + 팝업 닫힘
  → 주문완료 페이지 (주문번호/상품/금액 확인)
      → "주문 내역 확인" → profile.php#orders (주문내역 탭 자동 열림)
      → 상세보기 → order_detail.php?id=N (주문 상세)
```

---

## 버전 태그

| 태그 | 브랜치 | 내용 |
|---|---|---|
| `stable-2026-06-08` | main | 네이버페이 결제 흐름 전면 개선 (팝업/주문생성/주문내역) |
