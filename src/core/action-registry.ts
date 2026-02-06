/**
 * Centralized action registry for Whimsical Frog.
 * ES module version of legacy central-functions.js action handlers & delegation.
 */

import { IItem } from '../types/index.js';

export interface ActionParams {
    type?: string;
    id?: string | number;
    name?: string;
    action?: string;
    command?: string;
    message?: string;
    title?: string;
    confirmText?: string;
    confirmStyle?: string;
    icon?: string;
    iconType?: string;
    formId?: string;
    item?: Partial<IItem>;
    itemData?: Partial<IItem>;
}

declare global {
    interface Window {
        openEditModal?: (type?: string, id?: string | number) => void;
        openDeleteModal?: (type?: string, id?: string | number, name?: string) => void;
        performAction?: (action?: string) => void;
        runCommand?: (command?: string) => void;
        loadRoomConfig?: () => void;
        resetForm?: () => void;
        hideGlobalPopupImmediate?: () => void;
        showGlobalItemModal?: (sku: string, data?: unknown) => void;
        showItemDetailsModal?: (sku: string, data?: unknown) => void;
        centralFunctions?: Record<string, (el: HTMLElement, p: ActionParams, e?: Event) => void | Promise<void>>;
    }
}

export const centralFunctions = {
    openEditModal: (_el: HTMLElement, p: ActionParams) => window.openEditModal?.(p.type, p.id),
    openDeleteModal: (_el: HTMLElement, p: ActionParams) => window.openDeleteModal?.(p.type, p.id, p.name),
    performAction: (_el: HTMLElement, p: ActionParams) => window.performAction?.(p.action),
    runCommand: (_el: HTMLElement, p: ActionParams) => window.runCommand?.(p.command),
    loadRoomConfig: () => window.loadRoomConfig?.(),
    resetForm: () => window.resetForm?.(),

    openQuantityModal: async (el: HTMLElement, p: ActionParams = {}) => {
        try {
            window.hideGlobalPopupImmediate?.();
            const data = p.itemData || p.item || {};
            const sku = (data.sku as string) || el?.dataset?.sku || el?.dataset?.item_id;

            if (el?.dataset?.room || el?.dataset?.room_number) return;

            if (typeof window.showGlobalItemModal !== 'function') {
                try { await import('../js/detailed-item-modal.js' as unknown as string); } catch { /* Legacy modal import failed */ }
            }

            const opener = (typeof parent !== 'undefined' && parent !== window && (parent as Window).showGlobalItemModal)
                ? (parent as Window).showGlobalItemModal
                : window.showGlobalItemModal;

            if (typeof opener === 'function' && sku) {
                opener(sku, data);
                return;
            }

            if (typeof window.showItemDetailsModal === 'function' && sku) {
                window.showItemDetailsModal(sku, data);
            }
        } catch { /* Item modal opening failed - silent fail */ }
    },

    confirm: async (el: HTMLAnchorElement | HTMLButtonElement | HTMLElement, p: ActionParams = {}) => {
        const message = p.message || el.getAttribute('data-confirm') || 'Are you sure?';

        if (typeof window.showConfirmationModal !== 'function') {
            const errorMsg = 'Confirmation UI unavailable. Action canceled.';
            if (window.wfNotifications?.show) {
                window.wfNotifications.show(errorMsg, 'error');
            } else if (window.notifyError) {
                window.notifyError(errorMsg);
            }
            return;
        }

        const proceed = await window.showConfirmationModal?.({
            title: p.title || 'Please confirm',
            message,
            confirmText: p.confirmText || 'Confirm',
            confirmStyle: p.confirmStyle || 'confirm',
            icon: p.icon || '⚠️',
            iconType: p.iconType || 'warning'
        });

        if (!proceed) return;

        if (el instanceof HTMLAnchorElement && el.href) {
            if (el.target === '_blank') {
                window.open(el.href, '_blank');
            } else {
                window.location.href = el.href;
            }
            return;
        }

        const form = (el as HTMLButtonElement | HTMLInputElement).form || (p.formId ? document.getElementById(p.formId) : null);
        if (form && form instanceof HTMLFormElement) {
            form.submit();
            return;
        }

        document.dispatchEvent(new CustomEvent('wf:confirm:accepted', { detail: { element: el, params: p } }));
    },
};

export function delegate() {
    if (document.body.dataset.wfCentralListenersAttached) return;
    document.body.dataset.wfCentralListenersAttached = 'true';

    const parseParams = (el: HTMLElement): ActionParams => {
        try {
            return el.dataset.params ? JSON.parse(el.dataset.params) : {};
        } catch (e) { return {}; }
    };

    const add = (event: string, attr: string, fnName: keyof typeof centralFunctions) => {
        document.body.addEventListener(event, e => {
            const target = (e.target as HTMLElement).closest(`[${attr}]`) as HTMLElement;
            if (!target) return;
            if (event === 'click') e.preventDefault();
            const params = parseParams(target);
            const fn = centralFunctions[fnName] as (el: HTMLElement, p: ActionParams, e?: Event) => void;
            if (typeof fn === 'function') {
                fn(target, params, e);
            }
        });
    };

    add('click', 'data-action', 'openEditModal'); // Default mapping example
}

if (typeof window !== 'undefined') {
    window.centralFunctions = centralFunctions;
    document.addEventListener('DOMContentLoaded', delegate);
}

export default centralFunctions;
