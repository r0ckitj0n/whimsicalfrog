import React from 'react';

interface Size {
    code: string;
    name: string;
    stock: number;
    priceAdj: number;
}

interface SizeSelectionProps {
    availableSizes: Size[];
    selectedSize: string;
    onSelect: (sizeCode: string) => void;
}

/**
 * SizeSelection v1.1.0
 */
export const SizeSelection: React.FC<SizeSelectionProps> = ({
    availableSizes,
    selectedSize,
    onSelect
}) => {
    if (availableSizes.length === 0) return null;

    return (
        <div className="w-full">
            <label className="mb-3 block text-[11px] font-black uppercase tracking-[0.12em] text-slate-500 sm:text-xs">
                Select Size
            </label>
            <div className="flex flex-wrap gap-2.5 sm:gap-3">
                {availableSizes.map((s) => {
                    const isActive = selectedSize === s.code;

                    return (
                        <button
                            key={s.code}
                            type="button"
                            onClick={() => onSelect(s.code)}
                            disabled={s.stock <= 0}
                            className={`min-w-[58px] rounded-xl border-2 px-4 py-2 text-sm font-black uppercase transition sm:min-w-[64px] sm:px-5 sm:py-2.5 ${
                                isActive
                                    ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)] text-white shadow-[0_8px_20px_rgba(var(--brand-primary-rgb),0.3)]'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)]'
                            } disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400 disabled:shadow-none`}
                        >
                            {s.code}
                        </button>
                    );
                })}
            </div>
        </div>
    );
};
