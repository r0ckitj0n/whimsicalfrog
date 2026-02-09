import { useCallback, useState } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type {
    IAIPromptTemplate,
    IAIPromptVariable,
    IAIPromptTemplatesResponse,
    IAIPromptVariablesResponse,
    IAIPromptTemplateActionResponse,
    IAIGenerationHistoryRow
} from '../../types/ai-prompts.js';

export const useAIPromptTemplates = () => {
    const [templates, setTemplates] = useState<IAIPromptTemplate[]>([]);
    const [variables, setVariables] = useState<IAIPromptVariable[]>([]);
    const [history, setHistory] = useState<IAIGenerationHistoryRow[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchTemplates = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IAIPromptTemplatesResponse>('/api/ai_prompt_templates.php', { action: 'list_templates' });
            if (res?.success) {
                setTemplates(res.templates || []);
            } else {
                setError(res?.error || 'Failed to load templates');
            }
        } catch (err) {
            logger.error('[useAIPromptTemplates] fetchTemplates failed', err);
            setError('Unable to load templates');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const fetchVariables = useCallback(async () => {
        try {
            const res = await ApiClient.get<IAIPromptVariablesResponse>('/api/ai_prompt_templates.php', { action: 'list_variables' });
            if (res?.success) {
                setVariables(res.variables || []);
            }
        } catch (err) {
            logger.error('[useAIPromptTemplates] fetchVariables failed', err);
        }
    }, []);

    const fetchHistory = useCallback(async () => {
        try {
            const res = await ApiClient.get<{ success: boolean; history?: IAIGenerationHistoryRow[]; error?: string }>('/api/ai_prompt_templates.php', { action: 'list_history' });
            if (res?.success) {
                setHistory(res.history || []);
            }
        } catch (err) {
            logger.error('[useAIPromptTemplates] fetchHistory failed', err);
        }
    }, []);

    const saveTemplate = useCallback(async (template: Partial<IAIPromptTemplate>) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<IAIPromptTemplateActionResponse>('/api/ai_prompt_templates.php?action=save_template', template);
            if (!res?.success) {
                throw new Error(res?.error || 'Failed to save template');
            }
            await fetchTemplates();
            return { success: true };
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to save template';
            setError(message);
            return { success: false, error: message };
        } finally {
            setIsLoading(false);
        }
    }, [fetchTemplates]);

    const deleteTemplate = useCallback(async (id: number) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<IAIPromptTemplateActionResponse>('/api/ai_prompt_templates.php?action=delete_template', { id });
            if (!res?.success) {
                throw new Error(res?.error || 'Failed to delete template');
            }
            await fetchTemplates();
            return { success: true };
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to delete template';
            setError(message);
            return { success: false, error: message };
        } finally {
            setIsLoading(false);
        }
    }, [fetchTemplates]);

    return {
        templates,
        variables,
        history,
        isLoading,
        error,
        fetchTemplates,
        fetchVariables,
        fetchHistory,
        saveTemplate,
        deleteTemplate
    };
};
