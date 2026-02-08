import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IShippingRates } from '../../types/shipping.js';
import type { ItemDimensionsBackfillResult, ItemDimensionsToolsApiResponse } from '../../types/item-dimensions-tools.js';

// Re-export for backward compatibility
export type { IShippingRates } from '../../types/shipping.js';

export const useShippingSettings = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [rates, setRates] = useState<IShippingRates | null>(null);

    const fetchRates = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<{ success: boolean; settings: IShippingRates; error?: string }>('/api/business_settings.php', { category: 'ecommerce' });
            if (res?.success) {
                setRates(res.settings);
                return res.settings;
            } else {
                throw new Error(res?.error || 'Failed to fetch shipping rates');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchShippingRates failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const saveRates = useCallback(async (newRates: Partial<IShippingRates>) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<any>('/api/business_settings.php?action=upsert_settings', {
                category: 'ecommerce',
                settings: newRates
            });
            if (res) {
                await fetchRates();
                return true;
            } else {
                throw new Error('Failed to save shipping rates');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveShippingRates failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchRates]);

    const runDimensionsTool = useCallback(async (action: 'ensure_columns' | 'run_all'): Promise<ItemDimensionsBackfillResult | null> => {
        setIsLoading(true);
        setError(null);
        try {
            let res: ItemDimensionsToolsApiResponse | null = null;
            if (action === 'run_all') {
                res = await ApiClient.post('/api/item_dimensions_tools.php', { action, use_ai: 1 });
            } else {
                res = await ApiClient.get('/api/item_dimensions_tools.php', { action });
            }

            if (res && res.success) {
                return res.data || res;
            } else {
                const err = (res as { error?: string; message?: string } | null);
                throw new Error(err?.error || err?.message || 'Dimensions tool failed');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('runDimensionsTool failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, []);

    return {
        isLoading,
        error,
        rates,
        fetchRates,
        saveRates,
        runDimensionsTool
    };
};
