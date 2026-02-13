import { useCallback, useEffect, useState } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type {
    IInventoryOptionLink,
    IInventoryOptionLinksListResponse,
    IUpsertInventoryOptionLinkRequest,
    IClearInventoryOptionLinkRequest,
    IUpsertInventoryOptionLinkResponse,
} from '../../types/inventoryOptions.js';

export const useInventoryOptionLinks = () => {
    const [links, setLinks] = useState<IInventoryOptionLink[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchLinks = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IInventoryOptionLinksListResponse>('/api/inventory_option_links.php', { action: 'list' });
            if (res?.success) {
                setLinks(res.links || []);
            } else {
                setError(res?.error || res?.message || 'Failed to load inventory option links');
            }
        } catch (err) {
            logger.error('[useInventoryOptionLinks] fetchLinks failed', err);
            setError('Failed to load inventory option links');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const upsertLink = useCallback(async (payload: Omit<IUpsertInventoryOptionLinkRequest, 'action'>) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<IUpsertInventoryOptionLinkResponse>('/api/inventory_option_links.php', { action: 'upsert', ...payload });
            if (res?.success) {
                await fetchLinks();
                return { success: true as const };
            }
            const msg = res?.error || res?.message || 'Failed to save link';
            setError(msg);
            return { success: false as const, error: msg };
        } catch (err) {
            logger.error('[useInventoryOptionLinks] upsertLink failed', err);
            const msg = err instanceof Error ? err.message : 'Network error';
            setError(msg);
            return { success: false as const, error: msg };
        } finally {
            setIsLoading(false);
        }
    }, [fetchLinks]);

    const clearLink = useCallback(async (payload: Omit<IClearInventoryOptionLinkRequest, 'action'>) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<IUpsertInventoryOptionLinkResponse>('/api/inventory_option_links.php', { action: 'clear', ...payload });
            if (res?.success) {
                await fetchLinks();
                return { success: true as const };
            }
            const msg = res?.error || res?.message || 'Failed to clear link';
            setError(msg);
            return { success: false as const, error: msg };
        } catch (err) {
            logger.error('[useInventoryOptionLinks] clearLink failed', err);
            const msg = err instanceof Error ? err.message : 'Network error';
            setError(msg);
            return { success: false as const, error: msg };
        } finally {
            setIsLoading(false);
        }
    }, [fetchLinks]);

    useEffect(() => {
        void fetchLinks();
    }, [fetchLinks]);

    return { links, isLoading, error, fetchLinks, upsertLink, clearLink };
};

