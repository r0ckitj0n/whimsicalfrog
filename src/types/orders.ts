import { OrderStatus } from '../core/constants.js';

export interface IRecentOrder {
    id: string | number;
    username: string;
    total: number;
    total_amount?: number; // Legacy field alias for total
    status: string;
    created_at: string;
}

export interface IOrder {
    id: number;
    user_id: number;
    status: OrderStatus;
    total_amount: number;
    items: IOrderItem[];
    created_at: string;
}

export interface IOrderItem {
    id: number;
    order_id: number;
    sku: string;
    quantity: number;
    unit_price: number;
}

export interface IReceiptData {
    order_id: string;
    date: string;
    payment_status: string;
    items: Array<{
        sku: string;
        item_name: string;
        quantity: number;
        price: string;
        ext_price: string;
    }>;
    subtotal: string;
    shipping: string;
    tax: string;
    total: string;
    receipt_message: {
        title: string;
        content: string;
    };
    sales_verbiage: Record<string, string>;
    business_info: {
        name: string;
        tagline: string;
        phone: string;
        domain: string;
        url: string;
        address_block: string;
        owner: string;
    };
    policy_links: {
        label: string;
        url: string;
    }[];
}

// Fulfillment Types (migrated from FulfillmentTable.tsx)
export interface IFulfillmentOrder {
    id: number | string;
    username?: string;
    created_at: string;
    total_items: number;
    total: number | string;
    payment_status: string;
    payment_at?: string | null;
    status: string;
    payment_method: string | null;
    shipping_method: string | null;
}

