import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IShoppingCartSettings } from '../../types/commerce.js';

// Re-export for backward compatibility
export type { IShoppingCartSettings } from '../../types/commerce.js';

export const useShoppingCartSettings = () => {
    const [settings, setSettings] = useState<IShoppingCartSettings>({
        open_cart_on_add: true,
        merge_duplicates: true,
        show_upsells: true,
        confirm_clear_cart: true,
        minimum_checkout_total: 0
    });
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchSettings = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<any>('/api/business_settings.php?category=shopping_cart');
            if (res?.success && res.settings) {
                setSettings(prev => ({
                    ...prev,
                    ...res.settings
                }));
            }
        } catch (err) {
            logger.error('[useShoppingCartSettings] fetch failed', err);
            setError('Failed to load cart settings');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const saveSettings = async (newSettings: Partial<IShoppingCartSettings>) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<any>('/api/business_settings.php', {
                action: 'upsert_settings',
                category: 'shopping_cart',
                settings: newSettings
            });
            if (res?.success) {
                setSettings(prev => ({ ...prev, ...newSettings }));
                return true;
            }
            return false;
        } catch (err) {
            logger.error('[useShoppingCartSettings] save failed', err);
            setError('Failed to save cart settings');
            return false;
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        fetchSettings();
    }, [fetchSettings]);

    return { settings, isLoading, error, fetchSettings, saveSettings };
};
