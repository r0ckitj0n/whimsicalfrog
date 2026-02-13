import { useCallback, useEffect, useState } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type {
    ICascadeConfig,
    ICascadeConfigsListResponse,
    IUpsertCascadeConfigRequest,
    IDeleteCascadeConfigRequest,
    ICascadeConfigActionResponse,
    IEffectiveCascadeSettingsResponse,
} from '../../types/inventoryOptions.js';

export const useOptionCascadeConfigs = () => {
    const [configs, setConfigs] = useState<ICascadeConfig[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchConfigs = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<ICascadeConfigsListResponse>('/api/inventory_option_cascade.php', { action: 'list' });
            if (res?.success) {
                setConfigs(res.configs || []);
            } else {
                setError(res?.error || res?.message || 'Failed to load cascade configs');
            }
        } catch (err) {
            logger.error('[useOptionCascadeConfigs] fetchConfigs failed', err);
            setError('Failed to load cascade configs');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const upsertConfig = useCallback(async (payload: Omit<IUpsertCascadeConfigRequest, 'action'>) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<ICascadeConfigActionResponse>('/api/inventory_option_cascade.php', { action: 'upsert', ...payload });
            if (res?.success) {
                await fetchConfigs();
                return { success: true as const, id: res.id };
            }
            const msg = res?.error || res?.message || 'Failed to save cascade config';
            setError(msg);
            return { success: false as const, error: msg };
        } catch (err) {
            logger.error('[useOptionCascadeConfigs] upsertConfig failed', err);
            const msg = err instanceof Error ? err.message : 'Network error';
            setError(msg);
            return { success: false as const, error: msg };
        } finally {
            setIsLoading(false);
        }
    }, [fetchConfigs]);

    const deleteConfig = useCallback(async (payload: Omit<IDeleteCascadeConfigRequest, 'action'>) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<ICascadeConfigActionResponse>('/api/inventory_option_cascade.php', { action: 'delete', ...payload });
            if (res?.success) {
                await fetchConfigs();
                return { success: true as const };
            }
            const msg = res?.error || res?.message || 'Failed to delete cascade config';
            setError(msg);
            return { success: false as const, error: msg };
        } catch (err) {
            logger.error('[useOptionCascadeConfigs] deleteConfig failed', err);
            const msg = err instanceof Error ? err.message : 'Network error';
            setError(msg);
            return { success: false as const, error: msg };
        } finally {
            setIsLoading(false);
        }
    }, [fetchConfigs]);

    const fetchEffectiveForSku = useCallback(async (item_sku: string) => {
        try {
            const res = await ApiClient.get<IEffectiveCascadeSettingsResponse>('/api/inventory_option_cascade.php', { action: 'get_effective', item_sku });
            return res?.success ? res : null;
        } catch (err) {
            logger.error('[useOptionCascadeConfigs] fetchEffectiveForSku failed', err);
            return null;
        }
    }, []);

    useEffect(() => {
        void fetchConfigs();
    }, [fetchConfigs]);

    return { configs, isLoading, error, fetchConfigs, upsertConfig, deleteConfig, fetchEffectiveForSku };
};

