import React from 'react';
import { GlobalModalIcon } from './GlobalModalIcon.js';

interface GlobalModalHeaderProps {
    title: string;
    iconKey?: string;
    icon?: string | React.ReactNode;
    iconType?: 'success' | 'danger' | 'info' | 'warning';
    onClose: () => void;
}

export const GlobalModalHeader: React.FC<GlobalModalHeaderProps> = ({
    title, iconKey, icon, iconType, onClose
}) => {
    return (
        <div className="wf-modal-header" style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            padding: '1.25rem 2rem',
            background: 'var(--bg-gradient-brand, linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-secondary) 100%))',
            borderBottom: '1px solid rgba(255,255,255,0.1)',
            flexShrink: 0
        }}>
            <div className="flex items-center gap-3">
                <div className={`flex-shrink-0 p-2 rounded-lg`} style={{ background: 'rgba(255,255,255,0.2)' }}>
                    <GlobalModalIcon iconKey={iconKey} icon={icon} iconType={iconType} />
                </div>
                <h2 className="admin-card-title" style={{
                    margin: 0,
                    color: '#ffffff',
                    fontFamily: "'Merienda', cursive",
                    fontSize: '1.25rem',
                    fontWeight: 700,
                    textShadow: '0 2px 4px rgba(0,0,0,0.1)'
                }}>
                    {title}
                </h2>
            </div>
            <button
                onClick={onClose}
                className="admin-action-btn btn-icon--close"
                aria-label="Close"
                data-help-id="modal-close"
            />
        </div>
    );
};
