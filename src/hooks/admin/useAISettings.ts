import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { API_ACTION } from '../../core/constants.js';
import {
    IAISettings,
    IAISettingsResponse,
    IAIProviderTestResponse,
    IAIModelsResponse,
    IAIModel
} from '../../types/ai.js';

// Re-export for backward compatibility
export type { IAISettings } from '../../types/ai.js';

export const useAISettings = () => {
    const [settings, setSettings] = useState<IAISettings | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [models, setModels] = useState<Array<{ id: string; name: string; description?: string }>>([]);

    const fetchSettings = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IAISettingsResponse | IAISettings>(`/api/ai_settings.php?action=${API_ACTION.GET_SETTINGS}`);
            if (res) {
                const settings = 'ai_provider' in res ? res : (res.settings || res.data);
                if (settings) {
                    setSettings(settings);
                }
                return settings ?? null;
            } else {
                throw new Error('Failed to fetch AI settings');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchAISettings failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const saveSettings = useCallback(async (newSettings: IAISettings) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<IAISettingsResponse>(`/api/ai_settings.php?action=${API_ACTION.UPDATE_SETTINGS}`, newSettings);
            if (res?.success ?? true) {
                setSettings(newSettings);
                return true;
            } else {
                throw new Error('Failed to save AI settings');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveAISettings failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const testProvider = useCallback(async (provider: string) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<IAIProviderTestResponse>('/api/ai_settings.php', {
                action: API_ACTION.TEST_PROVIDER,
                provider: encodeURIComponent(provider)
            });
            if (res) {
                await fetchSettings(); // Refresh to get updated timestamps
                return res;
            } else {
                throw new Error('Provider test failed');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('testAIProvider failed', err);
            setError(message);
            return { success: false, message };
        } finally {
            setIsLoading(false);
        }
    }, [fetchSettings]);

    const fetchModels = useCallback(async (provider: string, force = false) => {
        if (!provider || provider === 'jons_ai') {
            setModels([]);
            return [];
        }
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IAIModelsResponse | IAIModel[]>(
                `/api/ai_settings.php?action=list_models&provider=${provider}&force=${force ? 1 : 0}`
            );
            if (res) {
                const modelsList = Array.isArray(res) ? res : res.models || res.data || [];
                const visionModels = (modelsList as Array<{ supportsVision?: boolean }>).filter(m => m.supportsVision === true);
                setModels(visionModels);
                return visionModels;
            }
            return [];
        } catch (err: unknown) {
            logger.error('fetchModels failed', err);
            return [];
        } finally {
            setIsLoading(false);
        }
    }, []);

    return {
        settings,
        isLoading,
        error,
        models,
        fetchSettings,
        saveSettings,
        testProvider,
        fetchModels
    };
};
