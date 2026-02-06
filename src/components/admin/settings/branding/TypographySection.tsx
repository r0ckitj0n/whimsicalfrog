import React from 'react';
import { IBrandingTokens } from '../../../../hooks/admin/useBranding.js';

interface TypographySectionProps {
    editTokens: Partial<IBrandingTokens>;
    onOpenPicker: (target: 'primary' | 'secondary' | 'title-primary' | 'title-secondary') => void;
}

export const TypographySection: React.FC<TypographySectionProps> = ({
    editTokens,
    onOpenPicker
}) => {
    return (
        <div className="space-y-12 animate-in fade-in slide-in-from-bottom-2">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <label className="text-xs font-black text-gray-400 uppercase tracking-widest px-1">Primary Font (Headings)</label>
                        <button
                            onClick={() => onOpenPicker('primary')}
                            className="text-[10px] font-black text-[var(--brand-primary)] uppercase tracking-widest hover:underline"
                        >
                            Change Font
                        </button>
                    </div>
                    <div className="p-8 rounded-[2rem] border-2 border-dashed border-gray-100 bg-gray-50/50 text-center space-y-4">
                        <h1
                            style={{ '--primary-font': editTokens.business_brand_font_primary } as React.CSSProperties}
                            className="text-4xl font-black text-gray-900 tracking-tight wf-preview-font-primary"
                        >
                            Whimsical Frog
                        </h1>
                        <p className="text-[10px] font-mono text-gray-400 uppercase tracking-widest">
                            Stack: {editTokens.business_brand_font_primary}
                        </p>
                    </div>
                </div>

                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <label className="text-xs font-black text-gray-400 uppercase tracking-widest px-1">Secondary Font (Body)</label>
                        <button
                            onClick={() => onOpenPicker('secondary')}
                            className="text-[10px] font-black text-[var(--brand-primary)] uppercase tracking-widest hover:underline"
                        >
                            Change Font
                        </button>
                    </div>
                    <div className="p-8 rounded-[2rem] border-2 border-dashed border-gray-100 bg-gray-50/50 space-y-4">
                        <p
                            style={{ '--secondary-font': editTokens.business_brand_font_secondary } as React.CSSProperties}
                            className="text-base leading-relaxed text-gray-700 italic wf-preview-font-secondary"
                        >
                            "In the heart of the forest, near the sparkling stream, lived a frog with a penchant for the extraordinary."
                        </p>
                        <p className="text-[10px] font-mono text-gray-400 uppercase tracking-widest text-center">
                            Stack: {editTokens.business_brand_font_secondary}
                        </p>
                    </div>
                </div>
            </div>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 pt-12 border-t border-gray-100">
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <label className="text-xs font-black text-gray-400 uppercase tracking-widest px-1">Primary Title Font</label>
                        <button
                            onClick={() => onOpenPicker('title-primary')}
                            className="text-[10px] font-black text-[var(--brand-primary)] uppercase tracking-widest hover:underline"
                        >
                            Change Font
                        </button>
                    </div>
                    <div className="p-8 rounded-[2rem] border-2 border-dashed border-gray-100 bg-gray-50/50 text-center space-y-4">
                        <h2
                            style={{ fontFamily: editTokens.business_brand_font_title_primary } as React.CSSProperties}
                            className="text-4xl font-black text-gray-900 tracking-tight"
                        >
                            The Whispering Woods
                        </h2>
                        <p className="text-[10px] font-mono text-gray-400 uppercase tracking-widest">
                            Stack: {editTokens.business_brand_font_title_primary}
                        </p>
                    </div>
                </div>

                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <label className="text-xs font-black text-gray-400 uppercase tracking-widest px-1">Secondary Title Font</label>
                        <button
                            onClick={() => onOpenPicker('title-secondary')}
                            className="text-[10px] font-black text-[var(--brand-primary)] uppercase tracking-widest hover:underline"
                        >
                            Change Font
                        </button>
                    </div>
                    <div className="p-8 rounded-[2rem] border-2 border-dashed border-gray-100 bg-gray-50/50 text-center space-y-4">
                        <h3
                            style={{ fontFamily: editTokens.business_brand_font_title_secondary } as React.CSSProperties}
                            className="text-2xl font-bold text-gray-800"
                        >
                            Enchanted Frog Pond
                        </h3>
                        <p className="text-[10px] font-mono text-gray-400 uppercase tracking-widest">
                            Stack: {editTokens.business_brand_font_title_secondary}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
};
