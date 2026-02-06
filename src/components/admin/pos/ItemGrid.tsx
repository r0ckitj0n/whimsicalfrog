import React from 'react';
import { IPOSItem } from '../../../hooks/admin/usePOS.js';

interface ItemGridProps {
    searchQuery: string;
    setSearchQuery: (query: string) => void;
    selectedCategory: string;
    setSelectedCategory: (category: string) => void;
    categories: string[];
    filteredItems: IPOSItem[];
    onShowDetails: (sku: string) => void;
}

export const ItemGrid: React.FC<ItemGridProps> = ({
    searchQuery,
    setSearchQuery,
    selectedCategory,
    setSelectedCategory,
    categories,
    filteredItems,
    onShowDetails
}) => {
    return (
        <section className="flex-1 flex flex-col min-w-0 min-h-0 bg-white">
            <div className="p-6 space-y-6 flex-shrink-0 bg-white border-b border-gray-100">
                <div className="flex flex-col md:flex-row gap-4">
                    <div className="flex-1">
                        <input
                            id="posSearchInput"
                            type="text"
                            value={searchQuery}
                            onChange={e => setSearchQuery(e.target.value)}
                            placeholder="Scan SKU or Search Items (F1)..."
                            className="form-input form-input-search text-base"
                        />
                    </div>
                    <select
                        value={selectedCategory}
                        onChange={e => setSelectedCategory(e.target.value)}
                        className="form-select md:w-64 py-4 rounded-2xl text-sm font-bold text-gray-600 bg-gray-50 border-gray-100 focus:ring-4 focus:ring-[var(--brand-primary)]/10 shadow-sm"
                    >
                        <option value="">All Categories</option>
                        {categories.map(cat => (
                            <option key={cat} value={cat}>{cat}</option>
                        ))}
                    </select>
                </div>
            </div>

            <div id="itemsGrid" className="flex-1 min-h-0 overflow-y-scroll px-6 pb-6">
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    {filteredItems.map((item) => (
                        <button
                            key={item.sku}
                            onClick={() => onShowDetails(item.sku)}
                            disabled={item.stock <= 0}
                            className={`group flex flex-col text-left bg-white border border-gray-100 rounded-3xl p-3 transition-all duration-300 hover:shadow-xl hover:border-[var(--brand-primary)]/30 hover:-translate-y-1 active:scale-95 ${item.stock <= 0 ? 'opacity-50 grayscale cursor-not-allowed' : ''}`}
                        >
                            <div className="aspect-square bg-gray-50 rounded-2xl mb-3 overflow-hidden border border-black/5 p-4 flex items-center justify-center">
                                {(item.primary_image?.image_path || item.image_url) ? (
                                    <img
                                        src={item.primary_image?.image_path || item.image_url}
                                        alt={item.primary_image?.alt_text || item.name}
                                        className="w-full h-full object-contain mix-blend-multiply transition-transform duration-500 group-hover:scale-110"
                                        loading="lazy"
                                    />
                                ) : (
                                    <div className="admin-action-btn btn-icon--shopping-cart text-5xl opacity-10" data-help-id="pos-item-no-image" />
                                )}
                            </div>
                            <div className="flex-1 min-w-0 space-y-1 px-1">
                                <div className="text-[10px] font-black text-[var(--brand-primary)]/60 uppercase tracking-widest truncate">{item.category}</div>
                                <div className="text-sm font-black text-gray-900 leading-tight line-clamp-2 h-10">{item.name}</div>
                                <div className="flex items-center justify-between pt-2">
                                    <div className="text-base font-black text-gray-900">${Number(item.retail_price || 0).toFixed(2)}</div>
                                    <div className={`text-[9px] font-bold px-2 py-0.5 rounded-full ${item.stock <= 5 ? 'bg-[var(--brand-error-bg)] text-[var(--brand-error)]' : 'bg-[var(--brand-accent-bg)] text-[var(--brand-accent)]'}`}>
                                        {item.stock} in stock
                                    </div>
                                </div>
                            </div>
                        </button>
                    ))}
                </div>
            </div>
        </section>
    );
};
