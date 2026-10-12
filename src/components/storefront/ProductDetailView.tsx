import React, { useEffect, useLayoutEffect, useMemo, useState } from 'react';
import { useLocation } from 'react-router-dom';
import type { IShopCategory, IShopItem } from '../../types/index.js';
import { categoryPathFromSlug, productPathFromItem, productSlugFromItem } from '../../utils/product-url.js';
import { hideShopBootOverlay } from '../../core/shop-boot-overlay.js';
import ApiClient from '../../core/ApiClient.js';

interface ProductDetailViewProps {
    categories: Record<string, IShopCategory>;
    onOpenItem?: (sku: string) => void;
}

const slugify = (value: string): string => value
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '');

/** Extract trailing SKU from canonical slug `name--wf-xx-001` or bare SKU. */
const skuFromIdentifier = (identifier: string): string | null => {
    const raw = identifier.trim();
    if (!raw) return null;
    const dashParts = raw.split('--');
    if (dashParts.length >= 2) {
        const tail = dashParts[dashParts.length - 1].trim();
        if (tail) return tail.toUpperCase();
    }
    if (/^[a-z0-9]+(?:-[a-z0-9]+)+$/i.test(raw) || /^[A-Z]{2,}-/i.test(raw)) {
        return raw.toUpperCase();
    }
    return null;
};

