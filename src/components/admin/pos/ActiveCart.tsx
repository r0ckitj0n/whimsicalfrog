import React from 'react';

import { PAYMENT_METHOD } from '../../../core/constants.js';

interface ICartItem {
    sku: string;
    name?: string;
    price: number;
    quantity: number;
    image?: string;
    image_url?: string;
    option_size?: string;
    option_color?: string;
    optionGender?: string;
}

interface IPricing {
    subtotal: number;
    tax: number;
    total: number;
    discount?: number;
    coupon?: {
        code: string;
    };
}

interface ActiveCartProps {
    cartItems: ICartItem[];
    pricing: IPricing | null;
    onUpdateQuantity: (sku: string, change: number) => void;
    onRemoveFromCart: (sku: string) => void;
    onClearCart: () => void;
    onApplyCoupon: (code: string) => void;
    onRemoveCoupon: () => void;
    onCheckout: (method: string) => void;
    onOpenCashModal: () => void;
    coupon_code: string;
    setCouponCode: (code: string) => void;
    isLoading: boolean;
}

export const ActiveCart: React.FC<ActiveCartProps> = ({
    cartItems,
    pricing,
    onUpdateQuantity,
    onRemoveFromCart,
    onClearCart,
    onApplyCoupon,
    onRemoveCoupon,
    onCheckout,
    onOpenCashModal,
    coupon_code,
    setCouponCode,
    isLoading
}) => {
    return (
        <aside className="w-full flex-1 min-h-0 flex-shrink-0 flex flex-col bg-gray-100 border-l border-white/5 relative z-[var(--wf-z-elevated)] shadow-[-20px_0_40px_rgba(0,0,0,0.05)]">
            <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                <h2 className="text-lg font-black text-gray-900 uppercase tracking-tighter flex items-center gap-2">
                    <div className="admin-action-btn btn-icon--shopping-cart text-3xl" data-help-id="pos-cart-active" />
                    Active Cart
                </h2>
                <span className="px-3 py-1 bg-[var(--brand-primary)] text-white rounded-full text-[10px] font-black uppercase tracking-widest shadow-lg shadow-[var(--brand-primary)]/30">
                    {cartItems.reduce((acc: number, it) => acc + it.quantity, 0)} Items
                </span>
            </div>

            <div className="flex-1 overflow-y-auto p-6 space-y-3 wf-scrollbar">
                {cartItems.length === 0 ? (
                    <div className="h-full flex flex-col items-center justify-center text-center space-y-4 opacity-30 grayscale">
                        <div className="w-24 h-24 bg-white rounded-full flex items-center justify-center shadow-inner">
                            <div className="admin-action-btn btn-icon--shopping-bag text-5xl opacity-10" data-help-id="pos-cart-empty" />
                        </div>
                        <p className="text-sm font-black uppercase tracking-widest text-gray-500">Cart is Empty</p>
                    </div>
                ) : (
                    cartItems.map((item) => (
                        <div key={item.sku} className="bg-white border border-gray-200 rounded-2xl p-3 flex gap-4 transition-all hover:border-[var(--brand-primary)]/30 shadow-sm">
                            <div className="w-16 h-16 bg-gray-50 rounded-xl flex-shrink-0 overflow-hidden border border-black/5 p-2">
                                <img
                                    src={item.image_url || item.image}
                                    alt={item.name || `Item image for ${item.sku}`}
                                    className="w-10 h-10 rounded-lg object-cover"
                                    loading="lazy"
                                />
                            </div>
                            <div className="flex-1 min-w-0 flex flex-col justify-between">
                                <div className="text-xs font-black text-gray-900 truncate">{item.name}</div>
                                <div className="space-y-1">
                                    {(item.option_color || item.option_size || item.optionGender) && (
                                        <div className="flex flex-wrap gap-1">
                                            {item.optionGender && <span className="text-[10px] bg-gray-100 px-1.5 py-0.5 rounded font-bold text-gray-500 uppercase">{item.optionGender}</span>}
                                            {item.option_color && <span className="text-[10px] bg-gray-100 px-1.5 py-0.5 rounded font-bold text-gray-500 uppercase">{item.option_color}</span>}
                                            {item.option_size && <span className="text-[10px] bg-gray-100 px-1.5 py-0.5 rounded font-bold text-gray-500 uppercase">{item.option_size}</span>}
                                        </div>
                                    )}
                                    <div className="flex items-center justify-between">
                                        <div className="text-sm font-black text-[var(--brand-primary)]">
                                            ${(item.price * item.quantity).toFixed(2)}
                                            {item.quantity > 1 && <span className="text-[10px] text-gray-400 font-bold ml-2">(${item.price} × {item.quantity})</span>}
                                        </div>
                                        <div className="flex items-center gap-2 bg-gray-50 p-1 rounded-lg">
                                            <button onClick={() => onUpdateQuantity(item.sku, -1)} className="w-6 h-6 flex items-center justify-center text-gray-400 hover:text-gray-900 hover:bg-gray-200 rounded">-</button>
                                            <span className="text-xs font-black min-w-[20px] text-center">{item.quantity}</span>
                                            <button onClick={() => onUpdateQuantity(item.sku, 1)} className="w-6 h-6 flex items-center justify-center text-gray-400 hover:text-gray-900 hover:bg-gray-200 rounded">+</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button
                                onClick={() => onRemoveFromCart(item.sku)}
                                className="admin-action-btn btn-icon--delete"
                                type="button"
                                data-help-id="pos-cart-action-remove"
                            />
                        </div>
                    ))
                )}
            </div>

            <div className="p-6 bg-white border-t border-[var(--brand-gray-200)] space-y-6 shadow-[0_-20px_40px_rgba(0,0,0,0.03)] rounded-tl-[3rem]">
                <div className="space-y-3">
                    {!pricing?.coupon ? (
                        <div className="flex gap-2">
                            <input
                                type="text"
                                value={coupon_code}
                                onChange={e => setCouponCode(e.target.value.toUpperCase())}
                                placeholder="Promo Code"
                                className="form-input flex-1 py-3 text-sm rounded-xl border-[var(--brand-gray-100)] bg-[var(--brand-gray-50)] focus:bg-white shadow-inner"
                            />
                            <button
                                onClick={() => onApplyCoupon(coupon_code)}
                                className="px-6 py-2 bg-transparent border-0 text-[var(--brand-gray-900)] hover:bg-[var(--brand-gray-100)] rounded-xl text-xs font-black uppercase tracking-widest transition-all"
                            >
                                Apply
                            </button>
                        </div>
                    ) : (
                        <div className="flex items-center justify-between bg-[var(--brand-accent)]/10 border border-[var(--brand-accent)]/20 px-4 py-2 rounded-xl text-[var(--brand-accent)] animate-in slide-in-from-top-2">
                            <div className="flex items-center gap-2">
                                <div className="admin-action-btn btn-icon--tag text-sm" data-help-id="pos-cart-coupon" />
                                <span className="text-sm font-bold uppercase tracking-tight">{pricing.coupon.code} Applied</span>
                            </div>
                            <button
                                type="button"
                                onClick={onRemoveCoupon}
                                className="admin-action-btn btn-icon--delete"
                                data-help-id="pos-cart-action-remove-coupon"
                            />
                        </div>
                    )}

                    <div className="space-y-2">
                        <div className="flex justify-between text-xs font-bold text-[var(--brand-gray-400)] uppercase tracking-widest">
                            <span>Subtotal</span>
                            <span className="text-[var(--brand-gray-900)]">${pricing?.subtotal?.toFixed(2) || '0.00'}</span>
                        </div>
                        <div className="flex justify-between text-xs font-bold text-[var(--brand-gray-400)] uppercase tracking-widest">
                            <span>Tax (8.25%)</span>
                            <span className="text-[var(--brand-gray-900)]">${pricing?.tax?.toFixed(2) || '0.00'}</span>
                        </div>
                        {pricing && (pricing.discount || 0) > 0 && (
                            <div className="flex justify-between text-xs font-bold text-[var(--brand-accent)] uppercase tracking-widest">
                                <span>Discount</span>
                                <span className="font-black">-${(pricing.discount || 0).toFixed(2)}</span>
                            </div>
                        )}
                        <div className="flex justify-between items-baseline pt-4 border-t border-[var(--brand-gray-100)]">
                            <span className="text-lg font-black text-[var(--brand-gray-900)] uppercase tracking-tighter">Total</span>
                            <span className="text-4xl font-black text-[var(--brand-primary)] tracking-tighter">
                                ${pricing?.total?.toFixed(2) || '0.00'}
                            </span>
                        </div>
                    </div>
                </div>

                <div className="flex gap-3">
                    <button
                        onClick={() => onCheckout(PAYMENT_METHOD.SQUARE)}
                        disabled={cartItems.length === 0 || isLoading || !pricing}
                        className="pos-complete-btn flex-1"
                    >
                        <span className="text-xl">💳</span>
                        Card
                    </button>
                    <button
                        onClick={onOpenCashModal}
                        disabled={cartItems.length === 0 || isLoading || !pricing}
                        className="pos-cash-btn flex-1"
                    >
                        <span className="text-xl">💵</span>
                        Cash
                    </button>
                </div>
            </div>
        </aside>
    );
};
