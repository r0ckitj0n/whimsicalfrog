import { useState, useCallback } from 'react';
import { ApiClient } from '../../../core/ApiClient.js';
import logger from '../../../core/logger.js';
import { API_ACTION } from '../../../core/constants.js';

export const useStockManagement = (sku: string, fetchColors: () => Promise<void>, fetchSizes: () => Promise<void>) => {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const syncStock = useCallback(async () => {
        setIsLoading(true);
        try {
            const data = await ApiClient.post<{ success: boolean; message?: string; new_total_stock?: number }>(`/api/item_sizes.php?action=${API_ACTION.SYNC_STOCK}`, { item_sku: sku });
            if (data?.success) {
                await Promise.all([fetchColors(), fetchSizes()]);
                return data.new_total_stock;
            } else {
                throw new Error(data?.message || 'Failed to sync stock');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('syncStock failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [sku, fetchColors, fetchSizes]);

    const distributeStockEvenly = useCallback(async () => {
        setIsLoading(true);
        try {
            const data = await ApiClient.post<{ success: boolean; message?: string; new_total_stock?: number }>(`/api/item_sizes.php?action=${API_ACTION.DISTRIBUTE_STOCK}`, { item_sku: sku });
            if (data?.success) {
                await Promise.all([fetchColors(), fetchSizes()]);
                return data.new_total_stock;
            } else {
                throw new Error(data?.message || 'Failed to distribute stock');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('distributeStockEvenly failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [sku, fetchColors, fetchSizes]);

    const ensureColorSizes = useCallback(async () => {
        setIsLoading(true);
        try {
            const data = await ApiClient.post<{ success: boolean; message?: string }>(`/api/item_sizes.php?action=${API_ACTION.ENSURE_COLOR_SIZES}`, { item_sku: sku });
            if (data?.success) {
                await Promise.all([fetchColors(), fetchSizes()]);
                return data;
            } else {
                throw new Error(data?.message || 'Failed to ensure color sizes');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('ensureColorSizes failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [sku, fetchColors, fetchSizes]);

    return { syncStock, distributeStockEvenly, ensureColorSizes, isLoading, error, setError };
};
