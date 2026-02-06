import { useEffect } from 'react';
import useCart from './use-cart.js';
import useRoomManager from './use-room-manager.js';
import { useApp } from '../context/AppContext.js';
import { useAuthModal } from './useAuthModal.js';
import { useAuthContext } from '../context/AuthContext.js';

/**
 * Hook for managing global event delegations.
 * Migrated from legacy app.js event listeners.
 */
export const useGlobalListeners = () => {
    const { removeItem } = useCart();
    const { openRoom } = useRoomManager();
    const { setIsCartOpen, openPolicy } = useApp();
    const { openLogin, openRegister, openAccountSettings } = useAuthModal();
    const { isLoggedIn } = useAuthContext();

    const handleCartOpen = () => setIsCartOpen(true);

    useEffect(() => {
        const handleGlobalClick = (e: MouseEvent) => {
            const target = e.target as HTMLElement;
            if (!target) return;

            const closestActionEl = target.closest('[data-action]');
            const action = closestActionEl?.getAttribute('data-action');

            // 1. Policy Links Interception
            const a = target.closest('a[href]') as HTMLAnchorElement;
            if (a && !e.defaultPrevented) {
                const href = (a.getAttribute('href') || '').toLowerCase();

                // Cart Link Interception
                const cartLink = target.closest('.cart-link') as HTMLElement;
                if (cartLink || href.includes('/cart')) {
                    // Robust login check: 
                    // 1. React state (AuthContext)
                    // 2. DOM Attribute (data-is-logged-in)
                    // 3. Cookie presence (WF_AUTH or WF_AUTH_V)
                    // 4. Existence of logout link or account settings link
                    const isDomLoggedIn = document.body.getAttribute('data-is-logged-in') === 'true';
                    const hasAuthCookie = typeof document !== 'undefined' && (document.cookie.includes('WF_AUTH=') || document.cookie.includes('WF_AUTH_V='));
                    const hasLogoutLink = !!document.querySelector('a[href*="logout.php"], [data-action="open-account-settings"]');

                    // Effective check: If we have ANY indicator of being logged in, trust it.
                    const effectiveLoggedIn = isLoggedIn || isDomLoggedIn || hasAuthCookie || hasLogoutLink;

                    e.preventDefault();
                    if (!effectiveLoggedIn) {
                        const rect = (cartLink || target).getBoundingClientRect();
                        openLogin('cart', rect);
                    } else {
                        setIsCartOpen(true);
                    }
                    return;
                }

                const isPolicyLink = (a.hasAttribute('data-open-policy')) ||
                    /(\/privacy(\.php)?(\?|$)|\/terms(\.php)?(\?|$)|\/policy(\.php)?(\?|$))/i.test(href);

                if (isPolicyLink) {
                    e.preventDefault();
                    openPolicy(href, (a.textContent || 'Policy').trim());
                    return;
                }
            }

            // 2. Room/Door Interception
            const doorLink = target.closest('.room-door, .door-area, .door-link, [data-room-number], a[data-room]') as HTMLElement;
            if (doorLink) {
                // Ignore if already inside an open modal (handled by modal's own listeners)
                if (target.closest('.room-modal-overlay.show')) return;

                // Skip shortcuts/content icons that happen to carry data-room (handled in RoomModal)
                if (doorLink.closest('.room-item-icon, .room-content-slot')) return;

                const room_number = doorLink.dataset?.room ||
                    (doorLink as HTMLAnchorElement).href?.match(/room=(\d+)/)?.[1] ||
                    doorLink.getAttribute('data-room-number');

                if (room_number) {
                    e.preventDefault();
                    e.stopPropagation();
                    openRoom(room_number);
                    return;
                }
            }

            // 3. Remove from Cart Delegation
            const removeBtn = target.closest('.remove-from-cart, .cart-item-remove, [data-action="remove-from-cart"]') as HTMLElement;
            if (removeBtn) {
                // Skip on Admin Settings as per legacy app.js logic
                const isAdminSettings = document.body.dataset.page === 'admin/settings';
                if (isAdminSettings) return;

                e.preventDefault();
                e.stopPropagation();

                let sku = removeBtn.getAttribute('data-sku') ||
                    removeBtn.dataset?.sku ||
                    (removeBtn.parentElement as HTMLElement)?.getAttribute('data-sku');

                if (!sku) {
                    const itemEl = removeBtn.closest('.cart-item') as HTMLElement;
                    if (itemEl) sku = itemEl.getAttribute('data-sku');
                }

                if (sku) {
                    removeItem(sku);
                }
                return;
            }

            // 4. Mobile Menu Toggle
            const menuToggle = target.closest('.mobile-menu-toggle') as HTMLElement;
            if (menuToggle) {
                e.preventDefault();
                const menu = document.getElementById('mobile-menu');
                if (menu) {
                    const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
                    menuToggle.setAttribute('aria-expanded', (!isExpanded).toString());
                    menu.classList.toggle('show');
                }
                return;
            }

            // 5. Admin Settings & Auth Action Interception
            const actionBtn = target.closest('[data-action]') as HTMLElement;
            if (actionBtn && !e.defaultPrevented) {
                const action = actionBtn.getAttribute('data-action') || '';

                // Skip modal close / admin-specific management actions (handled in admin_settings.php)
                if (action.startsWith('close-') || action === 'prevent-submit') return;

                const isSettingsPage = document.body.getAttribute('data-page') === 'admin/settings';
                // Auth Actions
                if (action === 'open-login-modal') {
                    e.preventDefault();
                    e.stopPropagation();
                    const rect = actionBtn.getBoundingClientRect();
                    const mode = actionBtn.getAttribute('data-login-mode') === 'register' ? 'register' : 'login';
                    if (mode === 'register') {
                        openRegister(undefined, rect);
                    } else {
                        openLogin(undefined, rect);
                    }
                    return;
                }
                if (action === 'open-account-settings') {
                    e.preventDefault();
                    e.stopPropagation();
                    openAccountSettings();
                    return;
                }

                // Map legacy data-actions to conductor sections
                const actionMap: Record<string, string> = {
                    // 'open-area-item-mapper': 'settings',
                    // 'open-file-manager': 'settings',
                    // 'open-global-entities': 'settings',
                    // 'open-backup-restore': 'settings',
                    // 'open-email-templates': 'settings',
                    // 'open-ai-settings': 'settings',
                    // 'open-background-manager': 'settings',
                    // 'open-cart-simulation': 'settings',
                    // 'open-secrets-modal': 'settings',
                    'open-marketing-root': 'marketing',
                    'open-social-media-manager': 'marketing',
                    'open-coupons-manager': 'marketing',
                    // 'open-dashboard-config': 'settings',
                    // 'open-theme-words': 'settings',
                    // 'open-receipt-settings': 'settings',
                    // 'open-inventory-archive': 'settings',
                    // 'open-marketing-selfcheck': 'settings',
                    // 'open-db-status-dashboard': 'settings',
                    // 'open-db-query-console': 'settings',
                    // 'open-brand-styling': 'settings',
                    // 'open-content-generator': 'settings',
                    // 'open-social-posts': 'settings',
                    // 'open-automation': 'settings',
                    // 'open-intent-heuristics': 'settings',
                    // 'open-email-history': 'settings',
                    // 'open-css-catalog': 'settings',
                    // 'open-admin-tools-modal': 'settings',
                    // 'open-logging-status': 'settings',
                    // 'open-address-diagnostics': 'settings',
                    'open-action-icons-manager': 'action-icons',
                    // 'open-business-info': 'business-info',
                    'open-settings-marketing-manager': 'marketing',
                    // 'open-attributes': 'settings',
                    // 'open-reports-browser': 'reports',
                    // 'open-shipping-settings': 'settings',
                    // 'open-square-settings': 'settings',
                    // 'open-inventory-manager': 'settings',
                    // 'open-db-status': 'settings',
                    // 'open-db-query-console-direct': 'settings',
                    // 'open-colors-fonts': 'settings',
                    // 'open-suggestions-manager': 'settings',
                    // 'open-ai-provider-parent': 'settings',
                    // 'open-cost-breakdown': 'settings',
                    // 'open-price-suggestions': 'settings',
                    // 'open-email-settings': 'settings',
                    // 'open-template-manager': 'settings',
                    // 'open-shopping-cart': 'settings',
                    // 'open-categories': 'categories',
                    'open-room-config-manager': 'room-config-manager',
                    'open-room-map-manager': 'room-map-manager'
                };

                const section = actionMap[action];
                if (section) {
                    e.preventDefault();
                    e.stopPropagation();

                    const url = new URL(window.location.href);
                    url.searchParams.set('section', section);
                    // For specific sub-sections we could scroll to them
                    window.history.pushState({}, '', url.toString());
                    window.dispatchEvent(new Event('popstate'));
                }
            }
        };

        const handleCartOpen = () => setIsCartOpen(true);

        document.addEventListener('click', handleGlobalClick, true);
        window.addEventListener('wf:cart:open', handleCartOpen);

        return () => {
            document.removeEventListener('click', handleGlobalClick, true);
            window.removeEventListener('wf:cart:open', handleCartOpen);
        };
    }, [openRoom, removeItem, setIsCartOpen, openPolicy, openLogin, openAccountSettings, isLoggedIn]);
};

export default useGlobalListeners;
