import { useCallback, useEffect, useState } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type {
    IMaterial,
    IMaterialsListResponse,
    ICreateMaterialRequest,
    IUpdateMaterialRequest,
    IDeleteMaterialRequest,
} from '../../types/inventoryOptions.js';

type IActionResponse = { success: boolean; message?: string; error?: string; id?: number };

export const useMaterials = () => {
    const [materials, setMaterials] = useState<IMaterial[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchMaterials = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IMaterialsListResponse>('/api/materials.php', { action: 'list' });
            if (res?.success) {
                setMaterials(res.materials || []);
            } else {
                setError(res?.error || res?.message || 'Failed to load materials');
            }
        } catch (err) {
            logger.error('[useMaterials] fetchMaterials failed', err);
            setError('Failed to load materials');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const createMaterial = useCallback(async (payload: Omit<ICreateMaterialRequest, 'action'>) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<IActionResponse>('/api/materials.php', { action: 'create', ...payload });
            if (res?.success) {
                await fetchMaterials();
                return { success: true as const, id: res.id };
            }
            const msg = res?.error || res?.message || 'Failed to create material';
            setError(msg);
            return { success: false as const, error: msg };
        } catch (err) {
            logger.error('[useMaterials] createMaterial failed', err);
            const msg = err instanceof Error ? err.message : 'Network error';
            setError(msg);
            return { success: false as const, error: msg };
        } finally {
            setIsLoading(false);
        }
    }, [fetchMaterials]);

    const updateMaterial = useCallback(async (payload: Omit<IUpdateMaterialRequest, 'action'>) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<IActionResponse>('/api/materials.php', { action: 'update', ...payload });
            if (res?.success) {
                await fetchMaterials();
                return { success: true as const };
            }
            const msg = res?.error || res?.message || 'Failed to update material';
            setError(msg);
            return { success: false as const, error: msg };
        } catch (err) {
            logger.error('[useMaterials] updateMaterial failed', err);
            const msg = err instanceof Error ? err.message : 'Network error';
            setError(msg);
            return { success: false as const, error: msg };
        } finally {
            setIsLoading(false);
        }
    }, [fetchMaterials]);

    const deleteMaterial = useCallback(async (payload: Omit<IDeleteMaterialRequest, 'action'>) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<IActionResponse>('/api/materials.php', { action: 'delete', ...payload });
            if (res?.success) {
                await fetchMaterials();
                return { success: true as const };
            }
            const msg = res?.error || res?.message || 'Failed to delete material';
            setError(msg);
            return { success: false as const, error: msg };
        } catch (err) {
            logger.error('[useMaterials] deleteMaterial failed', err);
            const msg = err instanceof Error ? err.message : 'Network error';
            setError(msg);
            return { success: false as const, error: msg };
        } finally {
            setIsLoading(false);
        }
    }, [fetchMaterials]);

    useEffect(() => {
        void fetchMaterials();
    }, [fetchMaterials]);

    return { materials, isLoading, error, fetchMaterials, createMaterial, updateMaterial, deleteMaterial };
};

