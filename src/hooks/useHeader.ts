import { useState, useCallback, useEffect } from 'react';
import { useAuthContext } from '../context/AuthContext.js';
import { useCart } from './use-cart.js';
import { useApp } from '../context/AppContext.js';

export const useHeader = () => {
    const { user, isLoggedIn, isAdmin, logout } = useAuthContext();
    const { refresh: refreshCart } = useCart();
    const { hintsEnabled, toggleHints } = useApp();
    const [headerHeight, setHeaderHeight] = useState(120);

    const updateHeaderHeight = useCallback(() => {
        try {
            const header = document.querySelector('.site-header') || document.querySelector('.universal-page-header');
            const nav = document.querySelector('.admin-tab-navigation');

            let hh = 120;
            let headerBottom = 0;
            let navBottom = 0;

            if (header) {
                const rect = header.getBoundingClientRect();
                hh = Math.max(40, Math.round(rect.height));
                headerBottom = Math.round(rect.bottom);
            }

            if (nav) {
                navBottom = Math.round(nav.getBoundingClientRect().bottom);
            }

            const offset = Math.max(headerBottom, navBottom) + 12;

            setHeaderHeight(hh);
            document.documentElement.style.setProperty('--wf-header-height', `${hh}px`);
            document.documentElement.style.setProperty('--wf-overlay-offset', `${offset}px`);

            // Sync with production style tag if it exists or create it for external scripts
            let st = document.getElementById('wf-header-dyn-vars') as HTMLStyleElement;
            if (!st) {
                st = document.createElement('style');
                st.id = 'wf-header-dyn-vars';
                document.head.appendChild(st);
            }
            st.textContent = `:root{--wf-header-height:${hh}px; --wf-overlay-offset:${offset}px}`;
        } catch (err) {
            console.warn('[useHeader] Failed to compute header height', err);
        }
    }, []);

    useEffect(() => {
        updateHeaderHeight();
        window.addEventListener('resize', updateHeaderHeight);

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(updateHeaderHeight);
        }

        return () => window.removeEventListener('resize', updateHeaderHeight);
    }, [updateHeaderHeight]);

    return {
        headerHeight,
        hintsEnabled,
        toggleHints,
        updateHeaderHeight,
        logout
    };
};
