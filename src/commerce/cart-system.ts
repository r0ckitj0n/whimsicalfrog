/**
 * WhimsicalFrog Core – Cart System (Conductor)
 * Delegates functionality to modular sub-modules.
 */

import api_client from '../core/ApiClient.js';
import { CartState, CartStoreOptions, CartItem, Coupon, ICartAPI } from './cart/types.js';
import * as Notifications from './cart/notifications.js';
import * as Storage from './cart/storage.js';
import * as Logic from './cart/logic.js';

const DEFAULT_STORAGE_KEY = 'whimsical_frog_cart';

export function createCartStore({
    storageKey = DEFAULT_STORAGE_KEY,
    notifications = true,
    broadcast = true,
}: CartStoreOptions = {}) {
    const state: CartState = {
        items: [],
        total: 0,
        subtotal: 0,
        count: 0,
        coupon: null,
        notifications,
        initialized: false,
    };

    const listeners = new Set<(detail: { action: string; state: CartState;[key: string]: unknown }) => void>();

    function notify(type: string, msg: string, title = '', duration = 5000): void {
        Notifications.notify(state.notifications, type, msg, title, duration);
    }

    function persist(): void {
        Storage.persist(state, storageKey);
    }

    function recalc(): void {
        Logic.recalc(state);
    }

    function emit(action: string, payload = {}): void {
        const detail = { action, state: api.getState(), ...payload };
        listeners.forEach((fn) => {
            try {
                fn(detail);
            } catch { /* Listener callback failed */ }
        });

        if (!broadcast || typeof window === 'undefined') return;

        window.dispatchEvent(new CustomEvent('cartUpdated', { detail }));

        const wf = window.WhimsicalFrog;
        if (wf && typeof wf.emit === 'function') {
            wf.emit('cart:updated', detail);
        }
    }

    async function applyCoupon(code: string): Promise<boolean> {
        const normCode = String(code || '').trim();
        if (!normCode) return false;

        if (state.coupon && state.coupon.code === normCode) {
            notify('info', 'Coupon already applied');
            return true;
        }

        try {
            const data = await api_client.post<{ success: boolean; coupon?: Coupon; message?: string }>('validate_coupon.php', {
                code: normCode,
                cartTotal: state.subtotal
            });

            if (data.success && data.coupon) {
                state.coupon = data.coupon;
                recalc();
                persist();
                notify('success', `Coupon ${normCode} applied! Saved $${Number(state.coupon!.discount_amount).toFixed(2)}`, 'Savings');
                emit('coupon_applied', { coupon: state.coupon });
                return true;
            } else {
                notify('error', data.message || 'Invalid coupon code', 'Coupon Error');
                return false;
            }
        } catch (e: unknown) {
            const err = e as Error;
            console.error(err);
            notify('error', err?.message || 'Unable to validate coupon', 'Error');
            return false;
        }
    }

    function removeCoupon(): void {
        if (!state.coupon) return;
        state.coupon = null;
        recalc();
        persist();
        notify('info', 'Coupon removed');
        emit('coupon_removed');
    }

    function addItem(itemIn: CartItem, qty = 1): void {
        const item = Logic.addItem(state, itemIn, qty);
        if (!item) return;

        recalc();
        persist();
        notify('success', `Added ${itemIn?.name || item.sku} (x${item.quantity})`, 'Added to Cart');

        const win = window;
        if (win.__WF_OPEN_CART_ON_ADD === true) {
            // win.WF_CartModal?.open?.(); // TODO: Add to Window interface if needed
        }

        emit('add', { item: { ...item } });
    }

    function removeItem(sku: string): void {
        const removed = Logic.removeItem(state, sku);
        if (!removed) return;

        recalc();
        persist();
        notify('info', `${removed?.name || sku} removed`, 'Cart');
        emit('remove', { item: { ...removed } });
    }

    function updateQuantity(sku: string, qty: number): void {
        const normalizedQty = Math.max(0, Number(qty) || 0);
        if (normalizedQty === 0) {
            removeItem(sku);
            return;
        }

        const updated = Logic.updateQuantity(state, sku, qty);
        if (!updated) return;

        recalc();
        persist();
        emit('update', { item: { ...updated } });
    }

    function clear(): void {
        if (!state.items.length) return;
        state.items = [];
        state.coupon = null;
        recalc();
        persist();
        emit('clear');
    }

    function load(): void {
        const { items, coupon } = Storage.load(storageKey);
        state.items = items;
        state.coupon = coupon;

        recalc();
        // persist(); // Remove redundant persist during load

        const firstInit = !state.initialized;
        state.initialized = true;
        if (firstInit) {
            emit('init');
        }
    }

    const api: ICartAPI = {
        load,
        save: persist,
        add: addItem,
        remove: removeItem,
        updateQuantity,
        clear,
        applyCoupon,
        removeCoupon,
        getItems: () => state.items.map((item) => ({ ...item })),
        getTotal: () => state.total,
        getSubtotal: () => state.subtotal,
        getCoupon: () => state.coupon ? { ...state.coupon } : null,
        getCount: () => state.count,
        getState: () => ({ ...state, items: state.items.map((item) => ({ ...item })) }),
        setNotifications: (enabled: boolean) => { state.notifications = !!enabled; },
        refreshFromStorage: () => {
            load();
            emit('refresh');
            return api.getState();
        },
        onChange: (listener: (detail: { action: string; state: CartState;[key: string]: unknown }) => void) => {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
    };

    load();

    if (typeof window !== 'undefined') {
        interface LegacyCartWindow {
            cart?: ICartAPI;
            WF_Cart?: {
                addItem: (item: CartItem, qty?: number) => void;
                removeItem: (sku: string) => void;
                updateItem: (sku: string, qty: number) => void;
                clearCart: () => void;
                getItems: () => CartItem[];
                getTotal: () => number;
                getCount: () => number;
                renderCart: () => void;
                refreshFromStorage: () => CartState;
            };
        }
        const win = window as unknown as LegacyCartWindow;
        if (!win.cart) win.cart = api;

        // Legacy bridge for WF_Cart API
        if (!win.WF_Cart) {
            win.WF_Cart = {
                addItem: (item: CartItem, qty?: number) => api.add(item, qty || 1),
                removeItem: (sku: string) => api.remove(sku),
                updateItem: (sku: string, qty: number) => api.updateQuantity(sku, qty),
                clearCart: () => api.clear(),
                getItems: () => api.getItems(),
                getTotal: () => api.getTotal(),
                getCount: () => api.getCount(),
                renderCart: () => {
                    window.dispatchEvent(new CustomEvent('wf:cart:render'));
                },
                refreshFromStorage: () => api.refreshFromStorage()
            };
        }
    }

    return api;
}

export const cart = createCartStore();
export default cart;
