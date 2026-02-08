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
    const [showUpsells, setShowUpsells] = useState<boolean>(() => (typeof window !== 'undefined' ? window.__WF_SHOW_UPSELLS !== false : true));
    const [confirmClearCart, setConfirmClearCart] = useState<boolean>(() => (typeof window !== 'undefined' ? window.__WF_CONFIRM_CLEAR_CART !== false : true));
    const [minimumCheckoutTotal, setMinimumCheckoutTotal] = useState<number>(() => {
        if (typeof window === 'undefined') return 0;
        const parsed = Number(window.__WF_MINIMUM_CHECKOUT_TOTAL);
        return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
    });

    useEffect(() => {
        if (typeof window === 'undefined') return;
        const refreshRuntimeSettings = () => {
            setShowUpsells(window.__WF_SHOW_UPSELLS !== false);
            setConfirmClearCart(window.__WF_CONFIRM_CLEAR_CART !== false);
            const nextMinimum = Number(window.__WF_MINIMUM_CHECKOUT_TOTAL);
            setMinimumCheckoutTotal(Number.isFinite(nextMinimum) ? Math.max(0, nextMinimum) : 0);
        };

        refreshRuntimeSettings();
        window.addEventListener('wf:cart-settings-updated', refreshRuntimeSettings);
        return () => window.removeEventListener('wf:cart-settings-updated', refreshRuntimeSettings);
    }, []);

    const fetchUpsells = useCallback(async () => {
        if (!showUpsells || items.length === 0) {
            setUpsells([]);
            return;
        }
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
    }, [items, showUpsells]);

    useEffect(() => {
        if (isOpen && showUpsells && items.length > 0) {
            fetchUpsells();
        }
    }, [isOpen, showUpsells, items.length, fetchUpsells]);


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

    const handleClearCart = async () => {
        if (confirmClearCart && typeof window !== 'undefined' && window.WF_Confirm) {
            const confirmed = await window.WF_Confirm({
                title: 'Empty Cart?',
                message: 'This will remove all items from your cart.',
                confirmText: 'Empty Cart',
                cancelText: 'Keep Items',
                confirmStyle: 'warning',
                iconKey: 'delete'
            });
            if (!confirmed) return;
        }
        clearCart();
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
        minimumCheckoutTotal,
        updateQuantity,
        removeItem,
        clearCart: handleClearCart,
        removeCoupon,
        addItem,
        handleApplyCoupon
    };
};
