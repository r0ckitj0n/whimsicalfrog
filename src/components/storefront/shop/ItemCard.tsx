import React from 'react';
import { IShopItem } from '../../../types/index.js';

export type Item = IShopItem;

interface ItemCardProps {
    item: IShopItem;
    categoryLabel: string;
    isExpanded: boolean;
    onToggleExpand: (sku: string) => void;
    onAddToCart: (item: IShopItem) => void;
}

export const ItemCard: React.FC<ItemCardProps> = ({
    item,
    categoryLabel,
    isExpanded,
    onToggleExpand,
    onAddToCart
}) => {
    const formattedPrice = parseFloat(String(item.price)).toFixed(2);

    return (
        <div
            className={`group cursor-pointer bg-white rounded-[8px] shadow-[0_2px_8px_0px_rgba(0,0,0,0.1)] hover:-translate-y-1 hover:shadow-[0_8px_24px_rgba(0,0,0,0.12)] transition-all duration-300 flex flex-col overflow-hidden border border-[#DDDDDD] ${item.stock <= 0 ? 'opacity-75 grayscale-[0.2]' : ''}`}
            onClick={() => onAddToCart(item)}
        >
            {/* Product Image */}
            <div className="relative aspect-square overflow-hidden bg-[#fbfbfb]">
                {item.image_url ? (
                    <img
                        src={item.image_url}
                        alt={item.item_name}
                        className="w-full h-full object-contain p-6 group-hover:scale-105 transition-transform duration-700"
                        loading="lazy"
                    />
                ) : (
                    <div className="flex items-center justify-center h-full">
                        <span className="btn-icon--shopping-bag text-gray-200" style={{ fontSize: '48px' }} aria-hidden="true" />
                    </div>
                )}

                {item.stock <= 0 && (
                    <div className="absolute top-3 right-3 bg-red-500/90 text-white text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-widest backdrop-blur-sm shadow-sm">
                        Sold Out
                    </div>
                )}

                {/* Subtle Hover Overlay */}
                <div className="absolute inset-0 bg-brand-primary/0 group-hover:bg-brand-primary/2 transition-colors duration-300" />
            </div>

            {/* Product Info */}
            <div className="p-4 flex flex-col flex-1">
                <div className="text-[11px] text-brand-secondary font-bold uppercase tracking-widest mb-2 opacity-80 font-nunito">
                    {categoryLabel}
                </div>

                <h3 className="text-[17.6px] font-merienda font-semibold text-[#333333] mb-3 line-clamp-2 min-h-[3rem] leading-snug">
                    {item.item_name}
                </h3>

                <p className={`text-[14px] font-nunito text-gray-600 mb-6 leading-relaxed transition-all duration-300 ${isExpanded ? '' : 'line-clamp-3'}`}>
                    {item.description}
                </p>

                <div className="mt-auto flex items-center justify-between overflow-hidden">
                    <span className="text-[20px] font-nunito font-bold text-gray-900">${formattedPrice}</span>
                    <button
                        type="button"
                        onClick={(e) => {
                            e.stopPropagation();
                            onToggleExpand(item.sku);
                        }}
                        className="text-[12px] text-brand-primary font-bold hover:underline underline-offset-4 font-nunito"
                    >
                        {isExpanded ? 'Less Info' : 'More Info'}
                    </button>
                </div>

                {/* Expanded Info - always rendered for grid row alignment, visibility controlled */}
                <div className={`mt-4 pt-4 border-t border-gray-100 text-[11px] text-gray-400 font-nunito transition-all duration-300 ${isExpanded ? 'opacity-100' : 'opacity-0 pointer-events-none h-0 mt-0 pt-0 border-t-0 overflow-hidden'}`}>
                    <div className="flex justify-between mb-1.5">
                        <span>SKU:</span>
                        <span className="font-mono bg-gray-50 px-1.5 rounded">{item.sku}</span>
                    </div>
                    <div className="flex justify-between">
                        <span>In Stock:</span>
                        <span className={`${item.stock < 5 ? 'text-orange-500 font-bold' : ''}`}>{item.stock} items</span>
                    </div>
                </div>

                <button
                    onClick={(e) => {
                        e.stopPropagation();
                        onAddToCart(item);
                    }}
                    disabled={item.stock <= 0}
                    className={`mt-6 w-full py-3.5 rounded-full text-[14px] font-merienda shadow-sm transition-all duration-300 transform active:scale-95 ${item.stock <= 0
                        ? 'bg-gray-100 text-gray-400 cursor-not-allowed opacity-50'
                        : 'bg-brand-primary text-white hover:brightness-110 hover:shadow-lg hover:shadow-brand-primary/10'
                        }`}
                >
                    {item.stock <= 0 ? 'Sold Out' : (item.custom_button_text || 'Order Now')}
                </button>
            </div>
        </div>
    );
};
