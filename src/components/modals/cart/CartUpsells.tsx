import React from 'react';

interface IUpsellItem {
    sku: string;
    name: string;
    price: number;
    image: string;
    hasOptions: boolean;
}

interface CartUpsellsProps {
    upsells: IUpsellItem[];
    isLoading: boolean;
    onAddItem: (item: IUpsellItem) => void;
    onShowDetails: (sku: string) => void;
}

export const CartUpsells: React.FC<CartUpsellsProps> = ({
    upsells,
    isLoading,
    onAddItem,
    onShowDetails
}) => {
    if (!isLoading && upsells.length === 0) return null;

    return (
        <div id="cartUpsells" className="pt-8 border-t border-gray-100">
            <div className="cart-upsell-heading merienda-font">
                <span className="btn-icon--sparkles mr-2" aria-hidden="true" style={{ fontSize: '16px' }} />
                You Might Also Like
            </div>
            {isLoading ? (
                <div className="wf-emoji-loader" style={{ fontSize: '32px' }}>⏳</div>
            ) : (
                <div className="cart-upsell-track">
                    {upsells.map(u => (
                        <div key={u.sku} className="cart-upsell-entry group relative transition-all duration-300">
                            <div className="aspect-square bg-gray-50 rounded-xl mb-2 overflow-hidden border border-black/5 p-2 w-full flex items-center justify-center">
                                <img
                                    src={u.image}
                                    alt={`Recommended: ${u.name}`}
                                    className="cart-upsell-thumb group-hover:scale-110 transition-transform duration-500"
                                    loading="lazy"
                                />
                            </div>
                            <div className="text-[10px] font-bold text-gray-900 truncate mb-1 w-full text-center" title={u.name}>{u.name}</div>
                            <div className="flex items-center justify-between w-full mt-auto">
                                <div className="text-[11px] font-black text-brand-primary">${u.price.toFixed(2)}</div>
                                <button
                                    type="button"
                                    onClick={() => u.hasOptions ? onShowDetails(u.sku) : onAddItem(u)}
                                    className="p-1.5 bg-white border-2 border-gray-100 rounded-lg text-gray-400 hover:text-brand-primary hover:border-brand-primary transition-colors"
                                    aria-label={`Add ${u.name} to cart`}
                                >
                                    <span className="btn-icon--add" style={{ fontSize: '14px' }} />
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
};
