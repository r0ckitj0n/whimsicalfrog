import React from 'react';
import { IPricingSummary, ShippingWaiverReason } from '../../../types/payment.js';

interface CartItem {
    sku: string;
    name?: string;
    price: number | string;
    quantity: number;
}

interface OrderSummaryProps {
    items: CartItem[];
    pricing: IPricingSummary;
    isLoading: boolean;
    error: string | null;
}

function shippingWaiverLabel(reason?: ShippingWaiverReason): string | null {
    if (reason === 'pickup') return 'Pickup — no shipping charge';
    if (reason === 'vip') return 'VIP free shipping';
    if (reason === 'threshold') return 'Free USPS shipping ($50+)';
    return null;
}

export const OrderSummary: React.FC<OrderSummaryProps> = ({
    items,
    pricing,
    isLoading,
    error
}) => {
    return (
        <section className="bg-white rounded-2xl p-4 border border-gray-200">
            <h3 style={{
                margin: 0,
                marginBottom: '0.75rem',
                paddingBottom: '0.5rem',
                borderBottom: '1px solid #e5e7eb',
                color: 'var(--brand-secondary)',
                fontFamily: "'Merienda', cursive",
                fontSize: '1.25rem',
                fontWeight: 700,
                fontStyle: 'italic'
            }}>
                Order summary
            </h3>

            <div className="space-y-2 mb-3">
                {items.map((item) => (
                    <div key={item.sku} className="flex justify-between items-start text-sm">
                        <div className="flex-1 min-w-0 pr-4">
                            <div className="text-gray-700">{item.name || item.sku}</div>
                        </div>
                        <div className="text-gray-900 font-medium whitespace-nowrap">
                            {item.quantity} × ${Number(item.price).toFixed(2)}
                        </div>
                    </div>
                ))}
                {items.length === 0 && (
                    <p className="text-sm text-gray-400 italic">No items in cart</p>
                )}
            </div>

            <div className="space-y-1.5 pt-3 border-t border-gray-200">
                <div className="flex justify-between text-sm">
                    <span className="text-gray-600">Subtotal</span>
                    <span className="font-semibold text-gray-900">${pricing.subtotal.toFixed(2)}</span>
                </div>
                <div className="flex justify-between text-sm items-center gap-2">
                    <span className="text-gray-600 flex items-center gap-2">
                        Shipping
                        {pricing.shipping === 0 && pricing.shipping_waiver_reason === 'vip' && (
                            <span
                                data-testid="order-summary-vip-badge"
                                className="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                                style={{
                                    background: 'var(--brand-primary-bg)',
                                    color: 'var(--brand-primary)',
                                    border: '1px solid var(--brand-primary-border)'
                                }}
                            >
                                VIP
                            </span>
                        )}
                    </span>
                    <span className="font-semibold text-gray-900">${pricing.shipping.toFixed(2)}</span>
                </div>
                {pricing.shipping === 0 && shippingWaiverLabel(pricing.shipping_waiver_reason) && (
                    <div className="text-xs text-gray-500 -mt-0.5">
                        {shippingWaiverLabel(pricing.shipping_waiver_reason)}
                    </div>
                )}
                <div className="flex justify-between text-sm">
                    <span className="text-gray-600">Tax</span>
                    <span className="font-semibold text-gray-900">${pricing.tax.toFixed(2)}</span>
                </div>
                {pricing.discount > 0 && (
                    <div className="flex justify-between text-sm text-brand-primary">
                        <span>Discount</span>
                        <span className="font-semibold">-${pricing.discount.toFixed(2)}</span>
                    </div>
                )}
                <div className="flex justify-between items-baseline pt-2 mt-1.5 border-t-2 border-gray-200">
                    <span style={{
                        color: 'var(--brand-secondary)',
                        fontFamily: "'Merienda', cursive",
                        fontSize: '1rem',
                        fontWeight: 600,
                        fontStyle: 'italic'
                    }}>Total</span>
                    <span style={{
                        color: 'var(--brand-secondary)',
                        fontSize: '1.5rem',
                        fontWeight: 700
                    }}>
                        ${pricing.total.toFixed(2)}
                    </span>
                </div>
            </div>

            {error && (
                <div className="mt-4 p-3 bg-brand-error-bg border border-brand-error-border rounded-lg text-brand-error text-sm font-medium">
                    {error}
                </div>
            )}

            {isLoading && (
                <div className="mt-4 text-center text-gray-400 text-sm">
                    Loading...
                </div>
            )}
        </section>
    );
};
