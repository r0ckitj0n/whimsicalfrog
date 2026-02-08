import { useCallback } from 'react';
import { useApp } from '../context/AppContext.js';

export type AuthModalMode = 'login' | 'register' | 'account-settings' | 'profile-completion' | 'none';

export const useAuthModal = () => {
    const { 
        authMode: mode, 
        setAuthMode: setMode, 
        authReturnTo: returnTo, 
        setAuthReturnTo: setReturnTo,
        authTriggerRect: triggerRect,
        setAuthTriggerRect: setTriggerRect
    } = useApp();

    const openLogin = useCallback((redirect?: string, rect?: DOMRect) => {
        setMode('login');
        if (redirect) setReturnTo(redirect);
        if (rect) setTriggerRect(rect);
    }, [setMode, setReturnTo, setTriggerRect]);

    const openRegister = useCallback((redirect?: string, rect?: DOMRect) => {
        setMode('register');
        if (redirect) setReturnTo(redirect);
        if (rect) setTriggerRect(rect);
    }, [setMode, setReturnTo, setTriggerRect]);

    const openAccountSettings = useCallback(() => {
        setMode('account-settings');
    }, [setMode]);

    const openProfileCompletion = useCallback((redirect?: string) => {
        setMode('profile-completion');
        if (redirect) setReturnTo(redirect);
    }, [setMode, setReturnTo]);

    const close = useCallback(() => {
        setMode('none');
        setReturnTo(null);
        setTriggerRect(null);
    }, [setMode, setReturnTo, setTriggerRect]);

    return {
        mode,
        returnTo,
        triggerRect,
        openLogin,
        openRegister,
        openAccountSettings,
        openProfileCompletion,
        close,
        setMode
    };
};
