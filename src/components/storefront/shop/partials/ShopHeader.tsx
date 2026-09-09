import React from 'react';
import { ShopFilters } from '../ShopFilters.js';
import { IShopCategory as Category } from '../../../../types/index.js';

interface ShopHeaderProps {
    navigate: (path: string) => void;
    categoryList: Category[];
    activeCategory: string;
    onCategoryChange: (cat: string) => void;
    searchQuery: string;
    onSearchChange: (q: string) => void;
    current_page: string;
}

export const ShopHeader: React.FC<ShopHeaderProps> = ({
    navigate,
    categoryList,
    activeCategory,
    onCategoryChange,
    searchQuery,
    onSearchChange,
    current_page
}) => {
    return (
        <div className="relative z-30 w-full px-5 py-4 mt-[20px] flex flex-col items-center justify-center gap-3">
            {/* Below page logo/title; above All Items category filters */}
            <div className="shop-back-btn-wrapper w-full flex justify-start">
                <button
                    type="button"
                    onClick={() => navigate('/room_main')}
                    className="px-6 py-2.5 text-[14px] font-merienda rounded-full bg-brand-primary text-white shadow-[0_0_15px_rgba(var(--brand-primary-rgb),0.3)] transition-all duration-300 hover:brightness-110 hover:scale-105 active:scale-95"
                >
                    Back to Main Room
                </button>
            </div>

            <ShopFilters
                categoryList={categoryList}
                activeCategory={activeCategory}
                onCategoryChange={onCategoryChange}
                searchQuery={searchQuery}
                onSearchChange={onSearchChange}
                current_page={current_page}
            />
        </div>
    );
};
