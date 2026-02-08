export interface IItem {
    sku: string;
    name: string;
    description: string;
    price: number;
    image_url?: string;
    category_id: number;
    is_active: boolean;
    stock_quantity?: number;
    reorder_point?: number;
    cost_price?: number;
    retail_price?: number;
    created_at?: string;
    updated_at?: string;
}

export interface IItemColor {
    id: number;
    item_sku: string;
    color_name: string;
    color_code?: string;
    image_path?: string;
    stock_level: number;
    display_order: number;
    is_active: boolean;
}

export interface IItemSize {
    id: number;
    item_sku: string;
    color_id: number | null;
    size_name: string;
    size_code: string;
    stock_level: number;
    price_adjustment: number;
    display_order: number;
    is_active: boolean;
    gender?: string | null;
}

// Note: IColorTemplate and ISizeTemplate are now in theming.ts
// Re-export here for backward compatibility with any imports from inventory
export type { IColorTemplate, ISizeTemplate } from './theming.js';

export interface IInventoryStats {
    total_items: number;
    total_stock: number;
    low_stock_count: number;
}

export interface ICategory {
    id: number;
    name: string;
    description?: string;
    parent_id: number | null;
    slug: string;
}

export interface IItemImage {
    id: number;
    sku: string;
    image_path: string;
    alt_text?: string;
    is_primary: boolean;
    display_order: number;
}

// Item Details Types (for shop/modal display)
export interface IItemOption {
    id: string | number;
    sku: string;
    size_code?: string;
    size_name?: string;
    color_id?: string;
    color_name?: string;
    color_code?: string;
    gender?: string;
    stock_level: number;
    price_adjustment?: number;
}

export interface IItemDetails {
    sku: string;
    name: string;
    description: string;
    totals?: {
        materials: number;
        labor: number;
        energy: number;
        equipment: number;
        total: number;
        ai_confidence?: number;
        ai_at?: string;
    };
    price?: number;
    retail_price: number;
    cost_price?: number;
    stock_quantity: number;
    total_stock?: number;
    image?: string;
    image_url?: string;
    category?: string;
    color_options?: string;
    size_options?: string;
    features?: string;
    usage_tips?: string;
    materials?: string;
    dimensions?: string;
    weight?: string;
    weight_oz?: number;
    package_length_in?: number;
    package_width_in?: number;
    package_height_in?: number;
    care_instructions?: string;
    technical_details?: string;
    button_text?: string;
    // AI Suggestions
    suggested_cost?: number;
    cost_reasoning?: string;
    cost_confidence?: number;
    cost_breakdown?: Record<string, number>;
    suggested_price?: number;
    price_reasoning?: string;
    price_confidence?: number;
    price_factors?: any[];
    price_components?: any[];
    // Field Lock State
    locked_fields?: Record<string, boolean>;
    locked_words?: Record<string, string>;
    quality_tier?: string;
    cost_quality_tier?: string;
    price_quality_tier?: string;
}

export interface IItemDetailsResponse {
    success: boolean;
    item: IItemDetails;
    images?: Array<{
        image_path: string;
        alt_text?: string;
        is_primary: boolean;
        sort_order: number;
    }>;
}

export interface IItemSizesResponse {
    success: boolean;
    sizes: IItemOption[];
}

// Storefront Shop Types
export interface IShopItem {
    sku: string;
    item_name: string;
    price: string | number;
    stock: number;
    description: string;
    custom_button_text?: string;
    image_url?: string;
}

export interface IShopCategory {
    slug: string;
    label: string;
    items: IShopItem[];
}

