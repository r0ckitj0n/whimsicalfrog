import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type {
    IEmailTemplate,
    IEmailAssignment,
    IEmailTemplatesResponse,
    IEmailAssignmentsResponse,
    IEmailTemplateActionResponse
} from '../../types/email.js';

// Re-export for backward compatibility
export type {
    IEmailTemplate,
    IEmailAssignment,
    IEmailTemplatesResponse,
    IEmailAssignmentsResponse,
    IEmailTemplateActionResponse
} from '../../types/email.js';



export const useEmailTemplates = () => {
    const [templates, setTemplates] = useState<IEmailTemplate[]>([]);
    const [assignments, setAssignments] = useState<IEmailAssignment>({});
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchAll = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const [tplRes, asgRes] = await Promise.all([
                ApiClient.get<IEmailTemplatesResponse>('/api/email_templates.php', { action: 'get_all' }),
                ApiClient.get<IEmailAssignmentsResponse>('/api/email_templates.php', { action: 'get_assignments' })
            ]);

            if (tplRes?.success) setTemplates(tplRes.templates || []);
            if (asgRes?.success) setAssignments(asgRes.assignments || asgRes.data || {});

            if (!tplRes?.success || !asgRes?.success) {
                setError('Some email data failed to load');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchEmailTemplates failed', err);
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const saveTemplate = useCallback(async (templateData: Partial<IEmailTemplate>) => {
        setIsLoading(true);
        try {
            const id = templateData.id;
            const action = id ? 'update' : 'create';
            const payload: Record<string, unknown> = { ...templateData };
            if (id) payload.template_id = id;

            const res = await ApiClient.post<IEmailTemplateActionResponse>(`/api/email_templates.php?action=${action}`, payload);
            if (res?.success) {
                await fetchAll();
                return res;
            } else {
                throw new Error('Failed to save template');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveEmailTemplate failed', err);
            setError(message);
            return { success: false, error: message };
        } finally {
            setIsLoading(false);
        }
    }, [fetchAll]);

    const deleteTemplate = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<IEmailTemplateActionResponse>('/api/email_templates.php?action=delete', { template_id: id });
            if (res?.success) {
                await fetchAll();
                return true;
            } else {
                throw new Error('Failed to delete template');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('deleteEmailTemplate failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchAll]);

    const setAssignment = useCallback(async (emailType: string, templateId: number | null) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<IEmailTemplateActionResponse>('/api/email_templates.php?action=set_assignment', {
                email_type: emailType,
                template_id: templateId
            });
            if (res?.success) {
                await fetchAll();
                return true;
            } else {
                throw new Error('Failed to update assignment');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('setEmailAssignment failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchAll]);

    const saveAllAssignments = useCallback(async (pendingAssignments: Record<string, number | null>) => {
        setIsLoading(true);
        try {
            const entries = Object.entries(pendingAssignments);
            for (const [emailType, templateId] of entries) {
                const res = await ApiClient.post<IEmailTemplateActionResponse>('/api/email_templates.php?action=set_assignment', {
                    email_type: emailType,
                    template_id: templateId
                });
                if (!res?.success) {
                    throw new Error(`Failed to update assignment for ${emailType}`);
                }
            }
            await fetchAll();
            return true;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveAllAssignments failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchAll]);

    const sendTestEmail = useCallback(async (templateId: string | number, email: string) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; message?: string }>('/api/email_templates.php?action=send_test', {
                template_id: templateId,
                test_email: email
            });
            return res;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('sendTestEmail failed', err);
            setError(message);
            return { success: false, message };
        } finally {
            setIsLoading(false);
        }
    }, []);

    return {
        templates,
        assignments,
        isLoading,
        error,
        fetchAll,
        saveTemplate,
        deleteTemplate,
        setAssignment,
        saveAllAssignments,
        sendTestEmail
    };
};
