import React, { useMemo, useState } from 'react';
import { IItemDetails } from '../../../hooks/useItemDetails.js';

interface ProductSpecificationsProps {
    item: IItemDetails;
}

export const ProductSpecifications: React.FC<ProductSpecificationsProps> = ({ item }) => {
    const [isDetailsOpen, setIsDetailsOpen] = useState(false);

    const sections = useMemo(
        () => [
            { label: 'Key Features', content: item.features },
            { label: 'Materials', content: item.materials },
            { label: 'Care Instructions', content: item.care_instructions }
        ].filter((section) => Boolean(section.content)),
        [item.care_instructions, item.features, item.materials]
    );

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
