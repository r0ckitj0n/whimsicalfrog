import React from 'react';

interface SnapGridSectionProps {
    snapSize: number;
    onSnapSizeChange: (size: number) => void;
}

export const SnapGridSection: React.FC<SnapGridSectionProps> = ({
    snapSize,
    onSnapSizeChange
}) => {
    return (
        <section className="space-y-4">
            <div className="flex items-center justify-between border-b pb-2">
                <div className="flex items-center gap-2 text-xs font-black text-gray-400 uppercase tracking-widest">
                    Snap Grid
                </div>
                <div className="text-[10px] font-bold text-[var(--brand-primary)] uppercase bg-[var(--brand-primary)]/5 px-1.5 py-0.5 rounded">
                    {snapSize}px
                </div>
            </div>
            <div className="grid grid-cols-4 gap-1.5">
                {[0, 5, 10, 20].map(s => (
                    <button 
                        key={s}
                        onClick={() => onSnapSizeChange(s)}
                        className={`py-1 rounded text-[10px] font-black border transition-all ${snapSize === s ? 'bg-[var(--brand-primary)] border-[var(--brand-primary)] text-white shadow-sm' : 'bg-white border-gray-200 text-gray-500 hover:border-[var(--brand-primary)]/30'}`}
                    >
                        {s === 0 ? 'OFF' : `${s}px`}
                    </button>
                ))}
            </div>
        </section>
    );
};
