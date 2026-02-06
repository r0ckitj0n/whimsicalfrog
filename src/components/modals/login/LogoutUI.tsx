import React from 'react';
import type { IUserProfile } from '../../../types/auth.js';

interface LogoutUIProps {
    user: IUserProfile | null;
    onClose: () => void;
    onLogout: () => void;
    isLoggingOut: boolean;
}

export const LogoutUI: React.FC<LogoutUIProps> = ({ user, onClose, onLogout, isLoggingOut }) => {
    return (
        <div className="flex-1 overflow-y-auto p-6">
            <div className="text-center mb-6">
                <div style={{
                    width: '80px',
                    height: '80px',
                    background: 'linear-gradient(135deg, var(--brand-primary) 0%, #5a7c23 100%)',
                    borderRadius: '50%',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    margin: '0 auto 16px',
                    fontSize: '2.5rem'
                }}>
                    🐸
                </div>
                <h3 style={{ fontSize: '1.25rem', fontWeight: 700, marginBottom: '8px', color: '#111827' }}>
                    Welcome back, {user?.first_name || user?.username || 'Frog Friend'}!
                </h3>
                <p style={{ color: '#6b7280', fontSize: '0.9rem' }}>
                    You're already signed in. What would you like to do?
                </p>
            </div>

            <div className="flex flex-col gap-3">
                <button
                    onClick={() => { onClose(); window.location.href = '/room_main'; }}
                    className="btn btn-primary w-full py-4 flex items-center justify-center gap-2"
                    style={{ fontWeight: 700 }}
                >
                    <span className="btn-icon--home" style={{ fontSize: '16px' }} aria-hidden="true" />
                    Go to Main Room
                </button>

                <button
                    onClick={onLogout}
                    disabled={isLoggingOut}
                    className="w-full py-4 flex items-center justify-center gap-2"
                    style={{
                        background: '#fee2e2',
                        color: '#dc2626',
                        border: 'none',
                        borderRadius: '14px',
                        fontWeight: 700,
                        cursor: isLoggingOut ? 'not-allowed' : 'pointer',
                        opacity: isLoggingOut ? 0.7 : 1
                    }}
                >
                    {isLoggingOut ? (
                        <span className="wf-emoji-loader" style={{ fontSize: '16px' }}>⏳</span>
                    ) : (
                        <span className="btn-icon--logout" style={{ fontSize: '16px' }} aria-hidden="true" />
                    )}
                    <span>{isLoggingOut ? 'Signing Out...' : 'Sign Out'}</span>
                </button>
            </div>
        </div>
    );
};
