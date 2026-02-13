import React, { useMemo, useRef, useState } from 'react';
import type { IGlobalColor } from '../../../../types/theming.js';

interface GlobalColorsTabProps {
    colors: IGlobalColor[];
    onAdd: () => void;
    onDelete: (id: number, name: string) => void;
    onUpdate: (payload: { id: number; color_code?: string; color_name?: string; category?: string }) => Promise<void> | void;
}

function groupLabelForColorName(name: string, category?: string): string {
    const n = String(name || '');
    if (n.startsWith('SM-') || String(category || '').toLowerCase() === 'sanmar') return 'Sanmar Colors';
    const m = n.match(/^([A-Za-z]{2,6})-/);
    if (m) return `${m[1].toUpperCase()} Colors`;
    return 'Base Colors';
}

export const GlobalColorsTab: React.FC<GlobalColorsTabProps> = ({ colors, onAdd, onDelete, onUpdate }) => {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return colors;
        return colors.filter(c => String(c.color_name || '').toLowerCase().includes(q));
    }, [colors, query]);

    const grouped = useMemo(() => {
        const map = new Map<string, IGlobalColor[]>();
        for (const c of filtered) {
            const label = groupLabelForColorName(c.color_name, c.category);
            const cur = map.get(label) || [];
            cur.push(c);
            map.set(label, cur);
        }
        // stable ordering: Base first, Sanmar second, then alpha.
        const labels = Array.from(map.keys()).sort((a, b) => {
            const weight = (x: string) => (x === 'Base Colors' ? 0 : x === 'Sanmar Colors' ? 1 : 2);
            const wa = weight(a);
            const wb = weight(b);
            if (wa !== wb) return wa - wb;
            return a.localeCompare(b);
        });
        return labels.map(label => ({ label, colors: (map.get(label) || []).slice().sort((a, b) => a.color_name.localeCompare(b.color_name)) }));
    }, [filtered]);

    return (
        <div className="space-y-4">
            <div className="flex justify-between items-center">
                <h4 className="text-md font-bold text-gray-800">Manage Colors</h4>
                <div className="flex gap-2">
                    <button
                        onClick={onAdd}
                        className="admin-action-btn btn-icon--add"
                        data-help-id="attributes-add-global-color"
                    />
                </div>
            </div>

            <div className="flex items-center gap-3">
                <input
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search colors..."
                    className="w-full max-w-md px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 bg-white"
                />
                <div className="text-[10px] font-mono text-slate-400">{filtered.length} shown</div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {grouped.map(section => (
                    <details
                        key={section.label}
                        className="md:col-span-2 lg:col-span-3 border border-slate-100 rounded-2xl bg-slate-50/40 p-3"
                        open={section.label === 'Base Colors' || section.label === 'Sanmar Colors'}
                    >
                        <summary className="cursor-pointer select-none flex items-center justify-between gap-3 px-2 py-1 text-xs font-black text-slate-700">
                            <span>{section.label}</span>
                            <span className="text-[10px] font-mono text-slate-400">{section.colors.length}</span>
                        </summary>

                        <div className="mt-3 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            {section.colors.map(color => (
                                <GlobalColorCard
                                    key={color.id}
                                    color={color}
                                    onDelete={onDelete}
                                    onUpdate={onUpdate}
                                />
                            ))}
                        </div>
                    </details>
                ))}
            </div>
        </div>
    );
};

const GlobalColorCard: React.FC<{
    color: IGlobalColor;
    onDelete: (id: number, name: string) => void;
    onUpdate: (payload: { id: number; color_code?: string }) => Promise<void> | void;
}> = ({ color, onDelete, onUpdate }) => {
    const inputRef = useRef<HTMLInputElement | null>(null);
    const current = (color.color_code && /^#[0-9a-fA-F]{6}$/.test(color.color_code)) ? color.color_code : '#cccccc';

    return (
        <div className="flex items-center justify-between p-3 border rounded-lg bg-white shadow-sm hover:shadow-md transition-shadow group">
            <div className="flex items-center gap-3 min-w-0">
                <button
                    type="button"
                    className="w-8 h-8 rounded-full border shadow-sm wf-color-preview-swatch shrink-0"
                    style={{ '--swatch-bg': current } as React.CSSProperties}
                    onClick={() => inputRef.current?.click()}
                    data-help-id="attributes-color-swatch-edit"
                />
                <input
                    ref={inputRef}
                    type="color"
                    value={current}
                    onChange={async (e) => {
                        const hex = e.target.value;
                        await onUpdate({ id: color.id, color_code: hex });
                    }}
                    className="hidden"
                    aria-hidden="true"
                    tabIndex={-1}
                />
                <div className="min-w-0">
                    <div className="text-sm font-bold text-gray-900 truncate">{color.color_name}</div>
                    <div className="text-[10px] text-gray-500 font-mono uppercase">{color.color_code || 'No code'}</div>
                </div>
            </div>
            <div className="flex items-center gap-1">
                <button
                    type="button"
                    onClick={() => onDelete(color.id, color.color_name)}
                    className="admin-action-btn btn-icon--delete"
                    data-help-id="attributes-delete-global-color"
                />
            </div>
        </div>
    );
};
