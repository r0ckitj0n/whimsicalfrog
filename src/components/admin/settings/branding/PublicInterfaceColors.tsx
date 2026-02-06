import React from 'react';
import { IBrandingTokens } from '../../../../hooks/admin/useBranding.js';

interface PublicInterfaceColorsProps {
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

export const PublicInterfaceColors: React.FC<PublicInterfaceColorsProps> = ({ editTokens, onChange }) => {
    return (
        <section className="pt-12 border-t">
            <h3 className="text-sm font-black text-gray-400 uppercase tracking-widest mb-6 flex items-center gap-2">
                Public Interface Colors
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <ColorInput label="Header BG" token="business_public_header_bg" editTokens={editTokens} onChange={onChange} />
                <ColorInput label="Header Text" token="business_public_header_text" editTokens={editTokens} onChange={onChange} />
                <ColorInput label="Modal BG" token="business_public_modal_bg" editTokens={editTokens} onChange={onChange} />
                <ColorInput label="Modal Text" token="business_public_modal_text" editTokens={editTokens} onChange={onChange} />
                <ColorInput label="Page BG" token="business_public_page_bg" editTokens={editTokens} onChange={onChange} />
                <ColorInput label="Page Text" token="business_public_page_text" editTokens={editTokens} onChange={onChange} />
            </div>
        </section>
    );
};
