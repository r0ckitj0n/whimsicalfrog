import React from 'react';
import { SpinningFrogHead } from '../../ui/SpinningFrogHead.js';

export const ShopLoadingScreen: React.FC = () => (
    <section
        className="fixed inset-0 flex flex-col items-center justify-center overflow-hidden z-elevated bg-black"
        role="status"
        aria-live="polite"
        aria-busy="true"
        aria-label="Loading the shop"
    >
        <SpinningFrogHead />
        <span className="sr-only">Loading the shop…</span>
    </section>
);

export default ShopLoadingScreen;
