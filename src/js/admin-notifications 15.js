/**
 * WhimsicalFrog Admin Notification Utility
 * Standardized notification system for all admin modules
 * Provides consistent API and guaranteed visibility above admin modals
 */

// Import the CSS for admin notifications
import '../styles/admin-notifications.css';

// Fallback inline CSS injection (only if external CSS fails)
const adminNotificationCSS = `
.admin-notification-container--positioned {
    position: fixed !important;
    top: 20px !important;
    right: 20px !important;
    z-index: 999999 !important;
    pointer-events: none !important;
    max-width: 400px !important;
}

.admin-notification {
    pointer-events: auto !important;
    margin-bottom: 10px !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
    color: white !important;
    padding: 16px !important;
    position: relative !important;
    transform: translateX(120%) !important;
    opacity: 0 !important;
    transition: all 0.4s ease !important;
    z-index: 999999 !important;
    min-width: 300px !important;
    max-width: 400px !important;
}

.admin-notification--visible {
    transform: translateX(0) !important;
    opacity: 1 !important;
}

.admin-notification.is-visible {
    transform: translateX(0) !important;
    opacity: 1 !important;
}

.admin-notification.slide-out {
    transform: translateX(120%) !important;
    opacity: 0 !important;
}

.admin-notification-content {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.admin-notification-icon {
    font-size: 20px;
    line-height: 1;
    flex-shrink: 0;
}

.admin-notification-body {
    flex: 1;
    min-width: 0;
}

.admin-notification-title {
    font-weight: bold;
    margin-bottom: 4px;
}

.admin-notification-message {
    font-size: 14px;
    line-height: 1.4;
    word-wrap: break-word;
}

.admin-notification-actions {
    margin-top: 8px;
    display: flex;
    gap: 8px;
}

.admin-notification-action {
    padding: 4px 8px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}

.admin-notification-close {
    position: absolute;
    top: 8px;
    right: 8px;
    background: none;
    border: none;
    color: rgba(255,255,255,0.8);
    font-size: 18px;
    cursor: pointer;
}

.admin-success-notification {
    border: 1px solid #6b8e23 !important;
    background: #87ac3a !important;
}

.admin-error-notification {
    border: 1px solid #991b1b !important;
    background: #dc2626 !important;
}

.admin-info-notification {
    border: 1px solid #A04000 !important;
    background: #BF5700 !important;
}

.admin-warning-notification {
    border: 1px solid #A04000 !important;
    background: #BF5700 !important;
}
`;

// Try to load external CSS first, fallback to inline if it fails
try {
    // Check if CSS is already loaded
    const existingStyle = document.getElementById('admin-notifications-css');
    if (!existingStyle) {
        const style = document.createElement('style');
        style.id = 'admin-notifications-css';
        style.textContent = adminNotificationCSS;
        document.head.appendChild(style);
        console.log('[AdminNotifications] Inline CSS injected successfully');
    }
} catch (error) {
    console.warn('[AdminNotifications] Failed to inject inline CSS:', error);
}

class AdminNotifications {
    constructor() {
        this.notifications = new Map();
        this.nextId = 1;
        this.container = null;
        this.initialized = false;
        this.init();
    }

