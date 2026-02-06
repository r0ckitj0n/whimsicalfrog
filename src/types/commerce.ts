export interface IShopperProfile {
    preferredCategory?: string;
    budget?: string;
    intent?: string;
    device?: string;
    region?: string;
}

export interface IRecommendation {
    sku: string;
    name: string;
    price: number;
    image: string;
}

export interface ISimulationResult {
    id: number;
    profile: IShopperProfile;
    cart_skus: string[];
    recommendations: IRecommendation[];
}

export interface IShoppingCartSettings {
    open_cart_on_add: boolean;
    merge_duplicates: boolean;
    show_upsells: boolean;
    confirm_clear_cart: boolean;
    minimum_checkout_total: number;
}

// Coupon & Discount Types (migrated from hooks)
import { DiscountType } from '../core/constants.js';

export interface ICoupon {
    id: number;
    code: string;
    type: DiscountType;
    value: number;
    description?: string;
    min_order_amount?: number;
    expires_at?: string;
    is_active: boolean;
    usage_limit?: number;
    usage_count: number;
}

export interface IDiscount {
    code: string;
    type: DiscountType;
    value: number;
    minTotal: number;
    expires: string;
    active: boolean | number;
}
