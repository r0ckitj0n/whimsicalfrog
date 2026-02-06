import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IShippingRates } from '../../types/shipping.js';

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

    const runDimensionsTool = useCallback(async (action: 'ensure_columns' | 'run_all'): Promise<{ updated: number; skipped: number } | null> => {
        setIsLoading(true);
        setError(null);
        try {
            let res: { success: boolean; data?: { updated: number; skipped: number }; error?: string; message?: string } | null = null;
            if (action === 'run_all') {
                res = await ApiClient.post('/api/item_dimensions_tools.php', { action, use_ai: 1 });
            } else {
                res = await ApiClient.get('/api/item_dimensions_tools.php', { action });
            }

            if (res && res.success) {
                // With the API update, results are now directly in res.data
                return res.data || { updated: 0, skipped: 0 };
            } else {
                throw new Error(res?.error || res?.message || 'Dimensions tool failed');
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
