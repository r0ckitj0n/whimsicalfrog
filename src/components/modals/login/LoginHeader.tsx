import React from 'react';

interface LoginHeaderProps {
    mode: 'login' | 'register';
    onClose: () => void;
}

export const LoginHeader: React.FC<LoginHeaderProps> = ({ mode, onClose }) => {
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
                <div className="wf-login-icon-box" style={{ background: 'rgba(255,255,255,0.2)', padding: '8px', borderRadius: '12px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    {mode === 'login' ? (
                        <span className="admin-action-btn btn-icon--user text-white" aria-hidden="true" />
                    ) : (
                        <span className="admin-action-btn btn-icon--add text-white" aria-hidden="true" />
                    )}
                </div>
                <h2 className="admin-card-title" style={{
                    margin: 0,
                    color: '#ffffff',
                    fontFamily: "'Merienda', cursive",
                    fontSize: '1.5rem',
                    fontWeight: 700,
                    textShadow: '0 2px 4px rgba(0,0,0,0.1)'
                }}>
                    {mode === 'login' ? 'Sign In' : 'Register'}
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
