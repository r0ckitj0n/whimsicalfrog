import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../core/ApiClient.js';
import { useCart } from './use-cart.js';
import { useAuthContext } from '../context/AuthContext.js';
import { IPricingSummary, PaymentMethod, ShippingMethod } from '../types/payment.js';
import { PAYMENT_METHOD, SHIPPING_METHOD } from '../core/constants.js';
import logger from '../core/logger.js';

export const usePayment = () => {
    const { items, total: clientTotal, subtotal: clientSubtotal, coupon, clearCart } = useCart();
    const { user, isLoggedIn } = useAuthContext();

    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [pricing, setPricing] = useState<IPricingSummary>({
        subtotal: 0,
        shipping: 0,
        tax: 0,
        discount: 0,
        total: 0
    });

    const [selected_address_id, setSelectedAddressId] = useState<string | number | null>(null);
    const [shipping_method, setShippingMethod] = useState<ShippingMethod>(SHIPPING_METHOD.USPS);
    const [payment_method, setPaymentMethod] = useState<PaymentMethod>(PAYMENT_METHOD.SQUARE);
    const [square_ready, setSquareReady] = useState(false);

    // Pricing calculation
    const updatePricing = useCallback(async () => {
        if (items.length === 0) return;

        try {
            const payload = {
                item_ids: items.map(i => i.sku),
                quantities: items.map(i => i.quantity),
                shipping_method,
                coupon_code: coupon?.code,
                user_id: user?.id
            };

            const res = await ApiClient.post<{ success: boolean; data?: { pricing?: IPricingSummary }; pricing?: IPricingSummary }>('/api/checkout_pricing.php', payload);
            // Handle both nested (res.data.pricing) and flat (res.pricing) response formats
            const pricingData = res?.data?.pricing || res?.pricing;
            if (res && res.success && pricingData) {
                setPricing(pricingData);
            }
        } catch (err) {
            logger.warn('[usePayment] Pricing update failed', err);
        }
    }, [items, shipping_method, coupon, user]);

    useEffect(() => {
        updatePricing();
    }, [updatePricing]);

    const placeOrder = async (square_token?: string) => {
        if (!isLoggedIn || !user) {
            setError('Please sign in to place your order.');
            return { success: false };
        }

        setIsLoading(true);
        setError(null);

        try {
            const payload = {
                user_id: user.id,
                item_ids: items.map(i => i.sku),
                quantities: items.map(i => i.quantity),
                colors: items.map(i => i.option_color || null),
                sizes: items.map(i => i.option_size || null),
                payment_method,
                shipping_method,
                total: pricing.total,
                coupon_code: coupon?.code,
                square_token,
                shipping_address_id: (shipping_method !== SHIPPING_METHOD.PICKUP && selected_address_id) ? selected_address_id : undefined
            };

            const res = await ApiClient.post<{ success: boolean; order_id?: string; error?: string }>('/api/add_order.php', payload);
            if (res && res.success && res.order_id) {
                clearCart();
                return { success: true, order_id: res.order_id };
            } else {
                setError(res?.error || 'Failed to place order');
                return { success: false };
            }
        } catch (err: unknown) {
            logger.error('[usePayment] Order failed', err);

            // Extract server message from ApiErrorHandler formatted message
            const msg = err instanceof Error ? err.message : '';
            if (msg.includes('HTTP 409')) {
                const parts = msg.split(' - ');
                setError(parts.length > 1 ? parts[1] : 'Item is out of stock.');
            } else if (msg.includes(' - ')) {
                const parts = msg.split(' - ');
                setError(parts[1]);
            } else {
                setError('An unexpected error occurred while placing your order.');
            }
            return { success: false };
        } finally {
            setIsLoading(false);
        }
    };

    return {
        isLoading,
        error,
        pricing,
        selected_address_id,
        setSelectedAddressId,
        shipping_method,
        setShippingMethod,
        payment_method,
        setPaymentMethod,
        placeOrder,
        setError,
        updatePricing
    };
};
