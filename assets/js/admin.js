// Admin Panel JavaScript

const TangsaengApp = {
    // Loading overlay functionality
    showLoading: function() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'flex';
        }
    },
    
    hideLoading: function() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    },
    
    // Alert system
    showAlert: function(message, type = 'info') {
        // Remove existing alerts
        const existingAlert = document.querySelector('.admin-alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        // Create new alert
        const alert = document.createElement('div');
        alert.className = `admin-alert alert-${type}`;
        alert.innerHTML = `
            <span>${message}</span>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        // Insert alert at top of main content
        const adminContent = document.querySelector('.admin-content');
        if (adminContent) {
            adminContent.insertBefore(alert, adminContent.firstChild);
        } else {
            document.body.appendChild(alert);
        }
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    },
    
    // Confirmation dialog
    confirm: function(message, callback) {
        if (window.confirm(message)) {
            callback();
        }
    },
    
    // Initialize admin features
    init: function() {
        this.initSidebar();
        this.initDropdowns();
        this.initTables();
        this.initForms();
    },
    
    // Sidebar functionality
    initSidebar: function() {
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        const sidebar = document.querySelector('.admin-sidebar');
        const mainContent = document.querySelector('.admin-main');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                if (mainContent) {
                    mainContent.classList.toggle('sidebar-collapsed');
                }
            });
        }
        
        // Handle nav item clicks
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Remove active class from all links
                navLinks.forEach(l => l.classList.remove('active'));
                // Add active class to clicked link
                this.classList.add('active');
            });
        });
    },
    
    // Dropdown menus
    initDropdowns: function() {
        const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
        
        dropdownToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                const dropdown = this.parentElement;
                dropdown.classList.toggle('active');
                
                // Close other dropdowns
                dropdownToggles.forEach(otherToggle => {
                    if (otherToggle !== toggle) {
                        otherToggle.parentElement.classList.remove('active');
                    }
                });
            });
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                dropdownToggles.forEach(toggle => {
                    toggle.parentElement.classList.remove('active');
                });
            }
        });
    },
    
    // Table functionality
    initTables: function() {
        // Row selection
        const selectAllCheckboxes = document.querySelectorAll('.select-all');
        selectAllCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const table = this.closest('table');
                const rowCheckboxes = table.querySelectorAll('.row-select');
                rowCheckboxes.forEach(rowCheckbox => {
                    rowCheckbox.checked = this.checked;
                });
            });
        });
        
        // Individual row selection
        const rowCheckboxes = document.querySelectorAll('.row-select');
        rowCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const table = this.closest('table');
                const selectAll = table.querySelector('.select-all');
                const allRowCheckboxes = table.querySelectorAll('.row-select');
                const checkedCount = table.querySelectorAll('.row-select:checked').length;
                
                selectAll.checked = checkedCount === allRowCheckboxes.length;
                selectAll.indeterminate = checkedCount > 0 && checkedCount < allRowCheckboxes.length;
            });
        });
        
        // Sortable columns
        const sortableHeaders = document.querySelectorAll('th[data-sort]');
        sortableHeaders.forEach(header => {
            header.addEventListener('click', function() {
                const sortBy = this.dataset.sort;
                const currentOrder = this.dataset.order || 'asc';
                const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
                
                // Update URL with sort parameters
                const url = new URL(window.location);
                url.searchParams.set('sort', sortBy);
                url.searchParams.set('order', newOrder);
                window.location.href = url.toString();
            });
        });
    },
    
    // Form functionality
    initForms: function() {
        // Auto-save forms
        const autoSaveForms = document.querySelectorAll('.auto-save-form');
        autoSaveForms.forEach(form => {
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    this.saveDraft();
                });
            });
        });
        
        // Form validation
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!this.validateForm()) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    },
    
    // Form validation
    validateForm: function(form) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                this.showFieldError(field, '이 필드는 필수입니다.');
                isValid = false;
            } else {
                this.clearFieldError(field);
            }
        });
        
        return isValid;
    },
    
    showFieldError: function(field, message) {
        this.clearFieldError(field);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.textContent = message;
        
        field.classList.add('error');
        field.parentNode.appendChild(errorDiv);
    },
    
    clearFieldError: function(field) {
        field.classList.remove('error');
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
    },
    
    // Utility functions
    formatNumber: function(num) {
        return new Intl.NumberFormat('ko-KR').format(num);
    },
    
    formatDate: function(date) {
        return new Intl.DateTimeFormat('ko-KR', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        }).format(new Date(date));
    },
    
    // AJAX helper
    ajax: function(url, options = {}) {
        const defaults = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        const config = Object.assign(defaults, options);
        
        return fetch(url, config)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            });
    }
};

// Chart utilities for dashboard
const ChartUtils = {
    createLineChart: function(canvas, data, options = {}) {
        const ctx = canvas.getContext('2d');
        
        // Simple line chart implementation
        const width = canvas.width;
        const height = canvas.height;
        const padding = 40;
        
        ctx.clearRect(0, 0, width, height);
        
        // Draw axes
        ctx.strokeStyle = '#ccc';
        ctx.beginPath();
        ctx.moveTo(padding, padding);
        ctx.lineTo(padding, height - padding);
        ctx.lineTo(width - padding, height - padding);
        ctx.stroke();
        
        // Draw data points
        if (data.length > 0) {
            const maxValue = Math.max(...data);
            const minValue = Math.min(...data);
            const range = maxValue - minValue || 1;
            
            ctx.strokeStyle = options.color || '#4CAF50';
            ctx.lineWidth = 2;
            ctx.beginPath();
            
            data.forEach((value, index) => {
                const x = padding + (index / (data.length - 1)) * (width - 2 * padding);
                const y = height - padding - ((value - minValue) / range) * (height - 2 * padding);
                
                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });
            
            ctx.stroke();
        }
    },
    
    createBarChart: function(canvas, data, options = {}) {
        const ctx = canvas.getContext('2d');
        const width = canvas.width;
        const height = canvas.height;
        const padding = 40;
        
        ctx.clearRect(0, 0, width, height);
        
        // Draw axes
        ctx.strokeStyle = '#ccc';
        ctx.beginPath();
        ctx.moveTo(padding, padding);
        ctx.lineTo(padding, height - padding);
        ctx.lineTo(width - padding, height - padding);
        ctx.stroke();
        
        // Draw bars
        if (data.length > 0) {
            const maxValue = Math.max(...data);
            const barWidth = (width - 2 * padding) / data.length * 0.8;
            
            ctx.fillStyle = options.color || '#4CAF50';
            
            data.forEach((value, index) => {
                const x = padding + index * (width - 2 * padding) / data.length + barWidth * 0.1;
                const barHeight = (value / maxValue) * (height - 2 * padding);
                const y = height - padding - barHeight;
                
                ctx.fillRect(x, y, barWidth, barHeight);
            });
        }
    }
};

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    TangsaengApp.init();
    
    // Add loading overlay to body if not exists
    if (!document.getElementById('loadingOverlay')) {
        const loadingOverlay = document.createElement('div');
        loadingOverlay.id = 'loadingOverlay';
        loadingOverlay.className = 'loading-overlay';
        loadingOverlay.style.display = 'none';
        loadingOverlay.innerHTML = `
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p>처리 중...</p>
            </div>
        `;
        document.body.appendChild(loadingOverlay);
    }
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { TangsaengApp, ChartUtils };
}