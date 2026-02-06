/**
 * Modal Title Mapping
 * 
 * Maps admin section keys (and optional tabs) to the exact button text
 * displayed in SettingsDashboard.tsx. This ensures modal headers always
 * match what the user clicked.
 */

import { ADMIN_SECTION } from './admin.js';

type TabTitles = Record<string, string>;

/**
 * Mapping of section keys to their display titles.
 * For sections with tabs, the value is an object mapping tab → title.
 */
export const MODAL_TITLES: Record<string, string | TabTitles> = {
    // Business Insights card
    [ADMIN_SECTION.AI_SETTINGS]: 'AI Settings',
    [ADMIN_SECTION.AUTOMATION]: 'Automation Manager',
    [ADMIN_SECTION.BUSINESS_INFO]: 'Business Information',
    [ADMIN_SECTION.COST_BREAKDOWN]: 'Cost Suggestions',
    [ADMIN_SECTION.INTENT_HEURISTICS]: 'Intent Heuristics',
    [ADMIN_SECTION.PRICE_SUGGESTIONS]: 'Price Suggestions',
    [ADMIN_SECTION.SHIPPING]: 'Shipping Rates',
    [ADMIN_SECTION.SQUARE]: 'Square Configuration',

    // Customer Outreach card
    [ADMIN_SECTION.COUPONS]: 'Coupon Manager',
    [ADMIN_SECTION.COMMUNICATION]: {
        'settings': 'E-mail',
        'templates': 'Email Templates',
        'history': 'Sent Messages'
    },
    [ADMIN_SECTION.NEWSLETTERS]: 'Newsletters',
    [ADMIN_SECTION.RECEIPT_SETTINGS]: 'Receipt Messages',
    [ADMIN_SECTION.MARKETING_SETTINGS]: 'Sales Nudge Kit',
    [ADMIN_SECTION.SOCIAL_MEDIA]: 'Social Media',

    // Store Display card
    [ADMIN_SECTION.AREA_MAPPER]: 'Area Content',
    [ADMIN_SECTION.BACKGROUNDS]: 'Background Manager',
    [ADMIN_SECTION.SHOPPING_CART]: 'Cart Settings',
    [ADMIN_SECTION.CATEGORIES]: 'Categories',
    [ADMIN_SECTION.INVENTORY_ARCHIVE]: 'Inventory Audit',
    [ADMIN_SECTION.INVENTORY]: 'Inventory Manager',
    [ADMIN_SECTION.ITEM_ATTRIBUTES]: 'Inventory Options',
    [ADMIN_SECTION.ROOM_MANAGER]: 'Room Manager',
    [ADMIN_SECTION.THEME_WORDS]: 'Theme Words',

    // System Maintenance card
    [ADMIN_SECTION.LOGS]: 'Activity Logs',
    [ADMIN_SECTION.ADVANCED_TOOLS]: 'Advanced Tools',
    [ADMIN_SECTION.BRAND_STYLING]: 'Brand Styling',
    [ADMIN_SECTION.DASHBOARD_CONFIG]: 'Dashboard Layout',
    [ADMIN_SECTION.HELP_GUIDES]: 'Help & Guides',
    [ADMIN_SECTION.SECRETS]: 'System Secrets',
    [ADMIN_SECTION.SITE_MAINTENANCE]: {
        'backups': 'Site Backups',
        'status': 'System Status'
    },
    [ADMIN_SECTION.CRON_MANAGER]: 'Cron Job Manager',
    [ADMIN_SECTION.SESSION_VIEWER]: 'Session Viewer',
    [ADMIN_SECTION.CART_SIMULATION]: 'Cart Simulation',
    [ADMIN_SECTION.SIZING_TOOLS]: 'Sizing Tools',
    [ADMIN_SECTION.MARKETING_CHECK]: 'Marketing AI Check',
    [ADMIN_SECTION.ADDRESS_CHECK]: 'Address Check',
    [ADMIN_SECTION.CSS_CATALOG]: 'CSS Catalog',
    [ADMIN_SECTION.ACTION_ICONS]: 'Icon Buttons',
    [ADMIN_SECTION.CSS_RULES]: 'CSS Override Rules',
    [ADMIN_SECTION.DB_STATUS]: 'DB Status Dashboard',
    [ADMIN_SECTION.DB_QUERY_CONSOLE]: 'DB Query Console'
};

/**
 * Get the display title for a modal based on section and optional tab.
 * 
 * @param section - The ADMIN_SECTION value
 * @param tab - Optional tab within the section
 * @returns The button title text, or a formatted fallback
 */
export function getModalTitle(section: string, tab?: string | null): string {
    const entry = MODAL_TITLES[section];

    if (typeof entry === 'string') {
        return entry;
    }

    if (typeof entry === 'object' && entry !== null) {
        // For tabbed modals, use the tab-specific title ONLY if a tab is provided
        if (tab && entry[tab]) {
            return entry[tab];
        }
        // Otherwise fall back to the section name (which matches the hub button label)
        return formatSectionName(section);
    }

    // Fallback: format the section key as a readable title
    return formatSectionName(section);
}

/**
 * Formats a section key like 'business-info' into 'Business Info'
 */
function formatSectionName(section: string): string {
    return section
        .split('-')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}
