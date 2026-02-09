import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { useLogin } from '../../hooks/useLogin.js';
import { useAuthModal } from '../../hooks/useAuthModal.js';
import { useAuthContext } from '../../context/AuthContext.js';
import { useNotificationContext } from '../../context/NotificationContext.js';
import { LoginHeader } from './login/LoginHeader.js';
import { LogoutUI } from './login/LogoutUI.js';
import { AuthForm } from './login/AuthForm.js';
import '../../styles/components/login-modal.css';

interface LoginModalProps {
    isOpen: boolean;
    initialMode?: 'login' | 'register';
    onClose: () => void;
}

/**
 * LoginModal v1.3.0
 * Refactored into sub-components to satisfy the <250 line rule.
 */
export const LoginModal: React.FC<LoginModalProps> = ({
    isOpen,
    initialMode = 'login',
    onClose
}) => {
    const {
        mode,
        setMode,
        formData,
        isLoading,
        error,
        success,
        handleInputChange,
        handleSubmit
    } = useLogin(initialMode, onClose);

    const { triggerRect } = useAuthModal();
    const { isLoggedIn, user, logout } = useAuthContext();
    const { success: showSuccess, error: showError } = useNotificationContext();
    const [isLoggingOut, setIsLoggingOut] = useState(false);

    const handleLogout = async () => {
        setIsLoggingOut(true);
        try {
            await logout();
            showSuccess('You have been logged out. See you soon! 👋', { duration: 3000 });
            onClose();
            if (window.location.pathname.toLowerCase().includes('/login')) {
                window.location.href = '/';
            }
        } catch (err) {
            showError('Failed to logout. Please try again.', { duration: 4000 });
        } finally {
            setIsLoggingOut(false);
        }
    };

    const { overlayStyles, cardStyles } = useMemo(() => {
        const isLoginPage = window.location.pathname.toLowerCase().includes('/login');
        const isMobile = window.innerWidth <= 768;

        // Standard centered modal for standalone login page or non-custom triggers
        if (!triggerRect || isLoginPage) {
            return {
                overlayStyles: {
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    backgroundColor: 'rgba(0, 0, 0, 0.85)',
                    backdropFilter: 'blur(8px)',
                    padding: '20px'
                },
                cardStyles: {
                    margin: 'auto'
                }
            };
        }

        // Custom positioning relative to trigger (e.g. Header button)
        const width = 400;
        let left = (triggerRect?.left ?? 0) + ((triggerRect?.width ?? 0) / 2) - (width / 2);
        const top = (triggerRect?.bottom ?? 0) + 10;

        if (left < 10) left = 10;
        if (left + width > window.innerWidth - 10) left = window.innerWidth - width - 10;

        return {
            overlayStyles: isMobile ? {
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                backgroundColor: 'rgba(0, 0, 0, 0.5)',
                padding: '10px'
            } : {
                display: 'block',
                backgroundColor: 'transparent',
                backdropFilter: 'none',
                padding: 0
            },
            cardStyles: (!isMobile) ? {
                position: 'absolute' as const,
                top: `${top}px`,
                left: `${left}px`,
                width: `${width}px`,
                maxWidth: `${width}px`,
                maxHeight: `calc(100vh - ${Math.max(top + 12, 24)}px)`,
                margin: 0
            } : {
                margin: 'auto'
            }
        };
    }, [triggerRect]);

    const isMobile = window.innerWidth <= 768;
    const isCustom = !!triggerRect && !isMobile;
    const isLoginPage = window.location.pathname.toLowerCase().includes('/login');

    useEffect(() => {
        const handleOutsideClick = (e: MouseEvent) => {
            if (isOpen && isCustom) {
                const card = document.querySelector('.login-modal .wf-modal-card');
                if (card && !card.contains(e.target as Node)) {
                    onClose();
                }
            }
        };

        if (isOpen && isCustom) {
            setTimeout(() => {
                window.addEventListener('click', handleOutsideClick);
            }, 0);
        }

        return () => {
            window.removeEventListener('click', handleOutsideClick);
        };
    }, [isOpen, isCustom, onClose]);

    if (!isOpen) return null;

    const modalContent = (
        <div
            className={`wf-modal-overlay show login-modal ${(isCustom || isLoginPage) ? 'no-tint' : ''} ${isCustom ? 'custom-positioned' : ''}`}
            style={{
                position: 'fixed',
                top: 0,
                left: 0,
                width: '100vw',
                height: '100vh',
                zIndex: 'calc(var(--wf-z-modal) + 100)',
                pointerEvents: isCustom ? 'none' : 'auto',
                ...overlayStyles
            }}
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <div
                className="wf-modal-card my-auto animate-in zoom-in-95 slide-in-from-bottom-4 duration-300 overflow-hidden flex flex-col"
                style={{
                    maxWidth: '400px',
                    width: '100%',
                    maxHeight: '100%',
                    backgroundColor: 'white',
                    borderRadius: '24px',
                    boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.5)',
                    position: 'relative',
                    pointerEvents: 'auto',
                    ...cardStyles
                }}
                onClick={(e) => e.stopPropagation()}
            >
                <LoginHeader mode={mode} onClose={onClose} />

                {isLoggedIn ? (
                    <LogoutUI
                        user={user}
                        onClose={onClose}
                        onLogout={handleLogout}
                        isLoggingOut={isLoggingOut}
                    />
                ) : (
                    <>
                        <div className="wf-login-tabs flex-shrink-0" style={{ display: 'flex', background: '#f9fafb', padding: '4px' }}>
                            <button
                                onClick={() => setMode('login')}
                                style={{
                                    flex: 1, padding: '12px', border: 'none', borderRadius: '12px',
                                    background: mode === 'login' ? 'white' : 'transparent',
                                    color: mode === 'login' ? 'var(--brand-primary)' : '#666',
                                    fontWeight: 700, cursor: 'pointer',
                                    boxShadow: mode === 'login' ? '0 2px 4px rgba(0,0,0,0.05)' : 'none'
                                }}
                            >
                                Sign In
                            </button>
                            <button
                                onClick={() => setMode('register')}
                                style={{
                                    flex: 1, padding: '12px', border: 'none', borderRadius: '12px',
                                    background: mode === 'register' ? 'white' : 'transparent',
                                    color: mode === 'register' ? 'var(--brand-primary)' : '#666',
                                    fontWeight: 700, cursor: 'pointer',
                                    boxShadow: mode === 'register' ? '0 2px 4px rgba(0,0,0,0.05)' : 'none'
                                }}
                            >
                                Register
                            </button>
                        </div>
                        <AuthForm
                            mode={mode}
                            formData={formData}
                            isLoading={isLoading}
                            error={error}
                            success={success}
                            handleInputChange={handleInputChange}
                            handleSubmit={handleSubmit}
                        />
                    </>
                )}
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};

export default LoginModal;
