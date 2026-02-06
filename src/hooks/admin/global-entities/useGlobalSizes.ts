import { useState, useCallback } from 'react';
import { ApiClient } from '../../../core/ApiClient.js';
import logger from '../../../core/logger.js';
import { API_ACTION } from '../../../core/constants.js';
import type { IGlobalSize } from '../../../types/theming.js';

// Re-export for backward compatibility
export type { IGlobalSize } from '../../../types/theming.js';

export const useGlobalSizes = (fetchAll: () => Promise<void>) => {
    const [sizes, setSizes] = useState<IGlobalSize[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const saveSize = useCallback(async (sizeData: Partial<IGlobalSize>) => {
        setIsLoading(true);
        try {
            const isEdit = !!sizeData.id;
            const action = isEdit ? API_ACTION.UPDATE_GLOBAL_SIZE : API_ACTION.ADD_GLOBAL_SIZE;
            const res = await ApiClient.post<{ success: boolean; message?: string }>(`/api/global_color_size_management.php?action=${action}`, sizeData);
            if (res?.success) {
                await fetchAll();
                return res;
            } else {
                throw new Error(res?.message || 'Failed to save size');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveGlobalSize failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [fetchAll]);

    const deleteSize = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; message?: string }>(`/api/global_color_size_management.php?action=${API_ACTION.DELETE_GLOBAL_SIZE}`, { id });
            if (res?.success) {
                await fetchAll();
                return true;
            } else {
                throw new Error(res?.message || 'Failed to delete size');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('deleteGlobalSize failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchAll]);

    return { sizes, setSizes, saveSize, deleteSize, isLoading, error, setError };
};
