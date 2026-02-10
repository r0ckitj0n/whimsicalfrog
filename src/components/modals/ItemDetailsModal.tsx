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
    const [isMobileLayout, setIsMobileLayout] = useState(() =>
        typeof window !== 'undefined' ? window.matchMedia('(max-width: 768px)').matches : false
    );

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

    useEffect(() => {
        if (typeof window === 'undefined') return;
        const mediaQuery = window.matchMedia('(max-width: 768px)');
        const updateLayoutMode = () => setIsMobileLayout(mediaQuery.matches);
        updateLayoutMode();
        mediaQuery.addEventListener('change', updateLayoutMode);
        return () => mediaQuery.removeEventListener('change', updateLayoutMode);
    }, []);

    if (!isOpen) return null;

    const modalContent = (
        <div
            className={isMobileLayout
                ? 'wf-modal-overlay show detailed-item-modal fixed inset-0 z-[var(--wf-z-modal)] box-border flex items-end justify-center bg-black/80 p-0 font-[Nunito,sans-serif] backdrop-blur-md sm:items-center sm:p-4 lg:p-8'
                : 'wf-modal-overlay show detailed-item-modal'}
            style={isMobileLayout ? undefined : {
                position: 'fixed',
                inset: 0,
                width: '100vw',
                height: '100vh',
                padding: '2.5vh 2.5vw',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                backgroundColor: 'rgba(0, 0, 0, 0.85)',
                backdropFilter: 'blur(15px)',
                zIndex: 'var(--wf-z-modal)',
                fontFamily: "'Nunito', sans-serif",
                boxSizing: 'border-box'
            }}
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <style dangerouslySetInnerHTML={{
                __html: `
                .detailed-item-modal-card .content-scroller::-webkit-scrollbar { width: 10px !important; height: 10px !important; display: block !important; }
                .detailed-item-modal-card .content-scroller::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.05) !important; border-radius: 999px !important; }
                .detailed-item-modal-card .content-scroller::-webkit-scrollbar-thumb { background: var(--brand-primary) !important; border-radius: 999px !important; border: 2px solid transparent !important; background-clip: content-box !important; min-height: 36px !important; }
                .detailed-item-modal-card .content-scroller::-webkit-scrollbar-thumb:hover { background: var(--brand-secondary) !important; }
                .detailed-item-modal-card .content-scroller { scrollbar-width: auto !important; scrollbar-color: var(--brand-primary) rgba(0, 0, 0, 0.05) !important; overflow-y: auto !important; overflow-x: hidden !important; }
                .detailed-item-modal-card .image-column, .detailed-item-modal-card .details-column { overflow: visible !important; height: auto !important; }
            ` }} />
            <div
                className={isMobileLayout
                    ? 'wf-modal-card detailed-item-modal-card animate-in zoom-in-95 duration-300 relative flex h-[100dvh] max-h-[100dvh] w-full flex-col overflow-hidden border border-slate-200 bg-white text-slate-900 shadow-2xl sm:h-[92dvh] sm:max-h-[92dvh] sm:max-w-[1100px] sm:rounded-3xl'
                    : 'wf-modal-card detailed-item-modal-card animate-in zoom-in-95 duration-300'}
                style={isMobileLayout ? undefined : {
                    width: '100%',
                    maxWidth: '1100px',
                    height: '95vh',
                    maxHeight: '95vh',
                    backgroundColor: '#ffffff',
                    borderRadius: '32px',
                    position: 'relative',
                    display: 'flex',
                    flexDirection: 'column',
                    overflow: 'hidden',
                    boxShadow: '0 30px 60px -12px rgba(0, 0, 0, 0.6)',
                    color: '#111827',
                    boxSizing: 'border-box',
                    border: '1px solid #eee'
                }}
                onClick={e => e.stopPropagation()}
            >
                <ItemDetailsHeader title={item?.name || 'Item Details'} onClose={onClose} isMobileLayout={isMobileLayout} />

                <div className="flex-1 content-scroller">
                    {isLoading && !item ? (
                        <ItemDetailsLoading />
                    ) : item ? (
                        <div
                            className={isMobileLayout ? 'modal-content-wrapper flex min-h-full w-full flex-col md:flex-row' : 'modal-content-wrapper'}
                            style={isMobileLayout ? undefined : { display: 'flex', flexDirection: 'row', width: '100%', minHeight: '100%' }}
                        >
                            <ImageColumn item={item} uniqueImages={uniqueImages} isMobileLayout={isMobileLayout} />
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
                                isMobileLayout={isMobileLayout}
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
