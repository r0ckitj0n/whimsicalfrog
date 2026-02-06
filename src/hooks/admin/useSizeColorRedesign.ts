import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import type {
    IRedesignAnalysis,
    IProposedColor,
    IProposedSize,
    IRedesignProposal,
    IRedesignItem
} from '../../types/inventory.js';

// Re-export for backward compatibility
export type {
    IRedesignAnalysis,
    IProposedColor,
    IProposedSize,
    IRedesignProposal,
    IRedesignItem
} from '../../types/inventory.js';

interface RedesignResponse<T = Record<string, unknown>> {
    success: boolean;
    message?: string;
    data?: T;
    analysis?: IRedesignAnalysis;
    proposedSizes?: IProposedSize[];
    is_backwards?: boolean;
}

interface MigrationPayload {
    item_sku: string;
    new_structure: IProposedSize[];
    preserve_stock: boolean;
    dry_run: boolean;
}

export const useSizeColorRedesign = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const apiCall = useCallback(async (
        action: string,
        params: Record<string, string | number | boolean> = {},
        body: unknown = null
    ) => {
        setIsLoading(true);
        setError(null);
        try {
            const searchParams = new URLSearchParams({ action, ...params });
            const url = `/api/redesign_size_color_system.php?${searchParams.toString()}`;

            let response: RedesignResponse | null = null;
            if (body) {
                response = await ApiClient.post<RedesignResponse>(url, body);
            } else {
                response = await ApiClient.get<RedesignResponse>(url);
            }

            if (response && response.success) {
                return response;
            } else {
                setError(response?.message || 'Action failed');
                return null;
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred');
            return null;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const fetchItems = useCallback(async (): Promise<IRedesignItem[]> => {
        setIsLoading(true);
        try {
            const res = await ApiClient.get<{ success: boolean; data: IRedesignItem[] }>('/api/get_items.php');
            return res?.success ? res.data : [];
        } catch (err) {
            setError('Failed to fetch items');
            return [];
        } finally {
            setIsLoading(false);
        }
    }, []);

    const checkIfBackwards = (sku: string) => apiCall('check_if_backwards', { item_sku: sku });
    const analyzeStructure = (sku: string) => apiCall('analyze_current_structure', { item_sku: sku });
    const proposeStructure = (sku: string) => apiCall('propose_new_structure', { item_sku: sku });
    const getRestructuredView = (sku: string) => apiCall('get_restructured_view', { item_sku: sku });

    const migrateStructure = (sku: string, newStructure: IProposedSize[], preserveStock: boolean, dryRun: boolean) => {
        const payload: MigrationPayload = {
            item_sku: sku,
            new_structure: newStructure,
            preserve_stock: preserveStock,
            dry_run: dryRun
        };
        return apiCall('migrate_to_new_structure', {}, payload);
    };

    return {
        isLoading,
        error,
        fetchItems,
        checkIfBackwards,
        analyzeStructure,
        proposeStructure,
        getRestructuredView,
        migrateStructure
    };
};
