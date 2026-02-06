import React from 'react';
import { createPortal } from 'react-dom';
import CartItem from '../cart/CartItem.js';
import { CartUpsells } from './cart/CartUpsells.js';
import { CartSummary } from './cart/CartSummary.js';
import { useCartModal } from '../../hooks/useCartModal.js';

interface CartModalProps {
    isOpen: boolean;
    onClose: () => void;
}

/**
 * CartModal v1.2.9
 * Uses React Portal to ensure topmost stacking and 95% viewport height cap.
 * Refactored into a hook to satisfy the <250 line rule.
 */
export const CartModal: React.FC<CartModalProps> = ({ isOpen, onClose }) => {
    const {
        items,
        total,
        subtotal,
        coupon,
        coupon_code,
        setCouponCode,
        upsells,
        isApplyingCoupon,
        isLoadingUpsells,
        updateQuantity,
        removeItem,
        clearCart,
        removeCoupon,
        addItem,
        handleApplyCoupon
    } = useCartModal(isOpen, onClose);

    if (!isOpen) return null;

    const modalContent = (
        <div
            id="cartModalOverlay"
            className={`wf-modal-overlay ${isOpen ? 'show' : ''}`}
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <div className="confirmation-modal admin-modal-content admin-modal--actions-in-header animate-in zoom-in-95 slide-in-from-bottom-4 duration-300">
                <div className="legacy-grid-container" style={{ display: 'grid', gridTemplateRows: 'auto auto auto', height: 'auto', maxHeight: '95vh', width: '100%', overflow: 'hidden' }}>
                    <div className="cart-modal-header-bar">
                        <h2 className="cart-modal-title merienda-font">Shopping Cart</h2>
                        <button
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            aria-label="Close"
                            data-help-id="modal-close"
                        />
                    </div>

                    <div id="cartModalItems" className="modal-body overflow-y-auto">
                        {items.length === 0 ? (
                            <div className="empty-cart py-12">
                                <div className="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <span className="admin-view btn-icon--shopping-cart opacity-10" style={{ fontSize: '32px' }} />
                                </div>
                                <p>Your pond is currently empty.</p>
                                <button
                                    onClick={onClose}
                                    className="text-brand-primary font-black uppercase text-xs tracking-widest hover:underline mt-4"
                                >
                                    Continue Shopping
                                </button>
                            </div>
                        ) : (
                            <div className="cart-items-list space-y-2 p-2">
                                {items.map(item => (
                                    <CartItem
                                        key={item.sku}
                                        item={item}
                                        onUpdateQuantity={updateQuantity}
                                        onRemove={removeItem}
                                    />
                                ))}
                            </div>
                        )}

                        {items.length > 0 && (
                            <CartUpsells
                                upsells={upsells}
                                isLoading={isLoadingUpsells}
                                onAddItem={(u) => addItem({ ...u, quantity: 1 })}
                                onShowDetails={(sku) => window.showDetailedModal?.(sku)}
                            />
                        )}
                    </div>

                    {items.length > 0 && (
                        <div className="cart-modal-footer">
                            <CartSummary
                                subtotal={subtotal}
                                total={total}
                                coupon={coupon}
                                coupon_code={coupon_code}
                                isApplyingCoupon={isApplyingCoupon}
                                onApplyCoupon={handleApplyCoupon}
                                onCouponCodeChange={setCouponCode}
                                onRemoveCoupon={removeCoupon}
                                onClearCart={clearCart}
                                onClose={onClose}
                            />
                        </div>
                    )}
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};

export default CartModal;
