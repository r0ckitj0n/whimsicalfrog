import React from 'react';
import { OptionSelect } from './OptionSelect.js';

interface StyleSelectionProps {
    availableGenders: string[];
    selectedGender: string;
    onSelect: (gender: string) => void;
    isMobileLayout?: boolean;
}

/**
 * StyleSelection v1.1.0
 */
export const StyleSelection: React.FC<StyleSelectionProps> = ({
    availableGenders,
    selectedGender,
    onSelect,
    isMobileLayout = false,
}) => {
    if (availableGenders.length === 0) return null;

    return (
        <div className={isMobileLayout ? 'w-full' : 'w-full'} style={!isMobileLayout ? { marginBottom: '24px' } : undefined}>
            <OptionSelect
                label="Select Style"
                value={selectedGender}
                options={availableGenders.map((g) => ({ value: g, label: g }))}
                placeholder="Choose a style…"
                searchPlaceholder="Search styles…"
                onChange={onSelect}
            />
        </div>
    );
};
