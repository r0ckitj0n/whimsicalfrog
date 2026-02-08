import React from 'react';
import { IBrandingTokens } from '../../../../hooks/admin/useBranding.js';
import { ColorTokenInput } from './ColorTokenInput.js';

interface CorePaletteProps {
    editTokens: Partial<IBrandingTokens>;
    onChange: (key: keyof IBrandingTokens, value: string) => void;
}

export const CorePalette: React.FC<CorePaletteProps> = ({ editTokens, onChange }) => {
    return (
        <section>
            <h3 className="text-sm font-black text-gray-400 uppercase tracking-widest mb-6 flex items-center gap-2">
                Core Brand Palette
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <ColorTokenInput label="Primary Brand" token="business_brand_primary" editTokens={editTokens} onChange={onChange} />
                <ColorTokenInput label="Secondary Brand" token="business_brand_secondary" editTokens={editTokens} onChange={onChange} />
                <ColorTokenInput label="Accent / Highlight" token="business_brand_accent" editTokens={editTokens} onChange={onChange} />
                <ColorTokenInput label="Main Background" token="business_brand_background" editTokens={editTokens} onChange={onChange} />
                <ColorTokenInput label="Primary Text" token="business_brand_text" editTokens={editTokens} onChange={onChange} />
                <ColorTokenInput label="Toast / Alert Text" token="business_toast_text" editTokens={editTokens} onChange={onChange} />
            </div>
        </section>
    );
};
