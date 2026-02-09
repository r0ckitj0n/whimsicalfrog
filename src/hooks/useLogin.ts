import { useState, useEffect } from 'react';
import { useAuthContext } from '../context/AuthContext.js';
import { useAuthModal } from './useAuthModal.js';
import { useApp } from '../context/AppContext.js';
import { useNotificationContext } from '../context/NotificationContext.js';
import { IRegisterData } from '../types/auth.js';
import { buildPostAuthRedirectPlan } from '../core/auth-redirect.js';

interface ILoginFormData extends IRegisterData {
    phone_number: string;
    address_line_2: string;
}

const getRegisterValidationMessage = (formData: ILoginFormData): string | null => {
    const requiredFieldChecks: Array<{ value: string; label: string }> = [
        { value: formData.username, label: 'Username is required.' },
        { value: formData.password, label: 'Password is required.' },
        { value: formData.first_name, label: 'First Name is required.' },
        { value: formData.last_name, label: 'Last Name is required.' },
        { value: formData.email, label: 'Email is required.' },
        { value: formData.address_line_1, label: 'Address Line 1 is required.' },
        { value: formData.city, label: 'City is required.' },
        { value: formData.state, label: 'State is required.' },
        { value: formData.zip_code, label: 'ZIP is required.' }
    ];

    const missingField = requiredFieldChecks.find(field => field.value.trim().length === 0);
    if (missingField) {
        return missingField.label;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email.trim())) {
        return 'Enter a valid email address.';
    }

    return null;
};

const isValidRegistrationData = (formData: ILoginFormData): boolean => {
    return getRegisterValidationMessage(formData) === null;
};

/**
 * Hook for managing login and registration state and actions.
 * Extracted from LoginModal.tsx
 */
export const useLogin = (initialMode: 'login' | 'register' = 'login', onClose: () => void) => {
    const { login, register, isLoading } = useAuthContext();
    const { returnTo, openProfileCompletion } = useAuthModal();
    const { setIsCartOpen } = useApp();
    const { success: showSuccess, error: showError } = useNotificationContext();
    const [mode, setMode] = useState<'login' | 'register'>(initialMode);
    const [formData, setFormData] = useState<ILoginFormData>({
        username: '',
        password: '',
        email: '',
        first_name: '',
        last_name: '',
        phone_number: '',
        address_line_1: '',
        address_line_2: '',
        city: '',
        state: '',
        zip_code: ''
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

                if (res.profile_completion_required) {
                    showSuccess('Welcome to Whimsical Frog! Please complete your profile to continue.', { duration: 6000 });
                    openProfileCompletion(returnTo || undefined);
                } else {
                    showSuccess('Welcome back! 🐸', { duration: 3000 });
                    onClose();

                    const plan = buildPostAuthRedirectPlan(returnTo);
                    if (plan.openCart) {
                        setIsCartOpen(true);
                    } else if (plan.redirectPath) {
                        const redirectPath = plan.redirectPath;
                        setTimeout(() => {
                            window.location.href = redirectPath;
                        }, 250);
                    }
                }
            } else {
                setError(res.error || 'Login failed');
                showError(res.error || 'Login failed. Please try again.', { duration: 4000 });
            }
        } else {
            const res = await register(formData as unknown as Record<string, unknown>);
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

    const canSubmitRegister = isValidRegistrationData(formData);
    const registerHelperText = canSubmitRegister ? null : getRegisterValidationMessage(formData);

    return {
        mode,
        setMode,
        formData,
        canSubmitRegister,
        registerHelperText,
        isLoading,
        error,
        success,
        handleInputChange,
        handleSubmit
    };
};
