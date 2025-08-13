/**
 * WhimsicalFrog Consolidated Utilities Module
 * Enhanced utility functions recovered and consolidated from legacy systems
 * Vite compatible ES6 module
 */

class ApiClient {
    /**
     * Make an authenticated API request
     * @param {string} url - The API endpoint URL
     * @param {Object} options - Fetch options
     * @returns {Promise<Object>} - The response data
     */
    static async request(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };

        const config = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        };

        try {
            const response = await fetch(url, config);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.success === false) {
                throw new Error(data.error || 'API request failed');
            }
            
            return data;
        } catch (error) {
            console.error('[API] Request failed:', error);
            throw error;
        }
    }

    // Convenience methods
    static async get(url, options = {}) {
        return this.request(url, { ...options, method: 'GET' });
    }

    static async post(url, data = null, options = {}) {
        const config = { ...options, method: 'POST' };
        if (data) {
            config.body = JSON.stringify(data);
        }
        return this.request(url, config);
    }

    static async put(url, data = null, options = {}) {
        const config = { ...options, method: 'PUT' };
        if (data) {
            config.body = JSON.stringify(data);
        }
        return this.request(url, config);
    }

    static async delete(url, options = {}) {
        return this.request(url, { ...options, method: 'DELETE' });
    }
}

class DOMUtils {
    /**
     * Safely set innerHTML with loading state
     * @param {HTMLElement} element - Target element
     * @param {string} content - HTML content to set
     * @param {boolean} showLoading - Whether to show loading state first
     */
    static async setContent(element, content, showLoading = false) {
        if (!element) return;

        if (showLoading) {
            element.innerHTML = this.createLoadingSpinner();
            // Small delay to show loading state
            await new Promise(resolve => setTimeout(resolve, 100));
        }

        element.innerHTML = content;
    }

    /**
     * Create a loading spinner element
     * @param {string} message - Loading message
     * @returns {string} - HTML for loading spinner
     */
    static createLoadingSpinner(message = 'Loading...') {
        return `
            <div class="wf-loading">
                <div class="wf-spinner"></div>
                <span>${this.escapeHtml(message)}</span>
            </div>
        `;
    }

    /**
     * Create an error message element
     * @param {string} message - Error message
     * @returns {string} - HTML for error message
     */
    static createErrorMessage(message) {
        return `
            <div class="wf-alert wf-alert--error">
                <strong>Error:</strong> ${this.escapeHtml(message)}
            </div>
        `;
    }

    /**
     * Create a success message element
     * @param {string} message - Success message
     * @returns {string} - HTML for success message
     */
    static createSuccessMessage(message) {
        return `
            <div class="wf-alert wf-alert--success">
                <strong>Success:</strong> ${this.escapeHtml(message)}
            </div>
        `;
    }

    /**
     * Show a temporary toast notification
     * @param {string} message - Message to show
     * @param {string} type - Type: 'success', 'error', 'info'
     * @param {number} duration - Duration in ms
     */
    static showToast(message, type = 'info', duration = 3000) {
        const valid = ['success', 'error', 'info', 'warning'];
        const typeClass = valid.includes(type) ? type : 'info';
        const toast = document.createElement('div');
        toast.className = `wf-toast wf-toast--${typeClass}`;
        toast.textContent = message;
        document.body.appendChild(toast);

        // Animate in via CSS class
        requestAnimationFrame(() => toast.classList.add('wf-toast--show'));

        // Animate out and remove
        setTimeout(() => {
            toast.classList.remove('wf-toast--show');
            setTimeout(() => toast.remove(), 300);
        }, duration);

        return toast;
    }

