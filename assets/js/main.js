// 탄생 - 메인 JavaScript 파일

console.log('=== MAIN.JS LOADED ===', new Date().toLocaleTimeString());

// Global Variables
let isLoading = false;
let cartCount = 0;

// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== DOM CONTENT LOADED ===', new Date().toLocaleTimeString());
    initializeApp();
});

// Initialize App
function initializeApp() {
    console.log('=== INITIALIZING APP ===');
    initMobileMenu();
    initMobileMenuSingleSelect();
    initScrollToTop();
    initLoadingOverlay();
    initCartCounter();
    initFormValidation();
    initTooltips();
    initSmoothScroll();
    console.log('=== APP INITIALIZATION COMPLETE ===');
}

// Mobile Menu Single Selection
function initMobileMenuSingleSelect() {
    if (window.innerWidth <= 768) {
        const navMenu = document.querySelector('.nav-menu');
        const menuItems = document.querySelectorAll('.nav-menu > li > a');
        
        if (navMenu && menuItems.length > 0) {
            // Clear any existing event listeners to prevent conflicts
            const newNavMenu = navMenu.cloneNode(true);
            navMenu.parentNode.replaceChild(newNavMenu, navMenu);
            
            // Get the new menu items after cloning
            const newMenuItems = document.querySelectorAll('.nav-menu > li > a');
            
            newMenuItems.forEach(item => {
                // Add touch event listeners
                item.addEventListener('touchstart', function(e) {
                    // Remove active states from all items
                    newMenuItems.forEach(otherItem => {
                        otherItem.classList.remove('touch-active', 'menu-clicked');
                    });
                    
                    // Add active state to current item
                    this.classList.add('touch-active');
                }, { passive: true });
                
                item.addEventListener('touchend', function(e) {
                    // Remove touch active but keep clicked state
                    this.classList.remove('touch-active');
                    this.classList.add('menu-clicked');
                }, { passive: true });
                
                item.addEventListener('click', function(e) {
                    // Remove active states from all items
                    newMenuItems.forEach(otherItem => {
                        otherItem.classList.remove('touch-active', 'menu-clicked');
                    });
                    
                    // Add clicked state to current item
                    this.classList.add('menu-clicked');
                });
            });
        }
    }
}

// Mobile Menu
function initMobileMenu() {
    console.log('Main.js initMobileMenu called');

    // 약간의 지연을 주어 DOM이 완전히 로드되도록 함
    setTimeout(function() {
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        const navigation = document.querySelector('.nav-menu');

        console.log('Main.js elements (delayed):', {
            mobileToggle,
            navigation,
            toggleExists: !!mobileToggle,
            navExists: !!navigation
        });

        if (mobileToggle && navigation) {
            console.log('Main.js toggle display:', window.getComputedStyle(mobileToggle).display);
            console.log('Main.js nav display:', window.getComputedStyle(navigation).display);

            // 기존 이벤트 리스너를 완전히 제거하고 새로 만들기
            const newToggle = mobileToggle.cloneNode(true);
            mobileToggle.parentNode.replaceChild(newToggle, mobileToggle);

            // 새로운 요소에 이벤트 리스너 추가
            const finalToggle = document.querySelector('.mobile-menu-toggle');

            // 강력한 이벤트 리스너 추가
            finalToggle.addEventListener('click', function(e) {
                console.log('=== HAMBURGER CLICKED ===');
                e.preventDefault();
                e.stopPropagation();

                const isActive = navigation.classList.contains('active');
                console.log('Current menu state:', isActive ? 'OPEN' : 'CLOSED');

                navigation.classList.toggle('active');
                finalToggle.classList.toggle('active');

                console.log('New menu state:', navigation.classList.contains('active') ? 'OPEN' : 'CLOSED');
                console.log('Navigation classes:', navigation.className);
                console.log('Toggle classes:', finalToggle.className);

                // 햄버거 아이콘 애니메이션
                const spans = finalToggle.querySelectorAll('span');
                spans.forEach((span, index) => {
                    if (finalToggle.classList.contains('active')) {
                        if (index === 0) span.style.transform = 'rotate(45deg) translate(5px, 5px)';
                        if (index === 1) span.style.opacity = '0';
                        if (index === 2) span.style.transform = 'rotate(-45deg) translate(7px, -6px)';
                    } else {
                        span.style.transform = '';
                        span.style.opacity = '';
                    }
                });
            });

            // 터치 이벤트도 추가 (모바일 대응)
            finalToggle.addEventListener('touchstart', function(e) {
                console.log('HAMBURGER TOUCHED');
            });

            // Close menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!navigation.contains(e.target) && !finalToggle.contains(e.target)) {
                    navigation.classList.remove('active');
                    finalToggle.classList.remove('active');

                    // 햄버거 아이콘 리셋
                    const spans = finalToggle.querySelectorAll('span');
                    spans.forEach(span => {
                        span.style.transform = '';
                        span.style.opacity = '';
                    });
                }
            });

            // Close menu when window resizes
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    navigation.classList.remove('active');
                    finalToggle.classList.remove('active');
                    const spans = finalToggle.querySelectorAll('span');
                    spans.forEach(span => {
                        span.style.transform = '';
                        span.style.opacity = '';
                    });
                }
            });

            console.log('Mobile menu initialization COMPLETED');
        } else {
            console.error('Mobile menu initialization FAILED - elements not found');
            console.log('Available elements with .mobile-menu-toggle class:', document.querySelectorAll('.mobile-menu-toggle'));
            console.log('Available elements with .nav-menu class:', document.querySelectorAll('.nav-menu'));
        }
    }, 200); // 200ms 지연
}

