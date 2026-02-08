import React from 'react';
import { IItemDetails } from '../../../hooks/useItemDetails.js';
import { StyleSelection } from './StyleSelection.js';
import { ColorSelection } from './ColorSelection.js';
import { SizeSelection } from './SizeSelection.js';
import { ItemDetailsActions } from './ItemDetailsActions.js';
import { ProductSpecifications } from './ProductSpecifications.js';

interface DetailsColumnProps {
    item: IItemDetails;
    total_price: number;
    maxQty: number;
    quantity: number;
    setQuantity: (qty: number) => void;
    handleAddToCart: () => void;
    selectedGender: string;
    setSelectedGender: (gender: string) => void;
    selectedColor: string;
    setSelectedColor: (color: string) => void;
    selectedSize: string;
    setSelectedSize: (size: string) => void;
    availableGenders: string[];
    availableColors: Array<{ id: string; name: string; code: string }>;
    availableSizes: Array<{ code: string; name: string; stock: number; priceAdj: number }>;
}

export const DetailsColumn: React.FC<DetailsColumnProps> = ({
    item,
    total_price,
    maxQty,
    quantity,
    setQuantity,
    handleAddToCart,
    selectedGender,
    setSelectedGender,
    selectedColor,
    setSelectedColor,
    selectedSize,
    setSelectedSize,
    availableGenders,
    availableColors,
    availableSizes
}) => {
    return (
        <div className="details-column flex w-full flex-col bg-white px-4 pb-4 pt-5 sm:px-6 sm:pb-6 sm:pt-6 md:w-1/2 md:flex-[0_0_50%] md:px-10 md:pb-10">
            <div className="details-block mb-6 sm:mb-8">
                <div className="mb-2 text-[11px] font-black uppercase tracking-[0.22em] text-[var(--brand-primary)] sm:text-xs">
                    {item.category || 'Whimsical Original'}
                </div>
                <h2 className="details-title mb-2 font-merienda text-3xl font-black leading-tight tracking-tight text-slate-900 sm:text-4xl">
                    {item.name}
                </h2>
                <div className="details-price mb-2 text-4xl font-black tracking-tight text-[var(--brand-secondary)] sm:text-[2.75rem]">
                    ${total_price.toFixed(2)}
                </div>
                <div className="details-status text-xs font-bold uppercase tracking-[0.08em] text-slate-400 sm:text-sm">
                    Status:{' '}
                    <span className={maxQty > 0 ? 'text-[var(--brand-primary)]' : 'text-[var(--brand-error)]'}>
                        {maxQty > 0 ? `${maxQty} available now` : 'Sold out'}
                    </span>
                </div>

                {item.description && (
                    <div className="mt-5">
                        <div className="mb-2 text-xs font-black uppercase tracking-[0.06em] text-slate-800">Item Story</div>
                        <div className="details-description whitespace-pre-line rounded-xl border border-slate-200 bg-white p-4 text-sm leading-relaxed text-slate-600 sm:text-[15px]">
                            {item.description}
                        </div>
                    </div>
                )}
            </div>

            <div className="details-block mb-6 sm:mb-8">
                <div className="mb-3 flex items-center gap-2 text-xs font-black uppercase tracking-[0.1em] text-[var(--brand-primary)] sm:text-sm">
                    ★ Why You'll Love This
                </div>
                <div className="details-feature flex w-full items-start gap-3 rounded-2xl border-2 border-[var(--brand-primary)] bg-white p-4 text-sm font-semibold text-[var(--brand-primary)] shadow-[0_4px_12px_rgba(var(--brand-primary-rgb),0.1)] sm:text-base">
                    <span className="btn-icon--check mt-0.5 text-lg sm:text-xl" aria-hidden="true" />
                    <span className="leading-snug sm:leading-relaxed">
                        {item.features ? item.features.split('\n')[0] : "Hand-crafted quality and unique design."}
                    </span>
                </div>
            </div>

            <div className="details-option-groups flex flex-col gap-6 sm:gap-7">
                {availableGenders.length > 0 && (
                    <StyleSelection
                        availableGenders={availableGenders}
                        selectedGender={selectedGender}
                        onSelect={setSelectedGender}
                    />
                )}
                {availableColors.length > 0 && (availableGenders.length === 0 || selectedGender) && (
                    <ColorSelection
                        availableColors={availableColors}
                        selectedColor={selectedColor}
                        onSelect={setSelectedColor}
                    />
                )}
                {availableSizes.length > 0 && (availableColors.length === 0 || selectedColor) && (availableGenders.length === 0 || selectedGender) && (
                    <SizeSelection
                        availableSizes={availableSizes}
                        selectedSize={selectedSize}
                        onSelect={setSelectedSize}
                    />
                )}
            </div>

            <div className="details-actions-wrap sticky bottom-0 z-10 -mx-4 mt-8 border-t border-slate-200 bg-white/95 px-4 pb-4 pt-3 backdrop-blur sm:static sm:mx-0 sm:border-t-0 sm:bg-transparent sm:px-0 sm:pb-0 sm:pt-0 sm:backdrop-blur-none">
                <ItemDetailsActions
                    total_price={total_price}
                    quantity={quantity}
                    onQuantityChange={setQuantity}
                    onAddToCart={handleAddToCart}
                    maxQty={maxQty}
                    disabled={
                        (availableGenders.length > 0 && !selectedGender) ||
                        (availableColors.length > 0 && !selectedColor) ||
                        (availableSizes.length > 0 && !selectedSize)
                    }
                    buttonText={item.button_text}
                />
            </div>

            <ProductSpecifications item={item} />
        </div>
    );
};
