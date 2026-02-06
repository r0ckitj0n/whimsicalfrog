import React from 'react';
import { PAYMENT_METHOD } from '../../../core/constants.js';
import { PaymentMethod } from '../../../types/payment.js';

interface PaymentMethodSelectionProps {
    selectedMethod: PaymentMethod;
    onSelect: (method: PaymentMethod) => void;
}

export const PaymentMethodSelection: React.FC<PaymentMethodSelectionProps> = ({
    selectedMethod,
    onSelect
}) => {
    return (
        <section className="bg-white rounded-2xl p-4 border border-gray-200">
            <h3 style={{
                margin: 0,
                marginBottom: '0.75rem',
                color: 'var(--brand-secondary)',
                fontFamily: "'Merienda', cursive",
                fontSize: '1.25rem',
                fontWeight: 700,
                fontStyle: 'italic'
            }}>
                Payment
            </h3>

            {/* Radio buttons */}
            <div className="flex gap-4 mb-4">
                <label className="flex items-center gap-2 cursor-pointer">
                    <input
                        type="radio"
                        name="payment_method"
                        checked={selectedMethod === PAYMENT_METHOD.SQUARE}
                        onChange={() => onSelect(PAYMENT_METHOD.SQUARE)}
                        style={{
                            width: '16px',
                            height: '16px',
                            accentColor: 'var(--brand-secondary)'
                        }}
                    />
                    <span style={{
                        color: selectedMethod === PAYMENT_METHOD.SQUARE ? 'var(--brand-secondary)' : '#6b7280',
                        fontSize: '0.875rem',
                        fontWeight: 600
                    }}>
                        Credit/Debit
                    </span>
                </label>
                <label className="flex items-center gap-2 cursor-pointer">
                    <input
                        type="radio"
                        name="payment_method"
                        checked={selectedMethod === PAYMENT_METHOD.CASH}
                        onChange={() => onSelect(PAYMENT_METHOD.CASH)}
                        style={{
                            width: '16px',
                            height: '16px',
                            accentColor: 'var(--brand-secondary)'
                        }}
                    />
                    <span style={{
                        color: selectedMethod === PAYMENT_METHOD.CASH ? 'var(--brand-secondary)' : '#6b7280',
                        fontSize: '0.875rem',
                        fontWeight: 600
                    }}>
                        Cash
                    </span>
                </label>
            </div>

            {/* Square Payment Form Container */}
            {selectedMethod === PAYMENT_METHOD.SQUARE && (
                <div
                    id="pm-card-container"
                    style={{
                        minHeight: '120px',
                        padding: '16px',
                        border: '1px solid #d1d5db',
                        borderRadius: '12px',
                        background: '#ffffff',
                        transition: 'all 0.3s ease'
                    }}
                >
                    {/* The Square Payment Form will be injected here by the useSquare hook */}
                    <div className="flex flex-col items-center justify-center h-full text-gray-400 space-y-2">
                        <div className="animate-pulse">Loading secure payment form...</div>
                    </div>
                </div>
            )}

            {selectedMethod === PAYMENT_METHOD.CASH && (
                <div style={{
                    padding: '16px',
                    background: 'var(--brand-warning-bg)',
                    border: '1px solid var(--brand-warning-border)',
                    borderRadius: '8px',
                    fontSize: '0.875rem',
                    color: 'var(--brand-warning)'
                }}>
                    Cash payment will be collected at pickup or delivery.
                </div>
            )}
        </section>
    );
};
