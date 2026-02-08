/**
 * POS (Point of Sale) Types
 * Centralized type definitions for POS system
 */

// POS Item Types (migrated from usePOS.ts)
export interface IPOSItem {
    sku: string;
    name: string;
    category: string;
    retail_price: number;
    current_price: number;
    original_price: number;
    stock: number;
    stock_quantity?: number;
    image_url?: string;
    primary_image?: {
        image_path?: string;
        alt_text?: string;
        is_primary?: boolean;
        sort_order?: number;
    } | null;
    is_on_sale: boolean;
    sale_discount_percentage: number;
    status: string;
}

export interface IPOSPricing {
    subtotal: number;
    tax: number;
    total: number;
    discount?: number;
}

export interface IPOSCheckoutResponse {
    success: boolean;
    order_id?: number;
    error?: string;
}

export interface IPOSInventoryResponse {
    success: boolean;
    data: IPOSItem[];
}
