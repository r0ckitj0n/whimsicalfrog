/**
 * WhimsicalFrog Unified Notification System
 * Consolidated branded notification system with fallback handling
 * Replaces both global-notifications.js and modules/notification-system.js
 */

class WhimsicalFrogNotifications {
    constructor() {
        this.notifications = new Map();
        this.nextId = 1;
        this.container = null;
        this.initialized = false;
        this.init();
    }

    init() {
        if (this.initialized) return;
        
        // Create notification container if it doesn't exist
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'wf-notification-container';
            this.container.className = 'wf-notification-container';
            document.body.appendChild(this.container);
        }
        
        this.initialized = true;
        console.log('✅ WhimsicalFrog Unified Notification System initialized');
    }

    show(message, type = 'info', options = {}) {
        if (!this.initialized) {
            console.warn('Notification system not initialized, initializing now...');
            this.init();
        }

        const {
            title = null,
            duration = this.getDefaultDuration(type),
            persistent = false,
            actions = null,
            autoHide = true
        } = options;

        const id = this.nextId++;
        const notification = this.createNotification(id, message, type, title, persistent, actions);
        
        this.container.appendChild(notification);
        this.notifications.set(id, notification);

        // Trigger animation via class toggle
        requestAnimationFrame(() => {
            notification.classList.add('is-visible');
        });

        // Auto-remove if not persistent and autoHide is enabled
        if (!persistent && autoHide && duration > 0) {
            setTimeout(() => {
                this.remove(id);
            }, duration);
        }

        return id;
    }

    createNotification(id, message, type, title, persistent, actions) {
        const notification = document.createElement('div');
        notification.className = `wf-notification wf-${type}-notification`;
        notification.dataset.id = id;
        notification.dataset.type = type;
        // Heuristic to detect cart-related messages for special click behavior
        try {
            const raw = (typeof message === 'string') ? message : '';
            const m = (raw || '').toLowerCase();
            const isCart = /\bcart\b|shopping\s*cart|added\s*to\s*cart|removed\s*from\s*cart|cart\s*updated/.test(m);
            if (isCart) notification.dataset.cartRelated = '1';
        } catch(_) {}
        
        // Add click-to-dismiss functionality (+ open cart for cart-related toasts)
        notification.addEventListener('click', (event) => {
            console.log(`Notification ${id} clicked`);
            event.preventDefault();
            event.stopPropagation();
            try {
                if (notification.dataset.cartRelated === '1' && typeof window.openCartModal === 'function') {
                    window.openCartModal();
                }
            } catch(_) {}
            this.remove(id);
        });

        // Prevent event bubbling on button clicks
        notification.addEventListener('mousedown', (_event) => {
            console.log(`Notification ${id} mousedown event`);
        });

        notification.title = 'Click to dismiss';

        // Create HTML content
        notification.innerHTML = `
            <div class="wf-notification-content">
                <div class="wf-notification-icon">
                    ${this.getTypeIcon(type)}
                </div>
                <div class="wf-notification-body">
                    ${title ? `<div class="wf-notification-title">${title}</div>` : ''}
                    <div class="wf-notification-message">
                        ${message}
                    </div>
                    ${actions ? this.createActions(actions) : ''}
                </div>
                ${!persistent ? `
                    <button class="wf-notification-close" type="button" aria-label="Close">&times;</button>
                ` : ''}
            </div>
        `;

        // Wire up close button without inline handlers
        if (!persistent) {
            const closeBtn = notification.querySelector('.wf-notification-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', (event) => {
                    event.stopPropagation();
                    this.remove(id);
                });
            }
        }

        // Wire up action buttons without inline handlers
        if (actions && Array.isArray(actions)) {
            const btns = notification.querySelectorAll('.wf-notification-action');
            btns.forEach((btn, idx) => {
                const action = actions[idx];
                if (!action) return;
                btn.addEventListener('click', (event) => {
                    event.stopPropagation();
                    try {
                        if (typeof action.onClick === 'function') {
                            action.onClick(event);
                        } else if (typeof action.onClick === 'string' && action.onClick.trim()) {
                            // Backward-compatibility: execute legacy string callback
                            const fn = new Function(action.onClick);
                            fn.call(window);
                        }
                    } catch (err) {
                        console.warn('Notification action handler failed', err);
                    }
                });
            });
        }

        // Add pulse effect for emphasis on certain types
        if (type === 'warning' || type === 'error') {
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.classList.add('pulse');
                    setTimeout(() => {
                        notification.classList.remove('pulse');
                    }, 150);
                }
            }, 200);
        }

        return notification;
    }

    createActions(actions) {
        if (!actions || !Array.isArray(actions)) return '';
        
        return `
            <div class="wf-notification-actions">
                ${actions.map(action => `
                    <button type="button" class="wf-notification-action ${action.style === 'primary' ? 'primary' : 'secondary'}">
                        ${action.text}
                    </button>
                `).join('')}
            </div>
        `;
    }

    getTypeConfig(type) {
        const configs = {
            success: {
                background: '#87ac3a',
                border: 'var(--brand-secondary, #BF5700)',
                color: 'white',
                titleColor: 'white',
                closeColor: 'white',
                shadow: 'rgba(135, 172, 58, 0.3)',
                icon: '✅'
            },
            error: {
                background: 'linear-gradient(135deg, #dc2626, #b91c1c)',
                border: '#991b1b',
                color: '#ffffff',
                titleColor: '#ffffff',
                closeColor: '#ffffff',
                shadow: 'rgba(220, 38, 38, 0.3)',
                icon: '❌'
            },
            warning: {
                background: 'var(--brand-secondary, #BF5700)',
                border: 'var(--brand-secondary, #BF5700)',
                color: '#ffffff',
                titleColor: '#ffffff',
                closeColor: '#ffffff',
                shadow: 'rgba(191, 87, 0, 0.3)',
                icon: '⚠️'
            },
            info: {
                // Use secondary brand color for info toasts (standardized site-wide)
                background: 'var(--brand-secondary, #BF5700)',
                border: 'var(--brand-secondary, #BF5700)',
                color: '#ffffff',
                titleColor: '#ffffff',
                closeColor: '#ffffff',
                shadow: 'rgba(191, 87, 0, 0.3)',
                icon: 'ℹ️'
            },
            validation: {
                background: 'var(--brand-primary, #87ac3a)',
                border: 'var(--brand-primary, #87ac3a)',
                color: '#ffffff',
                titleColor: '#ffffff',
                closeColor: '#ffffff',
                shadow: 'rgba(135, 172, 58, 0.3)',
                icon: '⚠️'
            }
        };

        return configs[type] || configs.info;
    }

    getDefaultDuration(_type) {
        // All notifications now auto-dismiss after 5 seconds (as requested)
        return 5000;
    }

    remove(id) {
        console.log(`Attempting to remove notification ${id}`);
        const notification = this.notifications.get(id);
        if (notification && notification.parentElement) {
            console.log(`Removing notification ${id} from DOM`);
            // Drive exit animation via CSS class
            notification.classList.remove('is-visible');
            notification.classList.add('slide-out');
            
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                    console.log(`Notification ${id} removed from DOM`);
                }
                this.notifications.delete(id);
                console.log(`Notification ${id} deleted from memory`);
            }, 400);
        } else {
            console.log(`Notification ${id} not found or already removed`);
        }
    }

    removeAll() {
        this.notifications.forEach((notification, id) => {
            this.remove(id);
        });
    }

    // Convenience methods
    success(message, options = {}) {
        return this.show(message, 'success', options);
    }

    error(message, options = {}) {
        return this.show(message, 'error', options);
    }

    warning(message, options = {}) {
        return this.show(message, 'warning', options);
    }

    info(message, options = {}) {
        return this.show(message, 'info', options);
    }

    validation(message, options = {}) {
        return this.show(message, 'validation', options);
    }
    
    // Helper methods for fallback styling
    getFallbackColor(type, property) {
        const fallbacks = {
            success: {
                background: 'linear-gradient(135deg, #87ac3a, #BF5700)',
                border: '#556B2F',
                text: '#ffffff'
            },
            error: {
                background: 'linear-gradient(135deg, #dc2626, #b91c1c)',
                border: '#991b1b',
                text: '#ffffff'
            },
            warning: {
                background: 'var(--brand-secondary, #BF5700)',
                border: '#A04000',
                text: '#ffffff'
            },
            info: {
                background: 'var(--brand-secondary, #BF5700)',
                border: '#A04000',
                text: '#ffffff'
            },
            validation: {
                background: 'var(--brand-primary, #87ac3a)',
                border: '#87ac3a',
                text: '#ffffff'
            }
        };
        
        return fallbacks[type]?.[property] || fallbacks.info[property];
    }
    
    getFallbackShadow(type) {
        const shadows = {
            success: '0 12px 28px rgba(135, 172, 58, 0.35), 0 4px 8px rgba(135, 172, 58, 0.15)',
            error: '0 12px 28px rgba(220, 38, 38, 0.35), 0 4px 8px rgba(220, 38, 38, 0.15)',
            warning: '0 12px 28px rgba(191, 87, 0, 0.35), 0 4px 8px rgba(191, 87, 0, 0.15)',
            info: '0 12px 28px rgba(191, 87, 0, 0.35), 0 4px 8px rgba(191, 87, 0, 0.15)',
            validation: '0 12px 28px rgba(135, 172, 58, 0.35), 0 4px 8px rgba(135, 172, 58, 0.15)'
        };
        
        return shadows[type] || shadows.info;
    }
    
    getTypeIcon(type) {
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️',
            validation: '⚠️'
        };
        
        return icons[type] || icons.info;
    }
}

