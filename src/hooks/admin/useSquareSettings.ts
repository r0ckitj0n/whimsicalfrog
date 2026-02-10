import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { ENVIRONMENT, Environment } from '../../core/constants.js';
import type { ISquareSettings, ISquareSettingsApiRecord } from '../../types/square.js';

// Re-export for backward compatibility
export type { ISquareSettings } from '../../types/square.js';

export const useSquareSettings = () => {
    const [settings, setSettings] = useState<ISquareSettings | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const parseBool = (value: unknown): boolean => {
        if (typeof value === 'boolean') return value;
        if (typeof value === 'number') return value === 1;
        if (typeof value === 'string') return ['1', 'true', 'yes', 'on', 'y'].includes(value.toLowerCase());
        return false;
    };

    const toEditorSettings = (raw: ISquareSettingsApiRecord): ISquareSettings => {
        const env = (raw.square_environment || ENVIRONMENT.SANDBOX) as Environment;

        const sandboxApp = raw.square_sandbox_application_id || raw.square_application_id || '';
        const sandboxLoc = raw.square_sandbox_location_id || raw.square_location_id || '';
        const sandboxToken = raw.square_sandbox_access_token || '';

        const productionApp = raw.square_production_application_id || '';
        const productionLoc = raw.square_production_location_id || '';
        const productionToken = raw.square_production_access_token || '';

        const activeApp = env === ENVIRONMENT.PRODUCTION ? productionApp : sandboxApp;
        const activeLoc = env === ENVIRONMENT.PRODUCTION ? productionLoc : sandboxLoc;
        const activeToken = env === ENVIRONMENT.PRODUCTION ? productionToken : sandboxToken;

        return {
            square_enabled: parseBool(raw.square_enabled),
            square_environment: env,
            square_application_id: activeApp,
            square_location_id: activeLoc,
            square_access_token: activeToken,
            square_sandbox_application_id: sandboxApp,
            square_sandbox_access_token: sandboxToken,
            square_sandbox_location_id: sandboxLoc,
            square_production_application_id: productionApp,
            square_production_access_token: productionToken,
            square_production_location_id: productionLoc,
            square_sync_enabled: parseBool(raw.auto_sync_enabled ?? raw.square_sync_enabled)
        };
    };

    const toSavePayload = (draft: ISquareSettings): Record<string, unknown> => {
        const env = draft.square_environment || ENVIRONMENT.SANDBOX;
        const next = { ...draft };

        if (env === ENVIRONMENT.PRODUCTION) {
            next.square_production_application_id = next.square_application_id;
            next.square_production_location_id = next.square_location_id;
            if (next.square_access_token !== '') {
                next.square_production_access_token = next.square_access_token;
            }
        } else {
            next.square_sandbox_application_id = next.square_application_id;
            next.square_sandbox_location_id = next.square_location_id;
            if (next.square_access_token !== '') {
                next.square_sandbox_access_token = next.square_access_token;
            }
        }

        const activeApplicationId = env === ENVIRONMENT.PRODUCTION
            ? next.square_production_application_id
            : next.square_sandbox_application_id;
        const activeLocationId = env === ENVIRONMENT.PRODUCTION
            ? next.square_production_location_id
            : next.square_sandbox_location_id;
        const activeAccessToken = env === ENVIRONMENT.PRODUCTION
            ? next.square_production_access_token
            : next.square_sandbox_access_token;

        return {
            square_enabled: !!next.square_enabled,
            square_environment: env,
            square_sandbox_application_id: next.square_sandbox_application_id,
            square_sandbox_access_token: next.square_sandbox_access_token,
            square_sandbox_location_id: next.square_sandbox_location_id,
            square_production_application_id: next.square_production_application_id,
            square_production_access_token: next.square_production_access_token,
            square_production_location_id: next.square_production_location_id,
            // Keep legacy mirrors aligned to active environment for compatibility.
            square_application_id: activeApplicationId,
            square_location_id: activeLocationId,
            square_access_token: activeAccessToken
        };
    };

    const fetchSettings = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<{ success: boolean; settings?: ISquareSettingsApiRecord; error?: string }>('/api/square_settings.php?action=get_settings');
            if (res?.success && res.settings) {
                const mapped = toEditorSettings(res.settings);
                setSettings(mapped);
                return mapped;
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
            const payload = toSavePayload(newSettings);
            const res = await ApiClient.post<{ success: boolean; message?: string; error?: string }>('/api/square_settings.php?action=save_settings', payload);
            if (res?.success) {
                const refreshed = await fetchSettings();
                if (refreshed) {
                    setSettings(refreshed);
                } else {
                    setSettings(newSettings);
                }
                return true;
            } else {
                throw new Error(res?.error || 'Failed to save Square settings');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveSquareSettings failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchSettings]);

    const testConnection = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<{ success: boolean; message: string }>('/api/square_settings.php?action=test_connection', {});
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
