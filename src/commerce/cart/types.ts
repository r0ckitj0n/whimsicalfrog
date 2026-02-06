import { DISCOUNT_TYPE, DiscountType } from '../../core/constants.js';
import { IItem } from '../../types/index.js';

export interface Coupon {
    code: string;
    value: number;
    type: DiscountType;
    discount_amount?: number;
}

export interface CartItem extends Partial<IItem> {
    sku: string;
    quantity: number;
    price: number;
    name?: string;
    image?: string;
    optionGender?: string;
    option_size?: string;
    option_color?: string;
}

export interface NotificationOptions {
    title?: string;
    duration?: number;
}

export interface WFNotificationSystem {
    success: (msg: string, options?: NotificationOptions) => void;
    error: (msg: string, options?: NotificationOptions) => void;
    info: (msg: string, options?: NotificationOptions) => void;
    warning: (msg: string, options?: NotificationOptions) => void;
    [key: string]: unknown; // for flexibility with other types if needed, but we prefer the main ones
}

export interface WFGlobal {
    warn: (data: { msg: string; item?: unknown; err?: unknown }) => void;
    error: (data: { msg: string; err?: unknown }) => void;
    emit: (event: string, detail: unknown) => void;
}

export interface ICartAPI {
    load: () => void;
    save: () => void;
    add: (item: CartItem, qty?: number) => void;
    remove: (sku: string) => void;
    updateQuantity: (sku: string, qty: number) => void;
    clear: () => void;
    applyCoupon: (code: string) => Promise<boolean>;
    removeCoupon: () => void;
    getItems: () => CartItem[];
    getTotal: () => number;
    getSubtotal: () => number;
    getCoupon: () => Coupon | null;
    getCount: () => number;
    getState: () => CartState;
    setNotifications: (enabled: boolean) => void;
    refreshFromStorage: () => CartState;
    onChange: (listener: (detail: { action: string; state: CartState; [key: string]: unknown }) => void) => () => void;
}

export interface CartState {
    items: CartItem[];
    total: number;
    subtotal: number;
    count: number;
    coupon: Coupon | null;
    notifications: boolean;
    initialized: boolean;
}

export interface CartStoreOptions {
    storageKey?: string;
    notifications?: boolean;
    broadcast?: boolean;
}