// Inventory Manager Types (from useInventory hook)
export interface IInventoryItem {
    sku: string;
    name: string;
    category: string;
    description?: string;
    stock_quantity: number;
    stock_level?: number; // alias for stock_quantity in some contexts
    reorder_point: number;
    cost_price: number;
    retail_price: number;
    price?: number; // legacy alias for retail_price
    image_count: number;
    status: 'live' | 'draft' | 'archived';
    is_active: boolean;
    is_archived?: boolean | number;
    primary_image?: string | {
        image_path: string;
        alt_text?: string;
        is_primary?: boolean;
    };
    weight_oz?: number;
    package_length_in?: number;
    package_width_in?: number;
    package_height_in?: number;
    // AI Suggestions
    suggested_cost?: number;
    cost_reasoning?: string;
    cost_confidence?: number;
    cost_breakdown?: Record<string, number>;
    suggested_price?: number;
    price_reasoning?: string;
    price_confidence?: number;
    price_factors?: any[];
    price_components?: any[];
}

export interface IInventoryFilters {
    search: string;
    category: string;
    stock: string;
    status: 'active' | 'archived' | 'all';
}

export interface IInventoryResponse {
    success: boolean;
    data: IInventoryItem[];
    categories?: string[];
    error?: string;
}

export interface ICommonApiResponse {
    success: boolean;
    error?: string;
    details?: unknown;
}

export interface IAddInventoryResponse extends ICommonApiResponse {
    data?: {
        id?: string;
        sku?: string;
        created?: boolean;
        updated?: boolean;
    };
}

/** Audit item structure used in inventory health checks */
export interface IAuditItem {
    sku: string;
    name: string;
    cost_price?: number;
    retail_price?: number;
    stock_quantity?: number;
    category?: string;
    description?: string;
}

export interface IInventoryAudit {
    pricing_alerts: IAuditItem[];
    missing_images: IAuditItem[];
    stock_issues: IAuditItem[];
    content_issues: IAuditItem[];
}

// Inventory Archive Types (migrated from useInventoryArchive.ts)
export interface IInventoryArchiveMetrics {
    total_archived: number;
    archived_last_7: number;
    archived_last_30: number;
    archived_over_90: number;
    avg_days_archived: number | string;
    total_stock: number;
    missing_images_count?: number;
    pricing_alerts_count?: number;
    stock_issues_count?: number;
    content_issues_count?: number;
}

export interface IInventoryAuditItem {
    sku: string;
    name: string;
    category: string;
    cost_price?: number;
    retail_price?: number;
    stock_quantity?: number;
}

export interface IInventoryAuditData {
    missing_images: IInventoryAuditItem[];
    pricing_alerts: IInventoryAuditItem[];
    stock_issues: IInventoryAuditItem[];
    content_issues: IInventoryAuditItem[];
}

export interface IInventoryArchiveItem {
    sku: string;
    name: string;
    category: string;
    stock_quantity: number;
    archived_at: string;
    archived_by: string;
    days_archived: number | string;
}

export interface IInventoryArchiveCategory {
    category: string;
    count: number;
}

// Size/Color Redesign Types (migrated from useSizeColorRedesign.ts)
export interface IRedesignAnalysis {
    total_colors: number;
    total_sizes: number;
    is_backwards: boolean;
    structure_issues: string[];
    recommendations: string[];
}

export interface IProposedColor {
    color_name: string;
    color_code: string;
    stock_level: number;
}

export interface IProposedSize {
    name?: string;
    size_name?: string;
    code?: string;
    size_code?: string;
    price_adjustment: number;
    colors: IProposedColor[];
}

export interface IRedesignProposal {
    success: boolean;
    message: string;
    proposedSizes: IProposedSize[];
}

export interface IRedesignItem {
    sku: string;
    name: string;
    price?: number | string;
    retail_price?: number | string;
}

// AI Content Generator Types (migrated from useAIContentGenerator.ts)
export interface IInventoryItemMinimal {
    sku: string;
    name: string;
    description: string;
    category: string;
    retail_price?: number;
    cost_price?: number;
}

// ============================================================================
// Additional API Response Interfaces
// ============================================================================

/** Response for inventory archive endpoint */
export interface IInventoryArchiveResponse {
    success: boolean;
    metrics?: IInventoryArchiveMetrics;
    categories?: IInventoryArchiveCategory[];
    items?: IInventoryArchiveItem[];
    audit?: IInventoryAuditData;
    message?: string;
}
