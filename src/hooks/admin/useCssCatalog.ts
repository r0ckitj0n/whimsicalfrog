import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { ICssCatalogData } from '../../types/theming.js';
import type { ICssCatalogResponse } from '../../types/admin.js';

// Re-export for backward compatibility
export type { ICssCatalogData } from '../../types/theming.js';
export type { ICssCatalogResponse } from '../../types/admin.js';


export const useCssCatalog = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [data, setData] = useState<ICssCatalogData | null>(null);
    const [error, setError] = useState<string | null>(null);

    const fetchCatalog = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const response = await ApiClient.get<ICssCatalogResponse>('/api/css_rules.php?action=catalog');
            if (response && response.success) {
                setData(response.data);
            } else {
                setError(response?.message || 'Failed to load CSS catalog.');
            }
        } catch (err) {
            logger.error('[CssCatalog] fetch failed', err);
            setError('Unable to load CSS catalog.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchCatalog();
    }, [fetchCatalog]);

    return {
        isLoading,
        data,
        error,
        fetchCatalog
    };
};
