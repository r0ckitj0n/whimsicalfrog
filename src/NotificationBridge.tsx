import { useEffect } from 'react';
import { useNotificationContext } from './context/NotificationContext.js';
import { INotificationOptions, NotificationType } from './types/notifications.js';
import logger from './core/logger.js';

interface LegacyCart {
    showNotification: (msg: string) => void;
    showErrorNotification: (msg: string) => void;
    showValidationError: (msg: string) => void;
}

interface WFWindow {
    showNotification: (message: string, type?: NotificationType, options?: INotificationOptions) => number;
    showSuccess: (message: string, options?: INotificationOptions) => number;
    showError: (message: string, options?: INotificationOptions) => number;
    showWarning: (message: string, options?: INotificationOptions) => number;
    showInfo: (message: string, options?: INotificationOptions) => number;
    showValidation: (message: string, options?: INotificationOptions) => number;
    showAdminSuccess: (message: string, options?: INotificationOptions) => number;
    showAdminError: (message: string, options?: INotificationOptions) => number;
    showAdminInfo: (message: string, options?: INotificationOptions) => number;
    showAdminWarning: (message: string, options?: INotificationOptions) => number;
    showAdminToast: (message: string, type?: NotificationType, options?: INotificationOptions) => number;
    hideNotification: (id: number) => void;
    clearNotifications: () => void;
    showToast: (typeOrMessage: string, messageOrType?: string | null, options?: INotificationOptions) => number;
    notifySuccess: (message: string) => void;
    notifyError: (message: string) => void;
    WFToast?: {
        success: (message: string) => void;
        error: (message: string) => void;
        info: (message: string) => void;
        warning: (message: string) => void;
        toastSuccess: (message: string | null | undefined) => void;
        toastError: (message: string | null | undefined) => void;
        toastFromData: (data: unknown, fallback?: string) => void;
    };
    cart?: LegacyCart;
    closeTopAdminModal?: () => void;
    _wfNotificationFunctionsRegistered?: boolean;
    alert: (message: string) => void;
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

/**
 * NotificationBridge Component
 * This component does not render anything. 
 * Its sole purpose is to expose the React notification system to the legacy window objects.
 */
export const NotificationBridge = () => {
    const { show, success, error, warning, info, validation, remove, removeAll } = useNotificationContext();

    useEffect(() => {
        if (typeof window === 'undefined') return;
        const win = window as unknown as WFWindow;

        // Register main notification functions
        win.showNotification = (message: string, type: NotificationType = 'info', options: INotificationOptions = {}) => {
            const id = show(message, type, options);
            return id;
        };

        win.showAdminSuccess = (message: string, options: INotificationOptions = {}) => success(message, options);
        win.showAdminError = (message: string, options: INotificationOptions = {}) => error(message, options);
        win.showAdminInfo = (message: string, options: INotificationOptions = {}) => info(message, options);
        win.showAdminWarning = (message: string, options: INotificationOptions = {}) => warning(message, options);

        // Standard helpers
        win.showSuccess = (message: string, options: INotificationOptions = {}) => success(message, options);
        win.showError = (message: string, options: INotificationOptions = {}) => error(message, options);
        win.showWarning = (message: string, options: INotificationOptions = {}) => warning(message, options);
        win.showInfo = (message: string, options: INotificationOptions = {}) => info(message, options);
        win.showValidation = (message: string, options: INotificationOptions = {}) => validation(message, options);

        // Global notify aliases
        win.notifySuccess = (msg: string) => success(msg);
        win.notifyError = (msg: string) => error(msg);

        // Register WFToast for legacy/site-wide support
        win.WFToast = {
            success: (msg: string) => success(msg),
            error: (msg: string) => error(msg),
            info: (msg: string) => info(msg),
            warning: (msg: string) => warning(msg),
            toastSuccess: (msg: string | null | undefined) => {
                if (msg) success(msg);
            },
            toastError: (msg: string | null | undefined) => {
                if (msg) error(msg);
            },
            toastFromData: (data: unknown, fallback?: string) => {
                if (!isRecord(data)) return;
                const successFlag = typeof data.success === 'boolean' ? data.success : undefined;
                const message = typeof data.message === 'string' ? data.message : undefined;
                const errorText = typeof data.error === 'string' ? data.error : undefined;
                if (successFlag) success(message || fallback || 'Success');
                else error(errorText || message || fallback || 'Error');
            }
        };

        // Register wfNotifications for cart system
        window.wfNotifications = {
            success: (msg: string, opts?: INotificationOptions) => success(msg, opts),
            error: (msg: string, opts?: INotificationOptions) => error(msg, opts),
            info: (msg: string, opts?: INotificationOptions) => info(msg, opts),
            warning: (msg: string, opts?: INotificationOptions) => warning(msg, opts)
        };

        win.showAdminToast = (message: string, type: NotificationType = 'info', options: INotificationOptions = {}) => {
            const hasActions = !!(options.actions && options.actions.length > 0);
            
            // If we have actions, we need to create the notification first to get an ID,
            // but we can't easily inject the ID back into the actions before it exists.
            // So we use a wrapper approach where show() handles the rendering.
            
            const finalOptions: INotificationOptions = {
                ...options,
                persistent: hasActions ? true : options.persistent,
                duration: hasActions ? 0 : options.duration,
                forceAdminRenderer: true
            };

            const id = show(message, type, finalOptions);

            // For actions that need the ID, we'd ideally capture it here.
            // But since finalOptions is passed to show(), we can't change it after.
            // Legacy showAdminToast usually only had a few specific actions.
            
            return id;
        };

        // Helper functions
        win.hideNotification = (id: number) => remove(id);
        win.clearNotifications = () => removeAll();

        // Standardized showToast for backward compatibility
        win.showToast = (typeOrMessage: string, messageOrType: string | null = null, options: INotificationOptions = {}) => {
            let message: string, type: NotificationType;
            
            if (messageOrType === null) {
                message = typeOrMessage;
                type = 'info';
            } else if (['success', 'error', 'warning', 'info'].includes(typeOrMessage)) {
                type = typeOrMessage as NotificationType;
                message = messageOrType;
            } else {
                message = typeOrMessage;
                type = (messageOrType || 'info') as NotificationType;
            }
            
            const id = show(message, type, options);
            return id;
        };

        // Integration with other legacy global objects
        if (win.cart && typeof win.cart === 'object') {
            win.cart.showNotification = (message: string) => success(message);
            win.cart.showErrorNotification = (message: string) => error(message);
            win.cart.showValidationError = (message: string) => validation(message);
        }

        // Override native alert for a prettier UI
        window.alert = (message: string) => {
            const msg = String(message);
            const lower = msg.toLowerCase();
            
            if (lower.includes('error') || lower.includes('fail') || lower.includes('unable') || lower.includes('invalid')) {
                error(msg);
            } else if (lower.includes('success') || lower.includes('added') || lower.includes('saved')) {
                success(msg);
            } else if (lower.includes('warning') || lower.includes('caution')) {
                warning(msg);
            } else {
                info(msg);
            }
        };

        win._wfNotificationFunctionsRegistered = true;
        logger.info('🎉 NotificationBridge: Legacy global handlers attached');

        return () => {
            // Optional: cleanup globals on unmount
            // We usually keep them for the session duration
        };
    }, [show, success, error, warning, info, validation, remove, removeAll]);

    return null; // Renderless component
};
