import React from 'react';
import { SpinningFrogHead } from '../../ui/SpinningFrogHead.js';

interface ShopLoadingScreenProps {
    nested?: boolean;
}

export const ShopLoadingScreen: React.FC<ShopLoadingScreenProps> = ({ nested = false }) => (
    <section
        className={`fixed inset-0 flex flex-col items-center justify-center overflow-hidden bg-black ${nested ? 'z-sticky' : 'z-elevated'}`}
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
