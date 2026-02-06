import React from 'react';
import { IItemDetails, IItemOption } from '../../../hooks/useItemDetails.js';
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
        <div className="details-column" style={{
            width: '50%',
            flex: '0 0 50%',
            padding: '60px',
            display: 'flex',
            flexDirection: 'column',
            background: 'white'
        }}>
            <div style={{ marginBottom: '40px' }}>
                <div style={{ fontSize: '12px', fontWeight: '900', color: 'var(--brand-primary)', textTransform: 'uppercase', letterSpacing: '0.25em', marginBottom: '12px' }}>
                    {item.category || 'Whimsical Original'}
                </div>
                <h2 style={{ fontSize: '36px', fontWeight: '900', color: '#111827', marginBottom: '8px', lineHeight: '1.1', fontFamily: "'Merienda', cursive", letterSpacing: '-0.02em' }}>
                    {item.name}
                </h2>
                <div style={{ color: 'var(--brand-secondary)', fontSize: '42px', fontWeight: '900', letterSpacing: '-0.01em', marginBottom: '12px' }}>
                    ${total_price.toFixed(2)}
                </div>
                <div style={{ fontSize: '15px', color: '#9ca3af', fontStyle: 'italic', fontWeight: 'bold', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                    Status: <span style={{ color: maxQty > 0 ? 'var(--brand-primary)' : 'var(--brand-error)' }}>{maxQty > 0 ? `${maxQty} available now` : 'Sold out'}</span>
                </div>

                {item.description && (
                    <div style={{ marginTop: '24px' }}>
                        <div style={{ fontWeight: '900', color: '#111827', marginBottom: '8px', textTransform: 'uppercase', letterSpacing: '0.05em', fontSize: '12px' }}>Item Story</div>
                        <div style={{ lineHeight: '1.7', color: '#4b5563', background: '#ffffff', padding: '16px 20px', borderRadius: '12px', border: '1px solid #eee', whiteSpace: 'pre-line' }}>
                            {item.description}
                        </div>
                    </div>
                )}
            </div>

            <div style={{ marginBottom: '40px' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: 'var(--brand-primary)', fontWeight: '900', fontSize: '14px', marginBottom: '16px', textTransform: 'uppercase', letterSpacing: '0.1em' }}>
                    ★ Why You'll Love This
                </div>
                <div style={{
                    backgroundColor: '#ffffff',
                    color: 'var(--brand-primary)',
                    padding: '16px 28px',
                    borderRadius: '16px',
                    fontSize: '16px',
                    fontWeight: 'bold',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '16px',
                    width: 'fit-content',
                    border: '2px solid var(--brand-primary)',
                    boxShadow: '0 4px 12px rgba(var(--brand-primary-rgb), 0.1)'
                }}>
                    <span className="btn-icon--check" style={{ fontSize: '20px' }} aria-hidden="true" />
                    <span style={{ lineHeight: '1.4' }}>
                        {item.features ? item.features.split('\n')[0] : "Hand-crafted quality and unique design."}
                    </span>
                </div>
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '40px' }}>
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

            <div style={{ marginTop: '60px', paddingTop: '40px', borderTop: '2px solid #f9fafb' }}>
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
