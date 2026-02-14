import { useState, useCallback } from 'react';
import { ApiClient } from '../../../core/ApiClient.js';
import { AUTH } from '../../../core/constants.js';
import logger from '../../../core/logger.js';
import type { ISizeTemplateItem, ISizeTemplate } from '../../../types/theming.js';

// Re-export for backward compatibility
export type { ISizeTemplateItem, ISizeTemplate } from '../../../types/theming.js';

export const useSizeTemplates = (fetchAll: () => Promise<void>) => {
    const [sizeTemplates, setSizeTemplates] = useState<ISizeTemplate[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchSizeTemplate = useCallback(async (id: number) => {
        try {
            const res = await ApiClient.get<{ success: boolean; template?: ISizeTemplate }>(`/api/size_templates.php?action=get_template&template_id=${id}&admin_token=${AUTH.ADMIN_TOKEN}`);
            return res?.success ? res.template : null;
        } catch (err) {
            logger.error('fetchSizeTemplate failed', err);
            return null;
        }
    }, []);

    const saveSizeTemplate = useCallback(async (data: Partial<ISizeTemplate>) => {
        setIsLoading(true);
        try {
            const action = data.id ? 'update_template' : 'create_template';
            // Backend expects template_id for updates.
            const payload: Record<string, unknown> = { ...data };
            if (action === 'update_template' && !('template_id' in payload)) {
                payload.template_id = data.id;
            }
            delete payload.id;

            const res = await ApiClient.post<{ success: boolean; template_id?: number; message?: string }>(`/api/size_templates.php?action=${action}&admin_token=${AUTH.ADMIN_TOKEN}`, payload);
            if (res?.success) {
                await fetchAll();
                return res;
            }
            throw new Error(res?.message || 'Failed to save size template');
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [fetchAll]);

    const deleteSizeTemplate = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean }>(`/api/size_templates.php?action=delete_template&admin_token=${AUTH.ADMIN_TOKEN}`, { template_id: id });
            if (res?.success) {
                await fetchAll();
                return true;
            }
            return false;
        } catch (err) {
            logger.error('deleteSizeTemplate failed', err);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchAll]);

    const duplicateSizeTemplate = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const template = await fetchSizeTemplate(id);
            if (!template) throw new Error('Template not found');

            const payload: Partial<ISizeTemplate> = {
                template_name: `Copy of ${template.template_name}`.slice(0, 100),
                description: template.description || '',
                category: template.category || 'General',
                sizes: (template.sizes || []).map((s, idx) => ({
                    size_name: s.size_name,
                    size_code: s.size_code,
                    price_adjustment: s.price_adjustment || 0,
                    display_order: idx
                }))
            };
            return await saveSizeTemplate(payload);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [fetchSizeTemplate, saveSizeTemplate]);

    return { sizeTemplates, setSizeTemplates, fetchSizeTemplate, saveSizeTemplate, deleteSizeTemplate, duplicateSizeTemplate, isLoading, error, setError };
};
