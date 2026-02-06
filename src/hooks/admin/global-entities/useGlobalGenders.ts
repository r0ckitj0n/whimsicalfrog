import { useState, useCallback } from 'react';
import { ApiClient } from '../../../core/ApiClient.js';
import logger from '../../../core/logger.js';
import { API_ACTION } from '../../../core/constants.js';
import type { IGlobalGender } from '../../../types/theming.js';

// Re-export for backward compatibility
export type { IGlobalGender } from '../../../types/theming.js';

export const useGlobalGenders = (fetchAll: () => Promise<void>) => {
    const [genders, setGenders] = useState<IGlobalGender[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const saveGender = useCallback(async (genderData: Partial<IGlobalGender>) => {
        setIsLoading(true);
        try {
            const isEdit = !!genderData.id;
            const action = isEdit ? API_ACTION.UPDATE_GLOBAL_GENDER : API_ACTION.ADD_GLOBAL_GENDER;
            const res = await ApiClient.post<{ success: boolean; message?: string }>(`/api/global_color_size_management.php?action=${action}`, genderData);
            if (res?.success) {
                await fetchAll();
                return res;
            } else {
                throw new Error(res?.message || 'Failed to save gender');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveGlobalGender failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [fetchAll]);

    const deleteGender = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; message?: string }>(`/api/global_color_size_management.php?action=${API_ACTION.DELETE_GLOBAL_GENDER}`, { id });
            if (res?.success) {
                await fetchAll();
                return true;
            } else {
                throw new Error(res?.message || 'Failed to delete gender');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('deleteGlobalGender failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchAll]);

    return { genders, setGenders, saveGender, deleteGender, isLoading, error, setError };
};
