import React from 'react';
import { useLocation } from 'react-router-dom';
import { useShopUI } from '../../hooks/storefront/useShopUI.js';
import { ShopHeader } from './shop/partials/ShopHeader.js';
import { ProductGridArea } from './shop/partials/ProductGridArea.js';
import { IShopCategory as Category, IShopItem as Item } from '../../types/index.js';

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

            <ProductGridArea
                filteredItems={filteredItems}
                expandedSkus={expandedSkus}
                onToggleExpand={toggleExpand}
                onAddToCart={handleAddToCart}
                handleClear={handleClear}
            />
        </section>
    );
};

export default ShopView;
