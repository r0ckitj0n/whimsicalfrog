/**
 * Cart Persistence and Loading Logic
 */

import { CartState, CartItem, Coupon, WFGlobal } from './types.js';

export function persist(state: CartState, storageKey: string): void {
    try {
        const payload = { 
            items: state.items, 
            total: state.total, 
            subtotal: state.subtotal, 
            count: state.count, 
            coupon: state.coupon, 
            t: Date.now() 
        };
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem(storageKey, JSON.stringify(payload));
        }
    } catch (error) {
        console.error('[Cart] Failed to save cart:', error);
    }
}

export function load(storageKey: string): { items: CartItem[], coupon: Coupon | null } {
    try {
        const raw = typeof localStorage !== 'undefined' ? localStorage.getItem(storageKey) : null;
        if (raw) {
            const data = JSON.parse(raw);
            if (Array.isArray(data?.items)) {
                const items = data.items.map((entry: { sku?: string; id?: string; quantity?: number; price?: number; retail_price?: number }) => {
                    const sku = String(entry?.sku ?? entry?.id ?? '').trim();
                    const qty = Math.max(1, Number(entry?.quantity ?? 1) || 1);
                    const price = Number(entry?.price ?? entry?.retail_price ?? 0) || 0;
                    return { ...entry, sku, quantity: qty, price } as CartItem;
                });
                return { items, coupon: (data.coupon as Coupon) || null };
            }
        }
    } catch (error) {
        if (window.WhimsicalFrog && typeof window.WhimsicalFrog.error === 'function') {
            window.WhimsicalFrog.error({ msg: '[Cart] Failed to load cart from storage', err: error });
        }
    }
    return { items: [], coupon: null };
}
