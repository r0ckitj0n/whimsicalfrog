import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { ENVIRONMENT, Environment } from '../../core/constants.js';
import type { ISquareSettings } from '../../types/square.js';

// Re-export for backward compatibility
export type { ISquareSettings } from '../../types/square.js';

export const useSquareSettings = () => {
    const [settings, setSettings] = useState<ISquareSettings | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchSettings = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<{ success: boolean; settings: ISquareSettings; error?: string }>('/api/business_settings.php', { category: 'square' });
            if (res?.success) {
                setSettings(res.settings);
                return res.settings;
            } else {
                throw new Error(res?.error || 'Failed to fetch Square settings');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchSquareSettings failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const saveSettings = useCallback(async (newSettings: ISquareSettings) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<any>('/api/business_settings.php?action=upsert_settings', {
                category: 'square',
                settings: newSettings
            });
            if (res) {
                setSettings(newSettings);
                return true;
            } else {
                throw new Error('Failed to save Square settings');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveSquareSettings failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const testConnection = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<{ success: boolean; message: string }>('/api/square_settings.php?action=test_connection');
            return res;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('testSquareConnection failed', err);
            setError(message);
            return { success: false, message };
        } finally {
            setIsLoading(false);
        }
    }, []);

    const syncItems = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<{ success: boolean; message: string }>('/api/square_settings.php?action=sync_items', {});
            return res;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('syncSquareItems failed', err);
            setError(message);
            return { success: false, message };
        } finally {
            setIsLoading(false);
        }
    }, []);

    return {
        settings,
        isLoading,
        error,
        fetchSettings,
        saveSettings,
        testConnection,
        syncItems
    };
};