// Initialize unified notification system
window.wfNotifications = new WhimsicalFrogNotifications();

// Consolidated global functions - prevent duplicate registrations
function registerNotificationFunctions() {
    // Only register if not already registered
    if (!window._wfNotificationFunctionsRegistered) {
        // Main notification functions
        window.showNotification = (message, type = 'info', options = {}) => {
            return window.wfNotifications.show(message, type, options);
        };

        window.showSuccess = (message, options = {}) => {
            return window.wfNotifications.success(message, options);
        };

        window.showError = (message, options = {}) => {
            return window.wfNotifications.error(message, options);
        };

        window.showWarning = (message, options = {}) => {
            return window.wfNotifications.warning(message, options);
        };

        window.showInfo = (message, options = {}) => {
            return window.wfNotifications.info(message, options);
        };

        window.showValidation = (message, options = {}) => {
            return window.wfNotifications.validation(message, options);
        };

        window._wfNotificationFunctionsRegistered = true;
        console.log('📢 WhimsicalFrog notification functions registered globally');
    }
}

// Register functions immediately
registerNotificationFunctions();

// Override alert and showToast functions (immediate execution)
if (!window._wfAlertOverridden) {
    window.alert = function(message) {
        // Detect if this is a cart-related message
        if (message.includes('added to your cart') || message.includes('added to cart')) {
            window.wfNotifications.success(message);
        } else {
            window.wfNotifications.info(message);
        }
    };
    window._wfAlertOverridden = true;
}

// Enhanced showToast function for backward compatibility
if (!window.showToast) {
    window.showToast = (typeOrMessage, messageOrType = null, options = {}) => {
        // Handle both (type, message) and (message, type) parameter orders
        let message, type;
        
        if (messageOrType === null) {
            message = typeOrMessage;
            type = 'info';
        } else if (typeof typeOrMessage === 'string' && ['success', 'error', 'warning', 'info'].includes(typeOrMessage)) {
            type = typeOrMessage;
            message = messageOrType;
        } else {
            message = typeOrMessage;
            type = messageOrType || 'info';
        }
        
        return window.wfNotifications.show(message, type, options);
    };
}

// Additional utility functions
window.hideNotification = (id) => window.wfNotifications.remove(id);
window.clearNotifications = () => window.wfNotifications.removeAll();

// Cart notification integration
if (window.cart && typeof window.cart === 'object') {
    window.cart.showNotification = (message) => window.wfNotifications.success(message);
    window.cart.showErrorNotification = (message) => window.wfNotifications.error(message);
    window.cart.showValidationError = (message) => window.wfNotifications.validation(message);
}

// Final system ready message
console.log('🎉 WhimsicalFrog Unified Notification System ready!');