    init() {
        if (this.initialized) return;

        // Create admin-specific notification container if it doesn't exist
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'admin-notification-container';
            this.container.className = 'admin-notification-container admin-notification-container--positioned';

            // Insert at the very top of body to ensure it's above all admin modals
            document.body.insertBefore(this.container, document.body.firstChild);
        }
        this.initialized = true;
        console.log('[AdminNotifications] System initialized successfully');
    }

    // Close the topmost admin modal overlay if present
    closeTopAdminModal() {
        try {
            const overlay = document.querySelector('#customerModalOuter, .admin-modal-overlay.topmost, .admin-modal-overlay.show');
            if (!overlay) return false;
            const closeBtn = overlay.querySelector('[data-action="close-customer-editor"], .modal-close, .modal-close-btn');
            if (closeBtn) {
                closeBtn.click();
            } else {
                overlay.classList.remove('show', 'topmost');
                overlay.classList.add('hidden');
                document.documentElement.classList.remove('modal-open');
                document.body.classList.remove('modal-open');
            }
            return true;
        } catch (_) { return false; }
    }

    /**
     * Show a success notification
     * @param {string} message - The message to display
     * @param {Object} options - Additional options
     */
    success(message, options = {}) {
        return this.show(message, 'success', options);
    }

    /**
     * Show an error notification
     * @param {string} message - The message to display
     * @param {Object} options - Additional options
     */
    error(message, options = {}) {
        return this.show(message, 'error', options);
    }

    /**
     * Show an info notification
     * @param {string} message - The message to display
     * @param {Object} options - Additional options
     */
    info(message, options = {}) {
        return this.show(message, 'info', options);
    }

    /**
     * Show a warning notification
     * @param {string} message - The message to display
     * @param {Object} options - Additional options
     */
    warning(message, options = {}) {
        return this.show(message, 'warning', options);
    }

    /**
     * Show a notification
     * @param {string} message - The message to display
     * @param {string} type - The notification type (success, error, info, warning)
     * @param {Object} options - Additional options
     */
    show(message, type = 'info', options = {}) {
        if (!this.initialized) {
            this.init();
        }

        const {
            title = null,
            duration = 5000,
            persistent = false,
            actions = null,
            forceAdminRenderer = false
        } = options;

        const id = this.nextId++;

        // Try to use the global notification system first unless forceAdminRenderer is true
        if (!forceAdminRenderer && window.wfNotifications && typeof window.wfNotifications.show === 'function') {
            try {
                console.log('[AdminNotifications] Using global wfNotifications system');
                return window.wfNotifications.show(message, type, options);
            } catch (error) {
                console.warn('[AdminNotifications] Global notification system failed, using fallback:', error);
            }
        }

        // Fallback: Create notification manually
        console.log('[AdminNotifications] Using manual notification fallback for:', message, type);
        const notification = this.createNotification(id, message, type, title, persistent, actions);
        this.container.appendChild(notification);
        this.notifications.set(id, notification);

        // Trigger animation
        requestAnimationFrame(() => {
            notification.classList.add('is-visible');
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
        notification.className = `admin-notification admin-${type}-notification admin-notification--visible`;
        notification.dataset.id = id;
        notification.dataset.type = type;

        // Add click-to-dismiss functionality
        notification.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            this.remove(id);
        });

        notification.title = 'Click to dismiss';

        notification.innerHTML = `
            <div class="admin-notification-content">
                <div class="admin-notification-icon">
                    ${this.getTypeIcon(type)}
                </div>
                <div class="admin-notification-body">
                    ${title ? `<div class="admin-notification-title">${title}</div>` : ''}
                    <div class="admin-notification-message">
                        ${message}
                    </div>
                    ${actions ? this.createActions(actions) : ''}
                </div>
                ${!persistent ? `<button class="admin-notification-close" type="button" aria-label="Close">&times;</button>` : ''}
            </div>
        `;

        // Wire up close button
        if (!persistent) {
            const closeBtn = notification.querySelector('.admin-notification-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', (event) => {
                    event.stopPropagation();
                    this.remove(id);
                });
            }
        }

        // Wire up action buttons
        if (actions && Array.isArray(actions)) {
            const btns = notification.querySelectorAll('.admin-notification-action');
            btns.forEach((btn, idx) => {
                const action = actions[idx];
                if (!action) return;
                btn.addEventListener('click', (event) => {
                    event.stopPropagation();
                    try {
                        const context = {
                            id,
                            closeToast: () => this.remove(id),
                            closeTopAdminModal: () => this.closeTopAdminModal(),
                        };
                        if (typeof action.onClick === 'function') {
                            action.onClick(event, context);
                        } else if (typeof action.onClick === 'string' && action.onClick.trim()) {
                            const fn = new Function(action.onClick);
                            fn.call(window);
                        }
                    } catch (err) {
                        console.warn('Admin notification action handler failed', err);
                    }
                });
            });
        }

        return notification;
    }

    createActions(actions) {
        if (!actions || !Array.isArray(actions)) return '';

        return `
            <div class="admin-notification-actions">
                ${actions.map(action => {
                    const label = (action.ariaLabel || action.text || '').toString();
                    const content = action.icon ? `<span class="admin-action-icon" aria-hidden="true">${action.icon}</span>` : (action.text || '');
                    return `
                        <button type="button" class="admin-notification-action" aria-label="${label}">
                            ${content}
                        </button>
                    `;
                }).join('')}
            </div>
        `;
    }

    getTypeColors(type) {
        const colors = {
            success: {
                background: '#87ac3a',
                border: '#6b8e23'
            },
            error: {
                background: '#dc2626',
                border: '#991b1b'
            },
            info: {
                background: '#BF5700',
                border: '#A04000'
            },
            warning: {
                background: '#BF5700',
                border: '#A04000'
            }
        };

        return colors[type] || colors.info;
    }

    getTypeIcon(type) {
        const icons = {
            success: '✅',
            error: '❌',
            info: 'ℹ️',
            warning: '⚠️'
        };

        return icons[type] || icons.info;
    }

    remove(id) {
        const notification = this.notifications.get(id);
        if (notification && notification.parentElement) {
            notification.classList.remove('is-visible');
            notification.classList.add('slide-out');

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
}

// Initialize the admin notification system
window.adminNotifications = new AdminNotifications();

// Convenience functions for backward compatibility
if (!window.showAdminSuccess) {
    window.showAdminSuccess = (message, options = {}) => {
        return window.adminNotifications.success(message, options);
    };
}

if (!window.showAdminError) {
    window.showAdminError = (message, options = {}) => {
        return window.adminNotifications.error(message, options);
    };
}

if (!window.showAdminInfo) {
    window.showAdminInfo = (message, options = {}) => {
        return window.adminNotifications.info(message, options);
    };
}

if (!window.showAdminWarning) {
    window.showAdminWarning = (message, options = {}) => {
        return window.adminNotifications.warning(message, options);
    };
}

// Universal Admin Toast Helper (persistent with icon actions)
if (!window.showAdminToast) {
    window.showAdminToast = function(message, type = 'info', options = {}) {
        // Ensure persistent if actions are present
        const hasActions = options.actions || !options.actions;
        if (hasActions) {
            options.persistent = true;
            options.duration = 0;
        }

        // Inject universal actions if none provided
        if (!options.actions) {
            options.actions = [
                { icon: '☑️', ariaLabel: 'Dismiss', onClick: (e, ctx) => ctx.closeToast() },
                { icon: '↩︎', ariaLabel: 'Back to list', onClick: (e, ctx) => { ctx.closeTopAdminModal(); ctx.closeToast(); } }
            ];
        }

        // Force admin renderer to ensure actions work
        options.forceAdminRenderer = true;

        return window.adminNotifications.show(message, type, options);
    };
}

// Final ready message
console.log('✅ AdminNotifications system ready!');
