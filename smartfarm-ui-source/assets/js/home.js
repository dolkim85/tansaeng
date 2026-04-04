// 홈페이지 전용 JavaScript

// Hero Slider Variables
let currentSlide = 0;
let slideInterval;
const slideDelay = 5000; // 5 seconds

// Initialize Home Page
document.addEventListener('DOMContentLoaded', function() {
    initHeroSlider();
    initScrollAnimations();
    initVideoPlayers();
    initCounterAnimations();
    
    console.log('홈페이지 JavaScript 초기화 완료');
});

// Hero Slider
function initHeroSlider() {
    const slides = document.querySelectorAll('.hero-slide');
    const indicators = document.querySelectorAll('.indicator');
    
    if (slides.length > 1) {
        startSlideShow();
        
        // Pause on hover
        const heroSection = document.querySelector('.hero-section');
        if (heroSection) {
            heroSection.addEventListener('mouseenter', stopSlideShow);
            heroSection.addEventListener('mouseleave', startSlideShow);
        }
        
        // Touch/Swipe support for mobile
        initSliderTouch();
    }
}

function changeSlide(direction) {
    const slides = document.querySelectorAll('.hero-slide');
    const indicators = document.querySelectorAll('.indicator');
    
    if (slides.length === 0) return;
    
    // Remove active classes
    slides[currentSlide].classList.remove('active');
    indicators[currentSlide].classList.remove('active');
    
    // Calculate next slide
    currentSlide += direction;
    
    if (currentSlide >= slides.length) {
        currentSlide = 0;
    } else if (currentSlide < 0) {
        currentSlide = slides.length - 1;
    }
    
    // Add active classes
    slides[currentSlide].classList.add('active');
    indicators[currentSlide].classList.add('active');
    
    // Restart auto-play
    stopSlideShow();
    startSlideShow();
}

function currentSlide(n) {
    const slides = document.querySelectorAll('.hero-slide');
    const indicators = document.querySelectorAll('.indicator');
    
    if (slides.length === 0 || n < 1 || n > slides.length) return;
    
    // Remove active classes
    slides[currentSlide].classList.remove('active');
    indicators[currentSlide].classList.remove('active');
    
    // Set new slide
    currentSlide = n - 1;
    
    // Add active classes
    slides[currentSlide].classList.add('active');
    indicators[currentSlide].classList.add('active');
    
    // Restart auto-play
    stopSlideShow();
    startSlideShow();
}

function startSlideShow() {
    stopSlideShow(); // Clear any existing interval
    slideInterval = setInterval(() => {
        changeSlide(1);
    }, slideDelay);
}

function stopSlideShow() {
    if (slideInterval) {
        clearInterval(slideInterval);
        slideInterval = null;
    }
}

// Touch/Swipe Support
function initSliderTouch() {
    const heroSlider = document.getElementById('heroSlider');
    if (!heroSlider) return;
    
    let startX = 0;
    let startY = 0;
    let endX = 0;
    let endY = 0;
    
    heroSlider.addEventListener('touchstart', function(e) {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
    });
    
    heroSlider.addEventListener('touchmove', function(e) {
        e.preventDefault(); // Prevent scrolling
    });
    
    heroSlider.addEventListener('touchend', function(e) {
        endX = e.changedTouches[0].clientX;
        endY = e.changedTouches[0].clientY;
        
        const deltaX = endX - startX;
        const deltaY = endY - startY;
        
        // Check if horizontal swipe is greater than vertical
        if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 50) {
            if (deltaX > 0) {
                changeSlide(-1); // Swipe right, go to previous
            } else {
                changeSlide(1); // Swipe left, go to next
            }
        }
    });
}

// Scroll Animations
function initScrollAnimations() {
    const animatedElements = document.querySelectorAll('.intro-item, .product-card, .news-item');
    
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    animatedElements.forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(30px)';
        element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(element);
    });
}

// Video Players
function initVideoPlayers() {
    // 모든 비디오 요소에 대해 로드 실패 처리
    const videos = document.querySelectorAll('video');
    
    videos.forEach(video => {
        // 비디오 로드 시도
        video.addEventListener('loadeddata', function() {
            console.log('비디오 로드 성공:', this.src);
        });
        
        // 비디오 로드 실패 시 처리
        video.addEventListener('error', function() {
            console.log('비디오 로드 실패:', this.src);
            handleVideoError(this);
        });
        
        // 네트워크 상태 확인 후 재시도
        video.addEventListener('stalled', function() {
            console.log('비디오 로딩 지연:', this.src);
            setTimeout(() => {
                this.load(); // 다시 로드 시도
            }, 1000);
        });
        
        // 자동재생이 차단된 경우 처리
        video.play().catch(error => {
            console.log('자동재생 차단됨:', error);
            // 사용자 인터랙션 후 재생되도록 클릭 이벤트 추가
            video.addEventListener('click', function() {
                this.play();
            }, { once: true });
        });
    });
    
    console.log('Video players initialized');
}

function handleVideoError(videoElement) {
    const container = videoElement.parentElement;
    
    // 비디오를 대체할 이미지나 메시지 생성
    const fallback = document.createElement('div');
    fallback.style.cssText = `
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #E8F5E8 0%, #C8E6C9 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        color: #2E7D32;
        font-size: 1.2rem;
        text-align: center;
        cursor: pointer;
    `;
    
    fallback.innerHTML = `
        <div>
            <div style="font-size: 3rem; margin-bottom: 1rem;">🎬</div>
            <div>영상을 불러오는 중입니다...</div>
            <div style="font-size: 0.9rem; margin-top: 0.5rem; opacity: 0.7;">클릭해서 다시 시도</div>
        </div>
    `;
    
    // 클릭 시 비디오 다시 로드 시도
    fallback.addEventListener('click', function() {
        container.removeChild(fallback);
        container.appendChild(videoElement);
        videoElement.load();
    });
    
    // 비디오를 fallback으로 교체
    container.removeChild(videoElement);
    container.appendChild(fallback);
}

