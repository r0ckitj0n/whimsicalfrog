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
