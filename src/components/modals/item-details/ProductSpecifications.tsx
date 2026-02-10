import React, { useMemo, useState } from 'react';
import { IItemDetails } from '../../../hooks/useItemDetails.js';

interface ProductSpecificationsProps {
    item: IItemDetails;
    isMobileLayout?: boolean;
}

export const ProductSpecifications: React.FC<ProductSpecificationsProps> = ({ item, isMobileLayout = false }) => {
    const [isDetailsOpen, setIsDetailsOpen] = useState(false);

    const sections = useMemo(
        () => [
            { label: 'Key Features', content: item.features },
            { label: 'Materials', content: item.materials },
            { label: 'Care Instructions', content: item.care_instructions }
        ].filter((section) => Boolean(section.content)),
        [item.care_instructions, item.features, item.materials]
    );

    if (!isMobileLayout) {
        return (
            <div style={{ marginTop: '40px' }}>
                <button
                    onClick={() => setIsDetailsOpen(!isDetailsOpen)}
                    style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '20px 28px', border: '2px solid #f3f4f6', borderRadius: '16px', fontSize: '17px', fontWeight: 'bold', color: '#4b5563', background: '#ffffff', cursor: 'pointer', transition: 'all 0.2s', outline: 'none' }}
                    type="button"
                >
                    <span>Product Specifications</span>
                    <span className="btn-icon--down" style={{ transition: 'transform 0.3s', transform: isDetailsOpen ? 'rotate(180deg)' : 'none', fontSize: '16px' }} />
                </button>
                {isDetailsOpen && (
                    <div style={{ marginTop: '16px', padding: '32px', backgroundColor: '#f9fafb', borderRadius: '20px', border: '1px solid #f3f4f6', fontSize: '15px', color: '#4b5563', boxShadow: 'inset 0 2px 8px rgba(0,0,0,0.03)' }}>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '32px', marginBottom: '32px' }}>
                            <div>
                                <div style={{ fontWeight: 900, color: '#111827', marginBottom: '8px', textTransform: 'uppercase', letterSpacing: '0.05em', fontSize: '12px' }}>Reference SKU</div>
                                <div style={{ fontFamily: 'monospace', color: '#111827', fontSize: '14px', background: '#ffffff', padding: '8px 12px', borderRadius: '8px', border: '1px solid #eee' }}>{item.sku}</div>
                            </div>
                            <div>
                                <div style={{ fontWeight: 900, color: '#111827', marginBottom: '8px', textTransform: 'uppercase', letterSpacing: '0.05em', fontSize: '12px' }}>Category</div>
                                <div style={{ color: '#111827', fontSize: '14px', background: '#ffffff', padding: '8px 12px', borderRadius: '8px', border: '1px solid #eee' }}>{item.category || 'Uncategorized'}</div>
                            </div>
                        </div>

                        {sections.map((sec, i) => (
                            <div key={sec.label} style={{ marginBottom: i === sections.length - 1 ? 0 : '24px' }}>
                                <div style={{ fontWeight: 900, color: '#111827', marginBottom: '8px', textTransform: 'uppercase', letterSpacing: '0.05em', fontSize: '12px' }}>{sec.label}</div>
                                <div style={{ lineHeight: '1.7', color: '#4b5563', background: '#ffffff', padding: '16px 20px', borderRadius: '12px', border: '1px solid #eee', whiteSpace: 'pre-line' }}>{sec.content}</div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        );
    }

    return (
        <div className="mt-8 sm:mt-10">
            <button
                onClick={() => setIsDetailsOpen((prev) => !prev)}
                className="specs-toggle-btn flex w-full items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-left text-sm font-bold text-slate-700 transition hover:border-slate-300 sm:px-5 sm:py-4 sm:text-base"
                type="button"
            >
                <span>Product Specifications</span>
                <span
                    className="btn-icon--down text-sm transition"
                    style={{ transform: isDetailsOpen ? 'rotate(180deg)' : 'none' }}
                    aria-hidden="true"
                />
            </button>
            {isDetailsOpen && (
                <div className="specs-panel mt-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 shadow-inner sm:mt-4 sm:p-6">
                    <div className="specs-grid mb-5 grid grid-cols-1 gap-3 sm:mb-6 sm:grid-cols-2 sm:gap-4">
                        <div>
                            <div className="mb-1 text-[11px] font-black uppercase tracking-[0.08em] text-slate-800">Reference SKU</div>
                            <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-900 sm:text-sm">{item.sku}</div>
                        </div>
                        <div>
                            <div className="mb-1 text-[11px] font-black uppercase tracking-[0.08em] text-slate-800">Category</div>
                            <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 sm:text-sm">{item.category || 'Uncategorized'}</div>
                        </div>
                    </div>

                    {sections.map((section) => (
                        <div key={section.label} className="mb-4 last:mb-0">
                            <div className="mb-1 text-[11px] font-black uppercase tracking-[0.08em] text-slate-800">{section.label}</div>
                            <div className="whitespace-pre-line rounded-xl border border-slate-200 bg-white px-3 py-3 leading-relaxed sm:px-4">
                                {section.content}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
};
