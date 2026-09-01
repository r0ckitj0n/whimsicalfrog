import React, { useEffect, useState } from 'react';
import { selectNextLoadingMessage } from '../utils/loadingMessageRotation.js';
import { selectFeaturedProduct } from '../utils/loadingProductRotation.js';
import { prefetchFeaturedProducts } from '../utils/featuredProductsPrefetch.js';
import { productPathFromItem } from '../utils/product-url.js';
import type {
    IFeaturedSplashProduct,
    ILoadingMessagePair
} from '../types/loadingSplash.js';
import { AdminLoading } from './admin/AdminLoading.js';
import '../styles/components/ui/site-loading-splash.css';

type SplashMode = 'pending' | 'whimsical' | 'fallback';

interface WhimsicalState {
    message: ILoadingMessagePair;
    product: IFeaturedSplashProduct;
}

/**
 * Storefront hydration splash: whimsical two-line message + featured product.
 * Must never delay bootstrap; product fetch runs in parallel and failures fall back.
 */
export const SiteLoadingSplash: React.FC = () => {
    const [mode, setMode] = useState<SplashMode>('pending');
    const [content, setContent] = useState<WhimsicalState | null>(null);

    useEffect(() => {
        let cancelled = false;

        const loadFeatured = async () => {
            try {
                const eligible = await prefetchFeaturedProducts();
                if (cancelled) return;

                if (eligible.length === 0) {
                    setMode('fallback');
                    return;
                }

                const selectedProduct = selectFeaturedProduct(eligible);
                if (!selectedProduct) {
                    setMode('fallback');
                    return;
                }

                let message: ILoadingMessagePair;
                try {
                    message = selectNextLoadingMessage();
                } catch {
                    message = {
                        id: 0,
                        line1: 'The pond is waking up…',
                        line2: 'Thanks for hopping by.'
                    };
                }

                setContent({ message, product: selectedProduct });
                setMode('whimsical');
            } catch {
                if (!cancelled) {
                    setMode('fallback');
                }
            }
        };

        void loadFeatured();
        return () => {
            cancelled = true;
        };
    }, []);

    if (mode === 'fallback') {
        return <AdminLoading />;
    }

    if (mode === 'pending' || !content) {
        return (
            <div
                className="wf-site-loading-splash flex min-h-[60vh] flex-col items-center justify-center px-6 py-10 text-center"
                role="status"
                aria-live="polite"
                aria-busy="true"
            >
                <div className="wf-site-loading-splash__pond relative mb-5 flex h-14 w-14 items-center justify-center">
                    <span className="wf-site-loading-splash__ripple" aria-hidden="true" />
                    <span className="wf-site-loading-splash__frog text-3xl" aria-hidden="true">
                        🐸
                    </span>
                </div>
                <div
                    className="wf-site-loading-splash__dots mt-2 flex items-center justify-center gap-1.5"
                    aria-hidden="true"
                >
                    <span />
                    <span />
                    <span />
                </div>
            </div>
        );
    }

    const { message, product } = content;
    const productHref = productPathFromItem({
        item_name: product.item_name,
        sku: product.sku
    });

    return (
        <div
            className="wf-site-loading-splash flex min-h-[60vh] flex-col items-center justify-center px-6 py-10 text-center"
            role="status"
            aria-live="polite"
            aria-busy="true"
        >
            <div className="wf-site-loading-splash__pond relative mb-5 flex h-14 w-14 items-center justify-center">
                <span className="wf-site-loading-splash__ripple" aria-hidden="true" />
                <span className="wf-site-loading-splash__frog text-3xl" aria-hidden="true">
                    🐸
                </span>
            </div>

            <p className="font-merienda text-xl font-semibold text-[var(--brand-primary)] sm:text-2xl max-w-xl leading-snug">
                {message.line1}
            </p>
            <p className="mt-2 font-nunito text-base text-slate-600 sm:text-lg max-w-lg leading-relaxed">
                {message.line2}
            </p>

            <a
                href={productHref}
                className="wf-site-loading-splash__product mt-8 block w-full max-w-[220px] rounded-2xl bg-white/90 p-4 shadow-md border border-[color-mix(in_srgb,var(--brand-primary)_25%,transparent)] no-underline text-inherit transition-transform duration-300 hover:-translate-y-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-secondary)]"
            >
                <div className="mb-2 flex items-center justify-center gap-2 font-nunito text-xs font-bold uppercase tracking-[0.18em] text-[var(--brand-secondary)]">
                    <span aria-hidden="true">🛍️</span>
                    <span>Featured</span>
                </div>
                <div className="mx-auto mb-3 flex h-36 w-full items-center justify-center overflow-hidden rounded-xl bg-[#f7faf2]">
                    {product.image_url ? (
                        <img
                            src={product.image_url}
                            alt={product.item_name}
                            className="max-h-full max-w-full object-contain p-2"
                            loading="eager"
                        />
                    ) : (
                        <span className="text-4xl" aria-hidden="true">
                            🛍️
                        </span>
                    )}
                </div>
                <p className="font-merienda text-sm font-semibold text-slate-800 line-clamp-2 leading-snug">
                    {product.item_name}
                </p>
            </a>

            <div
                className="wf-site-loading-splash__dots mt-8 flex items-center justify-center gap-1.5"
                aria-hidden="true"
            >
                <span />
                <span />
                <span />
            </div>
        </div>
    );
};

export default SiteLoadingSplash;
