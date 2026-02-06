import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type {
    IInventoryArchiveMetrics,
    IInventoryAuditItem,
    IInventoryAuditData,
    IInventoryArchiveItem,
    IInventoryArchiveCategory,
    IInventoryArchiveResponse
} from '../../types/inventory.js';

// Re-export for backward compatibility
export type {
    IInventoryArchiveMetrics,
    IInventoryAuditItem,
    IInventoryAuditData,
    IInventoryArchiveItem,
    IInventoryArchiveCategory,
    IInventoryArchiveResponse
} from '../../types/inventory.js';



export const useInventoryArchive = () => {
    const [metrics, setMetrics] = useState<IInventoryArchiveMetrics | null>(null);
    const [categories, setCategories] = useState<IInventoryArchiveCategory[]>([]);
    const [items, setItems] = useState<IInventoryArchiveItem[]>([]);
    const [audit, setAudit] = useState<IInventoryAuditData>({
        missing_images: [],
        pricing_alerts: [],
        stock_issues: [],
        content_issues: []
    });
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchArchive = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const response = await ApiClient.request<IInventoryArchiveResponse>('/api/inventory_archive.php');
            if (response && response.success) {
                setMetrics(response.metrics || null);
                setCategories(Array.isArray(response.categories) ? response.categories : []);
                setItems(Array.isArray(response.items) ? response.items : []);
                setAudit(response.audit || {
                    missing_images: [],
                    pricing_alerts: [],
                    stock_issues: [],
                    content_issues: []
                });
            } else {
                setError(response?.message || 'Failed to load archived inventory.');
            }
        } catch (err) {
            logger.error('[InventoryArchive] fetch failed', err);
            setError('Unable to load archived inventory. Please try again.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const restoreItem = async (sku: string) => {
        try {
            const res = await ApiClient.request<{ success: boolean }>((`/functions/process_inventory_update.php?action=restore&sku=${encodeURIComponent(sku)}`), { method: 'DELETE' });
            if (res && res.success) {
                await fetchArchive();
                return true;
            }
        } catch (err) {
            logger.error('[InventoryArchive] restore failed', err);
        }
        return false;
    };

    const nukeItem = async (sku: string) => {
        try {
            const res = await ApiClient.request<{ success: boolean }>((`/functions/process_inventory_update.php?action=nuke&sku=${encodeURIComponent(sku)}`), { method: 'DELETE' });
            if (res && res.success) {
                await fetchArchive();
                return true;
            }
        } catch (err) {
            logger.error('[InventoryArchive] nuke failed', err);
        }
        return false;
    };

    return {
        metrics,
        categories,
        items,
        audit,
        isLoading,
        error,
        fetchArchive,
        restoreItem,
        nukeItem
    };
};
