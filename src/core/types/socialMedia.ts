/**
 * Social Media Types
 * Shared type definitions for social media account management.
 * Following Shared Types Protocol for ESM compatibility.
 */
import type { SocialPlatform, SocialConnectionStatus } from '../constants/uienums.js';

/**
 * Provider configuration for UI display
 */
export interface ISocialProviderConfig {
    id: SocialPlatform;
    name: string;
    icon: string;
    color: string;
    description: string;
    authUrl?: string;
}

/**
 * Social media account record
 */
export interface ISocialAccount {
    id: number;
    platform: SocialPlatform;
    account_name: string;
    account_id: string;
    is_active: boolean;
    connection_status: SocialConnectionStatus;
    last_sync?: string;
    last_verified?: string;
    token_expires_at?: string;
    profile_url?: string;
    profile_image?: string;
    created_at?: string;
    updated_at?: string;
}

/**
 * Result of connection verification test
 */
export interface IConnectionTestResult {
    success: boolean;
    status: SocialConnectionStatus;
    message?: string;
    verified_at?: string;
    account_info?: {
        username?: string;
        followers?: number;
        profile_url?: string;
    };
}

/**
 * Request payload for creating a new account
 */
export interface ICreateAccountRequest {
    platform: SocialPlatform;
    account_name: string;
    access_token: string;
    account_id?: string;
    refresh_token?: string;
    token_expires_at?: string;
}

/**
 * Response from account operations
 */
export interface ISocialAccountResponse {
    success: boolean;
    message?: string;
    error?: string;
    account?: ISocialAccount;
    accounts?: ISocialAccount[];
}

/**
 * OAuth state for tracking auth flow
 */
export interface IOAuthState {
    provider: SocialPlatform;
    state: string;
    redirect_uri: string;
    initiated_at: string;
}
