import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IIconMapping } from '../../types/dashboard.js';
import type { IIconMapResponse } from '../../types/admin.js';

// Re-export for backward compatibility
export type { IIconMapping } from '../../types/dashboard.js';
export type { IIconMapResponse } from '../../types/admin.js';


export const useActionIcons = () => {
    const [iconMap, setIconMap] = useState<Record<string, string>>({});
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchIconMap = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IIconMapResponse>('/api/admin_icon_map.php', { action: 'get_map' });
            if (res && res.map) {
                setIconMap(res.map);
            }
        } catch (err) {
            logger.error('[useActionIcons] fetch failed', err);
            setError('Failed to load icon mappings');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const saveIconMap = async (map: Record<string, string>) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; error?: string }>('/api/admin_icon_map.php', {
                action: 'set_map',
                map
            });

            if (res && res.success) {
                setIconMap(map);
                // Refresh icon CSS on the page
                const linkId = 'admin-dynamic-icon-css';
                const link = (document.getElementById(linkId) || document.querySelector('link[href*="admin_icon_map.php"]')) as HTMLLinkElement;
                if (link) {
                    const url = new URL(link.href);
                    url.searchParams.set('v', Date.now().toString());
                    link.href = url.toString();
                }
                return { success: true };
            }
            return { success: false, error: res?.error || 'Save failed' };
        } catch (err) {
            logger.error('[useActionIcons] save failed', err);
            return { success: false, error: 'Network error' };
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        fetchIconMap();
    }, [fetchIconMap]);

    return {
        iconMap,
        isLoading,
        error,
        saveIconMap,
        refresh: fetchIconMap
    };
};
