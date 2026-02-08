import React from 'react';

interface ItemDetailsActionsProps {
    total_price: number;
    quantity: number;
    onQuantityChange: (qty: number) => void;
    onAddToCart: () => void;
    maxQty: number;
    disabled: boolean;
    buttonText?: string;
}

/**
 * ItemDetailsActions v1.1.0
 * Mobile-first purchase controls with sticky-friendly compact sizing.
 */
export const ItemDetailsActions: React.FC<ItemDetailsActionsProps> = ({
    total_price,
    quantity,
    onQuantityChange,
    onAddToCart,
    maxQty,
    disabled,
    buttonText = 'Own This'
}) => {
    const resolvedButtonText = (buttonText || '').trim() || 'Add to Cart';
    const subtotal = total_price * quantity;

    return (
        <div className="mt-4 w-full sm:mt-8">
            <div className="mb-3 text-right text-xs font-bold uppercase tracking-[0.08em] text-slate-500 sm:mb-4">
                Subtotal: <span className="text-slate-900">${subtotal.toFixed(2)}</span>
            </div>
            <div className="item-actions-row-v73 flex w-full flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:gap-4">
                <div className="qty-selector-v73 inline-flex h-14 w-full items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-100 p-1.5 sm:w-auto sm:min-w-[172px] sm:justify-center sm:gap-3">
                    <button
                        onClick={() => onQuantityChange(Math.max(1, quantity - 1))}
                        className="qty-btn-v73 flex h-11 w-11 items-center justify-center rounded-lg border border-slate-200 bg-white text-xl font-black text-slate-600 shadow-sm transition hover:text-[var(--brand-primary)]"
                        type="button"
                        aria-label="Decrease quantity"
                    >
                        −
                    </button>
                    <span className="inline-block w-9 text-center text-lg font-black text-slate-900">{quantity}</span>
                    <button
                        onClick={() => onQuantityChange(Math.min(maxQty, quantity + 1))}
                        className="qty-btn-v73 flex h-11 w-11 items-center justify-center rounded-lg border border-slate-200 bg-white text-xl font-black text-slate-600 shadow-sm transition hover:text-[var(--brand-primary)] disabled:opacity-50"
                        type="button"
                        disabled={maxQty <= quantity}
                        aria-label="Increase quantity"
                    >
                        +
                    </button>
                </div>

                <button
                    onClick={onAddToCart}
                    disabled={disabled || maxQty <= 0}
                    className="own-this-btn-v73 flex h-14 w-full flex-1 items-center justify-center gap-2 rounded-xl border-0 bg-[var(--brand-primary)] px-5 text-base font-black uppercase tracking-[0.05em] text-white shadow-[0_10px_20px_rgba(var(--brand-primary-rgb),0.3)] transition hover:bg-[var(--brand-secondary)] hover:shadow-[0_12px_24px_rgba(var(--brand-primary-rgb),0.4)] disabled:cursor-not-allowed disabled:bg-slate-400 disabled:shadow-none sm:text-lg"
                    type="button"
                >
                    <span className="btn-icon--shopping-cart text-xl" aria-hidden="true" />
                    <span>{resolvedButtonText}</span>
                </button>
            </div>
        </div>
    );
};
