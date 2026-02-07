import React from 'react';
import { AI_TIER } from '../../../core/constants.js';

interface QualityTierControlProps {
    value: string;
    onChange: (tier: string) => void;
    disabled?: boolean;
}

export const QualityTierControl: React.FC<QualityTierControlProps> = ({
    value,
    onChange,
    disabled = false
}) => {
    return (
        <div>
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                disabled={disabled}
                aria-label="Quality Positioning"
                className="w-full text-sm p-2 border border-gray-300 rounded bg-white shadow-sm focus:ring-2 focus:ring-[var(--brand-primary)] outline-none"
            >
                <option value={AI_TIER.PREMIUM}>Premium (High Quality / +15%)</option>
                <option value={AI_TIER.STANDARD}>Standard (Market Average)</option>
                <option value={AI_TIER.CONSERVATIVE}>Conservative (Economy / -15%)</option>
            </select>
        </div>
    );
};
