import { useCallback, useEffect, useState } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type {
    IInventoryOptionLink,
    IInventoryOptionLinksListResponse,
    IAddInventoryOptionLinkRequest,
    IDeleteInventoryOptionLinkRequest,
    IClearInventoryOptionLinksForOptionRequest,
    IInventoryOptionLinkActionResponse,
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

    const addLink = useCallback(async (payload: Omit<IAddInventoryOptionLinkRequest, 'action'>) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<IInventoryOptionLinkActionResponse>('/api/inventory_option_links.php', { action: 'add', ...payload });
            if (res?.success) {
                await fetchLinks();
                return { success: true as const, id: res.id };
            }
            const msg = res?.error || res?.message || 'Failed to add link';
            setError(msg);
            return { success: false as const, error: msg };
        } catch (err) {
            logger.error('[useInventoryOptionLinks] addLink failed', err);
            const msg = err instanceof Error ? err.message : 'Network error';
            setError(msg);
            return { success: false as const, error: msg };
        } finally {
            setIsLoading(false);
        }
    }, [fetchLinks]);

    const deleteLink = useCallback(async (payload: Omit<IDeleteInventoryOptionLinkRequest, 'action'>) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<IInventoryOptionLinkActionResponse>('/api/inventory_option_links.php', { action: 'delete', ...payload });
            if (res?.success) {
                await fetchLinks();
                return { success: true as const };
            }
            const msg = res?.error || res?.message || 'Failed to delete link';
            setError(msg);
            return { success: false as const, error: msg };
        } catch (err) {
            logger.error('[useInventoryOptionLinks] deleteLink failed', err);
            const msg = err instanceof Error ? err.message : 'Network error';
            setError(msg);
            return { success: false as const, error: msg };
        } finally {
            setIsLoading(false);
        }
    }, [fetchLinks]);

    const clearOptionLinks = useCallback(async (payload: Omit<IClearInventoryOptionLinksForOptionRequest, 'action'>) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<IInventoryOptionLinkActionResponse>('/api/inventory_option_links.php', { action: 'clear_option', ...payload });
            if (res?.success) {
                await fetchLinks();
                return { success: true as const };
            }
            const msg = res?.error || res?.message || 'Failed to clear option links';
            setError(msg);
            return { success: false as const, error: msg };
        } catch (err) {
            logger.error('[useInventoryOptionLinks] clearOptionLinks failed', err);
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

    return { links, isLoading, error, fetchLinks, addLink, deleteLink, clearOptionLinks };
};
