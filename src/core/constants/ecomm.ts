export const ORDER_STATUS = {
    PENDING: 'Pending',
    PROCESSING: 'Processing',
    SHIPPED: 'Shipped',
    DELIVERED: 'Delivered',
    CANCELLED: 'Cancelled'
} as const;

export type OrderStatus = typeof ORDER_STATUS[keyof typeof ORDER_STATUS];

export const PAYMENT_STATUS = {
    PENDING: 'Pending',
    PAID: 'Paid',
    FAILED: 'Failed',
    REFUNDED: 'Refunded'
} as const;

export type PaymentStatus = typeof PAYMENT_STATUS[keyof typeof PAYMENT_STATUS];

export const PAYMENT_METHOD = {
    SQUARE: 'Square',
    CASH: 'Cash',
    OTHER: 'Other'
} as const;

export type PaymentMethod = typeof PAYMENT_METHOD[keyof typeof PAYMENT_METHOD];

export const SHIPPING_METHOD = {
    USPS: 'USPS',
    UPS: 'UPS',
    FEDEX: 'FedEx',
    PICKUP: 'Customer Pickup',
    LOCAL_DELIVERY: 'Local Delivery'
} as const;

export type ShippingMethod = typeof SHIPPING_METHOD[keyof typeof SHIPPING_METHOD];

export const DISCOUNT_TYPE = {
    PERCENTAGE: 'percentage',
    FIXED: 'fixed'
} as const;

export type DiscountType = typeof DISCOUNT_TYPE[keyof typeof DISCOUNT_TYPE];

export const EMAIL_STATUS = {
    SENT: 'sent',
    FAILED: 'failed',
    PENDING: 'pending'
} as const;

export type EmailStatus = typeof EMAIL_STATUS[keyof typeof EMAIL_STATUS];
