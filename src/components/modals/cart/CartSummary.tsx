import React from 'react';
import { Coupon } from '../../../commerce/cart/types.js';
import { useAuthContext } from '../../../context/AuthContext.js';

interface CartSummaryProps {
    subtotal: number;
    total: number;
    minimumCheckoutTotal: number;
    coupon: Coupon | null;
    coupon_code: string;
    isApplyingCoupon: boolean;
    onApplyCoupon: (e: React.FormEvent) => void;
    onCouponCodeChange: (code: string) => void;
    onRemoveCoupon: () => void;
    onClearCart: () => Promise<void> | void;
    onClose: () => void;
}

export const CartSummary: React.FC<CartSummaryProps> = ({
    subtotal,
    total,
    minimumCheckoutTotal,
    coupon,
    coupon_code,
    isApplyingCoupon,
    onApplyCoupon,
    onCouponCodeChange,
    onRemoveCoupon,
    onClearCart,
    onClose,
}) => {
    const { isLoggedIn } = useAuthContext();
    const requiresMinimum = minimumCheckoutTotal > 0;
    const belowMinimum = requiresMinimum && total < minimumCheckoutTotal;
    const openCheckout = () => {
        if (typeof window === 'undefined') return;
        if (typeof window.openPaymentModal === 'function') {
            window.openPaymentModal();
            return;
        }
        if (window.WF_PaymentModal?.open) {
            window.WF_PaymentModal.open();
            return;
        }
        if (window.showError) {
            window.showError('Checkout unavailable: missing payment opener APIs (window.openPaymentModal and window.WF_PaymentModal.open).');
        }
    };

    return (
        <div className="cart-modal-footer-content p-6 bg-gray-50 border-t border-gray-100 space-y-4">
            {/* Coupon Section */}
            {coupon ? (
                <div className="flex items-center justify-between bg-white border border-brand-primary/20 px-4 py-2 rounded-xl text-brand-primary animate-in slide-in-from-top-2 shadow-sm">
                    <div className="flex items-center gap-2">
                        <span className="btn-icon--tag text-brand-primary" aria-hidden="true" style={{ fontSize: '14px' }} />
                        <span className="text-[10px] font-black uppercase tracking-widest">{coupon.code} Applied</span>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className="text-sm font-black">-${Number(coupon.discount_amount).toFixed(2)}</span>
                        <button
                            type="button"
                            onClick={onRemoveCoupon}
                            className="text-gray-400 hover:text-brand-secondary transition-colors p-1"
                            aria-label="Remove coupon"
                            data-help-id="cart-remove-coupon"
                        >
                            <span className="admin-action-btn btn-icon--close" style={{ fontSize: '12px' }} />
                        </button>
                    </div>
                </div>
            ) : (
                <form onSubmit={onApplyCoupon} className="flex gap-2">
                    <div className="relative flex-1">
                        <input
                            type="text"
                            value={coupon_code}
                            onChange={e => onCouponCodeChange(e.target.value.toUpperCase())}
                            placeholder="PROMO CODE"
                            className="w-full pl-3 py-2 text-[10px] font-black tracking-widest uppercase rounded-xl border-2 border-gray-100 focus:border-brand-primary outline-none transition-all"
                        />
                    </div>
                    <button
                        type="submit"
                        disabled={isApplyingCoupon || !coupon_code.trim()}
                        className="px-6 py-2 bg-white border-2 border-gray-100 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-400 hover:border-brand-primary hover:text-brand-primary transition-all disabled:opacity-50"
                    >
                        {isApplyingCoupon ? (
                            <div className="wf-emoji-loader" style={{ fontSize: '14px' }}>⏳</div>
                        ) : 'Apply'}
                    </button>
                </form>
            )}

            <div className="cart-footer-bar flex flex-col gap-2">
                <div className="flex justify-between text-[10px] font-black uppercase tracking-widest text-gray-400">
                    <span>Subtotal</span>
                    <span className="text-gray-900">${subtotal.toFixed(2)}</span>
                </div>
                {coupon && (
                    <div className="flex justify-between text-[10px] font-black uppercase tracking-widest text-brand-primary">
                        <span>Discount</span>
                        <span>-${Number(coupon.discount_amount).toFixed(2)}</span>
                    </div>
                )}

                <div className="cart-subtotal flex justify-between items-center pt-2 mt-2 border-t-2 border-gray-100">
                    <span className="total-label text-xl font-black text-gray-900 merienda-font">Total</span>
                    <span className="total-amount text-3xl font-black text-brand-primary merienda-font">${total.toFixed(2)}</span>
                </div>
            </div>

            <div className="flex gap-3 pt-2">
                <button
                    type="button"
                    onClick={() => { void onClearCart(); }}
                    className="px-4 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest text-gray-300 hover:text-brand-secondary transition-colors"
                    data-help-id="cart-empty"
                >
                    Empty Pond
                </button>
                <button
                    type="button"
                    onClick={() => {
                        if (belowMinimum) {
                            if (typeof window !== 'undefined' && window.showError) {
                                window.showError(`Minimum order total is $${minimumCheckoutTotal.toFixed(2)}.`);
                            }
                            return;
                        }
                        if (!isLoggedIn) {
                            if (typeof window !== 'undefined') {
                                window.__WF_PENDING_CHECKOUT_AFTER_LOGIN = true;
                                if (typeof window.openLoginModal === 'function') {
                                    onClose();
                                    window.openLoginModal();
                                    return;
                                }
                                window.__WF_PENDING_CHECKOUT_AFTER_LOGIN = false;
                                window.showError?.('Checkout requires login, but the login modal opener is unavailable.');
                            }
                            return;
                        }
                        // Close this modal immediately to prevent seeing the empty cart splash
                        // once the order is placed and the cart is cleared.
                        onClose();
                        openCheckout();
                    }}
                    className={`cart-checkout-btn ${belowMinimum ? 'opacity-80' : ''}`}
                >
                    Checkout • ${total.toFixed(2)}
                </button>
            </div>

            {belowMinimum && (
                <p className="text-[10px] font-black uppercase tracking-widest text-brand-secondary">
                    Minimum order total is ${minimumCheckoutTotal.toFixed(2)}
                </p>
            )}
        </div>
    );
};
