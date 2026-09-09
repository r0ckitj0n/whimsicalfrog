import React, { useLayoutEffect } from 'react';
import { showShopBootOverlay } from '../../../core/shop-boot-overlay.js';

/**
 * Shop wait gate. The visible spinner lives in `#wf-shop-boot-overlay`
 * (index.html + shop-boot-overlay.ts) so the previous room photo cannot
 * stack above Tailwind classes that have not loaded yet.
 */
export const ShopLoadingScreen: React.FC = () => {
    useLayoutEffect(() => {
        showShopBootOverlay();
    }, []);

    return null;
};

export default ShopLoadingScreen;
