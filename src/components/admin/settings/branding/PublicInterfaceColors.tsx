import React from 'react';
import { IBrandingTokens } from '../../../../hooks/admin/useBranding.js';
import { ColorTokenInput } from './ColorTokenInput.js';

interface PublicInterfaceColorsProps {
    editTokens: Partial<IBrandingTokens>;
    onChange: (key: keyof IBrandingTokens, value: string) => void;
}

export const PublicInterfaceColors: React.FC<PublicInterfaceColorsProps> = ({ editTokens, onChange }) => {
    return (
        <section className="pt-12 border-t">
            <h3 className="text-sm font-black text-gray-400 uppercase tracking-widest mb-6 flex items-center gap-2">
                Public Interface Colors
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <ColorTokenInput label="Header BG" token="business_public_header_bg" editTokens={editTokens} onChange={onChange} />
                <ColorTokenInput label="Header Text" token="business_public_header_text" editTokens={editTokens} onChange={onChange} />
                <ColorTokenInput label="Modal BG" token="business_public_modal_bg" editTokens={editTokens} onChange={onChange} />
                <ColorTokenInput label="Modal Text" token="business_public_modal_text" editTokens={editTokens} onChange={onChange} />
                <ColorTokenInput label="Page BG" token="business_public_page_bg" editTokens={editTokens} onChange={onChange} />
                <ColorTokenInput label="Page Text" token="business_public_page_text" editTokens={editTokens} onChange={onChange} />
            </div>
        </section>
    );
};
