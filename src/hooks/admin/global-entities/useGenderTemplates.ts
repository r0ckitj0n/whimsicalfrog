import { useState, useCallback } from 'react';
import { ApiClient } from '../../../core/ApiClient.js';
import { AUTH } from '../../../core/constants.js';
import logger from '../../../core/logger.js';
import type { IGenderTemplate, IGenderTemplateItem } from '../../../types/theming.js';

export type { IGenderTemplate, IGenderTemplateItem } from '../../../types/theming.js';

export const useGenderTemplates = (fetchAll: () => Promise<void>) => {
    const [genderTemplates, setGenderTemplates] = useState<IGenderTemplate[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchGenderTemplate = useCallback(async (id: number) => {
        try {
            const res = await ApiClient.get<{ success: boolean; template?: IGenderTemplate; data?: { template?: IGenderTemplate } }>(
                `/api/gender_templates.php?action=get_template&template_id=${id}&admin_token=${AUTH.ADMIN_TOKEN}`
            );
            const tpl = (res as any)?.template || (res as any)?.data?.template;
            return (res as any)?.success ? (tpl as IGenderTemplate) : null;
        } catch (err) {
            logger.error('fetchGenderTemplate failed', err);
            return null;
        }
    }, []);

    const saveGenderTemplate = useCallback(async (data: Partial<IGenderTemplate>) => {
        setIsLoading(true);
        try {
            const action = data.id ? 'update_template' : 'create_template';
            const payload: Record<string, unknown> = { ...data };
            if (action === 'update_template' && !('template_id' in payload)) {
                payload.template_id = data.id;
            }
            delete payload.id;

            const res = await ApiClient.post<{ success: boolean; template_id?: number; message?: string }>(
                `/api/gender_templates.php?action=${action}&admin_token=${AUTH.ADMIN_TOKEN}`,
                payload
            );
            if (res?.success) {
                await fetchAll();
                return res;
            }
            throw new Error(res?.message || 'Failed to save gender template');
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [fetchAll]);

    const deleteGenderTemplate = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean }>(
                `/api/gender_templates.php?action=delete_template&admin_token=${AUTH.ADMIN_TOKEN}`,
                { template_id: id }
            );
            if (res?.success) {
                await fetchAll();
                return true;
            }
            return false;
        } catch (err) {
            logger.error('deleteGenderTemplate failed', err);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchAll]);

    const duplicateGenderTemplate = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const template = await fetchGenderTemplate(id);
            if (!template) throw new Error('Template not found');

            const payload: Partial<IGenderTemplate> = {
                template_name: `Copy of ${template.template_name}`.slice(0, 100),
                description: template.description || '',
                category: template.category || 'General',
                genders: (template.genders || []).map((g, idx) => ({
                    gender_name: g.gender_name,
                    display_order: idx + 1
                }))
            };
            return await saveGenderTemplate(payload);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [fetchGenderTemplate, saveGenderTemplate]);

    return {
        genderTemplates,
        setGenderTemplates,
        fetchGenderTemplate,
        saveGenderTemplate,
        deleteGenderTemplate,
        duplicateGenderTemplate,
        isLoading,
        error,
        setError
    };
};

