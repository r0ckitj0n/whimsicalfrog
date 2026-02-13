import { MappingType } from '../core/constants.js';
import type { IShortcutSignAsset } from './room-shortcuts.js';

export interface IDashboardMetrics {
    total_revenue: number;
    total_orders: number;
    total_customers: number;
    total_items: number;
    total_stock_units?: number;
    top_stock_items?: Array<{
        sku: string;
        name: string;
        stock_quantity: number;
    }>;
    recent_customers?: Array<{
        id: number;
        username: string;
        email: string;
        created_at: string;
    }>;
    reports?: {
        last_7d: { revenue: number; orders: number };
        last_30d: { orders: number };
        payment_breakdown: Array<{ method: string; cnt: number }>;
        daily_sales: Array<{ d: string; revenue: number }>;
    };
    marketing?: {
        email_campaigns: number;
        active_discounts: number;
        scheduled_posts: number;
    };
}

export interface ICostItem {
    id: number | string;
    sku: string;
    label: string;
    cost: number;
    created_at?: string;
    updated_at?: string;
}

export interface ICostBreakdown {
    materials: ICostItem[];
    labor: ICostItem[];
    energy: ICostItem[];
    equipment: ICostItem[];
    totals: {
        materials: number;
        labor: number;
        energy: number;
        equipment: number;
        total: number;
        stored?: number;
        suggested_cost?: number;
        ai_confidence?: number;
        ai_at?: string;
    };
}

export interface IUpdateCostFactorsBulkRequest {
    sku: string;
    updates: Array<{
        id: number;
        cost: number;
        label?: string;
    }>;
}

export interface IUpdateCostFactorsBulkResponse {
    success: boolean;
    error?: string;
    message?: string;
}

export interface IAreaMapping {
    id: number;
    room_number: number | string;
    area_selector: string;
    mapping_type: MappingType;
    item_sku?: string;
    sku?: string;
    category_id?: number;
    link_url?: string;
    link_label?: string;
    link_icon?: string;
    link_image?: string;
    content_target?: string;
    content_image?: string;
    display_order: number;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
    // Enriched fields
    image_url?: string;
    name?: string;
    price?: number;
    retail_price?: number;
    stock_quantity?: number;
    derived?: boolean;
    shortcut_images?: IShortcutSignAsset[];
}

export interface IAdminTool {
    name: string;
    desc: string;
    file?: string;
    url?: string;
    external?: boolean;
    modal?: boolean;
    inline_modal?: string;
    icon: string;
    tooltip?: string;
}

export interface IAdminToolCategory {
    title: string;
    tools: IAdminTool[];
}

// Automation Types (migrated from useAutomation.ts)
export interface IAutomationPlaybook {
    name: string;
    trigger: string;
    action: string;
    cadence: string;
    status: string;
    active: boolean | number;
}

// Log Types (migrated from useLogs.ts)
export interface ILogFile {
    type: string;
    path: string;
    name: string;
    description: string;
    size: string;
    last_entry: string;
    log_source: 'file' | 'database';
    entries?: number;
}

export interface ILogEntry {
    timestamp: string;
    level: string;
    message: string;
    details?: unknown;
}

// ============================================================================
// API Response Interfaces
// ============================================================================

/** Response for log list endpoint */
export interface ILogListResponse {
    success: boolean;
    logs: ILogFile[];
}

/** Response for log status endpoint */
export interface ILogStatusResponse {
    success: boolean;
    status: Record<string, unknown>;
}

/** Response for log content endpoint */
export interface ILogContentResponse {
    success: boolean;
    entries: ILogEntry[];
}

/** Response for log action (clear) endpoints */
export interface ILogActionResponse {
    success: boolean;
    error?: string;
}

/** Response for CSS catalog endpoint */
export interface ICssCatalogResponse {
    success: boolean;
    data: import('./theming.js').ICssCatalogData;
    message?: string;
}

/** Response for CSS rules endpoint */
export interface ICssRulesResponse {
    success: boolean;
    rules?: import('./theming.js').ICssRule[];
    error?: string;
}

/** Response for icon map endpoint */
export interface IIconMapResponse {
    success: boolean;
    map: Record<string, string>;
}

/** Response for automation playbooks endpoint */
export interface IAutomationPlaybookResponse {
    success: boolean;
    settings?: Array<{
        setting_key: string;
        setting_value: string | IAutomationPlaybook[];
    }>;
}
