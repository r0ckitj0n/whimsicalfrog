import React, { useRef } from 'react';
import { ItemCard } from '../ItemCard.js';
import { IShopItem as Item } from '../../../../types/index.js';

interface ProductGridAreaProps {
    filteredItems: Array<{ item: Item; categoryLabel: string }>;
    expandedSkus: Set<string>;
    onToggleExpand: (sku: string, gridRef: React.RefObject<HTMLDivElement>) => void;
    onAddToCart: (item: Item) => void;
    handleClear: () => void;
}

export const ProductGridArea: React.FC<ProductGridAreaProps> = ({
    filteredItems,
    expandedSkus,
    onToggleExpand,
    onAddToCart,
    handleClear
}) => {
    const inStockGridRef = useRef<HTMLDivElement>(null);
    const comingSoonGridRef = useRef<HTMLDivElement>(null);

    const inStockItems = filteredItems.filter(({ item }) => item.stock > 0);
    const outOfStockItems = filteredItems.filter(({ item }) => item.stock <= 0);

    return (
        <div className="relative z-10 w-full overflow-y-auto pb-24 px-5 flex-1 scrollbar-hide">
            <div className="w-full pt-[20px]">
                {filteredItems.length === 0 ? (
                    <div className="py-24 text-center space-y-4">
                        <h2 className="text-2xl font-merienda text-white drop-shadow-md">No items found</h2>
                        <p className="text-white/80 font-nunito">Try adjusting your filters or search terms.</p>
                        <button type="button" onClick={handleClear} className="px-8 py-3 bg-brand-primary hover:bg-brand-secondary text-white font-merienda rounded-xl transition-all shadow-lg active:scale-95">Reset All Filters</button>
                    </div>
                ) : (
                    <>
                        {inStockItems.length > 0 && (
                            <div ref={inStockGridRef} className="grid grid-cols-[repeat(auto-fill,minmax(300px,400px))] justify-center gap-x-8 gap-y-12 w-full">
                                {inStockItems.map(({ item, categoryLabel }) => (
                                    <div key={item.sku} data-sku={item.sku}>
                                        <ItemCard
                                            item={item}
                                            categoryLabel={categoryLabel}
                                            isExpanded={expandedSkus.has(item.sku)}
                                            onToggleExpand={(sku) => onToggleExpand(sku, inStockGridRef)}
                                            onAddToCart={onAddToCart}
                                        />
                                    </div>
                                ))}
                            </div>
                        )}

                        {outOfStockItems.length > 0 && (
                            <div className="coming-soon-wrapper mt-16">
                                <div className="coming-soon-header">
                                    <h2 className="text-3xl md:text-4xl font-merienda font-bold text-white mb-2">Coming Soon...</h2>
                                    <p className="text-white/90 font-nunito text-lg">These items are currently out of stock but will be available soon!</p>
                                </div>
                                <div ref={comingSoonGridRef} className="grid grid-cols-[repeat(auto-fill,minmax(300px,400px))] justify-center gap-x-8 gap-y-12 w-full">
                                    {outOfStockItems.map(({ item, categoryLabel }) => (
                                        <div key={item.sku} data-sku={item.sku}>
                                            <ItemCard
                                                item={item}
                                                categoryLabel={categoryLabel}
                                                isExpanded={expandedSkus.has(item.sku)}
                                                onToggleExpand={(sku) => onToggleExpand(sku, comingSoonGridRef)}
                                                onAddToCart={onAddToCart}
                                            />
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    );
};