function toggleMobileMenu(e) {
    e.preventDefault();
    e.stopPropagation();

    const navigation = document.querySelector('.nav-menu');
    const toggle = document.querySelector('.mobile-menu-toggle');

    if (navigation && toggle) {
        navigation.classList.toggle('active');
        toggle.classList.toggle('active');
        
        // Animate hamburger icon
        const spans = toggle.querySelectorAll('span');
        spans.forEach((span, index) => {
            if (toggle.classList.contains('active')) {
                if (index === 0) span.style.transform = 'rotate(45deg) translate(5px, 5px)';
                if (index === 1) span.style.opacity = '0';
                if (index === 2) span.style.transform = 'rotate(-45deg) translate(7px, -6px)';
            } else {
                span.style.transform = '';
                span.style.opacity = '';
            }
        });
    }
}

// Scroll to Top
function initScrollToTop() {
    const scrollBtn = document.getElementById('scrollToTop');
    
    if (scrollBtn) {
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                scrollBtn.classList.add('show');
            } else {
                scrollBtn.classList.remove('show');
            }
        });
    }
}

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Loading Overlay
function initLoadingOverlay() {
    // Hide loading on page load
    window.addEventListener('load', function() {
        hideLoading();
    });
}

function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('show');
        isLoading = true;
    }
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.remove('show');
        isLoading = false;
    }
}

// Cart Counter
function initCartCounter() {
    updateCartCount();
}

function updateCartCount() {
    fetch('/api/store/cart_count.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                cartCount = data.count;
                const cartCountEl = document.getElementById('cartCount');
                if (cartCountEl) {
                    cartCountEl.textContent = cartCount;
                    cartCountEl.style.display = cartCount > 0 ? 'inline-block' : 'none';
                }
            }
        })
        .catch(error => {
            console.error('Cart count update failed:', error);
        });
}

// Form Validation
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate="true"]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(form)) {
                e.preventDefault();
            }
        });
        
        // Real-time validation
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(input);
            });
            
            input.addEventListener('input', function() {
                if (input.classList.contains('has-error')) {
                    validateField(input);
                }
            });
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
    
    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
        }
    });
    
    return isValid;
}

function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const required = field.hasAttribute('required');
    let isValid = true;
    let message = '';
    
    // Required validation
    if (required && !value) {
        isValid = false;
        message = '필수 입력 항목입니다.';
    }
    
    // Email validation
    else if (type === 'email' && value) {
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(value)) {
            isValid = false;
            message = '올바른 이메일 형식이 아닙니다.';
        }
    }
    
    // Password validation
    else if (type === 'password' && value) {
        if (value.length < 8) {
            isValid = false;
            message = '비밀번호는 8자 이상이어야 합니다.';
        }
    }
    
    // Phone validation
    else if (field.name === 'phone' && value) {
        const phonePattern = /^01[0-9]-?[0-9]{4}-?[0-9]{4}$/;
        if (!phonePattern.test(value.replace(/[^0-9]/g, ''))) {
            isValid = false;
            message = '올바른 휴대폰 번호 형식이 아닙니다.';
        }
    }
    
    // Update field appearance
    updateFieldValidation(field, isValid, message);
    
    return isValid;
}

