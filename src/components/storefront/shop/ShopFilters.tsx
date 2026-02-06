import React from 'react';
import { CATEGORY, PAGE } from '../../../core/constants.js';
import { Item } from './ItemCard.js';

interface Category {
    slug: string;
    label: string;
    items?: Item[];
}

interface ShopFiltersProps {
    categoryList: Category[];
    activeCategory: string;
    onCategoryChange: (slug: string) => void;
    searchQuery: string;
    onSearchChange: (query: string) => void;
    current_page: string;
}

export const ShopFilters: React.FC<ShopFiltersProps> = ({
    categoryList,
    activeCategory,
    onCategoryChange,
    onSearchChange,
    searchQuery,
    current_page
}) => {
    return (
        <div className="flex justify-center items-center w-full px-0 py-2">
            {/* Centered Category Filter Buttons */}
            <div className="flex flex-wrap items-center justify-center gap-3">
                <button
                    type="button"
                    onClick={() => onCategoryChange(CATEGORY.ALL)}
                    className={`px-6 py-2.5 text-[14px] font-merienda rounded-full transition-all duration-300 ${activeCategory === CATEGORY.ALL
                        ? 'bg-brand-primary text-white shadow-[0_0_15px_rgba(135,172,58,0.4)] ring-2 ring-white'
                        : 'bg-brand-primary/60 text-white hover:bg-brand-primary/80 backdrop-blur-sm shadow-sm'
                        }`}
                >
                    All Items
                </button>
                {categoryList
                    .filter(cat => cat.slug !== 'uncategorized' && (cat.items?.length ?? 0) > 0)
                    .map((cat) => (
                        <button
                            key={cat.slug}
                            type="button"
                            onClick={() => onCategoryChange(cat.slug)}
                            className={`px-6 py-2.5 text-[14px] font-merienda rounded-full transition-all duration-300 ${activeCategory === cat.slug
                                ? 'bg-brand-primary text-white shadow-[0_0_15px_rgba(135,172,58,0.4)] ring-2 ring-white'
                                : 'bg-brand-primary/60 text-white hover:bg-brand-primary/80 backdrop-blur-sm shadow-sm'
                                }`}
                        >
                            {cat.label}
                        </button>
                    ))}
            </div>
        </div>
    );
};
