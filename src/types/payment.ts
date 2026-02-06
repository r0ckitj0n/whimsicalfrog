import { PAYMENT_METHOD, SHIPPING_METHOD, ENVIRONMENT } from '../core/constants.js';

export type PaymentMethod = typeof PAYMENT_METHOD[keyof typeof PAYMENT_METHOD];
export type ShippingMethod = typeof SHIPPING_METHOD[keyof typeof SHIPPING_METHOD];

export interface IPricingSummary {
    subtotal: number;
    shipping: number;
    tax: number;
    discount: number;
    total: number;
}

export interface ICheckoutPayload {
    user_id: string | number;
    shipping_address_id: string | number | null;
    shipping_method: ShippingMethod;
    payment_method: PaymentMethod;
    items: Array<{ sku: string; quantity: number }>;
    source: string;
}

// Square Payment Types
export interface ISquareSettings {
    enabled: boolean;
    environment: typeof ENVIRONMENT.SANDBOX | typeof ENVIRONMENT.PRODUCTION;
    applicationId: string;
    locationId: string;
}

export interface ISquareSettingsResponse {
    success: boolean;
    settings?: {
        square_enabled: string | number | boolean;
        square_environment?: typeof ENVIRONMENT.SANDBOX | typeof ENVIRONMENT.PRODUCTION;
        square_production_application_id: string;
        square_sandbox_application_id: string;
        square_production_location_id: string;
        square_sandbox_location_id: string;
    };
}

// Cart Upsell Types
export interface IUpsellItem {
    sku: string;
    name: string;
    price: number;
    image: string;
    hasOptions: boolean;
}

export interface IUpsellApiResponse {
    success: boolean;
    data: {
        upsells: Array<{
            sku: string;
            name?: string;
            title?: string;
            price?: string | number;
            image?: string;
            thumbnail?: string;
            has_options?: boolean | number;
            hasOptions?: boolean | number;
        }>;
    };
}
