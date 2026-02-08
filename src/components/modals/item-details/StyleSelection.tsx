import React from 'react';

interface StyleSelectionProps {
    availableGenders: string[];
    selectedGender: string;
    onSelect: (gender: string) => void;
}

/**
 * StyleSelection v1.1.0
 */
export const StyleSelection: React.FC<StyleSelectionProps> = ({
    availableGenders,
    selectedGender,
    onSelect
}) => {
    if (availableGenders.length === 0) return null;

    return (
        <div className="w-full">
            <label className="mb-3 block text-[11px] font-black uppercase tracking-[0.12em] text-slate-500 sm:text-xs">
                Select Style
            </label>
            <div className="flex flex-wrap gap-2.5 sm:gap-3">
                {availableGenders.map((g) => {
                    const isActive = selectedGender === g;
                    return (
                        <button
                            key={g}
                            type="button"
                            onClick={() => onSelect(g)}
                            className={`rounded-xl border-2 px-4 py-2 text-sm font-black uppercase tracking-[0.04em] transition sm:px-5 sm:py-2.5 ${
                                isActive
                                    ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)] text-white shadow-[0_8px_20px_rgba(var(--brand-primary-rgb),0.3)]'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)]'
                            }`}
                        >
                            {g}
                        </button>
                    );
                })}
            </div>
        </div>
    );
};
