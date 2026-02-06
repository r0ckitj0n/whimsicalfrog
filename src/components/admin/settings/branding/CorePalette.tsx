import React from 'react';
import { IBrandingTokens } from '../../../../hooks/admin/useBranding.js';

interface CorePaletteProps {
    editTokens: Partial<IBrandingTokens>;
    onChange: (key: keyof IBrandingTokens, value: string) => void;
}

const ColorInput = ({ label, token, editTokens, onChange }: { 
    label: string, 
    token: keyof IBrandingTokens, 
    editTokens: Partial<IBrandingTokens>, 
    onChange: (key: keyof IBrandingTokens, value: string) => void 
}) => (
    <div className="space-y-2">
        <label className="text-xs font-bold text-gray-500 uppercase tracking-wider">{label}</label>
        <div className="flex gap-2">
            <div 
                className="w-10 h-10 rounded-lg border shadow-sm shrink-0 wf-color-preview-box" 
                style={{ '--preview-bg': (editTokens[token] as string) || '#ffffff' } as React.CSSProperties}
            />
            <input 
                type="text" 
                value={(editTokens[token] as string) || ''}
                onChange={(e) => onChange(token, e.target.value)}
                className="form-input font-mono text-sm flex-1"
                placeholder="#HEXCOLOR"
            />
        </div>
    </div>
);

export const CorePalette: React.FC<CorePaletteProps> = ({ editTokens, onChange }) => {
    return (
        <section>
            <h3 className="text-sm font-black text-gray-400 uppercase tracking-widest mb-6 flex items-center gap-2">
                Core Brand Palette
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <ColorInput label="Primary Brand" token="business_brand_primary" editTokens={editTokens} onChange={onChange} />
                <ColorInput label="Secondary Brand" token="business_brand_secondary" editTokens={editTokens} onChange={onChange} />
                <ColorInput label="Accent / Highlight" token="business_brand_accent" editTokens={editTokens} onChange={onChange} />
                <ColorInput label="Main Background" token="business_brand_background" editTokens={editTokens} onChange={onChange} />
                <ColorInput label="Primary Text" token="business_brand_text" editTokens={editTokens} onChange={onChange} />
                <ColorInput label="Toast / Alert Text" token="business_toast_text" editTokens={editTokens} onChange={onChange} />
            </div>
        </section>
    );
};
