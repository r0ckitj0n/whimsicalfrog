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
 * Back control in normal flow — fills the 100px gradient band and
 * aligns the link to the bottom so it stays clickable.
 */
export const ShopBackButton: React.FC = () => (
    <div className="shop-back-btn-wrapper relative z-30 w-full shrink-0 h-[100px] flex items-end px-5 pb-2">
        <Link
            to="/room_main"
            className="inline-flex px-6 py-2.5 text-[14px] font-merienda rounded-full bg-brand-primary text-white shadow-[0_0_15px_rgba(var(--brand-primary-rgb),0.3)] transition-all duration-300 hover:brightness-110 hover:scale-105 active:scale-95"
        >
            Back to Main Room
        </Link>
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