export const ProductDetailView: React.FC<ProductDetailViewProps> = ({ categories, onOpenItem }) => {
    const location = useLocation();
    const [fetched, setFetched] = useState<{ item: IShopItem; categorySlug: string; categoryLabel: string } | null>(null);
    const [fetchFailed, setFetchFailed] = useState(false);

    useLayoutEffect(() => {
        hideShopBootOverlay();
    }, []);
    const match = location.pathname.match(/^\/product\/([^/]+)$/i);
    const identifier = match?.[1] ? decodeURIComponent(match[1]) : null;

    const catalog = useMemo(() => {
        const rows: Array<{ item: IShopItem; categorySlug: string; categoryLabel: string }> = [];
        Object.values(categories).forEach((category) => {
            category.items.forEach((item) => {
                rows.push({ item, categorySlug: category.slug, categoryLabel: category.label });
            });
        });
        return rows;
    }, [categories]);

    const catalogProduct = useMemo(() => {
        if (!identifier) return null;
        const normalized = identifier.toLowerCase();
        const normalizedSlug = slugify(identifier);
        const skuHint = skuFromIdentifier(identifier)?.toLowerCase() || null;
        return catalog.find(({ item }) => {
            const sku = item.sku.toLowerCase();
            const skuSlug = slugify(item.sku);
            const canonicalSlug = productSlugFromItem(item);
            return sku === normalized
                || skuSlug === normalizedSlug
                || canonicalSlug === normalizedSlug
                || (skuHint !== null && sku === skuHint);
        }) || null;
    }, [catalog, identifier]);

    useEffect(() => {
        setFetched(null);
        setFetchFailed(false);
        if (!identifier || catalogProduct) return;

        const sku = skuFromIdentifier(identifier);
        if (!sku) {
            setFetchFailed(true);
            return;
        }

        let cancelled = false;
        (async () => {
            try {
                const res = await ApiClient.get<{
                    success?: boolean;
                    item?: {
                        sku?: string;
                        name?: string;
                        description?: string;
                        retail_price?: string | number;
                        stock_quantity?: number;
                        image_url?: string;
                        category?: string;
                        status?: string;
                    };
                }>('/api/get_item_details.php', { sku });

                const row = res?.item;
                if (cancelled) return;
                if (!row?.sku || String(row.status || 'live').toLowerCase() === 'draft') {
                    setFetchFailed(true);
                    return;
                }

                const categoryLabel = String(row.category || 'Shop');
                const categorySlug = slugify(categoryLabel) || 'shop';
                const item: IShopItem = {
                    sku: String(row.sku),
                    item_name: String(row.name || row.sku),
                    price: String(row.retail_price ?? '0'),
                    stock: Number(row.stock_quantity ?? 0),
                    description: String(row.description || ''),
                    image_url: row.image_url ? (String(row.image_url).startsWith('/') ? String(row.image_url) : `/${String(row.image_url).replace(/^\//, '')}`) : '',
                };

                setFetched({ item, categorySlug, categoryLabel });
            } catch {
                if (!cancelled) setFetchFailed(true);
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [identifier, catalogProduct]);

    if (!identifier || !location.pathname.toLowerCase().startsWith('/product/')) {
        return null;
    }

    const product = catalogProduct || fetched;

    if (!product) {
        if (!fetchFailed && !catalogProduct) {
            return (
                <section className="container mx-auto px-4 py-28 text-white">
                    <h1 className="text-4xl font-merienda mb-4">Loading product…</h1>
                </section>
            );
        }
        return (
            <section className="container mx-auto px-4 py-28 text-white">
                <h1 className="text-4xl font-merienda mb-4">Product not found</h1>
                <p className="font-nunito text-white/90">This product is no longer available. Browse current items in the <a href="/shop" className="underline">shop</a>.</p>
            </section>
        );
    }

    const { item, categorySlug, categoryLabel } = product;
    const inStock = Number(item.stock) > 0;
    const related = catalog.filter((entry) => entry.categorySlug === categorySlug && entry.item.sku !== item.sku).slice(0, 4);
    const imageSrc = item.image_url
        ? (item.image_url.startsWith('/') || item.image_url.startsWith('http') ? item.image_url : `/${item.image_url}`)
        : '';

    return (
        <section className="container mx-auto px-4 pt-28 pb-20 text-white">
            <nav aria-label="Breadcrumb" className="text-sm font-nunito mb-5 text-white/85">
                <a href="/shop" className="underline">Shop</a> /{' '}
                <a href={categoryPathFromSlug(categorySlug)} className="underline">{categoryLabel}</a> /{' '}
                <span>{item.item_name}</span>
            </nav>

            <div className="grid gap-8 lg:grid-cols-2">
                <div className="rounded-2xl bg-white p-4">
                    {imageSrc ? (
                        <img src={imageSrc} alt={item.item_name} className="w-full h-auto object-contain rounded-xl" loading="eager" decoding="async" />
                    ) : (
                        <div className="aspect-square rounded-xl bg-gray-100" />
                    )}
                </div>

                <div className="rounded-2xl bg-black/45 backdrop-blur-sm p-6">
                    <h1 className="text-4xl font-merienda leading-tight">{item.item_name}</h1>
                    <p className="mt-2 font-nunito text-white/85">SKU: <span className="font-mono">{item.sku}</span></p>
                    <p className="mt-4 text-2xl font-bold">${Number(item.price).toFixed(2)}</p>
                    <p className="mt-2 font-nunito text-sm">{inStock ? 'In stock and ready to customize.' : 'Currently out of stock. Check related items below.'}</p>
                    <p className="mt-6 font-nunito text-white/90 leading-relaxed">{item.description}</p>

                    <div className="mt-8 flex flex-wrap gap-3">
                        <button
                            type="button"
                            onClick={() => (onOpenItem ? onOpenItem(item.sku) : window.showDetailedModal?.(item.sku))}
                            className="px-6 py-3 rounded-full bg-brand-primary text-white font-merienda hover:bg-brand-secondary transition-colors"
                        >
                            {inStock ? 'Customize & Add to Cart' : 'View Options'}
                        </button>
                        <a href={categoryPathFromSlug(categorySlug)} className="px-6 py-3 rounded-full border border-white/50 text-white font-merienda hover:bg-white/10 transition-colors">
                            More {categoryLabel}
                        </a>
                    </div>
                </div>
            </div>

            <section className="mt-12 rounded-2xl bg-black/35 p-6">
                <h2 className="text-2xl font-merienda mb-4">Shop by popular custom gift intent</h2>
                <div className="grid gap-4 md:grid-cols-2">
                    <p className="font-nunito text-white/90"><a href="/shop/category/tumblers" className="underline">Custom tumblers</a> for events, birthdays, and everyday drinkware with personalized names and themes.</p>
                    <p className="font-nunito text-white/90"><a href="/shop/category/t-shirts" className="underline">Personalized t-shirts</a> for teams, family reunions, and one-off gifts with custom text and graphics.</p>
                    <p className="font-nunito text-white/90"><a href="/shop/category/resin" className="underline">Handmade resin gifts</a> including keepsakes, decorative pieces, and custom color palettes.</p>
                    <p className="font-nunito text-white/90"><a href="/contact" className="underline">Custom gift requests</a> for special timelines, bundle ideas, and made-to-order concepts.</p>
                </div>
            </section>

            <section className="mt-8 rounded-2xl bg-black/35 p-6">
                <h2 className="text-2xl font-merienda mb-4">Frequently asked before checkout</h2>
                <div className="grid gap-4">
                    <div>
                        <h3 className="font-merienda text-lg">Turnaround time</h3>
                        <p className="font-nunito text-white/90">Most custom orders are prepared in a few business days. Rush timing can be requested through our <a href="/contact" className="underline">contact page</a>.</p>
                    </div>
                    <div>
                        <h3 className="font-merienda text-lg">Shipping</h3>
                        <p className="font-nunito text-white/90">Shipping methods and delivery expectations are listed in our <a href="/policy" className="underline">store policy</a>.</p>
                    </div>
                    <div>
                        <h3 className="font-merienda text-lg">Returns and policies</h3>
                        <p className="font-nunito text-white/90">Please review our <a href="/policy" className="underline">store policy</a>, <a href="/privacy" className="underline">privacy</a>, and <a href="/terms" className="underline">terms</a> before placing custom orders.</p>
                    </div>
                    <div>
                        <h3 className="font-merienda text-lg">Custom order process</h3>
                        <p className="font-nunito text-white/90">Share your idea, timeline, and design direction. We confirm feasibility, pricing, and next steps before production begins.</p>
                    </div>
                </div>
            </section>

            {related.length > 0 && (
                <section className="mt-8">
                    <h2 className="text-2xl font-merienda mb-4">Related {categoryLabel}</h2>
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        {related.map(({ item: relatedItem }) => (
                            <a key={relatedItem.sku} href={productPathFromItem(relatedItem)} className="rounded-xl bg-black/40 p-4 hover:bg-black/55 transition-colors">
                                <p className="font-merienda">{relatedItem.item_name}</p>
                                <p className="font-nunito text-sm text-white/85 mt-2">${Number(relatedItem.price).toFixed(2)}</p>
                            </a>
                        ))}
                    </div>
                </section>
            )}
        </section>
    );
};
