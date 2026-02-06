import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { API_ACTION, SOCIAL_PLATFORM, SOCIAL_POST_STATUS, SOCIAL_CONNECTION_STATUS } from '../../core/constants.js';
import type { SocialPlatform, SocialConnectionStatus } from '../../core/constants/uienums.js';
import type {
    ISocialAccount,
    AuthMethod,
    AuthMethodConfig,
    ISocialProvider,
    ISocialPost,
    IPublishPostResponse,
    ISocialActionResponse,
    ISocialVerifyResponse,
    ICreateSocialAccountRequest
} from '../../types/social.js';

// Re-export for backward compatibility
export type { ISocialAccount, AuthMethod, AuthMethodConfig, ISocialProvider, ISocialPost } from '../../types/social.js';
export type {
    IPublishPostResponse as PublishPostResponse,
    ISocialActionResponse as ActionResponse,
    ISocialVerifyResponse as VerifyResponse,
    ICreateSocialAccountRequest as CreateAccountRequest
} from '../../types/social.js';

/**
 * Default provider configurations with platform-specific auth methods
 */
const DEFAULT_PROVIDERS: ISocialProvider[] = [
    {
        id: 'facebook',
        name: 'Facebook',
        icon: '📘',
        color: '#1877f2',
        description: 'Share posts, updates, and photos with your followers.',
        authMethods: [
            { type: 'oauth', label: 'OAuth (Recommended)', preferred: true, fields: [] },
            {
                type: 'api_key', label: 'Page Access Token', fields: [
                    { name: 'access_token', label: 'Page Access Token', type: 'password', placeholder: 'Enter your Page Access Token', required: true },
                    { name: 'page_id', label: 'Page ID', type: 'text', placeholder: 'Your Facebook Page ID', required: true }
                ]
            }
        ]
    },
    {
        id: 'instagram',
        name: 'Instagram',
        icon: '📷',
        color: '#e4405f',
        description: 'Share visual content and stories with your audience.',
        authMethods: [
            { type: 'oauth', label: 'Connect via Facebook (Recommended)', preferred: true, fields: [] },
            {
                type: 'api_key', label: 'Instagram Business Token', fields: [
                    { name: 'access_token', label: 'Access Token', type: 'password', placeholder: 'Instagram Graph API Token', required: true },
                    { name: 'account_id', label: 'Instagram Business Account ID', type: 'text', placeholder: 'Your Business Account ID', required: true }
                ]
            }
        ]
    },
    {
        id: 'twitter',
        name: 'Twitter/X',
        icon: '🐦',
        color: '#1da1f2',
        description: 'Post short updates and engage with customers.',
        authMethods: [
            { type: 'oauth', label: 'OAuth 2.0 (Recommended)', preferred: true, fields: [] },
            {
                type: 'api_key', label: 'API Keys', fields: [
                    { name: 'api_key', label: 'API Key', type: 'password', placeholder: 'Your Twitter API Key', required: true },
                    { name: 'api_secret', label: 'API Secret', type: 'password', placeholder: 'Your Twitter API Secret', required: true },
                    { name: 'access_token', label: 'Access Token', type: 'password', placeholder: 'Your Access Token', required: true },
                    { name: 'access_token_secret', label: 'Access Token Secret', type: 'password', placeholder: 'Your Access Token Secret', required: true }
                ]
            }
        ]
    },
    {
        id: 'linkedin',
        name: 'LinkedIn',
        icon: '💼',
        color: '#0077b5',
        description: 'Share professional updates and network.',
        authMethods: [
            { type: 'oauth', label: 'OAuth 2.0 (Recommended)', preferred: true, fields: [] },
            {
                type: 'api_key', label: 'Access Token', fields: [
                    { name: 'access_token', label: 'Access Token', type: 'password', placeholder: 'LinkedIn Access Token', required: true },
                    { name: 'organization_id', label: 'Organization ID', type: 'text', placeholder: 'Your LinkedIn Organization ID (optional)' }
                ]
            }
        ]
    },
    {
        id: 'pinterest',
        name: 'Pinterest',
        icon: '📌',
        color: '#bd081c',
        description: 'Pin products and ideas to boards.',
        authMethods: [
            {
                type: 'api_key', label: 'Access Token (Recommended)', preferred: true, fields: [
                    { name: 'access_token', label: 'Access Token', type: 'password', placeholder: 'Pinterest API Access Token', required: true }
                ]
            },
            { type: 'oauth', label: 'OAuth 2.0', fields: [] }
        ]
    },
    {
        id: 'tiktok',
        name: 'TikTok',
        icon: '🎵',
        color: '#010101',
        description: 'Share short-form video content.',
        authMethods: [
            { type: 'oauth', label: 'TikTok Login Kit (Required)', preferred: true, fields: [] }
        ]
    },
    {
        id: 'youtube',
        name: 'YouTube',
        icon: '📺',
        color: '#ff0000',
        description: 'Upload and manage video content.',
        authMethods: [
            { type: 'oauth', label: 'Google OAuth (Required)', preferred: true, fields: [] },
            {
                type: 'api_key', label: 'API Key (Read-only)', fields: [
                    { name: 'api_key', label: 'YouTube Data API Key', type: 'password', placeholder: 'Your YouTube API Key', required: true },
                    { name: 'channel_id', label: 'Channel ID', type: 'text', placeholder: 'Your YouTube Channel ID', required: true }
                ]
            }
        ]
    }
];

