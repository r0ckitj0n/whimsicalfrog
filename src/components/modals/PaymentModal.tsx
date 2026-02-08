import React from 'react';
import { createPortal } from 'react-dom';
import { AddressSelection } from './payment/AddressSelection.js';
import { ShippingMethodSelection } from './payment/ShippingMethodSelection.js';
import { PaymentMethodSelection } from './payment/PaymentMethodSelection.js';
import { OrderSummary } from './payment/OrderSummary.js';
import { usePaymentModal } from '../../hooks/usePaymentModal.js';

interface PaymentModalProps {
    isOpen: boolean;
    onClose: () => void;
}

/**
 * PaymentModal v1.2.9
 * Uses React Portal to ensure topmost stacking and 95% viewport height cap.
 * Refactored into a hook to satisfy the <250 line rule.
 */
export const PaymentModal: React.FC<PaymentModalProps> = ({ isOpen, onClose }) => {
    const {
        user,
        items,
        isLoading,
        error,
        pricing,
        selected_address_id,
        setSelectedAddressId,
        shipping_method,
        setShippingMethod,
        payment_method,
        setPaymentMethod,
        addresses,
        isPlacingOrder,
        handlePlaceOrder
    } = usePaymentModal(isOpen, onClose);

    if (!isOpen || !user) return null;

    const modalContent = (
        <div
            className="wf-modal-overlay show"
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 'var(--wf-z-modal)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                backgroundColor: 'rgba(0, 0, 0, 0.85)',
                backdropFilter: 'blur(8px)',
                width: '100vw',
                height: '100dvh',
                padding: 'max(12px, env(safe-area-inset-top, 0px)) max(12px, 2.5vw) max(12px, env(safe-area-inset-bottom, 0px))',
                boxSizing: 'border-box'
            }}
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <div
                className="wf-modal-card payment-modal my-auto animate-in zoom-in-95 slide-in-from-bottom-4 duration-300 overflow-hidden flex flex-col"
                style={{
                    maxWidth: '1000px',
                    width: '100%',
                    height: 'min(100%, calc(100dvh - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px) - 24px))',
                    maxHeight: '100%',
                    backgroundColor: 'white',
                    borderRadius: '24px',
                    boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.5)',
                    position: 'relative'
                }}
                onClick={e => e.stopPropagation()}
            >
                {/* Header */}
                <div className="wf-modal-header" style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '1rem 1.75rem',
                    background: '#faf8f5',
                    borderBottom: '1px solid #e5e2dc',
                    flexShrink: 0
                }}>
                    <h2 style={{
                        margin: 0,
                        color: 'var(--brand-secondary)',
                        fontFamily: "'Merienda', cursive",
                        fontSize: '1.5rem',
                        fontWeight: 700,
                        fontStyle: 'italic'
                    }}>
                        Checkout
                    </h2>
                </div>

                <div className="flex-1 overflow-y-auto p-6" style={{ background: '#faf8f5', minHeight: 0, WebkitOverflowScrolling: 'touch' }}>
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div className="space-y-5">
                            <AddressSelection
                                addresses={addresses}
                                selected_address_id={selected_address_id}
                                onSelect={setSelectedAddressId}
                            />

                            <ShippingMethodSelection
                                selectedMethod={shipping_method}
                                onSelect={(m) => setShippingMethod(m)}
                            />
                        </div>

                        <div className="space-y-5">
                            <OrderSummary
                                items={items}
                                pricing={pricing}
                                isLoading={isLoading}
                                error={error}
                            />

                            <PaymentMethodSelection
                                selectedMethod={payment_method}
                                onSelect={(m) => setPaymentMethod(m)}
                            />
                        </div>
                    </div>
                </div>

                {/* Footer */}
                <div style={{
                    display: 'flex',
                    flexDirection: 'column',
                    gap: '0.4rem',
                    padding: '0.75rem 1.75rem',
                    paddingBottom: 'calc(0.75rem + env(safe-area-inset-bottom, 0px))',
                    background: '#faf8f5',
                    borderTop: '1px solid #e5e2dc',
                    flexShrink: 0
                }}>
                    <button
                        type="button"
                        onClick={onClose}
                        style={{
                            width: '100%',
                            padding: '14px',
                            background: 'var(--brand-secondary)',
                            color: '#ffffff',
                            border: 'none',
                            borderRadius: '8px',
                            fontSize: '0.9rem',
                            fontWeight: 600,
                            cursor: 'pointer',
                            transition: 'all 0.2s ease'
                        }}
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={handlePlaceOrder}
                        disabled={isPlacingOrder || isLoading || !selected_address_id}
                        style={{
                            width: '100%',
                            padding: '14px',
                            background: isPlacingOrder || isLoading || !selected_address_id
                                ? '#9ca3af'
                                : 'var(--brand-primary)',
                            color: '#ffffff',
                            border: 'none',
                            borderRadius: '8px',
                            fontSize: '0.9rem',
                            fontWeight: 600,
                            cursor: isPlacingOrder || isLoading || !selected_address_id ? 'not-allowed' : 'pointer',
                            transition: 'all 0.2s ease'
                        }}
                    >
                        {isPlacingOrder ? 'Processing...' : 'Place order'}
                    </button>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};

export default PaymentModal;
