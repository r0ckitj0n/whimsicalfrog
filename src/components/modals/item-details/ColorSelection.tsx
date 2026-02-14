import React from 'react';
import { OptionSelect } from './OptionSelect.js';

interface Color {
    id: string;
    name: string;
    code: string;
}

interface ColorSelectionProps {
    availableColors: Color[];
    selectedColor: string;
    onSelect: (color_id: string) => void;
    isMobileLayout?: boolean;
}

/**
 * ColorSelection v1.1.0
 */
export const ColorSelection: React.FC<ColorSelectionProps> = ({
    availableColors,
    selectedColor,
    onSelect,
    isMobileLayout = false,
}) => {
    if (availableColors.length === 0) return null;

    return (
        <div className={isMobileLayout ? 'w-full' : 'w-full'} style={!isMobileLayout ? { marginBottom: '24px' } : undefined}>
            <OptionSelect
                label="Select Color"
                value={selectedColor}
                options={availableColors.map((c) => ({
                    value: String(c.id),
                    label: c.name,
                    subLabel: c.code ? c.code.toUpperCase() : undefined,
                    swatchHex: c.code || undefined,
                }))}
                placeholder="Choose a color…"
                searchPlaceholder="Search colors…"
                onChange={onSelect}
            />
        </div>
    );
};
