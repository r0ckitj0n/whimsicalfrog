/**
 * WhimsicalFrog Notification CSS Loader
 * Loads notification-specific CSS variables from the database and injects them into the page
 */

class NotificationCSSLoader {
    constructor() {
        this.styleElementId = 'wf-notification-styles';
        this.loaded = false;
    }

    async loadNotificationCSS() {
        if (this.loaded) return;

        try {
            // Load CSS rules from database
            const response = await fetch('/api/global_css_rules.php?action=generate_css');
            const data = await response.json();
            
            if (data.success && data.css_content) {
                // Extract notification-related CSS variables
                const notificationCSS = this.extractNotificationCSS(data.css_content);
                
                // Inject notification CSS into the page
                this.injectCSS(notificationCSS);
                this.loaded = true;
                
                console.log('✅ WhimsicalFrog notification CSS loaded successfully');
            }
        } catch (error) {
            console.warn('Failed to load notification CSS from database, using fallbacks:', error);
            // Inject fallback CSS if database loading fails
            this.injectFallbackCSS();
        }
    }

    extractNotificationCSS(cssContent) {
        // Extract lines that contain notification CSS variables
        const lines = cssContent.split('\n');
        const notificationLines = lines.filter(line => 
            line.includes('--notification-') || 
            line.includes('/* Notifications */') ||
            (line.includes(':root') && lines[lines.indexOf(line) + 1]?.includes('--notification-'))
        );

        // Build notification-specific CSS
        let notificationCSS = `/* WhimsicalFrog Notification Styles - Loaded from Database */\n`;
        
        // Add the extracted lines
        let inNotificationBlock = false;
        for (const line of lines) {
            if (line.includes('/* Notifications */')) {
                inNotificationBlock = true;
                notificationCSS += line + '\n';
            } else if (inNotificationBlock && line.includes('/* ') && !line.includes('notification')) {
                // End of notification block
                break;
            } else if (inNotificationBlock || line.includes('--notification-')) {
                notificationCSS += line + '\n';
            }
        }

        // Add utility classes for notifications
        notificationCSS += this.generateNotificationUtilityClasses();

        return notificationCSS;
    }

    generateNotificationUtilityClasses() {
        return `
/* WhimsicalFrog Notification Utility Classes */
.wf-notification-container {
    position: var(--notification-container-position, fixed);
    top: var(--notification-container-top, 24px);
    right: var(--notification-container-right, 24px);
    z-index: var(--notification-container-zindex, 2147483647);
    max-width: var(--notification-container-width, 420px);
    pointer-events: none;
}

.wf-notification {
    background: var(--notification-success-bg, linear-gradient(135deg, #87ac3a, #6b8e23));
    border: var(--notification-border-width, 2px) var(--notification-border-style, solid) var(--notification-success-border, #556B2F);
    color: var(--notification-success-text, #ffffff);
    border-radius: var(--notification-border-radius, 16px);
    padding: var(--notification-padding, 20px 24px);
    margin-bottom: var(--notification-margin, 16px);
    font-family: var(--notification-font-family, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
    font-size: var(--notification-font-size, 15px);
    font-weight: var(--notification-font-weight, 500);
    line-height: var(--notification-line-height, 1.5);
    transition: var(--notification-transition, all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275));
    backdrop-filter: var(--notification-backdrop-filter, blur(12px) saturate(180%));
    pointer-events: auto;
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.wf-success-notification {
    background: var(--notification-success-bg, linear-gradient(135deg, #87ac3a, #6b8e23));
    border-color: var(--notification-success-border, #556B2F);
    color: var(--notification-success-text, #ffffff);
    box-shadow: var(--notification-success-shadow, 0 12px 28px rgba(135, 172, 58, 0.35), 0 4px 8px rgba(135, 172, 58, 0.15));
}

.wf-error-notification {
    background: var(--notification-error-bg, linear-gradient(135deg, #dc2626, #b91c1c));
    border-color: var(--notification-error-border, #991b1b);
    color: var(--notification-error-text, #ffffff);
    box-shadow: var(--notification-error-shadow, 0 12px 28px rgba(220, 38, 38, 0.35), 0 4px 8px rgba(220, 38, 38, 0.15));
}

.wf-warning-notification {
    background: var(--notification-warning-bg, linear-gradient(135deg, #f59e0b, #d97706));
    border-color: var(--notification-warning-border, #b45309);
    color: var(--notification-warning-text, #ffffff);
    box-shadow: var(--notification-warning-shadow, 0 12px 28px rgba(245, 158, 11, 0.35), 0 4px 8px rgba(245, 158, 11, 0.15));
}

.wf-info-notification {
    background: var(--notification-info-bg, linear-gradient(135deg, #3b82f6, #2563eb));
    border-color: var(--notification-info-border, #1d4ed8);
    color: var(--notification-info-text, #ffffff);
    box-shadow: var(--notification-info-shadow, 0 12px 28px rgba(59, 130, 246, 0.35), 0 4px 8px rgba(59, 130, 246, 0.15));
}

.wf-validation-notification {
    background: var(--notification-validation-bg, linear-gradient(135deg, #f59e0b, #d97706));
    border-color: var(--notification-validation-border, #b45309);
    color: var(--notification-validation-text, #ffffff);
    box-shadow: var(--notification-validation-shadow, 0 12px 28px rgba(245, 158, 11, 0.35), 0 4px 8px rgba(245, 158, 11, 0.15));
}

.wf-notification-icon {
    font-size: var(--notification-icon-size, 24px);
}

.wf-notification-title {
    font-weight: var(--notification-title-weight, 600);
    margin-bottom: var(--notification-title-margin, 6px);
}

/* Animation keyframes for notifications */
@keyframes wf-notification-slide-in {
    from {
        opacity: 0;
        transform: var(--notification-transform-enter, translateX(100%) scale(0.9));
    }
    to {
        opacity: 1;
        transform: var(--notification-transform-show, translateX(0) scale(1));
    }
}

@keyframes wf-notification-slide-out {
    from {
        opacity: 1;
        transform: var(--notification-transform-show, translateX(0) scale(1));
    }
    to {
        opacity: 0;
        transform: var(--notification-transform-enter, translateX(100%) scale(0.9));
    }
}

/* Hover effects */
.wf-notification:hover {
    transform: var(--notification-transform-hover, translateX(0) scale(1.02)) !important;
}

/* Brand accent styling */
.wf-notification::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    width: 6px;
    background: var(--notification-brand-accent, #87ac3a);
    border-radius: var(--notification-border-radius, 16px) 0 0 var(--notification-border-radius, 16px);
    z-index: 0;
}

.wf-notification-content {
    position: relative;
    z-index: 1;
}
`;
    }

