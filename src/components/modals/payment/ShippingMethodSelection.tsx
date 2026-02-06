import React from 'react';
import { SHIPPING_METHOD } from '../../../core/constants.js';
import { ShippingMethod } from '../../../types/payment.js';

interface ShippingMethodSelectionProps {
    selectedMethod: ShippingMethod;
    onSelect: (method: ShippingMethod) => void;
}

export const ShippingMethodSelection: React.FC<ShippingMethodSelectionProps> = ({
    selectedMethod,
    onSelect
}) => {
    const methods = [
        { value: SHIPPING_METHOD.PICKUP, label: 'Customer Pickup', fee: 0, badge: 'PICKUP\nNO FEE' },
        { value: SHIPPING_METHOD.LOCAL_DELIVERY, label: 'Local Delivery', fee: 5.99 },
        { value: SHIPPING_METHOD.USPS, label: 'USPS', fee: 8.99 },
        { value: SHIPPING_METHOD.FEDEX, label: 'FedEx', fee: 12.99 },
        { value: SHIPPING_METHOD.UPS, label: 'UPS', fee: 14.99 }
    ];

    const selectedMethodData = methods.find(m => m.value === selectedMethod);

    return (
        <section className="bg-white rounded-2xl p-4 border border-gray-200">
            <div className="flex items-center justify-between mb-3">
                <h3 style={{
                    margin: 0,
                    color: 'var(--brand-secondary)',
                    fontFamily: "'Merienda', cursive",
                    fontSize: '1.25rem',
                    fontWeight: 700,
                    fontStyle: 'italic'
                }}>
                    Shipping method
                </h3>

                {/* Badge for pickup */}
                {selectedMethod === SHIPPING_METHOD.PICKUP && (
                    <div style={{
                        padding: '8px 12px',
                        background: 'var(--brand-primary-bg)',
                        border: '1px solid var(--brand-primary-border)',
                        borderRadius: '8px',
                        textAlign: 'center',
                        lineHeight: 1.2
                    }}>
                        <div style={{ fontSize: '0.75rem', fontWeight: 700, color: 'var(--brand-primary)' }}>PICKUP</div>
                        <div style={{ fontSize: '0.625rem', fontWeight: 600, color: 'var(--brand-primary)' }}>NO FEE</div>
                    </div>
                )}
            </div>

            <select
                value={selectedMethod}
                onChange={(e) => onSelect(e.target.value as ShippingMethod)}
                style={{
                    width: '100%',
                    minHeight: '52px',
                    padding: '12px 16px',
                    border: '1px solid #d1d5db',
                    borderRadius: '12px',
                    fontSize: '1rem',
                    color: '#374151',
                    background: '#ffffff',
                    cursor: 'pointer',
                    marginBottom: '1rem',
                    boxShadow: '0 2px 4px rgba(0,0,0,0.05)',
                    appearance: 'none',
                    backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E")`,
                    backgroundRepeat: 'no-repeat',
                    backgroundPosition: 'right 16px center',
                    backgroundSize: '16px'
                }}
            >
                {methods.map((m) => (
                    <option key={m.value} value={m.value}>
                        {m.label}
                    </option>
                ))}
            </select>

            <div className="text-sm text-gray-600">
                <div>Free USPS shipping on orders $50+.</div>
                <div className="text-gray-400 text-xs mt-1">Select a method. Address is required for delivery and carriers.</div>
            </div>
        </section>
    );
};
