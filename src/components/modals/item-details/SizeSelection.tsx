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
    isMobileLayout?: boolean;
}

/**
 * SizeSelection v1.1.0
 */
export const SizeSelection: React.FC<SizeSelectionProps> = ({
    availableSizes,
    selectedSize,
    onSelect,
    isMobileLayout = false
}) => {
    if (availableSizes.length === 0) return null;

    if (!isMobileLayout) {
        return (
            <div style={{ marginBottom: '24px', width: '100%' }}>
                <style dangerouslySetInnerHTML={{
                    __html: `
                    .size-btn-v73 {
                        min-width: 64px !important;
                        height: 48px !important;
                        padding: 0 20px !important;
                        border-radius: 14px !important;
                        font-size: 15px !important;
                        font-weight: 900 !important;
                        border: 3px solid !important;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                        cursor: pointer !important;
                        display: flex !important;
                        align-items: center !important;
                        justify-content: center !important;
                        text-transform: uppercase !important;
                        outline: none !important;
                        background-color: #ffffff !important;
                        color: #4b5563 !important;
                        border-color: #e5e7eb !important;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.05) !important;
                    }
                    .size-btn-v73.active {
                        background-color: var(--brand-primary) !important;
                        color: #ffffff !important;
                        border-color: var(--brand-primary) !important;
                        box-shadow: 0 8px 20px rgba(var(--brand-primary-rgb), 0.3) !important;
                    }
                    .size-btn-v73:disabled {
                        opacity: 0.4 !important;
                        cursor: not-allowed !important;
                        background-color: #f3f4f6 !important;
                        border-color: #e5e7eb !important;
                        color: #9ca3af !important;
                        box-shadow: none !important;
                    }
                    .size-btn-v73:hover:not(.active):not(:disabled) {
                        border-color: var(--brand-primary) !important;
                        color: var(--brand-primary) !important;
                    }
                ` }} />
                <label style={{
                    fontSize: '12px',
                    fontWeight: 900,
                    color: '#6b7280',
                    textTransform: 'uppercase',
                    letterSpacing: '0.15em',
                    marginBottom: '16px',
                    display: 'block'
                }}>
                    Select Size
                </label>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '12px' }}>
                    {availableSizes.map((s) => (
                        <button
                            key={s.code}
                            type="button"
                            onClick={() => onSelect(s.code)}
                            disabled={s.stock <= 0}
                            className={`size-btn-v73 ${selectedSize === s.code ? 'active' : ''}`}
                        >
                            {s.code}
                        </button>
                    ))}
                </div>
            </div>
        );
    }

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