function openAnalysisVideo() {
    // Create modal for analysis video
    const modal = createVideoModal();
    
    modal.innerHTML = `
        <div class="video-modal-content">
            <div class="video-modal-header">
                <h3>AI 식물분석 시스템 소개</h3>
                <button class="video-modal-close" onclick="closeVideoModal()">&times;</button>
            </div>
            <div class="video-modal-body">
                <div class="video-placeholder">
                    <p>식물분석 시스템 데모 영상</p>
                    <p>실제 구현 시 실제 비디오 파일로 교체하세요</p>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.style.display = 'flex';
    
    // Close on backdrop click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeVideoModal();
        }
    });
}

function playIntroVideo() {
    const modal = createVideoModal();
    
    modal.innerHTML = `
        <div class="video-modal-content">
            <div class="video-modal-header">
                <h3>탄생 회사 소개 영상</h3>
                <button class="video-modal-close" onclick="closeVideoModal()">&times;</button>
            </div>
            <div class="video-modal-body">
                <div class="video-placeholder">
                    <p>회사 소개 영상</p>
                    <p>실제 구현 시 실제 비디오 파일로 교체하세요</p>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.style.display = 'flex';
    
    // Close on backdrop click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeVideoModal();
        }
    });
}

function createVideoModal() {
    // Remove existing modal
    const existingModal = document.getElementById('videoModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    const modal = document.createElement('div');
    modal.id = 'videoModal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.9);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.3s ease;
    `;
    
    setTimeout(() => {
        modal.style.opacity = '1';
    }, 10);
    
    return modal;
}

function closeVideoModal() {
    const modal = document.getElementById('videoModal');
    if (modal) {
        modal.style.opacity = '0';
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

// Counter Animations
function initCounterAnimations() {
    const counters = document.querySelectorAll('.counter');
    
    if (counters.length === 0) return;
    
    const observerOptions = {
        threshold: 0.5
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    counters.forEach(counter => {
        observer.observe(counter);
    });
}

function animateCounter(element) {
    const target = parseInt(element.getAttribute('data-target'));
    const duration = 2000; // 2 seconds
    const increment = target / (duration / 16); // 60 FPS
    let current = 0;
    
    const updateCounter = () => {
        current += increment;
        
        if (current < target) {
            element.textContent = Math.floor(current).toLocaleString();
            requestAnimationFrame(updateCounter);
        } else {
            element.textContent = target.toLocaleString();
        }
    };
    
    updateCounter();
}

// Lazy Loading for Images
function initLazyLoading() {
    const lazyImages = document.querySelectorAll('img[loading="lazy"]');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src || img.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        lazyImages.forEach(img => {
            imageObserver.observe(img);
        });
    }
}

// Parallax Effect
function initParallax() {
    const parallaxElements = document.querySelectorAll('.parallax');
    
    if (parallaxElements.length === 0) return;
    
    const handleScroll = throttle(() => {
        const scrollTop = window.pageYOffset;
        
        parallaxElements.forEach(element => {
            const speed = element.dataset.speed || 0.5;
            const yPos = -(scrollTop * speed);
            element.style.transform = `translateY(${yPos}px)`;
        });
    }, 10);
    
    window.addEventListener('scroll', handleScroll);
}

// Smooth scrolling for anchor links
function initAnchorSmoothing() {
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
}

// Utility function for throttling
function throttle(func, limit) {
    let lastFunc;
    let lastRan;
    return function(...args) {
        if (!lastRan) {
            func.apply(this, args);
            lastRan = Date.now();
        } else {
            clearTimeout(lastFunc);
            lastFunc = setTimeout(() => {
                if ((Date.now() - lastRan) >= limit) {
                    func.apply(this, args);
                    lastRan = Date.now();
                }
            }, limit - (Date.now() - lastRan));
        }
    }
}

// Modal CSS (added programmatically)
const modalStyles = `
    .video-modal-content {
        background: white;
        border-radius: 12px;
        max-width: 90vw;
        max-height: 90vh;
        overflow: hidden;
        position: relative;
        animation: modalSlideIn 0.3s ease-out;
    }
    
    .video-modal-header {
        padding: 1rem 1.5rem;
        background: #4CAF50;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .video-modal-header h3 {
        margin: 0;
        font-size: 1.25rem;
    }
    
    .video-modal-close {
        background: none;
        border: none;
        color: white;
        font-size: 2rem;
        cursor: pointer;
        padding: 0;
        line-height: 1;
    }
    
    .video-modal-close:hover {
        opacity: 0.8;
    }
    
    .video-modal-body {
        padding: 2rem;
        text-align: center;
    }
    
    .video-placeholder {
        aspect-ratio: 16/9;
        background: #f0f0f0;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #666;
        font-size: 1.1rem;
        min-height: 300px;
    }
    
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: scale(0.9) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
`;

// Add modal styles to document
const style = document.createElement('style');
style.textContent = modalStyles;
document.head.appendChild(style);

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('videoModal');
    
    if (modal && modal.style.display === 'flex') {
        if (e.key === 'Escape') {
            closeVideoModal();
        }
    }
    
    // Hero slider keyboard navigation
    const heroSection = document.querySelector('.hero-section');
    if (heroSection && e.target === document.body) {
        if (e.key === 'ArrowLeft') {
            changeSlide(-1);
        } else if (e.key === 'ArrowRight') {
            changeSlide(1);
        }
    }
});