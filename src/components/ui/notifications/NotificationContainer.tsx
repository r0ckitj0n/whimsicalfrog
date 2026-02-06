import React from 'react';
import { createPortal } from 'react-dom';
import { useNotificationContext } from '../../../context/NotificationContext.js';
import { NotificationItem } from './NotificationItem.js';

export const NotificationContainer: React.FC = () => {
    const { notifications, remove, removeAll } = useNotificationContext();

    const hasNotifications = notifications.length > 0;

    const content = (
        <>
            {hasNotifications && (
                <div 
                    className="wf-notification-overlay"
                    onClick={removeAll}
                    style={{
                        position: 'fixed',
                        top: 0,
                        left: 0,
                        right: 0,
                        bottom: 0,
                        zIndex: 'calc(var(--z-index-global-notification) - 1)',
                        backgroundColor: 'transparent',
                        pointerEvents: 'auto'
                    }}
                />
            )}
            <div 
                id="wf-notification-container"
                className="wf-notification-container"
                style={{ zIndex: 'var(--z-index-global-notification)' }}
            >
                {notifications.map((notification) => (
                    <NotificationItem 
                        key={notification.id}
                        notification={notification}
                        onRemove={remove}
                    />
                ))}
            </div>
        </>
    );

    return createPortal(content, document.body);
};
