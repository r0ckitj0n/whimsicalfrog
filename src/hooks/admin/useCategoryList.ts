import { useCallback, useEffect, useState } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';

export interface ICategoryLite {
    id: number;
    name: string;
}

type CategoriesResponse = { success: boolean; categories?: Array<{ id?: number; name?: string; category?: string }>; data?: { categories?: Array<{ id?: number; name?: string; category?: string }> }; message?: string; error?: string };

export const useCategoryList = () => {
    const [categories, setCategories] = useState<ICategoryLite[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchCategories = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<CategoriesResponse>('/api/categories.php', { action: 'list' });
            const root = (res && 'data' in res && res.data) ? res.data : res;
            const raw = (root && 'categories' in root && Array.isArray(root.categories)) ? root.categories : [];
            const normalized = raw
                .map((c) => ({
                    id: Number(c.id || 0),
                    name: String(c.name || c.category || '').trim(),
                }))
                .filter((c) => c.id > 0 && c.name !== '');

            setCategories(normalized);
        } catch (err) {
            logger.error('[useCategoryList] fetch failed', err);
            setError('Failed to load categories');
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        void fetchCategories();
    }, [fetchCategories]);

    return { categories, isLoading, error, fetchCategories };
};

