import React from 'react';
import { OptionSelect } from './OptionSelect.js';

interface Size {
    code: string;
    name: string;
    stock: number;
    priceAdj: number;
}

interface SizeSelectionProps {
    availableSizes: Size[];
    selectedSize: string;
    onSelect: (sizeCode: string) => void;
    isMobileLayout?: boolean;
}

/**
 * SizeSelection v1.1.0
 */
export const SizeSelection: React.FC<SizeSelectionProps> = ({
    availableSizes,
    selectedSize,
    onSelect,
    isMobileLayout = false,
}) => {
    if (availableSizes.length === 0) return null;

    return (
        <div className={isMobileLayout ? 'w-full' : 'w-full'} style={!isMobileLayout ? { marginBottom: '24px' } : undefined}>
            <OptionSelect
                label="Select Size"
                value={selectedSize}
                options={availableSizes.map((s) => ({
                    value: s.code,
                    label: s.code,
                    subLabel: s.name ? s.name : undefined,
                    disabled: s.stock <= 0,
                }))}
                placeholder="Choose a size…"
                searchPlaceholder="Search sizes…"
                onChange={onSelect}
            />
        </div>
    );
};
