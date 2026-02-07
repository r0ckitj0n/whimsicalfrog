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
 * ItemDetailsActions v1.0.73
 * Enforced premium aesthetics for quantity selector and "Own This" button.
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

    return (
        <div style={{ marginTop: '32px', width: '100%' }}>
            <style dangerouslySetInnerHTML={{
                __html: `
                .own-this-btn-v73 {
                    background-color: var(--brand-primary) !important;
                    color: #ffffff !important;
                    padding: 0 40px !important;
                    border-radius: 14px !important;
                    font-weight: 900 !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    justifyContent: center !important;
                    gap: 12px !important;
                    border: none !important;
                    cursor: pointer !important;
                    flex: 1 !important;
                    font-size: 18px !important;
                    height: 56px !important;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                    box-shadow: 0 10px 20px rgba(var(--brand-primary-rgb), 0.3) !important;
                    text-transform: uppercase !important;
                    letter-spacing: 0.05em !important;
                    width: 100% !important;
                    outline: none !important;
                }
                .own-this-btn-v73:hover:not(:disabled) {
                    background-color: var(--brand-secondary) !important;
                    transform: translateY(-2px) !important;
                    box-shadow: 0 12px 24px rgba(var(--brand-primary-rgb), 0.4) !important;
                }
                .own-this-btn-v73:active:not(:disabled) {
                    transform: translateY(0) !important;
                }
                .own-this-btn-v73:disabled {
                    opacity: 0.5 !important;
                    cursor: not-allowed !important;
                    background-color: #9ca3af !important;
                    box-shadow: none !important;
                }
                .qty-selector-v73 {
                    background-color: #f3f4f6 !important;
                    border-radius: 14px !important;
                    padding: 6px !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    gap: 10px !important;
                    height: 56px !important;
                    box-sizing: border-box !important;
                    border: 1px solid #e5e7eb !important;
                }
                .qty-btn-v73 {
                    width: 44px !important;
                    height: 44px !important;
                    display: flex !important;
                    align-items: center !important;
                    justifyContent: center !important;
                    color: #4b5563 !important;
                    font-weight: 900 !important;
                    border: none !important;
                    background-color: #ffffff !important;
                    border-radius: 10px !important;
                    cursor: pointer !important;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05) !important;
                    font-size: 20px !important;
                    line-height: 1 !important;
                    transition: all 0.2s !important;
                    outline: none !important;
                }
                .qty-btn-v73:hover:not(:disabled) {
                    background-color: #f9fafb !important;
                    color: var(--brand-primary) !important;
                }
                .qty-btn-v73:active:not(:disabled) {
                    transform: scale(0.95) !important;
                }
            ` }} />
            <div style={{
                display: 'flex',
                flexDirection: 'row',
                alignItems: 'center',
                gap: '20px',
                width: '100%',
                justifyContent: 'flex-start'
            }}>
                <div className="qty-selector-v73">
                    <button
                        onClick={() => onQuantityChange(Math.max(1, quantity - 1))}
                        className="qty-btn-v73"
                        type="button"
                    >
                        −
                    </button>
                    <span style={{
                        width: '40px',
                        textAlign: 'center',
                        fontWeight: 900,
                        color: '#111827',
                        fontSize: '18px',
                        display: 'inline-block'
                    }}>{quantity}</span>
                    <button
                        onClick={() => onQuantityChange(Math.min(maxQty, quantity + 1))}
                        className="qty-btn-v73"
                        type="button"
                    >
                        +
                    </button>
                </div>

                <button
                    onClick={onAddToCart}
                    disabled={disabled || maxQty <= 0}
                    className="own-this-btn-v73"
                    type="button"
                >
                    <span className="btn-icon--shopping-cart" style={{ fontSize: '22px' }} aria-hidden="true" />
                    <span style={{ color: '#ffffff', fontWeight: 900 }}>{resolvedButtonText}</span>
                </button>
            </div>
        </div>
    );
};
