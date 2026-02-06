import { useState, useEffect } from 'react';
import { useAuthContext } from '../context/AuthContext.js';
import { useAuthModal } from './useAuthModal.js';
import { useApp } from '../context/AppContext.js';
import { useNotificationContext } from '../context/NotificationContext.js';
import { IRegisterData } from '../types/auth.js';

/**
 * Hook for managing login and registration state and actions.
 * Extracted from LoginModal.tsx
 */
export const useLogin = (initialMode: 'login' | 'register' = 'login', onClose: () => void) => {
    const { login, register, isLoading } = useAuthContext();
    const { returnTo, close: closeAuth } = useAuthModal();
    const { setIsCartOpen } = useApp();
    const { success: showSuccess, error: showError } = useNotificationContext();
    const [mode, setMode] = useState<'login' | 'register'>(initialMode);
    const [formData, setFormData] = useState({
        username: '',
        password: '',
        email: '',
        first_name: '',
        last_name: ''
    });
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState<string | null>(null);

    useEffect(() => {
        setMode(initialMode);
    }, [initialMode]);

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setFormData(prev => ({ ...prev, [e.target.name]: e.target.value }));
        setError(null);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);
        setSuccess(null);

        if (mode === 'login') {
            const res = await login(formData.username, formData.password);
            if (res.success) {
                // Sync login state to DOM for immediate recognition by global listeners
                document.body.setAttribute('data-is-logged-in', 'true');

                // Show success toast
                showSuccess('Welcome back! 🐸', { duration: 3000 });

                onClose();

                // Redirect to Main Room after a brief delay for toast visibility
                if (returnTo === 'cart') {
                    setIsCartOpen(true);
                } else if (window.location.pathname.toLowerCase().includes('/login')) {
                    setTimeout(() => {
                        window.location.href = '/room_main';
                    }, 500);
                }
            } else {
                setError(res.error || 'Login failed');
                showError(res.error || 'Login failed. Please try again.', { duration: 4000 });
            }
        } else {
            const res = await register(formData);
            if (res.success) {
                setSuccess('Account created! You can now sign in.');
                showSuccess('Account created successfully! Please sign in. 🎉', { duration: 4000 });
                setMode('login');
            } else {
                setError(res.error || 'Registration failed');
                showError(res.error || 'Registration failed. Please try again.', { duration: 4000 });
            }
        }
    };

    return {
        mode,
        setMode,
        formData,
        isLoading,
        error,
        success,
        handleInputChange,
        handleSubmit
    };
};
