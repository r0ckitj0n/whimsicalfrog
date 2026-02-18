import React from 'react';
import { useLocation } from 'react-router-dom';
import { useShopUI } from '../../hooks/storefront/useShopUI.js';
import { ShopHeader } from './shop/partials/ShopHeader.js';
import { ProductGridArea } from './shop/partials/ProductGridArea.js';
import { IShopCategory as Category, IShopItem as Item } from '../../types/index.js';
import { categoryPathFromSlug } from '../../utils/product-url.js';

interface ShopViewProps {
    categories: Record<string, Category>;
    current_page: string;
    onOpenItem?: (sku: string) => void;
}

export const ShopView: React.FC<ShopViewProps> = ({ categories, current_page, onOpenItem }) => {
    const location = useLocation();
    const isVisible = location.pathname.includes('/shop') || new URLSearchParams(window.location.search).get('room_id') === 'S';

    const {
        activeCategory, setActiveCategory,
        searchQuery, setSearchQuery,
        bgUrl, categoryList, filteredItems,
        expandedSkus, toggleExpand,
        navigate, handleClear
    } = useShopUI({ categories, isVisible });

    const handleAddToCart = (item: Item) => {
        if (onOpenItem) onOpenItem(item.sku);
        else if (window.showDetailedModal) window.showDetailedModal(item.sku);
    };

    if (!isVisible) return null;

    const visibleCategories = categoryList.filter(cat => cat.slug !== 'uncategorized' && (cat.items?.length ?? 0) > 0);

    return (
        <section
            id="shopPage"
            className="fixed inset-0 pt-20 flex flex-col items-center overflow-hidden z-base bg-cover bg-center bg-no-repeat"
            style={{ backgroundImage: bgUrl ? `url("${bgUrl}")` : 'none' }}
        >
            <div className="absolute top-0 left-0 right-0 h-40 bg-gradient-to-b from-black/80 to-transparent pointer-events-none z-20" />

            <ShopHeader
                navigate={navigate}
                categoryList={categoryList}
                activeCategory={activeCategory}
                onCategoryChange={setActiveCategory}
                searchQuery={searchQuery}
                onSearchChange={setSearchQuery}
                current_page={current_page}
            />

            <div className="w-full px-6 max-w-6xl mx-auto relative z-20">
                <h1 className="text-3xl md:text-4xl font-merienda text-white drop-shadow-md">Custom Gifts, Tumblers, Shirts, and Resin Keepsakes</h1>
                <p className="mt-2 font-nunito text-white/90 max-w-4xl">
                    Browse handmade products built for gifting. Explore custom tumblers, personalized t-shirts, and resin gift ideas with clear turnaround and policy support.
                </p>
                <h2 className="sr-only">Shop featured products</h2>
                <nav aria-label="Shop categories" className="mt-4 flex flex-wrap gap-3">
                    {visibleCategories.map((cat) => (
                        <a key={cat.slug} href={categoryPathFromSlug(cat.slug)} className="text-sm font-nunito text-white underline underline-offset-4">
                            {cat.label}
                        </a>
                    ))}
                </nav>
            </div>

            <ProductGridArea
                filteredItems={filteredItems}
                expandedSkus={expandedSkus}
                onToggleExpand={toggleExpand}
                onAddToCart={handleAddToCart}
                handleClear={handleClear}
            />

            <div className="w-full px-6 pb-24 max-w-6xl mx-auto relative z-20 text-white">
                <section className="rounded-2xl bg-black/45 p-6 mb-4">
                    <h2 className="font-merienda text-2xl mb-3">Popular custom order categories</h2>
                    <p className="font-nunito text-white/90 mb-2"><a href="/shop/category/tumblers" className="underline">Custom tumblers</a> for birthdays, weddings, and team gifts with names or event themes.</p>
                    <p className="font-nunito text-white/90 mb-2"><a href="/shop/category/t-shirts" className="underline">Personalized t-shirts</a> for reunions, school groups, and branded small-batch apparel.</p>
                    <p className="font-nunito text-white/90 mb-2"><a href="/shop/category/resin" className="underline">Handmade resin gifts</a> including keepsakes and decorative gift-ready pieces.</p>
                    <p className="font-nunito text-white/90"><a href="/contact" className="underline">Custom gift requests</a> are available when you need an idea turned into a made-to-order product.</p>
                </section>

                <section className="rounded-2xl bg-black/45 p-6">
                    <h2 className="font-merienda text-2xl mb-3">FAQ for orders and shipping</h2>
                    <p className="font-nunito text-white/90 mb-2"><strong>Turnaround time:</strong> Most items are prepared within a few business days, and complex custom orders may take longer.</p>
                    <p className="font-nunito text-white/90 mb-2"><strong>Shipping:</strong> Delivery and shipping details are listed in our <a href="/policy" className="underline">store policy</a>.</p>
                    <p className="font-nunito text-white/90 mb-2"><strong>Returns:</strong> Review return and support terms in <a href="/policy" className="underline">store policy</a>, <a href="/privacy" className="underline">privacy</a>, and <a href="/terms" className="underline">terms</a>.</p>
                    <p className="font-nunito text-white/90"><strong>Custom process:</strong> Submit details through <a href="/contact" className="underline">contact</a> and we confirm pricing, timeline, and production steps.</p>
                </section>
            </div>
        </section>
    );
};

export default ShopView;
