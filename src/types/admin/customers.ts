export interface ICustomer {
    id: string | number;
    username: string;
    email: string;
    role: string;
    first_name: string;
    last_name: string;
    phone_number: string;
    password?: string;
    address_line_1: string;
    address_line_2: string;
    city: string;
    state: string;
    zip_code: string;
    order_count: number;
    // Metadata
    company?: string;
    job_title?: string;
    preferred_contact?: string;
    preferred_language?: string;
    marketing_opt_in?: string | number | boolean;
    status?: string;
    vip?: string | number | boolean;
    tax_exempt?: string | number | boolean;
    referral_source?: string;
    birthdate?: string;
    tags?: string;
    admin_notes?: string;
    // Order History
    order_history?: IOrderHistoryItem[];
}

export interface IOrderHistoryItem {
    id: string | number;
    date: string;
    created_at?: string;
    total: number | string;
    total_amount?: number | string;
    status: string;
    payment_method?: string;
    payment_status?: string;
    shipping_method?: string;
}

export interface ICustomerAddress {
    id: string | number;
    user_id: string | number;
    address_name: string;
    address_line_1: string;
    address_line_2: string;
    city: string;
    state: string;
    zip_code: string;
    is_default: boolean | number;
}

export interface ICustomerNote {
    id: number;
    user_id: number | string;
    note_text: string;
    author_username: string;
    created_at: string;
}

export interface ICustomersResponse {
    success: boolean;
    customers: ICustomer[];
}
