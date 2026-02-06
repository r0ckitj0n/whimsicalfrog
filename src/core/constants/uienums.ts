export const KEYBOARD = {
    ESCAPE: 'Escape'
} as const;

export const AI_TIER = {
    PREMIUM: 'premium',
    STANDARD: 'standard',
    CONSERVATIVE: 'conservative'
} as const;

export type AiTier = typeof AI_TIER[keyof typeof AI_TIER];

export const AUTH = {
    ADMIN_TOKEN: 'whimsical_admin_2024'
} as const;

export const ROLE = {
    ADMIN: 'admin',
    CUSTOMER: 'customer',
    GUEST: 'guest'
} as const;

export type Role = typeof ROLE[keyof typeof ROLE];

export const SOCIAL_PLATFORM = {
    FACEBOOK: 'facebook',
    INSTAGRAM: 'instagram',
    TWITTER: 'twitter',
    LINKEDIN: 'linkedin',
    PINTEREST: 'pinterest',
    TIKTOK: 'tiktok',
    YOUTUBE: 'youtube'
} as const;

export type SocialPlatform = typeof SOCIAL_PLATFORM[keyof typeof SOCIAL_PLATFORM];

export const SOCIAL_CONNECTION_STATUS = {
    CONNECTED: 'connected',
    EXPIRED: 'expired',
    ERROR: 'error',
    PENDING: 'pending',
    DISCONNECTED: 'disconnected'
} as const;

export type SocialConnectionStatus = typeof SOCIAL_CONNECTION_STATUS[keyof typeof SOCIAL_CONNECTION_STATUS];

export const SOCIAL_POST_STATUS = {
    DRAFT: 'draft',
    SCHEDULED: 'scheduled',
    PUBLISHED: 'published',
    FAILED: 'failed'
} as const;

export type SocialPostStatus = typeof SOCIAL_POST_STATUS[keyof typeof SOCIAL_POST_STATUS];

export const TOOLTIP_POSITION = {
    TOP: 'top',
    BOTTOM: 'bottom',
    LEFT: 'left',
    RIGHT: 'right'
} as const;

export type TooltipPosition = typeof TOOLTIP_POSITION[keyof typeof TOOLTIP_POSITION];

export const FILE_TYPE = {
    FILE: 'file',
    DIRECTORY: 'directory'
} as const;

export type FileType = typeof FILE_TYPE[keyof typeof FILE_TYPE];

export const BACKUP_TYPE = {
    FULL: 'full',
    DATABASE: 'database'
} as const;

export type BackupType = typeof BACKUP_TYPE[keyof typeof BACKUP_TYPE];

export const BACKUP_DESTINATION = {
    CLOUD: 'cloud'
} as const;

export const ENVIRONMENT = {
    SANDBOX: 'sandbox',
    PRODUCTION: 'production',
    LOCAL: 'local'
} as const;

export type Environment = typeof ENVIRONMENT[keyof typeof ENVIRONMENT];

export const Z_INDEX = {
    NEGATIVE: 'var(--wf-z-negative)',
    BASE: 'var(--wf-z-base)',
    ELEVATED: 'var(--wf-z-elevated)',
    STICKY: 'var(--wf-z-sticky)',
    BACKDROP: 'var(--wf-z-backdrop)',
    MODAL: 'var(--wf-z-modal)',
    TOAST: 'var(--wf-z-toast)',
    CURSOR: 'var(--wf-z-cursor)'
} as const;

export const PAGE = {
    LANDING: 'landing',
    SHOP: 'shop',
    RECEIPT: 'receipt',
    ROOM_MAIN: 'room_main',
    CONTACT: 'contact',
    ABOUT: 'about',
    CART: 'cart',
    HELP: 'help',
    POS: 'pos',
    ADMIN: 'admin'
} as const;

export type Page = typeof PAGE[keyof typeof PAGE];
