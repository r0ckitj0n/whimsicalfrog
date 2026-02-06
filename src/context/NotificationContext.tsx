import React, { createContext, useContext, ReactNode, useMemo } from 'react';
import { useNotifications } from '../hooks/useNotifications.js';
import { INotification, INotificationOptions, NotificationType } from '../types/notifications.js';

interface NotificationContextType {
    notifications: INotification[];
    show: (message: string, type?: NotificationType, options?: INotificationOptions) => number;
    success: (message: string, options?: INotificationOptions) => number;
    error: (message: string, options?: INotificationOptions) => number;
    warning: (message: string, options?: INotificationOptions) => number;
    info: (message: string, options?: INotificationOptions) => number;
    validation: (message: string, options?: INotificationOptions) => number;
    remove: (id: number) => void;
    removeAll: () => void;
}

const NotificationContext = createContext<NotificationContextType | undefined>(undefined);


export const NotificationProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
    const notificationHandlers = useNotifications();

    const value = useMemo(() => ({
        ...notificationHandlers
    }), [notificationHandlers]);

    return (
        <NotificationContext.Provider value={value}>
            {children}
        </NotificationContext.Provider>
    );
};

export const useNotificationContext = () => {
    const context = useContext(NotificationContext);
    if (!context) {
        throw new Error('useNotificationContext must be used within a NotificationProvider');
    }
    return context;
};
