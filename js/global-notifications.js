/**
 * WhimsicalFrog Global Notification System
 * Provides consistent, branded notifications across the entire application
 */

class WhimsicalFrogNotifications {
    constructor() {
        this.notifications = new Map();
        this.nextId = 1;
        this.container = null;
        this.init();
    }

    init() {
        // Create notification container if it doesn't exist
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'wf-notification-container';
            this.container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 2147483648;
                pointer-events: none;
                max-width: 420px;
                width: 100%;
            `;
            document.body.appendChild(this.container);
        }
    }

    show(message, type = 'info', options = {}) {
        const {
            title = null,
            duration = this.getDefaultDuration(type),
            persistent = false,
            actions = null
        } = options;

        const id = this.nextId++;
        const notification = this.createNotification(id, message, type, title, persistent, actions);
        
        this.container.appendChild(notification);
        this.notifications.set(id, notification);

        // Trigger animation
        requestAnimationFrame(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0) scale(1)';
        });

        // Auto-remove if not persistent
        if (!persistent && duration > 0) {
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
        
        const config = this.getTypeConfig(type);
        
        // Apply base styles without colors for success notifications (to allow CSS override)
        if (type === 'success') {
            notification.style.cssText = `
                border-radius: 12px;
                padding: 16px 20px;
                margin-bottom: 12px;
                box-shadow: 0 10px 25px ${config.shadow};
                backdrop-filter: blur(10px);
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                font-size: 14px;
                font-weight: 500;
                opacity: 0;
                transform: translateX(100%) scale(0.9);
                transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                pointer-events: auto;
                position: relative;
                overflow: hidden;
            `;
        } else {
            notification.style.cssText = `
                background: ${config.background};
                border: 2px solid ${config.border};
                color: ${config.color};
                border-radius: 12px;
                padding: 16px 20px;
                margin-bottom: 12px;
                box-shadow: 0 10px 25px ${config.shadow};
                backdrop-filter: blur(10px);
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                font-size: 14px;
                font-weight: 500;
                opacity: 0;
                transform: translateX(100%) scale(0.9);
                transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                pointer-events: auto;
                position: relative;
                overflow: hidden;
            `;
        }

        notification.innerHTML = `
            <div class="wf-notification-content" style="display: flex; align-items: flex-start; gap: 12px;">
                <div class="wf-notification-icon" style="font-size: 20px; flex-shrink: 0; margin-top: 1px;">
                    ${config.icon}
                </div>
                <div class="wf-notification-body" style="flex: 1; min-width: 0;">
                    ${title ? `<div class="wf-notification-title" style="font-weight: 600; margin-bottom: 4px; color: ${config.titleColor};">${title}</div>` : ''}
                    <div class="wf-notification-message" style="line-height: 1.4; word-wrap: break-word;">
                        ${message}
                    </div>
                    ${actions ? this.createActions(actions) : ''}
                </div>
                ${!persistent ? `
                    <button class="wf-notification-close" onclick="window.wfNotifications.remove(${id})" 
                            style="background: none; border: none; color: ${config.closeColor}; cursor: pointer; font-size: 18px; padding: 0; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: all 0.2s; margin-top: 1px; flex-shrink: 0;"
                            onmouseover="this.style.backgroundColor='rgba(0,0,0,0.1)'"
                            onmouseout="this.style.backgroundColor='transparent'">&times;</button>
                ` : ''}
            </div>
        `;

        // Add pulse effect for emphasis on certain types
        if (type === 'warning' || type === 'error') {
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.transform = 'translateX(0) scale(1.02)';
                    setTimeout(() => {
                        notification.style.transform = 'translateX(0) scale(1)';
                    }, 150);
                }
            }, 200);
        }

        return notification;
    }

    createActions(actions) {
        if (!actions || !Array.isArray(actions)) return '';
        
        return `
            <div class="wf-notification-actions" style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
                ${actions.map(action => `
                    <button onclick="${action.onClick}" 
                            style="background: ${action.style === 'primary' ? '#87ac3a' : 'transparent'}; 
                                   color: ${action.style === 'primary' ? 'white' : '#87ac3a'}; 
                                   border: 1px solid #87ac3a; 
                                   padding: 6px 12px; 
                                   border-radius: 6px; 
                                   font-size: 12px; 
                                   font-weight: 500; 
                                   cursor: pointer; 
                                   transition: all 0.2s;"
                            onmouseover="this.style.opacity='0.8'"
                            onmouseout="this.style.opacity='1'">
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
                border: '#6b8e23',
                color: 'white',
                titleColor: 'white',
                closeColor: 'white',
                shadow: 'rgba(135, 172, 58, 0.3)',
                icon: '✅'
            },
            error: {
                background: 'linear-gradient(135deg, #fee2e2, #fecaca)',
                border: '#ef4444',
                color: '#7f1d1d',
                titleColor: '#dc2626',
                closeColor: '#ef4444',
                shadow: 'rgba(239, 68, 68, 0.2)',
                icon: '❌'
            },
            warning: {
                background: 'linear-gradient(135deg, #fef3c7, #fde68a)',
                border: '#f59e0b',
                color: '#92400e',
                titleColor: '#d97706',
                closeColor: '#f59e0b',
                shadow: 'rgba(245, 158, 11, 0.2)',
                icon: '⚠️'
            },
            info: {
                background: 'linear-gradient(135deg, #dbeafe, #bfdbfe)',
                border: '#3b82f6',
                color: '#1e3a8a',
                titleColor: '#2563eb',
                closeColor: '#3b82f6',
                shadow: 'rgba(59, 130, 246, 0.2)',
                icon: 'ℹ️'
            },
            validation: {
                background: 'linear-gradient(135deg, #fef3c7, #fde68a)',
                border: '#f59e0b',
                color: '#92400e',
                titleColor: '#d97706',
                closeColor: '#f59e0b',
                shadow: 'rgba(245, 158, 11, 0.2)',
                icon: '⚠️'
            }
        };

        return configs[type] || configs.info;
    }

    getDefaultDuration(type) {
        const durations = {
            success: 4000,
            error: 6000,
            warning: 6000,
            info: 5000,
            validation: 6000
        };
        return durations[type] || 5000;
    }

    remove(id) {
        const notification = this.notifications.get(id);
        if (notification && notification.parentElement) {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%) scale(0.9)';
            
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
                this.notifications.delete(id);
            }, 400);
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
}

// Initialize global notification system
window.wfNotifications = new WhimsicalFrogNotifications();

// Global convenience functions that replace alert(), showToast(), etc.
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

// Replace common alert patterns
window.alert = (message) => {
    return window.wfNotifications.info(message, { persistent: true });
};

// Enhanced showToast function for backward compatibility
window.showToast = (typeOrMessage, messageOrType = null, options = {}) => {
    // Handle both (type, message) and (message, type) parameter orders
    let message, type;
    
    if (messageOrType === null) {
        // Single parameter - assume it's a message with info type
        message = typeOrMessage;
        type = 'info';
    } else if (typeof typeOrMessage === 'string' && ['success', 'error', 'warning', 'info'].includes(typeOrMessage)) {
        // First parameter is type, second is message
        type = typeOrMessage;
        message = messageOrType;
    } else {
        // First parameter is message, second is type
        message = typeOrMessage;
        type = messageOrType || 'info';
    }
    
    return window.wfNotifications.show(message, type, options);
};

// Cart notification integration
if (window.cart && typeof window.cart.showNotification === 'function') {
    window.cart.showNotification = (message) => {
        return window.wfNotifications.success(message);
    };
    
    window.cart.showErrorNotification = (message) => {
        return window.wfNotifications.error(message);
    };
    
    window.cart.showValidationError = (message) => {
        return window.wfNotifications.validation(message);
    };
}

console.log('✅ WhimsicalFrog Global Notification System initialized'); 