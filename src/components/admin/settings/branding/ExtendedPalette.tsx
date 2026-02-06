import React from 'react';
import { IPaletteColor } from '../../../../hooks/admin/useBranding.js';

interface ExtendedPaletteProps {
    palette: IPaletteColor[];
    onAdd: () => void;
    onRemove: (index: number) => void;
}

export const ExtendedPalette: React.FC<ExtendedPaletteProps> = ({ palette, onAdd, onRemove }) => {
    return (
        <section className="pt-12 border-t">
            <div className="flex items-center justify-between mb-6">
                <h3 className="text-sm font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                    Extended Palette
                </h3>
                <button
                    onClick={onAdd}
                    className="admin-action-btn btn-icon--add"
                    data-help-id="extended-palette-add"
                />
            </div>
            <div className="flex flex-wrap gap-4">
                {palette.map((c, idx) => (
                    <div key={idx} className="group relative flex items-center gap-3 p-3 bg-gray-50 rounded-2xl border border-transparent hover:border-[var(--brand-primary)]/30 transition-all">
                        <div
                            className="w-8 h-8 rounded-xl shadow-inner border border-black/5 wf-color-preview-box"
                            style={{ '--preview-bg': c.hex } as React.CSSProperties}
                        />
                        <div>
                            <div className="text-[10px] font-black text-gray-900 uppercase tracking-tight">{c.name}</div>
                            <div className="text-[9px] font-mono text-gray-400">{c.hex}</div>
                        </div>
                        <button
                            onClick={() => onRemove(idx)}
                            className="admin-action-btn btn-icon--delete"
                            data-help-id="extended-palette-remove"
                        />
                    </div>
                ))}
                {palette.length === 0 && (
                    <p className="text-xs text-gray-400 italic py-4">No extended colors defined.</p>
                )}
            </div>
        </section>
    );
};
