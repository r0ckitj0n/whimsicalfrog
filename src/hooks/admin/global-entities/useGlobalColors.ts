import { useState, useCallback } from 'react';
import { ApiClient } from '../../../core/ApiClient.js';
import logger from '../../../core/logger.js';
import { API_ACTION } from '../../../core/constants.js';
import type { IGlobalColor } from '../../../types/theming.js';

// Re-export for backward compatibility
export type { IGlobalColor } from '../../../types/theming.js';

export const useGlobalColors = (fetchAll: () => Promise<void>) => {
    const [colors, setColors] = useState<IGlobalColor[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const saveColor = useCallback(async (colorData: Partial<IGlobalColor>) => {
        setIsLoading(true);
        try {
            const isEdit = !!colorData.id;
            const action = isEdit ? API_ACTION.UPDATE_GLOBAL_COLOR : API_ACTION.ADD_GLOBAL_COLOR;
            const res = await ApiClient.post<{ success: boolean; message?: string }>(`/api/global_color_size_management.php?action=${action}`, colorData);
            if (res?.success) {
                await fetchAll();
                return res;
            } else {
                throw new Error(res?.message || 'Failed to save color');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveGlobalColor failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [fetchAll]);

    const deleteColor = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; message?: string }>(`/api/global_color_size_management.php?action=${API_ACTION.DELETE_GLOBAL_COLOR}`, { id });
            if (res?.success) {
                await fetchAll();
                return true;
            } else {
                throw new Error(res?.message || 'Failed to delete color');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('deleteGlobalColor failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchAll]);

    return { colors, setColors, saveColor, deleteColor, isLoading, error, setError };
};
