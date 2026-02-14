import { useState, useCallback } from 'react';
import { ApiClient } from '../../../core/ApiClient.js';
import { AUTH } from '../../../core/constants.js';
import logger from '../../../core/logger.js';
import type { IColorTemplateItem, IColorTemplate } from '../../../types/theming.js';

// Re-export for backward compatibility
export type { IColorTemplateItem, IColorTemplate } from '../../../types/theming.js';

export const useColorTemplates = (fetchAll: () => Promise<void>) => {
    const [colorTemplates, setColorTemplates] = useState<IColorTemplate[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchColorTemplate = useCallback(async (id: number) => {
        try {
            const res = await ApiClient.get<{ success: boolean; template?: IColorTemplate }>(`/api/color_templates.php?action=get_template&template_id=${id}&admin_token=${AUTH.ADMIN_TOKEN}`);
            return res?.success ? res.template : null;
        } catch (err) {
            logger.error('fetchColorTemplate failed', err);
            return null;
        }
    }, []);

    const saveColorTemplate = useCallback(async (data: Partial<IColorTemplate>) => {
        setIsLoading(true);
        try {
            const action = data.id ? 'update_template' : 'create_template';
            // Backend expects template_id for updates.
            const payload: Record<string, unknown> = { ...data };
            if (action === 'update_template' && !('template_id' in payload)) {
                payload.template_id = data.id;
            }
            delete payload.id;

            const res = await ApiClient.post<{ success: boolean; template_id?: number; message?: string }>(`/api/color_templates.php?action=${action}&admin_token=${AUTH.ADMIN_TOKEN}`, payload);
            if (res?.success) {
                await fetchAll();
                return res;
            }
            throw new Error(res?.message || 'Failed to save color template');
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [fetchAll]);

    const deleteColorTemplate = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean }>(`/api/color_templates.php?action=delete_template&admin_token=${AUTH.ADMIN_TOKEN}`, { template_id: id });
            if (res?.success) {
                await fetchAll();
                return true;
            }
            return false;
        } catch (err) {
            logger.error('deleteColorTemplate failed', err);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchAll]);

    const duplicateColorTemplate = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const template = await fetchColorTemplate(id);
            if (!template) throw new Error('Template not found');

            const payload: Partial<IColorTemplate> = {
                template_name: `Copy of ${template.template_name}`.slice(0, 100),
                description: template.description || '',
                category: template.category || 'General',
                colors: (template.colors || []).map((c, idx) => ({
                    color_name: c.color_name,
                    color_code: c.color_code,
                    display_order: idx + 1
                }))
            };
            return await saveColorTemplate(payload);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [fetchColorTemplate, saveColorTemplate]);

    return { colorTemplates, setColorTemplates, fetchColorTemplate, saveColorTemplate, deleteColorTemplate, duplicateColorTemplate, isLoading, error, setError };
};
