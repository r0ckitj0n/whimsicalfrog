import React from 'react';

interface StyleSelectionProps {
    availableGenders: string[];
    selectedGender: string;
    onSelect: (gender: string) => void;
    isMobileLayout?: boolean;
}

/**
 * StyleSelection v1.1.0
 */
export const StyleSelection: React.FC<StyleSelectionProps> = ({
    availableGenders,
    selectedGender,
    onSelect,
    isMobileLayout = false
}) => {
    if (availableGenders.length === 0) return null;

    if (!isMobileLayout) {
        return (
            <div style={{ marginBottom: '24px', width: '100%' }}>
                <style dangerouslySetInnerHTML={{
                    __html: `
                    .style-btn-v73 {
                        padding: 12px 28px !important;
                        border-radius: 14px !important;
                        font-size: 15px !important;
                        font-weight: 900 !important;
                        border: 3px solid !important;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                        cursor: pointer !important;
                        text-transform: uppercase !important;
                        letter-spacing: 0.05em !important;
                        outline: none !important;
                        background-color: #ffffff !important;
                        color: #4b5563 !important;
                        border-color: #e5e7eb !important;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.05) !important;
                    }
                    .style-btn-v73.active {
                        background-color: var(--brand-primary) !important;
                        color: #ffffff !important;
                        border-color: var(--brand-primary) !important;
                        box-shadow: 0 8px 20px rgba(var(--brand-primary-rgb), 0.3) !important;
                    }
                    .style-btn-v73:hover:not(.active) {
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
                    Select Style
                </label>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '12px' }}>
                    {availableGenders.map((g) => (
                        <button
                            key={g}
                            type="button"
                            onClick={() => onSelect(g)}
                            className={`style-btn-v73 ${selectedGender === g ? 'active' : ''}`}
                        >
                            {g}
                        </button>
                    ))}
                </div>
            </div>
        );
    }

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
