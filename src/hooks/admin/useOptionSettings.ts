import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IOptionSettings, IOptionSettingsResponse } from '../../types/settings.js';

// Re-export for backward compatibility
export type { IOptionSettings, IOptionSettingsResponse } from '../../types/settings.js';



export const useOptionSettings = (sku: string) => {
    const [settings, setSettings] = useState<IOptionSettings>({
        cascade_order: ['gender', 'size', 'color'],
        enabled_dimensions: ['gender', 'size', 'color'],
        grouping_rules: {}
    });
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchSettings = useCallback(async () => {
        if (!sku) return;
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<any>('/api/item_options.php', {
                action: 'get_settings',
                item_sku: sku,
                wf_dev_admin: 1
            });

            if (res) {
                const data = res.settings || res.data || res;
                setSettings({
                    cascade_order: Array.isArray(data.cascade_order) ? data.cascade_order : ['gender', 'size', 'color'],
                    enabled_dimensions: Array.isArray(data.enabled_dimensions) ? data.enabled_dimensions : ['gender', 'size', 'color'],
                    grouping_rules: data.grouping_rules || {}
                });
            }
        } catch (err) {
            logger.warn('[useOptionSettings] fetch failed', err);
            setError('Failed to load settings');
        } finally {
            setIsLoading(false);
        }
    }, [sku]);

    const saveSettings = async (newSettings: IOptionSettings) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<any>('/api/item_options.php', {
                action: 'update_settings',
                item_sku: sku,
                ...newSettings
            });

            if (res) {
                setSettings(newSettings);
                return { success: true };
            }
            return { success: false, error: 'Save failed' };
        } catch (err) {
            logger.error('[useOptionSettings] save failed', err);
            return { success: false, error: 'Network error' };
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        fetchSettings();
    }, [fetchSettings]);

    return {
        settings,
        isLoading,
        error,
        saveSettings,
        refresh: fetchSettings
    };
};
