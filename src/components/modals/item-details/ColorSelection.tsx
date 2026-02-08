import React from 'react';

interface Color {
    id: string;
    name: string;
    code: string;
}

interface ColorSelectionProps {
    availableColors: Color[];
    selectedColor: string;
    onSelect: (color_id: string) => void;
}

/**
 * ColorSelection v1.1.0
 */
export const ColorSelection: React.FC<ColorSelectionProps> = ({
    availableColors,
    selectedColor,
    onSelect
}) => {
    if (availableColors.length === 0) return null;

    return (
        <div className="w-full">
            <label className="mb-3 block text-[11px] font-black uppercase tracking-[0.12em] text-slate-500 sm:text-xs">
                Select Color
            </label>
            <div className="flex flex-wrap gap-3">
                {availableColors.map((c) => {
                    const isActive = selectedColor === String(c.id);

                    return (
                        <button
                            key={c.id}
                            type="button"
                            onClick={() => onSelect(String(c.id))}
                            className={`flex h-11 w-11 items-center justify-center rounded-full border-2 bg-white p-1.5 transition sm:h-12 sm:w-12 ${
                                isActive
                                    ? 'border-[var(--brand-primary)] shadow-[0_8px_20px_rgba(var(--brand-primary-rgb),0.35)]'
                                    : 'border-transparent shadow-sm hover:border-slate-300'
                            }`}
                            data-help-id={`color-${c.name.toLowerCase().replace(/\s+/g, '-')}`}
                            aria-label={`Select color ${c.name}`}
                        >
                            <span
                                className="h-full w-full rounded-full border border-black/10"
                                style={{ backgroundColor: c.code || '#ccc' }}
                            />
                        </button>
                    );
                })}
            </div>
        </div>
    );
};