    /**
     * Debounce function calls
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in ms
     * @returns {Function} - Debounced function
     */
    static debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Format currency value
     * @param {number} value - Numeric value
     * @returns {string} - Formatted currency string
     */
    static formatCurrency(value) {
        if (isNaN(value)) return '$0.00';
        return `$${parseFloat(value).toFixed(2)}`;
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} - Escaped text
     */
    static escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Create a confirmation dialog
     * @param {string} message - Confirmation message
     * @param {string} title - Dialog title
     * @returns {Promise<boolean>} - True if confirmed
     */
    static confirm(message, title = 'Confirm') {
        return new Promise((resolve) => {
            // Create modal overlay and dialog using CSS classes
            const overlay = document.createElement('div');
            overlay.className = 'wf-overlay';

            const dialog = document.createElement('div');
            dialog.className = 'wf-dialog';

            dialog.innerHTML = `
                <h3 class="wf-dialog-title">${this.escapeHtml(title)}</h3>
                <p class="wf-dialog-message">${this.escapeHtml(message)}</p>
                <div class="wf-dialog-actions">
                    <button class="confirm-btn wf-btn wf-btn-primary">Confirm</button>
                    <button class="cancel-btn wf-btn wf-btn-muted">Cancel</button>
                </div>
            `;

            overlay.appendChild(dialog);
            document.body.appendChild(overlay);

            // Event handlers
            const cleanup = () => {
                if (overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
            };

            dialog.querySelector('.confirm-btn').addEventListener('click', () => {
                cleanup();
                resolve(true);
            });

            dialog.querySelector('.cancel-btn').addEventListener('click', () => {
                cleanup();
                resolve(false);
            });

            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    cleanup();
                    resolve(false);
                }
            });

            // Focus confirm button
            setTimeout(() => {
                dialog.querySelector('.confirm-btn')?.focus();
            }, 100);
        });
    }

    /**
     * Throttle function calls
     * @param {Function} func - Function to throttle
     * @param {number} limit - Time limit in ms
     * @returns {Function} - Throttled function
     */
    static throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    /**
     * Deep clone an object
     * @param {Object} obj - Object to clone
     * @returns {Object} - Cloned object
     */
    static deepClone(obj) {
        if (obj === null || typeof obj !== 'object') return obj;
        if (obj instanceof Date) return new Date(obj.getTime());
        if (obj instanceof Array) return obj.map(item => this.deepClone(item));
        if (typeof obj === 'object') {
            const clonedObj = {};
            for (const key in obj) {
                if (obj.hasOwnProperty(key)) {
                    clonedObj[key] = this.deepClone(obj[key]);
                }
            }
            return clonedObj;
        }
    }
}

// Consolidated Utilities class that combines everything
class WhimsicalFrogUtils {
    constructor() {
        this.api = ApiClient;
        this.dom = DOMUtils;
        this.init();
    }

    init() {
        console.log('[Utils] Consolidated utilities initialized');
        
        // Make convenience methods available on the instance
        this.toast = DOMUtils.showToast;
        this.confirm = DOMUtils.confirm;
        this.debounce = DOMUtils.debounce;
        this.throttle = DOMUtils.throttle;
        this.formatCurrency = DOMUtils.formatCurrency;
        this.escapeHtml = DOMUtils.escapeHtml;
        this.deepClone = DOMUtils.deepClone;
    }

    // Convenience API methods
    async get(url, options = {}) {
        return ApiClient.get(url, options);
    }

    async post(url, data = null, options = {}) {
        return ApiClient.post(url, data, options);
    }

    async put(url, data = null, options = {}) {
        return ApiClient.put(url, data, options);
    }

    async delete(url, options = {}) {
        return ApiClient.delete(url, options);
    }

    // Show different types of toast notifications
    showSuccess(message, duration = 3000) {
        return DOMUtils.showToast(message, 'success', duration);
    }

    showError(message, duration = 5000) {
        return DOMUtils.showToast(message, 'error', duration);
    }

    showInfo(message, duration = 3000) {
        return DOMUtils.showToast(message, 'info', duration);
    }

    showWarning(message, duration = 4000) {
        return DOMUtils.showToast(message, 'warning', duration);
    }
}

// Export for ES6 modules
export default WhimsicalFrogUtils;
export { ApiClient, DOMUtils };

// Global compatibility
if (typeof window !== 'undefined') {
    window.WhimsicalFrogUtils = WhimsicalFrogUtils;
    window.ApiClient = ApiClient;
    window.DOMUtils = DOMUtils;
    
    // Convenience global functions
    window.apiGet = (url, options = {}) => ApiClient.get(url, options);
    window.apiPost = (url, data = null, options = {}) => ApiClient.post(url, data, options);
    window.apiPut = (url, data = null, options = {}) => ApiClient.put(url, data, options);
    window.apiDelete = (url, options = {}) => ApiClient.delete(url, options);
    
    window.showToast = DOMUtils.showToast;
    window.confirm = DOMUtils.confirm;
    window.debounce = DOMUtils.debounce;
    window.throttle = DOMUtils.throttle;
    window.formatCurrency = DOMUtils.formatCurrency;
    window.escapeHtml = DOMUtils.escapeHtml;
}
