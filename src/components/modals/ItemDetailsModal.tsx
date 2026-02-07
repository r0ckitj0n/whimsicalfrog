import React, { useState, useMemo, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useItemDetails } from '../../hooks/useItemDetails.js';
import { useItemOptions } from '../../hooks/useItemOptions.js';
import { ImageColumn } from './item-details/ImageColumn.js';
import { DetailsColumn } from './item-details/DetailsColumn.js';
import { ItemDetailsHeader } from './item-details/ItemDetailsHeader.js';
import { ItemDetailsLoading } from './item-details/ItemDetailsLoading.js';

interface ItemDetailsModalProps {
    sku: string;
    isOpen: boolean;
    onClose: () => void;
}

/**
 * ItemDetailsModal v1.3.0
 * Refactored into sub-components to satisfy the <250 line rule.
 */
export const ItemDetailsModal: React.FC<ItemDetailsModalProps> = ({ sku, isOpen, onClose }) => {
    const { item, options, images, isLoading, addToCart } = useItemDetails(sku);
    const {
        selectedGender, setSelectedGender,
        selectedColor, setSelectedColor,
        selectedSize, setSelectedSize,
        availableGenders,
        availableColors,
        availableSizes,
        currentVariant
    } = useItemOptions(item, options);

    const [quantity, setQuantity] = useState(1);

    const total_price = useMemo(() => {
        if (!item) return 0;
        return Number(item.price || item.retail_price || 0) + Number(currentVariant?.price_adjustment || 0);
    }, [item, currentVariant]);

    const maxQty = useMemo(() => {
        if (!item) return 0;
        if (options.length > 0) {
            if (currentVariant && selectedSize && (availableColors.length === 0 || selectedColor)) {
                return currentVariant.stock_level;
            }
            return item.total_stock ?? item.stock_quantity ?? 0;
        }
        return item.total_stock ?? item.stock_quantity ?? 0;
    }, [item, options, currentVariant, selectedSize, selectedColor, availableColors]);

    const handleAddToCart = () => {
        if (!item) return;
        addToCart(quantity, {
            gender: selectedGender,
            color_id: selectedColor,
            size_code: selectedSize,
            price_adjustment: currentVariant?.price_adjustment
        });
        onClose();
    };

    const uniqueImages = useMemo(() => {
        const allPaths: string[] = [];
        if (item?.image) allPaths.push(item.image);
        if (images && images.length > 0) {
            images.forEach(img => {
                if (img.image_path) allPaths.push(img.image_path);
            });
        }
        const seen = new Set();
        return allPaths.filter(path => {
            if (!path) return false;
            const cleanPath = path.replace(/^\.\//, '/').replace(/^\.\.\//, '/').replace(/\/+/g, '/');
            if (!seen.has(cleanPath)) {
                seen.add(cleanPath);
                return true;
            }
            return false;
        });
    }, [images, item]);

    useEffect(() => {
        if (isOpen) {
            document.body.classList.add('detailed-item-modal-open');
        } else {
            document.body.classList.remove('detailed-item-modal-open');
        }
        return () => {
            document.body.classList.remove('detailed-item-modal-open');
        };
    }, [isOpen]);

    if (!isOpen) return null;

    const modalContent = (
        <div
            className="wf-modal-overlay show detailed-item-modal"
            style={{
                position: 'fixed', inset: 0, width: '100vw', height: '100vh',
                padding: '2.5vh 2.5vw', display: 'flex', alignItems: 'center',
                justifyContent: 'center', backgroundColor: 'rgba(0, 0, 0, 0.85)',
                backdropFilter: 'blur(15px)', zIndex: 'var(--wf-z-modal)',
                fontFamily: "'Nunito', sans-serif", boxSizing: 'border-box'
            }}
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <style dangerouslySetInnerHTML={{
                __html: `
                .detailed-item-modal-card .content-scroller::-webkit-scrollbar { width: 12px !important; height: 12px !important; display: block !important; }
                .detailed-item-modal-card .content-scroller::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.05) !important; border-radius: 10px !important; }
                .detailed-item-modal-card .content-scroller::-webkit-scrollbar-thumb { background: var(--brand-primary) !important; border-radius: 10px !important; border: 3px solid transparent !important; background-clip: content-box !important; min-height: 40px !important; }
                .detailed-item-modal-card .content-scroller::-webkit-scrollbar-thumb:hover { background: var(--brand-secondary) !important; }
                .detailed-item-modal-card .content-scroller { scrollbar-width: auto !important; scrollbar-color: var(--brand-primary) rgba(0, 0, 0, 0.05) !important; overflow-y: auto !important; overflow-x: hidden !important; }
                .detailed-item-modal-card .image-column, .detailed-item-modal-card .details-column { overflow: visible !important; height: auto !important; }
                @media (max-width: 1024px) {
                    .detailed-item-modal {
                        padding: 16px !important;
                    }
                    .detailed-item-modal-card {
                        max-height: calc(100vh - 32px) !important;
                    }
                    .modal-content-wrapper {
                        flex-direction: column !important;
                    }
                    .detailed-item-modal-card .image-column,
                    .detailed-item-modal-card .details-column {
                        width: 100% !important;
                        flex: 1 1 auto !important;
                    }
                    .detailed-item-modal-card .image-column {
                        border-right: 0 !important;
                        border-bottom: 1px solid #f0f0f0 !important;
                    }
                }
                @media (max-width: 768px) {
                    .detailed-item-modal {
                        align-items: flex-end !important;
                        padding: 0 !important;
                    }
                    .detailed-item-modal-card {
                        width: 100% !important;
                        height: 100vh !important;
                        max-height: 100vh !important;
                        border-radius: 20px 20px 0 0 !important;
                        border-left: 0 !important;
                        border-right: 0 !important;
                        border-bottom: 0 !important;
                    }
                    .detailed-item-modal-card .wf-modal-header {
                        padding: 12px 14px !important;
                    }
                    .detailed-item-modal-card .wf-modal-header .admin-card-title {
                        font-size: 1.05rem !important;
                        line-height: 1.25 !important;
                    }
                    .detailed-item-modal-card .wf-modal-header .btn-icon--shopping-bag {
                        font-size: 18px !important;
                    }
                    .detailed-item-modal-card .image-column {
                        padding: 14px !important;
                        border-bottom: 1px solid #f0f0f0 !important;
                    }
                    .detailed-item-modal-card .image-column .image-column-inner {
                        gap: 14px !important;
                    }
                    .detailed-item-modal-card .image-column .image-slot-card {
                        height: 260px !important;
                        border-radius: 16px !important;
                    }
                    .detailed-item-modal-card .image-column .image-slot-carousel {
                        height: 220px !important;
                    }
                    .detailed-item-modal-card .details-column {
                        padding: 18px 14px 24px !important;
                    }
                    .detailed-item-modal-card .details-column .details-block {
                        margin-bottom: 20px !important;
                    }
                    .detailed-item-modal-card .details-column .details-title {
                        font-size: 1.7rem !important;
                        line-height: 1.15 !important;
                    }
                    .detailed-item-modal-card .details-column .details-price {
                        font-size: 2rem !important;
                    }
                    .detailed-item-modal-card .details-column .details-status {
                        font-size: 0.75rem !important;
                    }
                    .detailed-item-modal-card .details-column .details-description {
                        padding: 12px 14px !important;
                        font-size: 0.95rem !important;
                        line-height: 1.55 !important;
                    }
                    .detailed-item-modal-card .details-column .details-feature {
                        width: 100% !important;
                        padding: 12px 14px !important;
                        font-size: 0.95rem !important;
                    }
                    .detailed-item-modal-card .details-column .details-option-groups {
                        gap: 20px !important;
                    }
                    .detailed-item-modal-card .details-column .details-actions-wrap {
                        margin-top: 22px !important;
                        padding-top: 20px !important;
                    }
                    .detailed-item-modal-card .details-column .item-actions-row-v73 {
                        flex-direction: column !important;
                        align-items: stretch !important;
                        gap: 12px !important;
                    }
                    .detailed-item-modal-card .details-column .qty-selector-v73 {
                        width: 100% !important;
                        justify-content: space-between !important;
                    }
                    .detailed-item-modal-card .details-column .specs-toggle-btn {
                        padding: 14px 16px !important;
                        font-size: 0.95rem !important;
                    }
                    .detailed-item-modal-card .details-column .specs-panel {
                        padding: 16px !important;
                    }
                    .detailed-item-modal-card .details-column .specs-grid {
                        grid-template-columns: 1fr !important;
                        gap: 16px !important;
                    }
                }
            ` }} />
            <div
                className="wf-modal-card detailed-item-modal-card animate-in zoom-in-95 duration-300"
                style={{
                    width: '100%', maxWidth: '1100px', height: '95vh', maxHeight: '95vh',
                    backgroundColor: '#ffffff', borderRadius: '32px', position: 'relative',
                    display: 'flex', flexDirection: 'column', overflow: 'hidden',
                    boxShadow: '0 30px 60px -12px rgba(0, 0, 0, 0.6)', color: '#111827',
                    boxSizing: 'border-box', border: '1px solid #eee'
                }}
                onClick={e => e.stopPropagation()}
            >
                <ItemDetailsHeader title={item?.name || 'Item Details'} onClose={onClose} />

                <div className="flex-1 content-scroller">
                    {isLoading && !item ? (
                        <ItemDetailsLoading />
                    ) : item ? (
                        <div className="modal-content-wrapper" style={{ display: 'flex', flexDirection: 'row', width: '100%', minHeight: '100%' }}>
                            <ImageColumn item={item} uniqueImages={uniqueImages} />
                            <DetailsColumn
                                item={item}
                                total_price={total_price}
                                maxQty={maxQty}
                                quantity={quantity}
                                setQuantity={setQuantity}
                                handleAddToCart={handleAddToCart}
                                selectedGender={selectedGender}
                                setSelectedGender={setSelectedGender}
                                selectedColor={selectedColor}
                                setSelectedColor={setSelectedColor}
                                selectedSize={selectedSize}
                                setSelectedSize={setSelectedSize}
                                availableGenders={availableGenders}
                                availableColors={availableColors}
                                availableSizes={availableSizes}
                            />
                        </div>
                    ) : null}
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};

export default ItemDetailsModal;
