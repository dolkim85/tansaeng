// Store JavaScript Functions

// Search functionality
function searchProducts() {
    const searchTerm = document.getElementById('productSearch').value.trim();
    if (searchTerm) {
        alert(`"${searchTerm}" 검색 기능은 준비 중입니다.`);
        // 실제 구현시 AJAX로 검색 결과 가져오기
    }
}

function searchKeyword(keyword) {
    document.getElementById('productSearch').value = keyword;
    searchProducts();
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
    
    // 실제 구현시 AJAX로 정렬된 결과 가져오기
    alert(`${getSortLabel(sortValue)} 정렬 기능은 준비 중입니다.`);
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
    window.location.href = currentUrl.toString();
}

// Product navigation
function showProducts(type) {
    // Remove active class from all nav buttons
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Add active class to clicked button
    event.target.classList.add('active');
    
    console.log('Showing products:', type);
    
    const labels = {
        'featured': '추천 제품',
        'new': '신상품',
        'bestseller': '베스트셀러',
        'sale': '할인 상품'
    };
    
    // 실제 구현시 AJAX로 해당 타입의 제품 가져오기
    alert(`${labels[type]} 보기 기능은 준비 중입니다.`);
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
    fetch('/api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: 1
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.textContent = '완료!';
            updateCartCount();
            setTimeout(() => {
                button.textContent = originalText;
                button.disabled = false;
            }, 1000);
        } else {
            button.textContent = originalText;
            button.disabled = false;
            alert(data.message || '장바구니 추가에 실패했습니다');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        button.textContent = originalText;
        button.disabled = false;
        alert('오류가 발생했습니다');
    });
}

function buyNow(productId) {
    console.log('Buying now:', productId);
    
    // 장바구니에 추가 후 장바구니 페이지로 이동
    fetch('/api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: 1
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.href = '/pages/store/cart.php';
        } else {
            alert(data.message || '구매 처리에 실패했습니다');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('오류가 발생했습니다');
    });
}

function quickView(productId) {
    console.log('Quick view:', productId);
    
    // 모달 생성 및 표시
    showQuickViewModal(productId);
}

function showQuickViewModal(productId) {
    // 모달 HTML 생성
    const modalHTML = `
        <div id="quickViewModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>제품 미리보기</h3>
                    <span class="close-modal" onclick="closeQuickViewModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="quick-view-content">
                        <div class="quick-view-image">
                            <img src="/assets/images/products/placeholder.jpg" alt="제품 이미지">
                        </div>
                        <div class="quick-view-info">
                            <h3>제품명</h3>
                            <div class="quick-view-rating">
                                <span class="stars">⭐⭐⭐⭐⭐</span>
                                <span>(24 리뷰)</span>
                            </div>
                            <div class="quick-view-price">
                                <span class="price">25,000원</span>
                            </div>
                            <p class="quick-view-description">
                                이 제품의 상세한 설명이 들어갑니다. 
                                미리보기에서는 기본 정보만 제공됩니다.
                            </p>
                            <div class="quick-view-actions">
                                <button onclick="addToCart(${productId})" class="btn btn-primary">
                                    장바구니 담기
                                </button>
                                <button onclick="buyNow(${productId})" class="btn btn-outline">
                                    바로 구매
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <style>
            #quickViewModal .quick-view-content {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 2rem;
            }
            #quickViewModal .quick-view-image {
                width: 100%;
                height: 300px;
                background: #f8f9fa;
                border-radius: 8px;
                overflow: hidden;
            }
            #quickViewModal .quick-view-image img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            #quickViewModal .quick-view-rating {
                margin: 0.5rem 0;
                color: #666;
            }
            #quickViewModal .quick-view-price {
                font-size: 1.5rem;
                font-weight: bold;
                color: #4CAF50;
                margin: 1rem 0;
            }
            #quickViewModal .quick-view-description {
                color: #666;
                line-height: 1.6;
                margin-bottom: 2rem;
            }
            #quickViewModal .quick-view-actions {
                display: flex;
                gap: 1rem;
            }
            @media (max-width: 768px) {
                #quickViewModal .quick-view-content {
                    grid-template-columns: 1fr;
                    gap: 1rem;
                }
                #quickViewModal .quick-view-image {
                    height: 200px;
                }
            }
        </style>
    `;
    
    // 기존 모달 제거
    const existingModal = document.getElementById('quickViewModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // 새 모달 추가
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // 모달 표시
    document.getElementById('quickViewModal').style.display = 'block';
    
    // 모달 외부 클릭시 닫기
    document.getElementById('quickViewModal').onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            closeQuickViewModal();
        }
    };
}

function closeQuickViewModal() {
    const modal = document.getElementById('quickViewModal');
    if (modal) {
        modal.remove();
    }
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
    fetch('/api/cart.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const cartCount = document.querySelector('.cart-count');
            if (cartCount) {
                cartCount.textContent = data.summary.total_items;
                
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