import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { API_ACTION } from '../../core/constants.js';
import type { IReceiptMessage, IReceiptVerbiage } from '../../types/settings.js';

// Re-export for backward compatibility
export type { IReceiptMessage, IReceiptVerbiage } from '../../types/settings.js';

export const useReceiptSettings = () => {
    const [messages, setMessages] = useState<IReceiptMessage[]>([]);
    const [verbiage, setVerbiage] = useState<IReceiptVerbiage>({
        receipt_thank_you_message: '',
        receipt_next_steps: '',
        receipt_social_sharing: '',
        receipt_return_customer: ''
    });
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchMessages = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const [msgRes, verbRes] = await Promise.all([
                ApiClient.get<{ success: boolean; messages?: IReceiptMessage[]; error?: string }>('/api/receipt_settings.php', { action: 'list' }),
                ApiClient.get<{ success: boolean; settings?: IReceiptVerbiage; error?: string }>('/api/business_settings.php', {
                    action: API_ACTION.GET_BY_CATEGORY,
                    category: 'sales'
                })
            ]);

            if (msgRes?.success) {
                setMessages(msgRes.messages || []);
            }
            if (verbRes?.success && verbRes.settings) {
                setVerbiage({
                    receipt_thank_you_message: verbRes.settings.receipt_thank_you_message || '',
                    receipt_next_steps: verbRes.settings.receipt_next_steps || '',
                    receipt_social_sharing: verbRes.settings.receipt_social_sharing || '',
                    receipt_return_customer: verbRes.settings.receipt_return_customer || ''
                });
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('[useReceiptSettings] fetch failed', err);
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const saveVerbiage = async (newVerbiage: IReceiptVerbiage) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<any>('/api/business_settings.php', {
                action: 'upsert_settings',
                category: 'sales',
                settings: newVerbiage
            });
            if (res) {
                setVerbiage(newVerbiage);
                return { success: true };
            }
            return { success: false, error: 'Save failed' };
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('[useReceiptSettings] saveVerbiage failed', err);
            return { success: false, error: message };
        } finally {
            setIsLoading(false);
        }
    };

    const saveMessage = useCallback(async (msgData: Partial<IReceiptMessage>) => {
        setIsLoading(true);
        try {
            const isEdit = !!msgData.id;
            const action = isEdit ? 'update' : 'create';
            const res = await ApiClient.post<{ success: boolean; error?: string; message?: string }>(`/api/receipt_settings.php?action=${action}`, msgData);
            if (res?.success) {
                await fetchMessages();
                return res;
            } else {
                throw new Error(res?.error || res?.message || 'Failed to save receipt message');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveReceiptMessage failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [fetchMessages]);

    const deleteMessage = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; error?: string; message?: string }>('/api/receipt_settings.php?action=delete', { id });
            if (res?.success) {
                await fetchMessages();
                return true;
            } else {
                throw new Error(res?.error || res?.message || 'Failed to delete receipt message');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('deleteReceiptMessage failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchMessages]);

    return {
        messages,
        verbiage,
        isLoading,
        error,
        fetchMessages,
        saveMessage,
        deleteMessage,
        saveVerbiage,
        refresh: fetchMessages
    };
};
