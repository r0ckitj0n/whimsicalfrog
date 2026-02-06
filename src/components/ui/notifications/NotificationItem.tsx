import React, { useState, useEffect } from 'react';
import { INotification } from '../../../types/notifications.js';

interface NotificationItemProps {
    notification: INotification;
    onRemove: (id: number) => void;
}

export const NotificationItem: React.FC<NotificationItemProps> = ({
    notification,
    onRemove
}) => {
    const { id, message, type, title, isVisible: propIsVisible, isExiting, persistent, actions } = notification;
    const [localVisible, setLocalVisible] = useState(false);

    // Sync local visibility with prop to ensure entrance animation triggers reliably
    useEffect(() => {
        if (propIsVisible) {
            const timer = setTimeout(() => setLocalVisible(true), 10);
            return () => clearTimeout(timer);
        } else {
            setLocalVisible(false);
        }
    }, [propIsVisible]);

    const getIcon = () => {
        switch (type) {
            case 'success': return <span className="text-xl">✅</span>;
            case 'error': return <span className="text-xl">❌</span>;
            case 'warning':
            case 'validation': return <span className="text-xl">⚠️</span>;
            case 'info':
            default: return <span className="text-xl">ℹ️</span>;
        }
    };

    const getVariantClass = () => {
        switch (type) {
            case 'success': return 'wf-success-notification';
            case 'error': return 'wf-error-notification';
            case 'warning': return 'wf-warning-notification';
            case 'validation': return 'wf-warning-notification';
            case 'info':
            default: return 'wf-info-notification';
        }
    };

    const isCartRelated = /\bcart\b|shopping\s*cart|added\s*to\s*cart|removed\s*from\s*cart|cart\s*updated/.test(message.toLowerCase());

    const handleClick = () => {
        if (isCartRelated && window.openCartModal) {
            window.openCartModal();
        }
        onRemove(id);
    };

    return (
        <div
            className={`
                wf-notification 
                ${getVariantClass()}
                ${localVisible && !isExiting ? 'is-visible' : ''}
                ${isExiting ? 'slide-out' : ''}
            `}
            style={{ pointerEvents: 'auto' }}
            onClick={handleClick}
        >
            <div className="wf-notification-content">
                <div className="wf-notification-icon">
                    {getIcon()}
                </div>
                <div className="wf-notification-body">
                    {title && <div className="wf-notification-title">{title}</div>}
                    <div className="wf-notification-message">{message}</div>

                    {actions && actions.length > 0 && (
                        <div className="wf-notification-actions">
                            {actions.map((action, idx) => (
                                <button
                                    key={idx}
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        action.onClick(e);
                                    }}
                                    className={`btn btn-sm ${action.style === 'primary' ? 'btn-primary' : 'btn-secondary'}`}
                                >
                                    {action.text}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
                {!persistent && (
                    <button
                        onClick={(e) => {
                            e.stopPropagation();
                            onRemove(id);
                        }}
                        className="btn-icon btn-icon--close p-1 ml-2 flex items-center justify-center opacity-60 hover:opacity-100 transition-colors"
                        aria-label="Close notification"
                        data-help-id="common-close"
                    />
                )}
            </div>
        </div>
    );
};