export const useSocialMedia = () => {
    const [accounts, setAccounts] = useState<ISocialAccount[]>([]);
    const [providers, setProviders] = useState<ISocialProvider[]>(DEFAULT_PROVIDERS);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [verifyingId, setVerifyingId] = useState<number | null>(null);

    const fetchAccounts = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<{ success: boolean; accounts?: ISocialAccount[]; error?: string }>(`/api/social_accounts.php?action=${API_ACTION.LIST}`);
            if (res?.success) {
                setAccounts(res.accounts || []);
            } else {
                setError(res?.error || 'Failed to load social accounts');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchSocialAccounts failed', err);
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const fetchProviders = useCallback(async () => {
        try {
            const res = await ApiClient.get<{ success: boolean; providers?: Record<string, ISocialProvider> }>('/api/social_accounts.php?action=providers');
            if (res?.success && res.providers) {
                const providerList = Object.entries(res.providers).map(([id, p]) => {
                    const defaultProvider = DEFAULT_PROVIDERS.find(dp => dp.id === id);
                    return {
                        id,
                        name: p.name,
                        icon: p.icon,
                        color: p.color,
                        description: defaultProvider?.description,
                        authMethods: defaultProvider?.authMethods || []
                    };
                });
                setProviders(providerList);
            }
        } catch (err) {
            logger.error('fetchProviders failed', err);
            // Keep default providers on error
        }
    }, []);

    const createAccount = useCallback(async (data: ICreateSocialAccountRequest): Promise<{ success: boolean; id?: number; message?: string }> => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<{ success: boolean; id?: number; message?: string; error?: string }>('/api/social_accounts.php?action=create', data);
            if (res?.success) {
                await fetchAccounts();
                return { success: true, id: res.id, message: res.message };
            } else {
                throw new Error(res?.error || 'Failed to create account');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('createAccount failed', err);
            setError(message);
            return { success: false, message };
        } finally {
            setIsLoading(false);
        }
    }, [fetchAccounts]);

    const verifyConnection = useCallback(async (id: number): Promise<ISocialVerifyResponse> => {
        setVerifyingId(id);
        try {
            const res = await ApiClient.post<ISocialVerifyResponse>('/api/social_accounts.php?action=verify', { id });
            if (res) {
                await fetchAccounts(); // Refresh to update status
                return res;
            }
            throw new Error('No response from server');
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('verifyConnection failed', err);
            return { success: false, status: 'error' as SocialConnectionStatus, message };
        } finally {
            setVerifyingId(null);
        }
    }, [fetchAccounts]);

    const publishPost = useCallback(async (postData: { account_id: number; content: string; media_urls?: string[] }) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<IPublishPostResponse>('/api/publish_social.php', postData);
            if (res) {
                return res;
            } else {
                throw new Error('Failed to publish post');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('publishSocialPost failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const deleteAccount = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<ISocialActionResponse>('/api/social_accounts.php?action=delete', { id });
            if (res?.success) {
                await fetchAccounts();
                return true;
            }
        } catch (err) {
            logger.error('deleteAccount failed', err);
        } finally {
            setIsLoading(false);
        }
        return false;
    }, [fetchAccounts]);

    const updateAccount = useCallback(async (account: Partial<ISocialAccount>) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<ISocialActionResponse>('/api/social_accounts.php?action=update', account);
            if (res?.success) {
                await fetchAccounts();
                return true;
            }
        } catch (err) {
            logger.error('updateAccount failed', err);
        } finally {
            setIsLoading(false);
        }
        return false;
    }, [fetchAccounts]);

    const getAccount = useCallback(async (id: number) => {
        try {
            const res = await ApiClient.get<{ success: boolean; account?: ISocialAccount }>('/api/social_accounts.php', { action: 'get', id });
            return res?.account || null;
        } catch (err) {
            logger.error('getAccount failed', err);
            return null;
        }
    }, []);

    /**
     * Initiate OAuth flow for a provider (opens popup/redirect)
     * For demo purposes, returns a simulated success after brief delay
     */
    const initiateOAuth = useCallback(async (platform: SocialPlatform): Promise<{ success: boolean; accountName?: string; accountId?: string }> => {
        // In production, this would open an OAuth popup or redirect
        // For now, simulate the OAuth flow with a delay
        return new Promise((resolve) => {
            setTimeout(() => {
                // Simulate successful OAuth with generated account info
                const mockAccountId = `${platform}_${Date.now()}`;
                const mockAccountName = `${platform.charAt(0).toUpperCase() + platform.slice(1)} Account`;
                resolve({
                    success: true,
                    accountName: mockAccountName,
                    accountId: mockAccountId
                });
            }, 1500);
        });
    }, []);

    return {
        accounts,
        providers,
        isLoading,
        error,
        verifyingId,
        fetchAccounts,
        fetchProviders,
        createAccount,
        verifyConnection,
        publishPost,
        deleteAccount,
        updateAccount,
        getAccount,
        initiateOAuth
    };
};
