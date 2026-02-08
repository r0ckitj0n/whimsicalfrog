import React, { useMemo, useRef } from 'react';
import { IBrandingTokens } from '../../../../hooks/admin/useBranding.js';

interface ColorTokenInputProps {
    label: string;
    token: keyof IBrandingTokens;
    editTokens: Partial<IBrandingTokens>;
    onChange: (key: keyof IBrandingTokens, value: string) => void;
}

const isValidHex = (value: string): boolean =>
    /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(value.trim());

export const ColorTokenInput: React.FC<ColorTokenInputProps> = ({
    label,
    token,
    editTokens,
    onChange
}) => {
    const pickerRef = useRef<HTMLInputElement>(null);
    const rawValue = ((editTokens[token] as string) || '').trim();
    const isTransparent = rawValue === '' || rawValue.toLowerCase() === 'transparent';

    const pickerValue = useMemo(() => {
        if (isValidHex(rawValue)) {
            const normalized = rawValue.toLowerCase();
            if (normalized.length === 4) {
                return `#${normalized[1]}${normalized[1]}${normalized[2]}${normalized[2]}${normalized[3]}${normalized[3]}`;
            }
            return normalized;
        }
        return '#ffffff';
    }, [rawValue]);

    return (
        <div className="space-y-2">
            <label className="text-xs font-bold text-gray-500 uppercase tracking-wider">{label}</label>
            <div className="flex gap-2">
                <button
                    type="button"
                    onClick={() => pickerRef.current?.click()}
                    className="w-10 h-10 rounded-lg border border-gray-400 shadow-sm shrink-0 relative overflow-hidden"
                    title={isTransparent ? 'Transparent - click to pick color' : 'Click to pick color'}
                    aria-label={`${label} color swatch`}
                    style={
                        isTransparent
                            ? {
                                backgroundColor: '#ffffff',
                                backgroundImage:
                                    'linear-gradient(45deg, #d1d5db 25%, transparent 25%), linear-gradient(-45deg, #d1d5db 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #d1d5db 75%), linear-gradient(-45deg, transparent 75%, #d1d5db 75%)',
                                backgroundSize: '12px 12px',
                                backgroundPosition: '0 0, 0 6px, 6px -6px, -6px 0'
                            }
                            : { backgroundColor: rawValue }
                    }
                />
                <input
                    ref={pickerRef}
                    type="color"
                    value={pickerValue}
                    onChange={(e) => onChange(token, e.target.value)}
                    className="sr-only"
                    tabIndex={-1}
                    aria-hidden="true"
                />
                <input
                    type="text"
                    value={(editTokens[token] as string) || ''}
                    onChange={(e) => {
                        const value = e.target.value;
                        if (value.trim().toLowerCase() === 'transparent') {
                            onChange(token, '');
                            return;
                        }
                        onChange(token, value);
                    }}
                    className="form-input font-mono text-sm flex-1"
                    placeholder="#HEXCOLOR"
                />
                <button
                    type="button"
                    onClick={() => onChange(token, '')}
                    className={`px-3 rounded-lg border text-[10px] font-bold uppercase tracking-wide transition-colors ${
                        isTransparent
                            ? 'bg-gray-800 text-white border-gray-800'
                            : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'
                    }`}
                    title="Set transparent"
                    aria-label={`Set ${label} to transparent`}
                >
                    Clear
                </button>
            </div>
        </div>
    );
};
