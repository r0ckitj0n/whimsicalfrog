import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IEmailSettings } from '../../types/email.js';

// Re-export for backward compatibility
export type { IEmailSettings } from '../../types/email.js';

export const useEmailSettings = () => {
    const [settings, setSettings] = useState<IEmailSettings | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchSettings = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<{ success: boolean; config: IEmailSettings }>('/api/get_email_config.php');
            if (res?.success) {
                setSettings(res.config);
                return res.config;
            } else {
                throw new Error('Failed to load email settings');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchEmailSettings failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const saveSettings = useCallback(async (newSettings: Partial<IEmailSettings>, smtpPassword?: string) => {
        setIsLoading(true);
        setError(null);
        try {
            interface IPayload extends Partial<IEmailSettings> {
                smtpPassword?: string;
            }
            const payload: IPayload = { ...newSettings };
            if (smtpPassword) {
                payload.smtpPassword = smtpPassword;
            }

            const res = await ApiClient.post<{ success: boolean; message?: string; error?: string }>('/api/save_email_config.php', payload);
            if (res?.success) {
                await fetchSettings();
                return true;
            } else {
                throw new Error(res?.error || 'Failed to save email settings');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveEmailSettings failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchSettings]);

    return {
        settings,
        isLoading,
        error,
        fetchSettings,
        saveSettings
    };
};
