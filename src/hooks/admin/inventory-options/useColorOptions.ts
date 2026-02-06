import { useState, useCallback } from 'react';
import { ApiClient } from '../../../core/ApiClient.js';
import logger from '../../../core/logger.js';
import { IItemColor } from '../../../types/index.js';
import { API_ACTION } from '../../../core/constants.js';

export const useColorOptions = (sku: string) => {
    const [colors, setColors] = useState<IItemColor[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchColors = useCallback(async () => {
        if (!sku) return;
        setIsLoading(true);
        try {
            const data = await ApiClient.get<{ success: boolean; colors?: IItemColor[]; message?: string }>(`/api/item_colors.php?action=${API_ACTION.GET_COLORS}&item_sku=${sku}`);
            if (data?.success) {
                setColors(data.colors || []);
            } else {
                setError(data?.message || 'Failed to load colors');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchColors failed', err);
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, [sku]);

    const saveColor = useCallback(async (colorData: Partial<IItemColor>) => {
        setIsLoading(true);
        try {
            const isEdit = !!colorData.id;
            const action = isEdit ? API_ACTION.UPDATE_COLOR : API_ACTION.ADD_COLOR;
            const payload: Record<string, unknown> = { ...colorData, item_sku: sku };
            if (isEdit) payload.color_id = colorData.id;

            const data = await ApiClient.post<{ success: boolean; message?: string }>(`/api/item_colors.php?action=${action}`, payload);
            if (data?.success) {
                await fetchColors();
                return data;
            } else {
                throw new Error(data?.message || 'Failed to save color');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveColor failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [sku, fetchColors]);

    const deleteColor = useCallback(async (color_id: number) => {
        setIsLoading(true);
        try {
            const data = await ApiClient.post<{ success: boolean; message?: string }>(`/api/item_colors.php?action=${API_ACTION.DELETE_COLOR}`, { color_id: color_id });
            if (data?.success) {
                await fetchColors();
                return true;
            } else {
                throw new Error(data?.message || 'Failed to delete color');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('deleteColor failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchColors]);

    return { colors, setColors, fetchColors, saveColor, deleteColor, isLoading, error, setError };
};
