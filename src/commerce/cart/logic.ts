/**
 * Cart Core Business Logic
 */

import { DISCOUNT_TYPE } from '../../core/constants.js';
import { CartState, CartItem, WFGlobal } from './types.js';

export function recalc(state: CartState): void {
    const sub = state.items.reduce((sum, item) => sum + (Number(item.price) || 0) * (Number(item.quantity) || 0), 0);
    state.subtotal = sub;
    state.count = state.items.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0);

    let discount = 0;
    if (state.coupon) {
        const val = Number(state.coupon.value) || 0;
        if (state.coupon.type === DISCOUNT_TYPE.PERCENTAGE) {
            discount = sub * (val / 100);
        } else {
            discount = val;
        }
        if (discount > sub) discount = sub;
        state.coupon.discount_amount = discount;
    }

    state.total = Math.max(0, sub - discount);
}

export function addItem(state: CartState, itemIn: { sku?: string; id?: string; price?: number; retail_price?: number; name?: string }, qty = 1): CartItem | null {
    const normSku = String(itemIn?.sku ?? itemIn?.id ?? '').trim();
    if (!normSku) {
        if (window.WhimsicalFrog && typeof window.WhimsicalFrog.warn === 'function') {
            window.WhimsicalFrog.warn({ msg: '[Cart] Refusing to add item without SKU', item: itemIn });
        }
        return null;
    }
    const normQty = Math.max(1, Number(qty) || 1);
    const normPrice = Number(itemIn?.price ?? itemIn?.retail_price ?? 0) || 0;
    const mergeDupes = !(window.__WF_CART_MERGE_DUPES === false);
    
    let target: CartItem | undefined;
    if (mergeDupes) {
        target = state.items.find((entry) => String(entry.sku) === normSku);
        if (target) {
            target.quantity += normQty;
            if (!Number.isFinite(target.price) || target.price <= 0) {
                target.price = normPrice;
            }
        }
    }
    if (!target) {
        target = { ...itemIn, sku: normSku, quantity: normQty, price: normPrice } as CartItem;
        state.items.push(target!);
    }
    return target!;
}

export function removeItem(state: CartState, sku: string): CartItem | null {
    const key = String(sku);
    const idx = state.items.findIndex((item) => String(item.sku) === key);
    if (idx === -1) return null;
    const [removed] = state.items.splice(idx, 1);

    if (state.items.length === 0) state.coupon = null;
    return removed;
}

export function updateQuantity(state: CartState, sku: string, qty: number): CartItem | null {
    const key = String(sku);
    const target = state.items.find((item) => String(item.sku) === key);
    if (!target) return null;
    const normalizedQty = Math.max(0, Number(qty) || 0);
    target.quantity = normalizedQty;
    return target;
}
