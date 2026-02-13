import { useCallback, useEffect, useState } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IInventoryOptionLink } from '../../types/inventoryOptions.js';
import type { IEffectiveCascadeSettingsResponse } from '../../types/inventoryOptions.js';

type EffectiveLinksResponse = { success: boolean; links: IInventoryOptionLink[]; error?: string; message?: string };

export const useEffectiveInventoryOptions = (sku: string) => {
    const [cascade, setCascade] = useState<IEffectiveCascadeSettingsResponse | null>(null);
    const [links, setLinks] = useState<IInventoryOptionLink[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const refresh = useCallback(async () => {
        if (!sku) return;
        setIsLoading(true);
        setError(null);
        try {
            const [cRes, lRes] = await Promise.all([
                ApiClient.get<IEffectiveCascadeSettingsResponse>('/api/inventory_option_cascade.php', { action: 'get_effective', item_sku: sku }),
                ApiClient.get<EffectiveLinksResponse>('/api/inventory_option_links.php', { action: 'get_effective', item_sku: sku }),
            ]);

            if (cRes?.success) setCascade(cRes);
            else setCascade(null);

            if (lRes?.success) setLinks(lRes.links || []);
            else setLinks([]);

            if (!cRes?.success || !lRes?.success) {
                setError(cRes?.error || lRes?.error || 'Failed to load effective inventory options');
            }
        } catch (err) {
            logger.error('[useEffectiveInventoryOptions] refresh failed', err);
            setError('Failed to load effective inventory options');
        } finally {
            setIsLoading(false);
        }
    }, [sku]);

    useEffect(() => {
        void refresh();
    }, [refresh]);

    return { cascade, links, isLoading, error, refresh };
};