function updateFieldValidation(field, isValid, message) {
    const formGroup = field.closest('.form-group');
    
    if (!formGroup) return;
    
    // Remove existing validation classes and messages
    formGroup.classList.remove('has-error', 'has-success');
    const existingMessage = formGroup.querySelector('.error-message, .success-message');
    if (existingMessage) {
        existingMessage.remove();
    }
    
    // Add validation feedback
    if (isValid) {
        formGroup.classList.add('has-success');
    } else {
        formGroup.classList.add('has-error');
        
        if (message) {
            const messageEl = document.createElement('div');
            messageEl.className = 'error-message';
            messageEl.textContent = message;
            formGroup.appendChild(messageEl);
        }
    }
}

// Tooltips
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const element = e.target;
    const text = element.getAttribute('data-tooltip');
    
    if (!text) return;
    
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    tooltip.style.position = 'absolute';
    tooltip.style.background = 'rgba(0,0,0,0.8)';
    tooltip.style.color = 'white';
    tooltip.style.padding = '8px 12px';
    tooltip.style.borderRadius = '6px';
    tooltip.style.fontSize = '0.875rem';
    tooltip.style.zIndex = '1000';
    tooltip.style.pointerEvents = 'none';
    
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 8) + 'px';
    tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
    
    element.tooltip = tooltip;
}

function hideTooltip(e) {
    const element = e.target;
    if (element.tooltip) {
        element.tooltip.remove();
        delete element.tooltip;
    }
}

// Smooth Scroll
function initSmoothScroll() {
    const smoothScrollLinks = document.querySelectorAll('a[href^="#"]');
    
    smoothScrollLinks.forEach(link => {
        link.addEventListener('click', function(e) {
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
}

// Utility Functions
function debounce(func, wait, immediate) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            timeout = null;
            if (!immediate) func(...args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func(...args);
    };
}

function throttle(func, limit) {
    let lastFunc;
    let lastRan;
    return function(...args) {
        if (!lastRan) {
            func(...args);
            lastRan = Date.now();
        } else {
            clearTimeout(lastFunc);
            lastFunc = setTimeout(() => {
                if ((Date.now() - lastRan) >= limit) {
                    func(...args);
                    lastRan = Date.now();
                }
            }, limit - (Date.now() - lastRan));
        }
    }
}

// AJAX Helper
function makeRequest(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    const finalOptions = { ...defaultOptions, ...options };
    
    if (finalOptions.data && finalOptions.method !== 'GET') {
        finalOptions.body = JSON.stringify(finalOptions.data);
    }
    
    return fetch(url, finalOptions)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error('Request failed:', error);
            throw error;
        });
}

// Alert/Toast Functions
function showAlert(message, type = 'info', duration = 5000) {
    const alertContainer = getOrCreateAlertContainer();
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    alertContainer.appendChild(alert);
    
    // Auto dismiss
    if (duration > 0) {
        setTimeout(() => {
            if (alert.parentElement) {
                alert.remove();
            }
        }, duration);
    }
    
    return alert;
}

function getOrCreateAlertContainer() {
    let container = document.getElementById('alertContainer');
    
    if (!container) {
        container = document.createElement('div');
        container.id = 'alertContainer';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        `;
        document.body.appendChild(container);
    }
    
    return container;
}

// Format Functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('ko-KR', {
        style: 'currency',
        currency: 'KRW'
    }).format(amount);
}

function formatDate(date) {
    return new Intl.DateTimeFormat('ko-KR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    }).format(new Date(date));
}

function formatDateTime(date) {
    return new Intl.DateTimeFormat('ko-KR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).format(new Date(date));
}

// Storage Functions
function setStorage(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
        return true;
    } catch (e) {
        console.error('Storage set failed:', e);
        return false;
    }
}

function getStorage(key, defaultValue = null) {
    try {
        const item = localStorage.getItem(key);
        return item ? JSON.parse(item) : defaultValue;
    } catch (e) {
        console.error('Storage get failed:', e);
        return defaultValue;
    }
}

function removeStorage(key) {
    try {
        localStorage.removeItem(key);
        return true;
    } catch (e) {
        console.error('Storage remove failed:', e);
        return false;
    }
}

// Export functions for use in other scripts
window.TangsaengApp = {
    showLoading,
    hideLoading,
    updateCartCount,
    showAlert,
    makeRequest,
    formatCurrency,
    formatDate,
    formatDateTime,
    setStorage,
    getStorage,
    removeStorage,
    validateForm,
    validateField
};