    injectCSS(cssContent) {
        // Remove existing notification styles
        const existingStyle = document.getElementById(this.styleElementId);
        if (existingStyle) {
            existingStyle.remove();
        }

        // Create and inject new style element
        const style = document.createElement('style');
        style.id = this.styleElementId;
        style.textContent = cssContent;
        document.head.appendChild(style);
    }

    injectFallbackCSS() {
        const fallbackCSS = `
/* WhimsicalFrog Notification Fallback Styles */
:root {
    --notification-success-bg: linear-gradient(135deg, #87ac3a, #6b8e23);
    --notification-success-border: #556B2F;
    --notification-success-text: #ffffff;
    --notification-success-shadow: 0 12px 28px rgba(135, 172, 58, 0.35), 0 4px 8px rgba(135, 172, 58, 0.15);
    
    --notification-error-bg: linear-gradient(135deg, #dc2626, #b91c1c);
    --notification-error-border: #991b1b;
    --notification-error-text: #ffffff;
    --notification-error-shadow: 0 12px 28px rgba(220, 38, 38, 0.35), 0 4px 8px rgba(220, 38, 38, 0.15);
    
    --notification-warning-bg: linear-gradient(135deg, #f59e0b, #d97706);
    --notification-warning-border: #b45309;
    --notification-warning-text: #ffffff;
    --notification-warning-shadow: 0 12px 28px rgba(245, 158, 11, 0.35), 0 4px 8px rgba(245, 158, 11, 0.15);
    
    --notification-info-bg: linear-gradient(135deg, #3b82f6, #2563eb);
    --notification-info-border: #1d4ed8;
    --notification-info-text: #ffffff;
    --notification-info-shadow: 0 12px 28px rgba(59, 130, 246, 0.35), 0 4px 8px rgba(59, 130, 246, 0.15);
    
    --notification-validation-bg: linear-gradient(135deg, #f59e0b, #d97706);
    --notification-validation-border: #b45309;
    --notification-validation-text: #ffffff;
    --notification-validation-shadow: 0 12px 28px rgba(245, 158, 11, 0.35), 0 4px 8px rgba(245, 158, 11, 0.15);
    
    --notification-border-radius: 16px;
    --notification-padding: 20px 24px;
    --notification-margin: 16px;
    --notification-font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    --notification-font-size: 15px;
    --notification-font-weight: 500;
    --notification-line-height: 1.5;
    --notification-transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    --notification-backdrop-filter: blur(12px) saturate(180%);
    --notification-border-width: 2px;
    --notification-border-style: solid;
    
    --notification-transform-enter: translateX(100%) scale(0.9);
    --notification-transform-show: translateX(0) scale(1);
    --notification-transform-hover: translateX(0) scale(1.02);
    
    --notification-container-position: fixed;
    --notification-container-top: 24px;
    --notification-container-right: 24px;
    --notification-container-zindex: 2147483647;
    --notification-container-width: 420px;
    
    --notification-brand-accent: #87ac3a;
    --notification-icon-size: 24px;
    --notification-title-weight: 600;
    --notification-title-margin: 6px;
}
` + this.generateNotificationUtilityClasses();

        this.injectCSS(fallbackCSS);
        this.loaded = true;
        console.log('✅ WhimsicalFrog notification fallback CSS loaded');
    }

    // Public method to reload CSS (useful for admin changes)
    async reloadCSS() {
        this.loaded = false;
        await this.loadNotificationCSS();
    }
}

// Initialize notification CSS loader
const notificationCSSLoader = new NotificationCSSLoader();

// Load notification CSS when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        notificationCSSLoader.loadNotificationCSS();
    });
} else {
    notificationCSSLoader.loadNotificationCSS();
}

// Expose globally for debugging and admin use
window.notificationCSSLoader = notificationCSSLoader;

console.log('✅ WhimsicalFrog Notification CSS Loader initialized'); 