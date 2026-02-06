import { OrderStatus } from '../../core/constants.js';

export interface IOrderNote {
    id: number;
    order_id: string | number;
    note_type: 'fulfillment' | 'payment';
    note_text: string;
    created_at: string;
    author_username?: string;
}

export interface IOrder {
    id: string | number;
    user_id: string | number | null;
    created_at: string;
    status: OrderStatus;
    total: number;
    payment_method: string;
    payment_status: string;
    shipping_method: string;
    tracking_number?: string;
    payment_at?: string;
    total_items: number;
    username?: string;
    address_line_1?: string;
    address_line_2?: string;
    city?: string;
    state?: string;
    zip_code?: string;
    items?: IOrderItem[];
    fulfillment_notes?: string;
    payment_notes?: string;
    shipping_cost?: number;
    tax_amount?: number;
    notes?: IOrderNote[];
    discount_amount?: number;
    coupon_code?: string;
    total_amount?: number;
}

export interface IRecentOrder {
    id: string | number;
    username: string;
    total: number;
    status: string;
    created_at: string;
}

export interface IOrderItem {
    id: string | number;
    order_id: string | number;
    sku: string;
    name: string;
    quantity: number;
    price: number;
    image_url?: string;
}

export interface IOrdersResponse {
    success?: boolean;
    data?: {
        orders: IOrder[];
        status_options: string[];
        payment_method_options: string[];
        shipping_method_options: string[];
        payment_status_options: string[];
        filters: {
            filter_created_at: string;
            filter_items: string;
            filter_status: string;
            filter_payment_method: string;
            filter_shipping_method: string;
            filter_payment_status: string;
        };
    };
    orders: IOrder[];
    status_options: string[];
    payment_method_options: string[];
    shipping_method_options: string[];
    payment_status_options: string[];
    filters: {
        filter_created_at: string;
        filter_items: string;
        filter_status: string;
        filter_payment_method: string;
        filter_shipping_method: string;
        filter_payment_status: string;
    };
}
