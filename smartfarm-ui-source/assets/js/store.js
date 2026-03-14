// Store JavaScript Functions

// Search functionality
function searchProducts() {
    const searchTerm = document.getElementById('productSearch').value.trim();
    if (searchTerm) {
        // URL에 검색어 매개변수 추가하여 페이지 새로고침
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('search', searchTerm);
        // 검색 시 첫 페이지로 리셋
        currentUrl.searchParams.delete('page');
        window.location.href = currentUrl.toString();
    } else {
        // 검색어가 비어있으면 검색 매개변수 제거
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.delete('search');
        window.location.href = currentUrl.toString();
    }
}

function searchKeyword(keyword) {
    document.getElementById('productSearch').value = keyword;
    searchProducts();
}

function clearSearch() {
    document.getElementById('productSearch').value = '';
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.delete('search');
    currentUrl.searchParams.delete('page');
    window.location.href = currentUrl.toString();
}

// Product search on Enter key
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchProducts();
            }
        });
    }
});

// Sort products
function sortProducts() {
    const sortValue = document.getElementById('sortSelect').value;
    console.log('Sorting by:', sortValue);

    // 현재 카테고리 유지하면서 정렬 매개변수 추가
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('sort', sortValue);
    // 정렬 변경 시 첫 페이지로 리셋
    currentUrl.searchParams.delete('page');
    window.location.href = currentUrl.toString();
}

function getSortLabel(value) {
    const labels = {
        'newest': '최신 순',
        'popular': '인기 순',
        'price-low': '낮은 가격 순',
        'price-high': '높은 가격 순'
    };
    return labels[value] || value;
}

// View toggle (grid/list)
function toggleView(viewType) {
    const gridView = document.getElementById('gridView');
    const listView = document.getElementById('listView');
    const productsGrid = document.getElementById('productsGrid');
    
    if (viewType === 'grid') {
        gridView.classList.add('active');
        listView.classList.remove('active');
        productsGrid.classList.remove('list-view');
    } else {
        listView.classList.add('active');
        gridView.classList.remove('active');
        productsGrid.classList.add('list-view');
    }
}

// Category filter
function filterByCategory(categoryId) {
    console.log('Filtering by category:', categoryId);

    // URL에 카테고리 매개변수 추가하여 페이지 새로고침
    const currentUrl = new URL(window.location);
    if (categoryId && categoryId !== 'all') {
        currentUrl.searchParams.set('category', categoryId);
    } else {
        currentUrl.searchParams.delete('category');
    }
    // 카테고리 변경 시 첫 페이지로 리셋
    currentUrl.searchParams.delete('page');
    window.location.href = currentUrl.toString();
}

// Product navigation
function showProducts(type) {
    console.log('Showing products:', type);

    // URL에 제품 타입 매개변수 추가하여 페이지 새로고침
    const currentUrl = new URL(window.location);
    if (type && type !== 'new') {
        currentUrl.searchParams.set('type', type);
    } else {
        currentUrl.searchParams.delete('type');
    }
    // 제품 타입 변경 시 첫 페이지로 리셋
    currentUrl.searchParams.delete('page');
    window.location.href = currentUrl.toString();
}

