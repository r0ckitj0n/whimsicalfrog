import React from 'react';
import { Link } from 'react-router-dom';
import { ShopFilters } from '../ShopFilters.js';
import { IShopCategory as Category } from '../../../../types/index.js';

interface ShopHeaderProps {
    categoryList: Category[];
    activeCategory: string;
    onCategoryChange: (cat: string) => void;
    searchQuery: string;
    onSearchChange: (q: string) => void;
    current_page: string;
}

/**
 * Back control — positioned at the bottom of the top gradient band.
 * Kept outside the decorative gradient so clicks are not blocked by pointer-events-none.
 */
export const ShopBackButton: React.FC = () => (
    <div className="shop-back-btn-layer absolute top-20 left-0 right-0 h-[100px] z-30 pointer-events-none">
        <div className="shop-back-btn-wrapper absolute bottom-2 left-5 pointer-events-auto">
            <Link
                to="/room_main"
                className="inline-flex px-6 py-2.5 text-[14px] font-merienda rounded-full bg-brand-primary text-white shadow-[0_0_15px_rgba(var(--brand-primary-rgb),0.3)] transition-all duration-300 hover:brightness-110 hover:scale-105 active:scale-95"
            >
                Back to Main Room
            </Link>
        </div>
    </div>
);

/** Category filters — sit in normal flow below the gradient overlay. */
export const ShopHeader: React.FC<ShopHeaderProps> = ({
    categoryList,
    activeCategory,
    onCategoryChange,
    searchQuery,
    onSearchChange,
    current_page
}) => {
    return (
        <div className="relative z-30 w-full px-5 pt-2 pb-4 flex flex-col items-center justify-center">
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
