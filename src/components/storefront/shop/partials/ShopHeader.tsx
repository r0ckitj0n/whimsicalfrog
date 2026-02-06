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
        <div className="relative z-30 w-full px-5 py-4 mt-[20px] flex items-center justify-center">
            <div className="shop-back-btn-wrapper">
                <button
                    type="button"
                    onClick={() => navigate('/')}
                    className="px-6 py-2.5 text-[14px] font-merienda rounded-full bg-brand-primary text-white shadow-[0_0_15px_rgba(var(--brand-primary-rgb),0.3)] transition-all duration-300 hover:brightness-110 hover:scale-105 active:scale-95"
                >
                    Back to Main Room
                </button>
            </div>

            <div className="md:hidden mr-4">
                <button type="button" onClick={() => navigate('/')} className="p-2.5 rounded-full bg-brand-primary text-white shadow-lg flex items-center justify-center">
                    <span className="btn-icon--back" style={{ fontSize: '20px' }} />
                </button>
            </div>

            <div className="flex-1 flex justify-center">
                <ShopFilters
                    categoryList={categoryList}
                    activeCategory={activeCategory}
                    onCategoryChange={onCategoryChange}
                    searchQuery={searchQuery}
                    onSearchChange={onSearchChange}
                    current_page={current_page}
                />
            </div>
            <div className="absolute right-8 hidden lg:block w-[180px]" />
        </div>
    );
};
