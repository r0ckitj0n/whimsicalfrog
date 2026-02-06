import { useState, useCallback, useRef } from 'react';
import { INotification, INotificationOptions, NotificationType } from '../types/notifications.js';

export const useNotifications = () => {
    const [notifications, setNotifications] = useState<INotification[]>([]);
    const nextId = useRef(1);

    const remove = useCallback((id: number) => {
        setNotifications(prev => prev.map(n =>
            n.id === id ? { ...n, isExiting: true } : n
        ));

        // Wait for exit animation
        setTimeout(() => {
            setNotifications(prev => prev.filter(n => n.id !== id));
        }, 400);
    }, []);

    const show = useCallback((message: string, type: NotificationType = 'info', options: INotificationOptions = {}) => {
        const id = nextId.current++;
        const duration = options.duration ?? 15000;
        const persistent = options.persistent ?? false;
        const autoHide = options.autoHide ?? true;

        const newNotification: INotification = {
            id,
            message,
            type,
            isVisible: false,
            isExiting: false,
            ...options,
            duration,
            persistent,
            autoHide
        };

        setNotifications(prev => [...prev, newNotification]);

        // Trigger entrance animation
        requestAnimationFrame(() => {
            setNotifications(prev => prev.map(n =>
                n.id === id ? { ...n, isVisible: true } : n
            ));
        });

        if (!persistent && autoHide && duration > 0) {
            setTimeout(() => {
                remove(id);
            }, duration);
        }

        return id;
    }, [remove]);

    const success = useCallback((message: string, options?: INotificationOptions) => show(message, 'success', options), [show]);
    const error = useCallback((message: string, options?: INotificationOptions) => show(message, 'error', options), [show]);
    const warning = useCallback((message: string, options?: INotificationOptions) => show(message, 'warning', options), [show]);
    const info = useCallback((message: string, options?: INotificationOptions) => show(message, 'info', options), [show]);
    const validation = useCallback((message: string, options?: INotificationOptions) => show(message, 'validation', options), [show]);

    const removeAll = useCallback(() => {
        setNotifications(prev => prev.map(n => ({ ...n, isExiting: true })));
        setTimeout(() => {
            setNotifications([]);
        }, 400);
    }, []);

    return {
        notifications,
        show,
        success,
        error,
        warning,
        info,
        validation,
        remove,
        removeAll
    };
};
