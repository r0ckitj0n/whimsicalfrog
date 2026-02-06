import { useState, useCallback } from 'react';
import { ApiClient } from '../../../core/ApiClient.js';
import logger from '../../../core/logger.js';
import { IItemSize } from '../../../types/index.js';
import { API_ACTION } from '../../../core/constants.js';

export const useSizeOptions = (sku: string) => {
    const [sizes, setSizes] = useState<IItemSize[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchSizes = useCallback(async (color_id: number | 'general' | null = null) => {
        if (!sku) return;
        setIsLoading(true);
        try {
            let url = `/api/item_sizes.php?action=${API_ACTION.GET_SIZES}&item_sku=${sku}`;
            if (color_id === 'general') {
                url += '&color_id=0';
            } else if (color_id !== null) {
                url += `&color_id=${color_id}`;
            }
            const data = await ApiClient.get<{ success: boolean; sizes?: IItemSize[]; message?: string }>(url);
            if (data?.success) {
                setSizes(data.sizes || []);
            } else {
                setError(data?.message || 'Failed to load sizes');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchSizes failed', err);
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, [sku]);

    const saveSize = useCallback(async (sizeData: Partial<IItemSize>) => {
        setIsLoading(true);
        try {
            const isEdit = !!sizeData.id;
            const action = isEdit ? API_ACTION.UPDATE_SIZE : API_ACTION.ADD_SIZE;
            
            // Get current size data to ensure all required fields are present for update
            let payload: Record<string, unknown>;
            if (isEdit) {
                const currentSize = sizes.find(s => s.id === sizeData.id);
                payload = { 
                    ...currentSize,
                    ...sizeData, 
                    size_id: sizeData.id,
                    item_sku: sku 
                };
            } else {
                payload = { ...sizeData, item_sku: sku };
            }

            const data = await ApiClient.post<{ success: boolean; message?: string }>(`/api/item_sizes.php?action=${action}`, payload);
            if (data?.success) {
                await fetchSizes();
                return data;
            } else {
                throw new Error(data?.message || 'Failed to save size');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveSize failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [sku, fetchSizes, sizes]);

    const deleteSize = useCallback(async (sizeId: number) => {
        setIsLoading(true);
        try {
            const data = await ApiClient.post<{ success: boolean; message?: string }>(`/api/item_sizes.php?action=${API_ACTION.DELETE_SIZE}`, { size_id: sizeId });
            if (data?.success) {
                await fetchSizes();
                return true;
            } else {
                throw new Error(data?.message || 'Failed to delete size');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('deleteSize failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchSizes]);

    return { sizes, setSizes, fetchSizes, saveSize, deleteSize, isLoading, error, setError };
};
