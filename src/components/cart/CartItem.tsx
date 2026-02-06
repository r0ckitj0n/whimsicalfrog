import React, { useEffect, useRef } from 'react';
import { normalizeAssetUrl, attachStrictImageGuards, removeBrokenImage } from '../../core/asset-utils.js';

interface CartItemProps {
    item: {
        sku: string;
        name?: string;
        price: number;
        quantity: number;
        image?: string;
        optionGender?: string;
        option_size?: string;
        option_color?: string;
    };
    onUpdateQuantity: (sku: string, qty: number) => void;
    onRemove: (sku: string) => void;
}

export const CartItem: React.FC<CartItemProps> = ({ item, onUpdateQuantity, onRemove }) => {
    const lineTotal = item.price * item.quantity;
    const imgSrc = normalizeAssetUrl(item.image);
    const imgRef = useRef<HTMLImageElement>(null);

    useEffect(() => {
        if (imgRef.current) {
            const img = imgRef.current;
            if (img.complete && (!img.naturalWidth || img.naturalWidth === 0)) {
                removeBrokenImage(img);
            } else {
                img.addEventListener('error', () => { removeBrokenImage(img); }, { once: true });
            }
        }
    }, [imgSrc]);

    const optionBits = [item.optionGender, item.option_size, item.option_color].filter(Boolean);

    return (
        <div className="cart-item border-b border-gray-100 last:border-0" data-sku={item.sku}>
            <div className="flex items-center gap-4 w-full">
                {imgSrc && (
                    <img
                        ref={imgRef}
                        src={imgSrc}
                        alt={item.name || item.sku}
                        className="cart-item-image"
                        loading="lazy"
                    />
                )}
                <div className="cart-item-details flex-1 min-w-0">
                    <div className="cart-item-title font-bold truncate text-gray-900">{item.name || item.sku}</div>
                    {optionBits.length > 0 && (
                        <div className="cart-item-options text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                            {optionBits.join(' • ')}
                        </div>
                    )}
                    <div className="cart-item-price font-black text-brand-primary mt-1">${item.price.toFixed(2)}</div>
                </div>

                <div className="cart-item-quantity flex items-center gap-1">
                    <input
                        type="number"
                        min="0"
                        className="cart-quantity-input w-12 text-center border-2 border-gray-100 rounded-lg p-1 font-bold text-sm"
                        value={item.quantity}
                        onChange={(e) => onUpdateQuantity(item.sku, parseInt(e.target.value, 10) || 0)}
                    />
                    <div className="cart-qty-arrows flex flex-col">
                        <button
                            type="button"
                            className="admin-action-btn btn-icon--up text-gray-400 hover:text-brand-secondary transition-colors p-0.5"
                            onClick={() => onUpdateQuantity(item.sku, item.quantity + 1)}
                            aria-label="Increase quantity"
                        />
                        <button
                            type="button"
                            className="admin-action-btn btn-icon--down text-gray-400 hover:text-brand-secondary transition-colors p-0.5"
                            onClick={() => onUpdateQuantity(item.sku, Math.max(0, item.quantity - 1))}
                            aria-label="Decrease quantity"
                        />
                    </div>
                </div>

                <div className="cart-item-line-total font-black text-gray-900 w-20 text-right">
                    ${lineTotal.toFixed(2)}
                </div>

                <button
                    type="button"
                    className="admin-action-btn btn-icon--delete remove-from-cart text-gray-300 hover:text-brand-secondary transition-colors"
                    onClick={() => onRemove(item.sku)}
                    aria-label="Remove item"
                    data-help-id="cart-remove-item"
                />
            </div>
        </div>
    );
};

export default CartItem;
