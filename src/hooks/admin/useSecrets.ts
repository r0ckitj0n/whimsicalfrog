import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { API_ACTION } from '../../core/constants.js';
import type { ISecret } from '../../types/auth.js';

// Re-export for backward compatibility
export type { ISecret } from '../../types/auth.js';

export const useSecrets = () => {
    const [secrets, setSecrets] = useState<ISecret[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);

    const fetchSecrets = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<{ success: boolean; secrets?: ISecret[]; error?: string }>(`/api/secrets.php?action=${API_ACTION.LIST}`);
            if (res?.success) {
                setSecrets(res.secrets || []);
            } else {
                setError(res?.error || 'Failed to load secrets');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchSecrets failed', err);
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const saveSecret = useCallback(async (key: string, value: string, csrf: string) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<any>(`/api/secrets.php?action=${API_ACTION.SET}`, { key, value, csrf });
            if (res) {
                setSuccessMessage(`Secret "${key}" saved.`);
                await fetchSecrets();
                return true;
            } else {
                throw new Error('Failed to save secret');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveSecret failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchSecrets]);

    const deleteSecret = useCallback(async (key: string, csrf: string) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<{ success: boolean; error?: string }>(`/api/secrets.php?action=${API_ACTION.DELETE}`, { key, csrf });
            if (res?.success) {
                setSuccessMessage(`Secret "${key}" deleted.`);
                await fetchSecrets();
                return true;
            } else {
                throw new Error(res?.error || 'Failed to delete secret');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('deleteSecret failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchSecrets]);

    const rotateKeys = useCallback(async (csrf: string) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<{ success: boolean; error?: string; re_encrypted?: number; failed?: number }>(`/api/secrets.php?action=${API_ACTION.ROTATE_KEYS}`, { csrf });
            if (res?.success) {
                setSuccessMessage(`Keys rotated. Re-encrypted: ${res.re_encrypted}, Failed: ${res.failed}`);
                await fetchSecrets();
                return true;
            } else {
                throw new Error(res?.error || 'Failed to rotate keys');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('rotateKeys failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchSecrets]);

    return {
        secrets,
        isLoading,
        error,
        successMessage,
        setSuccessMessage,
        fetchSecrets,
        saveSecret,
        deleteSecret,
        rotateKeys
    };
};
