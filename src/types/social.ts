/**
 * Social Media Types
 * Centralized type definitions for social media accounts, posts, and providers
 */

import type { SocialPlatform, SocialConnectionStatus } from '../core/constants/uienums.js';
import { SOCIAL_POST_STATUS } from '../core/constants.js';

/**
 * Social account record with connection status
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
    provider_icon?: string;
    provider_color?: string;
    provider_name?: string;
    created_at?: string;
    updated_at?: string;
    // Credential fields (write-only, not returned from API for security)
    access_token?: string;
    app_secret?: string;
}

/**
 * Authentication method types supported by providers
 */
export type AuthMethod = 'oauth' | 'api_key' | 'username_password';

export interface AuthMethodConfig {
    type: AuthMethod;
    label: string;
    preferred?: boolean;
    fields: {
        name: string;
        label: string;
        type: 'text' | 'password' | 'email';
        placeholder: string;
        required?: boolean;
    }[];
}

/**
 * Provider configuration for UI display
 */
export interface ISocialProvider {
    id: string;
    name: string;
    icon: string;
    color: string;
    description?: string;
    authMethods: AuthMethodConfig[];
}

export interface ISocialPost {
    id: number;
    account_id: number;
    content: string;
    media_urls?: string[];
    scheduled_at?: string;
    status: typeof SOCIAL_POST_STATUS[keyof typeof SOCIAL_POST_STATUS];
}

export interface ISocialPostTemplate {
    id: string;
    name: string;
    content: string;
    image_url: string;
    platforms: string[];
    is_active: boolean | number;
}

export interface ISocialImage {
    url: string;
    path: string;
    name: string;
}

// ============================================================================
// API Response Interfaces
// ============================================================================

/** Response for social templates list endpoint */
export interface ISocialTemplatesResponse {
    success: boolean;
    templates?: ISocialPostTemplate[];
    message?: string;
}

/** Response for social images endpoint */
export interface ISocialImagesResponse {
    success: boolean;
    images?: ISocialImage[];
}

/** Response for social template detail endpoint */
export interface ISocialTemplateDetailResponse {
    success: boolean;
    template?: ISocialPostTemplate;
}

/** Response for social accounts endpoint */
export interface ISocialAccountsResponse {
    success: boolean;
    accounts?: ISocialAccount[];
    error?: string;
}

export interface IPublishPostResponse {
    success: boolean;
    message?: string;
    error?: string;
    post?: ISocialPost;
}

export interface ISocialActionResponse {
    success: boolean;
    message?: string;
    error?: string;
}

export interface ISocialVerifyResponse {
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

export interface ICreateSocialAccountRequest {
    platform: SocialPlatform;
    account_name: string;
    access_token: string;
    account_id?: string;
    refresh_token?: string;
    token_expires_at?: string;
}
