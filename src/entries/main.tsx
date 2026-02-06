// src/entries/main.tsx

import React from 'react';
import { createRoot, Root } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App.js';

/**
 * Main Entry Point
 * Mounts the React application conductor to the DOM.
 */
declare global {
    interface Window {
        __WF_REACT_ROOT__?: Root | "MOUNTING";
        __WF_REACT_INIT_COUNT__?: number;
        __WF_ROOT_NOT_FOUND_LOGGED__?: boolean;
        openCartModal?: () => void;
        closeCartModal?: () => void;
        WF_CartModal?: {
            open: () => void;
            close: () => void;
        };
    }
}

const init = () => {
    // 1. Check for existing root or mounting state
    const currentRoot = window.__WF_REACT_ROOT__;

    if (currentRoot === "MOUNTING") {
        return;
    }

    if (currentRoot && typeof currentRoot === 'object') {
        return;
    }

    const container = document.getElementById('wf-root');
    if (!container) {
        if (!window.__WF_ROOT_NOT_FOUND_LOGGED__) {
            console.error('[WF-Main] #wf-root not found! Make sure footer.php is included.');
            window.__WF_ROOT_NOT_FOUND_LOGGED__ = true;
        }
        return;
    }

    // 2. Immediate lock
    window.__WF_REACT_ROOT__ = "MOUNTING";
    window.__WF_REACT_INIT_COUNT__ = (window.__WF_REACT_INIT_COUNT__ || 0) + 1;

    try {
        const root = createRoot(container);
        window.__WF_REACT_ROOT__ = root;

        root.render(
            <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
                <App />
            </BrowserRouter>
        );
    } catch (err) {
        console.error('[WF-Main] Mounting failed:', err);
        window.__WF_REACT_ROOT__ = undefined;
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
