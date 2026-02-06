import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { API_ACTION } from '../../core/constants.js';
import {
    INewsletterSubscriber,
    INewsletterCampaign,
    INewsletterListResponse,
    ICampaignListResponse
} from '../../types/newsletter.js';

// Re-export for backward compatibility
export type { INewsletterSubscriber, INewsletterCampaign } from '../../types/newsletter.js';

export const useNewsletter = () => {
    const [subscribers, setSubscribers] = useState<INewsletterSubscriber[]>([]);
    const [campaigns, setCampaigns] = useState<INewsletterCampaign[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // === SUBSCRIBERS ===
    const fetchSubscribers = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<INewsletterListResponse>(`/api/newsletter_manager.php?action=${API_ACTION.LIST}`);
            if (res?.success) {
                setSubscribers(res.subscribers || []);
            } else {
                setError(res?.error || 'Failed to load subscribers');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchSubscribers failed', err);
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const addSubscriber = useCallback(async (email: string) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; error?: string }>(`/api/newsletter_manager.php?action=add_subscriber`, { email });
            if (res?.success) {
                await fetchSubscribers();
                return true;
            } else {
                throw new Error(res?.error || 'Failed to add subscriber');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('addSubscriber failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchSubscribers]);

    const updateSubscriber = useCallback(async (id: number, data: Partial<INewsletterSubscriber>) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; error?: string }>(`/api/newsletter_manager.php?action=update_subscriber`, { id, ...data });
            if (res?.success) {
                await fetchSubscribers();
                return true;
            } else {
                throw new Error(res?.error || 'Failed to update subscriber');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('updateSubscriber failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchSubscribers]);

    const deleteSubscriber = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<any>(`/api/newsletter_manager.php?action=${API_ACTION.DELETE}`, { id });
            if (res) {
                await fetchSubscribers();
                return true;
            } else {
                throw new Error('Failed to delete subscriber');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('deleteSubscriber failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchSubscribers]);

    // === CAMPAIGNS ===
    const fetchCampaigns = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<ICampaignListResponse>(`/api/newsletter_manager.php?action=list_campaigns`);
            if (res?.success) {
                setCampaigns(res.campaigns || []);
            } else {
                setError(res?.error || 'Failed to load campaigns');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchCampaigns failed', err);
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const saveCampaign = useCallback(async (data: Partial<INewsletterCampaign>) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; error?: string }>(`/api/newsletter_manager.php?action=save_campaign`, data);
            if (res?.success) {
                await fetchCampaigns();
                return true;
            } else {
                throw new Error(res?.error || 'Failed to save campaign');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveCampaign failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchCampaigns]);

    const deleteCampaign = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; error?: string }>(`/api/newsletter_manager.php?action=delete_campaign`, { id });
            if (res?.success) {
                await fetchCampaigns();
                return true;
            } else {
                throw new Error(res?.error || 'Failed to delete campaign');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('deleteCampaign failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchCampaigns]);

    const sendCampaign = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; sent_count?: number; error?: string }>(`/api/newsletter_manager.php?action=send_campaign`, { id });
            if (res?.success) {
                await fetchCampaigns();
                return { success: true, sent_count: res.sent_count };
            } else {
                throw new Error(res?.error || 'Failed to send campaign');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('sendCampaign failed', err);
            setError(message);
            return { success: false, message };
        } finally {
            setIsLoading(false);
        }
    }, [fetchCampaigns]);

    // Legacy: direct send (for backward compatibility)
    const sendNewsletter = useCallback(async (subject: string, content: string) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; message?: string }>(`/api/newsletter_manager.php?action=${API_ACTION.SEND}`, { subject, content });
            return res;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('sendNewsletter failed', err);
            setError(message);
            return { success: false, message };
        } finally {
            setIsLoading(false);
        }
    }, []);

    return {
        // State
        subscribers,
        campaigns,
        isLoading,
        error,
        // Subscriber actions
        fetchSubscribers,
        addSubscriber,
        updateSubscriber,
        deleteSubscriber,
        // Campaign actions
        fetchCampaigns,
        saveCampaign,
        deleteCampaign,
        sendCampaign,
        // Legacy
        sendNewsletter
    };
};
