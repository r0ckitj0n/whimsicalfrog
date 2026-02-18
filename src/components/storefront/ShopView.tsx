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

    type HelpTopicKey = 'categories' | 'shipping' | 'custom';
    const [activeHelpTopic, setActiveHelpTopic] = React.useState<HelpTopicKey | null>(null);

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

            <div className="sr-only">
                <h1>Custom Gifts, Tumblers, Shirts, and Resin Keepsakes</h1>
                <p>
                    Browse handmade products built for gifting. Explore custom tumblers, personalized t-shirts, and resin gift ideas with clear turnaround and policy support.
                </p>
                <h2>Shop featured products</h2>
                <p>Filter products using the category buttons at the top of this page.</p>
                <nav aria-label="Shop categories">
                    {visibleCategories.map((cat) => (
                        <a key={cat.slug} href={categoryPathFromSlug(cat.slug)}>
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

            <div className="shop-footer-help w-full max-w-6xl mx-auto relative z-20 text-white">
                <section className="rounded-2xl bg-[var(--brand-primary)]/45 p-3 text-white">
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm font-nunito">
                        <span className="font-merienda text-base">Shop Help</span>
                        <button type="button" className="underline text-white" onClick={() => setActiveHelpTopic('categories')}>Custom order categories</button>
                        <button type="button" className="underline text-white" onClick={() => setActiveHelpTopic('shipping')}>Orders & shipping FAQ</button>
                        <button type="button" className="underline text-white" onClick={() => setActiveHelpTopic('custom')}>Custom process</button>
                    </div>
                </section>
            </div>

            {activeHelpTopic && (
                <div className="fixed inset-0 z-[var(--wf-z-modal)] bg-black/60 backdrop-blur-sm p-4 flex items-center justify-center">
                    <div className="w-full max-w-2xl rounded-2xl bg-[var(--brand-primary)] text-white shadow-2xl border border-white/20">
                        <div className="flex items-center justify-between px-5 py-4 border-b border-white/20">
                            <h2 className="font-merienda text-2xl">
                                {activeHelpTopic === 'categories' && 'Popular custom order categories'}
                                {activeHelpTopic === 'shipping' && 'FAQ for orders and shipping'}
                                {activeHelpTopic === 'custom' && 'Custom process'}
                            </h2>
                            <button type="button" className="underline text-white" onClick={() => setActiveHelpTopic(null)}>Close</button>
                        </div>

                        <div className="p-5 space-y-3 font-nunito text-white">
                            {activeHelpTopic === 'categories' && (
                                <>
                                    <p><a href="/shop/category/tumblers" className="underline text-white">Custom tumblers</a> for birthdays, weddings, and team gifts with names or event themes.</p>
                                    <p><a href="/shop/category/t-shirts" className="underline text-white">Personalized t-shirts</a> for reunions, school groups, and branded small-batch apparel.</p>
                                    <p><a href="/shop/category/resin" className="underline text-white">Handmade resin gifts</a> including keepsakes and decorative gift-ready pieces.</p>
                                    <p><a href="/contact" className="underline text-white">Custom gift requests</a> are available when you need an idea turned into a made-to-order product.</p>
                                </>
                            )}

                            {activeHelpTopic === 'shipping' && (
                                <>
                                    <p><strong>Turnaround time:</strong> Most items are prepared within a few business days, and complex custom orders may take longer.</p>
                                    <p><strong>Shipping:</strong> Delivery and shipping details are listed in our <a href="/policy" className="underline text-white">store policy</a>.</p>
                                    <p><strong>Returns:</strong> Review return and support terms in <a href="/policy" className="underline text-white">store policy</a>, <a href="/privacy" className="underline text-white">privacy</a>, and <a href="/terms" className="underline text-white">terms</a>.</p>
                                </>
                            )}

                            {activeHelpTopic === 'custom' && (
                                <p><strong>Custom process:</strong> Submit details through <a href="/contact" className="underline text-white">contact</a> and we confirm pricing, timeline, and production steps.</p>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </section>
    );
};

export default ShopView;
