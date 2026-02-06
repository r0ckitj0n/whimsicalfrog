/**
 * Cart Notification Helpers
 */

import { NotificationOptions, WFNotificationSystem } from './types.js';

export function getGlobalNotifications(): WFNotificationSystem {
    if (typeof window === 'undefined') return { success: () => {}, error: () => {}, info: () => {}, warning: () => {} };
    const win = window as unknown as Window & { wfNotifications?: WFNotificationSystem };
    return win.wfNotifications || { success: () => {}, error: () => {}, info: () => {}, warning: () => {} };
}

export function runFallbackNotifier(type: string, msg: string, options: NotificationOptions): void {
    if (typeof window === 'undefined') return;
    const win = window as unknown as Window & { [key: string]: unknown };
    const fnName = `show${type.charAt(0).toUpperCase() + type.slice(1)}`;
    const fallback = win[fnName];
    if (typeof fallback === 'function') {
        (fallback as Function)(msg, options);
    }
}

export function notify(enabled: boolean, type: string, msg: string, title = '', duration = 5000): void {
    if (!enabled) return;
    const sys = getGlobalNotifications();
    const notifier = typeof (sys as Record<string, unknown>)[type] === 'function' ? ((sys as Record<string, unknown>)[type] as Function) : null;
    const options: NotificationOptions = { title, duration };
    if (notifier) {
        try {
            notifier.call(sys, msg, options);
        } catch (err) {
            const win = window as unknown as Window & { WF?: { warn: (data: { msg: string; err: unknown }) => void } };
            const wf = win.WF;
            if (wf && typeof wf.warn === 'function') {
                wf.warn({ msg: '[Cart] Notification handler failed, falling back', err });
            }
            runFallbackNotifier(type, msg, options);
        }
        return;
    }
    runFallbackNotifier(type, msg, options);
}
