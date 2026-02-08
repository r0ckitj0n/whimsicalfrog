import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IInventoryItem, IInventoryFilters, IInventoryResponse, ICommonApiResponse, IAddInventoryResponse } from '../../types/inventory.js';

// Re-export for backward compatibility
export type { IInventoryItem, IInventoryFilters, IInventoryResponse, ICommonApiResponse } from '../../types/inventory.js';


export const useInventory = (initialFilters: IInventoryFilters = { search: '', category: '', stock: '', status: 'active' }) => {
    const [items, setItems] = useState<IInventoryItem[]>([]);
    const [categories, setCategories] = useState<string[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [filters, setFilters] = useState<IInventoryFilters>(initialFilters);
    const [sort, setSort] = useState({ column: 'sku', direction: 'asc' as 'asc' | 'desc' });

    const fetchInventory = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const params = {
                search: filters.search,
                category: filters.category,
                stock: filters.stock,
                status: filters.status,
                sort: sort.column,
                dir: sort.direction
            };
            const res = await ApiClient.get<IInventoryResponse>('/api/inventory.php', params);
            if (res && res.success && Array.isArray(res.data)) {
                setItems(res.data);
            } else {
                setError(res?.error || 'Failed to load inventory');
            }
        } catch (err) {
            logger.error('[useInventory] fetch failed', err);
            setError('Unable to load inventory data');
        } finally {
            setIsLoading(false);
        }
    }, [filters, sort]);

    const fetchCategories = useCallback(async () => {
        try {
            const res = await ApiClient.get<string[]>('/api/get_categories.php');
            if (res && Array.isArray(res)) {
                setCategories(res);
            }
        } catch (err) {
            logger.error('[useInventory] fetchCategories failed', err);
        }
    }, []);

    const deleteItem = async (sku: string) => {
        try {
            const res = await ApiClient.post<ICommonApiResponse>('/api/database_tables.php?action=delete_row', {
                table: 'items',
                row_data: { sku }
            });
            if (res && res.success) {
                setItems(prev => prev.filter(item => item.sku !== sku));
                return { success: true };
            }
            const isForeignKeyError = res?.error?.includes('SQLSTATE[23000]') || res?.error?.includes('Integrity constraint violation');
            return {
                success: false,
                error: res?.error || 'Delete failed',
                code: isForeignKeyError ? 'ERR_FOREIGN_KEY' : undefined
            };
        } catch (err: unknown) {
            logger.error('[useInventory] delete failed', err);
            const errMsg = err instanceof Error ? err.message : '';
            const isForeignKeyError = errMsg.includes('SQLSTATE[23000]') || errMsg.includes('Integrity constraint violation');

            return {
                success: false,
                error: isForeignKeyError ? 'This item cannot be deleted because it is part of existing orders.' : (errMsg || 'Network error'),
                code: isForeignKeyError ? 'ERR_FOREIGN_KEY' : undefined
            };
        }
    };

    const updateCell = async (sku: string, column: string, value: string | number | boolean | null) => {
        try {
            const res = await ApiClient.post<ICommonApiResponse>('/api/database_tables.php?action=update_cell', {
                table: 'items',
                column,
                new_value: value,
                row_data: { sku: sku }
            });
            if (!res?.success) {
                return { success: false, error: res?.error || 'Update failed' };
            }

            // The API returns success for both updated and no-op writes; keep local state synced.
            setItems(prev => prev.map(item => item.sku === sku ? { ...item, [column]: value } : item));
            return { success: true };
        } catch (err) {
            logger.error('[useInventory] update failed', err);
            return { success: false, error: 'Network error' };
        }
    };

    const addItem = async (itemData: Partial<IInventoryItem>) => {
        try {
            const res = await ApiClient.post<IAddInventoryResponse>('/api/add_inventory.php', itemData);
            if (res && res.success) {
                await fetchInventory();
                return { success: true };
            }
            const detailMessage = typeof res?.details === 'string' ? res.details : '';
            return { success: false, error: detailMessage || res?.error || 'Add failed' };
        } catch (err) {
            logger.error('[useInventory] add failed', err);
            const message = err instanceof Error ? err.message : 'Network error';
            return { success: false, error: message };
        }
    };

    useEffect(() => {
        fetchInventory();
        fetchCategories();
    }, [fetchInventory, fetchCategories]);

    const refresh = useCallback(() => {
        fetchInventory();
        fetchCategories();
    }, [fetchInventory, fetchCategories]);

    return {
        items,
        categories,
        isLoading,
        error,
        filters,
        setFilters,
        sort,
        setSort,
        deleteItem,
        updateCell,
        addItem,
        refresh
    };
};
