import React from 'react';
import { createPortal } from 'react-dom';
import { SpinningFrogHead } from '../../ui/SpinningFrogHead.js';

export const ShopLoadingScreen: React.FC = () => {
    if (typeof document === 'undefined') return null;

    return createPortal(
        <section
            className="fixed inset-0 flex flex-col items-center justify-center overflow-hidden bg-black z-modal"
            role="status"
            aria-live="polite"
            aria-busy="true"
            aria-label="Loading the shop"
        >
            <SpinningFrogHead />
            <span className="sr-only">Loading the shop…</span>
        </section>,
        document.body
    );
};

export default ShopLoadingScreen;
