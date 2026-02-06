/**
 * Shipping Types
 * Centralized type definitions for shipping rates and logistics
 */

// Shipping Rates Types (migrated from useShippingSettings.ts)
export interface IShippingRates {
    free_shipping_threshold: string | number;
    local_delivery_fee: string | number;
    shipping_rate_usps: string | number;
    shipping_rate_fedex: string | number;
    shipping_rate_ups: string | number;
    shipping_rate_per_lb_usps?: string | number;
    shipping_rate_per_lb_fedex?: string | number;
    shipping_rate_per_lb_ups?: string | number;
    shipping_category_weight_defaults?: Record<string, number>;
}
