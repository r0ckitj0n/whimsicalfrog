import { useState, useEffect, useCallback } from 'react';
import { cart as cartStore } from '../commerce/cart-access.js';
import { CartItem } from '../commerce/cart/types.js';
import { IItem } from '../types/index.js';

/**
 * Hook for accessing and interacting with the cart system.
 * Uses cart-access to respect any cart overrides (e.g., POS mode).
 */
export const useCart = () => {
    const [cartState, setCartState] = useState(cartStore.getState());

    useEffect(() => {
        // Initial sync of legacy DOM elements if they exist
        const { count, total } = cartState;
        if (typeof document !== 'undefined') {
            document.querySelectorAll('.cart-count, .cart-counter, #cart-count').forEach(el => {
                el.textContent = String(count);
                el.classList.toggle('hidden', count === 0);
            });
            document.querySelectorAll('#cartCount').forEach(el => {
                el.textContent = `${count} ${count === 1 ? 'item' : 'items'}`;
            });
            document.querySelectorAll('#cartTotal').forEach(el => {
                el.textContent = `$${total.toFixed(2)}`;
            });
        }

        const unsubscribe = cartStore.onChange((detail) => {
            setCartState(detail.state);

            // Sync legacy DOM elements if they exist (for PHP shells)
            if (typeof document !== 'undefined') {
                const { count, total } = detail.state;
                document.querySelectorAll('.cart-count, .cart-counter, #cart-count').forEach(el => {
                    el.textContent = String(count);
                    el.classList.toggle('hidden', count === 0);
                });
                document.querySelectorAll('#cartCount').forEach(el => {
                    el.textContent = `${count} ${count === 1 ? 'item' : 'items'}`;
                });
                document.querySelectorAll('#cartTotal').forEach(el => {
                    el.textContent = `$${total.toFixed(2)}`;
                });
            }
        });
        return () => { unsubscribe(); };
    }, []);

    const addItem = useCallback((item: CartItem | IItem, qty: number = 1) => {
        cartStore.add(item as CartItem, qty);
    }, []);

    const removeItem = useCallback((sku: string) => {
        cartStore.remove(sku);
    }, []);

    const updateQuantity = useCallback((sku: string, qty: number) => {
        cartStore.updateQuantity(sku, qty);
    }, []);

    const clearCart = useCallback(() => {
        cartStore.clear();
    }, []);

    const applyCoupon = useCallback(async (code: string) => {
        return await cartStore.applyCoupon(code);
    }, []);

    const removeCoupon = useCallback(() => {
        cartStore.removeCoupon();
    }, []);

    return {
        ...cartState,
        addItem,
        removeItem,
        updateQuantity,
        clearCart,
        applyCoupon,
        removeCoupon,
        refresh: () => cartStore.refreshFromStorage()
    };
};

export default useCart;
