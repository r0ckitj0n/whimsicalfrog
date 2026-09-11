import React, { useState } from 'react';
import { IOrderItem } from '../../../../types/admin/orders.js';
import { ItemDetailsModal } from '../../../modals/ItemDetailsModal.js';

interface OrderItemsSectionProps {
    items: IOrderItem[];
    mode: 'view' | 'edit';
    updateItemQty: (sku: string, qty: number) => void;
    removeItem: (sku: string) => void;
}

const IMAGE_FALLBACK = '/images/items/placeholder.webp';

export const OrderItemsSection: React.FC<OrderItemsSectionProps> = ({ items, mode, updateItemQty, removeItem }) => {
    const [previewSku, setPreviewSku] = useState('');
    const [isPreviewOpen, setIsPreviewOpen] = useState(false);

    const getThumbnailUrl = (item: IOrderItem) => {
        if (item.image_url) return item.image_url;
        if (item.sku) return `/images/items/${item.sku}A.webp`;
        return IMAGE_FALLBACK;
    };

    const openItemPreview = (sku: string) => {
        if (!sku) return;
        setPreviewSku(sku);
        setIsPreviewOpen(true);
    };

    const closeItemPreview = () => {
        setIsPreviewOpen(false);
    };

    return (
        <>
            <section className="admin-section--orange rounded-2xl shadow-sm overflow-hidden wf-contained-section">
                <div className="px-6 py-4 flex items-center justify-between">
                    <h5 className="text-xs font-black uppercase tracking-widest">Order Items ({items.length})</h5>
                    {mode === 'edit' && (
                        <button
                            type="button"
                            className="admin-action-btn btn-icon--add"
                            data-help-id="orders-action-add-item"
                        />
                    )}
                </div>
                <div className="p-4 flex flex-col gap-1">
                    <div className="space-y-2 max-h-[400px] overflow-y-auto pr-2">
                        {items.map((item) => (
                            <div key={item.sku} className="flex items-center gap-4 p-3 bg-white border rounded-xl group transition-all hover:border-[var(--brand-primary)]/30">
                                <button
                                    type="button"
                                    onClick={() => openItemPreview(item.sku)}
                                    className="bg-gray-100 rounded-md flex-shrink-0 flex items-center justify-center border overflow-hidden w-12 h-12 object-cover rounded-lg cursor-pointer hover:ring-2 hover:ring-[var(--brand-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand-secondary)]"
                                    data-help-id="orders-action-view-item"
                                    aria-label={`View details for ${item.name || item.sku}`}
                                >
                                    <img
                                        src={getThumbnailUrl(item)}
                                        alt={item.name || `Item image for ${item.sku}`}
                                        className="object-contain bg-gray-50 w-12 h-12 bg-gray-50 rounded-lg flex items-center justify-center border border-gray-100"
                                        loading="lazy"
                                        onError={(e) => {
                                            (e.target as HTMLImageElement).src = IMAGE_FALLBACK;
                                        }}
                                    />
                                </button>
                                <div className="flex-1 min-w-0">
                                    <button
                                        type="button"
                                        onClick={() => openItemPreview(item.sku)}
                                        className="text-sm font-bold text-gray-900 truncate text-left w-full hover:text-[var(--brand-secondary)] focus:outline-none focus-visible:underline cursor-pointer"
                                        data-help-id="orders-action-view-item"
                                    >
                                        {item.name}
                                    </button>
                                    <div className="text-[10px] text-gray-400 font-mono uppercase tracking-wider">SKU: {item.sku}</div>
                                </div>
                                <div className="flex items-center gap-6">
                                    <div className="text-right">
                                        <div className="text-sm font-black text-gray-900">${Number(item.price).toFixed(2)}</div>
                                        <div className="text-[10px] text-gray-500 font-black uppercase tracking-tighter">x {item.quantity}</div>
                                    </div>
                                    {mode === 'edit' && (
                                        <div className="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <input
                                                type="number"
                                                value={item.quantity}
                                                onChange={e => updateItemQty(item.sku, parseInt(e.target.value))}
                                                className="w-14 p-1 border rounded text-xs text-center font-bold"
                                            />
                                            <button
                                                onClick={() => removeItem(item.sku)}
                                                className="admin-action-btn btn-icon--delete"
                                                data-help-id="orders-action-remove-item"
                                            />
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <ItemDetailsModal
                sku={previewSku}
                isOpen={isPreviewOpen}
                onClose={closeItemPreview}
                nested
            />
        </>
    );
};
