import { useState, useCallback } from 'react';
import { ApiClient } from '../core/ApiClient.js';
import logger from '../core/logger.js';

export const useContact = () => {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [status, setStatus] = useState<{ message: string; type: 'success' | 'error' | 'info' } | null>(null);
    const [captcha, setCaptcha] = useState<{ a: number; b: number; expected: string } | null>(null);
    const [isCaptchaOpen, setIsCaptchaOpen] = useState(false);

    const generateCaptcha = useCallback(() => {
        const a = Math.floor(Math.random() * 8) + 1;
        const b = Math.floor(Math.random() * 8) + 1;
        setCaptcha({ a, b, expected: String(a + b) });
        setIsCaptchaOpen(true);
    }, []);

    const submitForm = async (formData: Record<string, unknown>) => {
        setIsSubmitting(true);
        setStatus({ message: 'Sending...', type: 'info' });

        try {
            const res = await ApiClient.post<{ success: boolean; message?: string; error?: string }>('/api/contact_submit.php', formData);
            if (res && res.success) {
                setStatus({ message: res.message || 'Thanks! Your message has been sent.', type: 'success' });
                return true;
            } else {
                setStatus({ message: res?.error || 'Failed to send your message. Please try again later.', type: 'error' });
                return false;
            }
        } catch (err) {
            logger.error('[useContact] submitForm failed', err);
            setStatus({ message: 'Network error. Please check your connection and try again.', type: 'error' });
            return false;
        } finally {
            setIsSubmitting(false);
        }
    };

    return {
        isSubmitting,
        status,
        setStatus,
        captcha,
        isCaptchaOpen,
        setIsCaptchaOpen,
        generateCaptcha,
        submitForm
    };
};
