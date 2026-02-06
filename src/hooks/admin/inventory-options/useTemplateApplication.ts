import { useState, useCallback } from 'react';
import { ApiClient } from '../../../core/ApiClient.js';
import logger from '../../../core/logger.js';
import { API_ACTION } from '../../../core/constants.js';

export const useTemplateApplication = (sku: string, fetchColors: () => Promise<void>, fetchSizes: () => Promise<void>) => {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const applyColorTemplate = useCallback(async (templateId: number, replaceExisting: boolean, defaultStock: number) => {
        setIsLoading(true);
        try {
            const data = await ApiClient.post<{ success: boolean; message?: string }>(`/api/color_templates.php?action=${API_ACTION.APPLY_COLOR_TEMPLATE}`, {
                template_id: templateId,
                item_sku: sku,
                replace_existing: replaceExisting,
                default_stock: defaultStock
            });
            if (data?.success) {
                await fetchColors();
                return data;
            } else {
                throw new Error(data?.message || 'Failed to apply color template');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('applyColorTemplate failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [sku, fetchColors]);

    const applySizeTemplate = useCallback(async (params: {
        templateId: number;
        applyMode: 'general' | 'color_specific';
        color_id?: number | null;
        replaceExisting: boolean;
        defaultStock: number;
    }) => {
        setIsLoading(true);
        try {
            const data = await ApiClient.post<{ success: boolean; message?: string }>('/api/size_templates.php?action=apply_to_item', {
                template_id: params.templateId,
                item_sku: sku,
                apply_mode: params.applyMode,
                color_id: params.color_id,
                replace_existing: params.replaceExisting,
                default_stock: params.defaultStock
            });
            if (data?.success) {
                await fetchSizes();
                return data;
            } else {
                throw new Error(data?.message || 'Failed to apply size template');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('applySizeTemplate failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [sku, fetchSizes]);

    return { applyColorTemplate, applySizeTemplate, isLoading, error, setError };
};