// Product actions
function addToCart(productId) {
    console.log('Adding to cart:', productId);

    // 간단한 시각적 피드백
    const button = event.target;
    const originalText = button.textContent;

    button.textContent = '추가 중...';
    button.disabled = true;

    // AJAX로 장바구니에 추가
    fetch('../../api/cart.php?action=add', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: 1
        })
    })
    .then(response => {
        console.log('HTTP 상태:', response.status);
        console.log('응답 객체:', response);
        // HTTP 400도 JSON으로 파싱 (로그인 필요 등의 경우)
        return response.json();
    })
    .then(data => {
        console.log('파싱된 데이터:', data);
        console.log('장바구니 추가 응답:', data);
        console.log('Response details:', JSON.stringify(data, null, 2));

        if (data.success) {
            button.textContent = '완료!';
            console.log('Cart summary in response:', data.cart);

            // 전역 함수 확인 후 호출
            if (typeof window.updateCartCount === 'function') {
                window.updateCartCount();
            } else {
                console.log('updateCartCount 함수를 찾을 수 없습니다');
            }

            setTimeout(() => {
                button.textContent = originalText;
                button.disabled = false;
            }, 1000);
        } else {
            button.textContent = originalText;
            button.disabled = false;

            // 로그인이 필요한 경우 팝업 표시
            if (data.require_login) {
                if (confirm(data.message + '\n로그인 페이지로 이동하시겠습니까?')) {
                    // 현재 페이지를 기억하고 로그인 페이지로 이동
                    window.location.href = '/pages/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
                }
            } else {
                alert(data.message || '장바구니 추가에 실패했습니다');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        button.textContent = originalText;
        button.disabled = false;

        // 에러 메시지에서 로그인 필요 여부 확인
        const errorStr = error.toString();
        if (errorStr.includes('로그인') || errorStr.includes('require_login')) {
            if (confirm('로그인이 필요한 기능입니다.\n로그인 페이지로 이동하시겠습니까?')) {
                window.location.href = '/pages/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
            }
        } else {
            alert('오류가 발생했습니다');
        }
    });
}

function buyNow(productId) {
    console.log('Buying now:', productId);

    // 장바구니에 추가 후 장바구니 페이지로 이동
    fetch('../../api/cart.php?action=add', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: 1
        })
    })
    .then(response => {
        // HTTP 400도 JSON으로 파싱 (로그인 필요 등의 경우)
        return response.json();
    })
    .then(data => {
        console.log('바로구매 응답:', data);
        if (data.success) {
            // 장바구니 카운트 업데이트 후 이동
            if (typeof window.updateCartCount === 'function') {
                window.updateCartCount();
            }
            location.href = './cart.php';
        } else {
            // 로그인이 필요한 경우 팝업 표시
            if (data.require_login) {
                if (confirm(data.message + '\n로그인 페이지로 이동하시겠습니까?')) {
                    // 현재 페이지를 기억하고 로그인 페이지로 이동
                    window.location.href = '/pages/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
                }
            } else {
                alert(data.message || '구매 처리에 실패했습니다');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('오류가 발생했습니다');
    });
}


function toggleWishlist(productId) {
    const button = event.target;
    const isWishlisted = button.textContent === '♥';
    
    button.textContent = isWishlisted ? '♡' : '♥';
    button.style.color = isWishlisted ? 'inherit' : '#ff4444';
    
    console.log('Toggle wishlist:', productId, !isWishlisted);
    
    // 실제 구현시 AJAX로 위시리스트 상태 변경
}

// Utility functions
function updateCartCount() {
    // 서버에서 장바구니 개수 가져와서 업데이트
    fetch('../../api/cart.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const cartCount = document.querySelector('.cart-count');
            if (cartCount) {
                // summary 정보 활용하여 정확한 아이템 수 표시
                const itemCount = data.summary ? data.summary.item_count : data.count;
                cartCount.textContent = itemCount;

                // 간단한 애니메이션
                cartCount.style.transform = 'scale(1.3)';
                setTimeout(() => {
                    cartCount.style.transform = 'scale(1)';
                }, 200);
            }
        }
    })
    .catch(error => console.error('Cart count update error:', error));
}

// Initialize store functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Store initialized');
    
    // Set first nav button as active
    const firstNavBtn = document.querySelector('.nav-btn');
    if (firstNavBtn) {
        firstNavBtn.classList.add('active');
    }
    
    // 초기 장바구니 카운트 업데이트
    updateCartCount();
    
    // Add smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Add lazy loading for product images
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        imageObserver.unobserve(img);
                    }
                }
            });
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Escape key to close modals
    if (e.key === 'Escape') {
        closeQuickViewModal();
    }
    
    // Ctrl/Cmd + K for search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.getElementById('productSearch');
        if (searchInput) {
            searchInput.focus();
        }
    }
});