import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import { createCartStore } from '../../commerce/cart-system.js';
import { setCartOverride } from '../../commerce/cart-access.js';
import { CartState } from '../../commerce/cart/types.js';
import { PAYMENT_METHOD, PAYMENT_STATUS, SHIPPING_METHOD, ORDER_STATUS } from '../../core/constants.js';
import logger from '../../core/logger.js';
import type { IPOSItem, IPOSPricing, IPOSCheckoutResponse, IPOSInventoryResponse } from '../../types/pos.js';

// Re-export for backward compatibility
export type { IPOSItem, IPOSPricing, IPOSCheckoutResponse } from '../../types/pos.js';
// Export IInventoryResponse as an alias for backward compatibility
export type IInventoryResponse = IPOSInventoryResponse;

export const usePOS = () => {
    const [cart, setCart] = useState<ReturnType<typeof createCartStore> | null>(null);
    const [items, setItems] = useState<IPOSItem[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [pricing, setPricing] = useState<IPOSPricing | null>(null);
    const [error, setError] = useState<string | null>(null);

    const normalizeItem = useCallback((item: IPOSItem): IPOSItem => {
        const rawStock = (item.stock ?? item.stock_quantity ?? 0) as unknown;
        const normalizedStock = Number(rawStock);
        return {
            ...item,
            stock: Number.isFinite(normalizedStock) ? normalizedStock : 0
        };
    }, []);

    const normalizeItems = useCallback((data: IPOSItem[]): IPOSItem[] => data.map(normalizeItem), [normalizeItem]);

    const fetchItems = useCallback(async () => {
        // Try to hydrate from DOM first if available
        const posDataEl = document.getElementById('pos-data');
        if (posDataEl && !items.length) {
            try {
                const data = JSON.parse(posDataEl.textContent || '[]');
                if (Array.isArray(data)) {
                    setItems(normalizeItems(data));
                }
            } catch (e) {
                logger.warn('[usePOS] Failed to parse pos-data from DOM', e);
            }
        }

        setIsLoading(true);
        try {
            const res = await ApiClient.get<IInventoryResponse | IPOSItem[]>('/api/inventory.php');
            const data = Array.isArray(res) ? res : (res && Array.isArray(res.data) ? res.data : []);
            setItems(normalizeItems(data));
        } catch (err) {
            logger.error('[usePOS] Failed to fetch items', err);
            setError('Failed to load items');
        } finally {
            setIsLoading(false);
        }
    }, [items.length, normalizeItems]);

    useEffect(() => {
        const posCart = createCartStore({
            storageKey: 'wf_pos_cart',
            notifications: false,
            broadcast: false,
        });
        setCart(posCart);
        setCartOverride(posCart);
        fetchItems();

        // Listen for cart changes to refresh pricing
        let debounceTimer: ReturnType<typeof setTimeout> | null = null;
        const unsubscribe = posCart.onChange(() => {
            // Debounce rapid updates
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                const cartItems = posCart.getItems();
                if (!cartItems.length) {
                    setPricing(null);
                    return;
                }

                const coupon = posCart.getCoupon();
                const payload = {
                    item_ids: cartItems.map(i => i.sku),
                    quantities: cartItems.map(i => i.quantity),
                    shipping_method: SHIPPING_METHOD.PICKUP,
                    coupon_code: coupon?.code
                };

                try {
                    const res = await ApiClient.post<{ success: boolean; pricing: IPOSPricing }>('/api/checkout_pricing.php', payload);
                    if (res && res.success) {
                        setPricing(res.pricing);
                    }
                } catch (err) {
                    logger.warn('[usePOS] Pricing update failed', err);
                }
            }, 100);
        });

        return () => {
            if (debounceTimer) clearTimeout(debounceTimer);
            unsubscribe();
            setCartOverride(null); // Clear override when unmounting
        };
    }, [fetchItems]);

    const refreshPricing = useCallback(async () => {
        if (!cart) return;
        const cartItems = cart.getItems();
        if (!cartItems.length) {
            setPricing(null);
            return;
        }

        const coupon = cart.getCoupon();
        const payload = {
            item_ids: cartItems.map(i => i.sku),
            quantities: cartItems.map(i => i.quantity),
            shipping_method: SHIPPING_METHOD.PICKUP,
            coupon_code: coupon?.code
        };

        try {
            const res = await ApiClient.post<{ success: boolean; pricing: IPOSPricing }>('/api/checkout_pricing.php', payload);
            if (res && res.success) {
                setPricing(res.pricing);
            }
        } catch (err) {
            logger.warn('[usePOS] Pricing update failed', err);
        }
    }, [cart]);

    const addToCart = useCallback((sku: string, qty: number = 1) => {
        if (!cart) return;
        const item = items.find(i => i.sku === sku);
        if (!item) return;

        cart.add({
            sku: item.sku,
            name: item.name,
            price: item.current_price || item.retail_price,
            image: item.image_url,
            quantity: qty
        }, qty);
        refreshPricing();
    }, [cart, items, refreshPricing]);

    const removeFromCart = useCallback((sku: string) => {
        if (!cart) return;
        cart.remove(sku);
        refreshPricing();
    }, [cart, refreshPricing]);

    const updateQuantity = useCallback((sku: string, change: number) => {
        if (!cart) return;
        const current = cart.getItems().find(i => i.sku === sku);
        if (current) {
            cart.updateQuantity(sku, current.quantity + change);
            refreshPricing();
        }
    }, [cart, refreshPricing]);

    const applyCoupon = useCallback(async (code: string) => {
        if (!cart) return false;
        const ok = await cart.applyCoupon(code);
        if (ok) refreshPricing();
        return ok;
    }, [cart, refreshPricing]);

    const processCheckout = async (payment_method: string, square_token?: string, _cashReceived?: number) => {
        if (!cart || !pricing) return { success: false };

        const cartItems = cart.getItems();
        try {
            const orderData = {
                user_id: 'POS001', // POS system user ID
                item_ids: cartItems.map(i => i.sku),
                quantities: cartItems.map(i => i.quantity),
                colors: cartItems.map(i => i.option_color || null),
                sizes: cartItems.map(i => i.option_size || null),
                total: pricing.total,
                subtotal: pricing.subtotal,
                tax_amount: pricing.tax,
                tax_rate: 0.0825, // Fallback rate
                coupon_code: cart.getCoupon()?.code,
                payment_method,
                square_token,
                payment_status: PAYMENT_STATUS.PAID,
                shipping_method: SHIPPING_METHOD.PICKUP,
                status: ORDER_STATUS.DELIVERED
            };

            const res = await ApiClient.post<IPOSCheckoutResponse>('/api/add_order.php', orderData);
            if (res && res.success) {
                cart.clear();
                setPricing(null);
                return { success: true, order_id: res.order_id };
            }
            return { success: false, error: res?.error };
        } catch (err) {
            logger.error('[usePOS] Checkout failed', err);
            return { success: false, error: 'Checkout failed' };
        }
    };

    return {
        isLoading,
        items,
        cartItems: cart ? cart.getItems() : [],
        pricing,
        error,
        addToCart,
        removeFromCart,
        updateQuantity,
        applyCoupon,
        removeCoupon: () => { cart?.removeCoupon?.(); refreshPricing(); },
        processCheckout,
        refresh: fetchItems
    };
};
