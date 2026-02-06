import { useState, useEffect, useCallback } from 'react';
import useCart from '../hooks/use-cart.js';
import { ApiClient } from '../core/ApiClient.js';
import { IUpsellItem, IUpsellApiResponse } from '../types/payment.js';

/**
 * Hook for managing cart modal state and actions.
 * Extracted from CartModal.tsx
 */
export const useCartModal = (isOpen: boolean, onClose: () => void) => {
    const {
        items,
        total,
        subtotal,
        coupon,
        updateQuantity,
        removeItem,
        clearCart,
        applyCoupon,
        removeCoupon,
        addItem
    } = useCart();

    const [coupon_code, setCouponCode] = useState('');
    const [upsells, setUpsells] = useState<IUpsellItem[]>([]);
    const [isApplyingCoupon, setIsApplyingCoupon] = useState(false);
    const [isLoadingUpsells, setIsLoadingUpsells] = useState(false);

    const fetchUpsells = useCallback(async () => {
        if (items.length === 0) return;
        setIsLoadingUpsells(true);
        try {
            const skus = items.map(i => i.sku);
            const res = await ApiClient.post<IUpsellApiResponse>('/api/cart_upsells.php', { skus, limit: 4 });
            if (res && res.success && res.data?.upsells) {
                setUpsells(res.data.upsells.map(u => ({
                    sku: u.sku,
                    name: u.name || u.title || u.sku,
                    price: Number(u.price || 0),
                    image: u.image || u.thumbnail || '',
                    hasOptions: !!(u.has_options || u.hasOptions)
                })));
            }
        } catch (err) {
            console.warn('[CartModal] Failed to fetch upsells', err);
        } finally {
            setIsLoadingUpsells(false);
        }
    }, [items]);

    useEffect(() => {
        if (isOpen && items.length > 0) {
            fetchUpsells();
        }
    }, [isOpen, items.length, fetchUpsells]);


    const handleApplyCoupon = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!coupon_code.trim()) return;

        setIsApplyingCoupon(true);
        try {
            const success = await applyCoupon(coupon_code.trim());
            if (success) setCouponCode('');
        } finally {
            setIsApplyingCoupon(false);
        }
    };

    return {
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
    };
};
