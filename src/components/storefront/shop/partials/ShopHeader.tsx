import React from 'react';
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

/** Back control — pinned to the bottom of the 100px gradient band. */
export const ShopBackButton: React.FC = () => (
    <a
        href="/room_main"
        className="shop-back-btn inline-flex px-6 py-2.5 text-[14px] font-merienda rounded-full bg-brand-primary text-white shadow-[0_0_15px_rgba(var(--brand-primary-rgb),0.3)] transition-all duration-300 hover:brightness-110 hover:scale-105 active:scale-95"
        onClick={(e) => {
            e.preventDefault();
            window.location.assign('/room_main');
        }}
    >
        Back to Main Room
    </a>
);

/**
 * Shop top controls:
 * 1) 100px band matching the top gradient — Back sits at its bottom, under the logo
 * 2) Category filters in normal flow below that band (below Back / below the gradient)
 */
export const ShopHeader: React.FC<ShopHeaderProps> = ({
    categoryList,
    activeCategory,
    onCategoryChange,
    searchQuery,
    onSearchChange,
    current_page
}) => {
    return (
        <div className="shop-top-controls relative z-30 w-full flex flex-col items-stretch">
            <div className="shop-back-btn-wrapper w-full h-[100px] flex items-end justify-start px-5 pb-2">
                <ShopBackButton />
            </div>
            <div className="w-full px-5 pt-2 pb-3">
                <ShopFilters
                    categoryList={categoryList}
                    activeCategory={activeCategory}
                    onCategoryChange={onCategoryChange}
                    searchQuery={searchQuery}
                    onSearchChange={onSearchChange}
                    current_page={current_page}
                />
            </div>
        </div>
    );
};
