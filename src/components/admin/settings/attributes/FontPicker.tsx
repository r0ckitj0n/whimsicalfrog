import React, { useState, useMemo } from 'react';
import { IFontMeta, FONT_LIBRARY } from '../../../../hooks/admin/useFontPicker.js';

interface FontPickerProps {
    isOpen: boolean;
    onClose: () => void;
    onSelect: (font: IFontMeta) => void;
    currentStack?: string;
    title: string;
}

export const FontPicker: React.FC<FontPickerProps> = ({
    isOpen,
    onClose,
    onSelect,
    currentStack,
    title
}) => {
    const [searchTerm, setSearchTerm] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('all');



    const categories = useMemo(() => Array.from(new Set(FONT_LIBRARY.map((f: IFontMeta) => f.category))) as string[], []);

    const filteredFonts = useMemo(() => {
        return FONT_LIBRARY.filter((font: IFontMeta) => {
            const matchesCategory = categoryFilter === 'all' || font.category === categoryFilter;
            const matchesQuery = !searchTerm ||
                font.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                font.stack.toLowerCase().includes(searchTerm.toLowerCase()) ||
                font.detail.toLowerCase().includes(searchTerm.toLowerCase());
            return matchesCategory && matchesQuery;
        });
    }, [searchTerm, categoryFilter]);

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-[var(--wf-z-modal)] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
            <div
                className="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-4xl overflow-hidden animate-in zoom-in-95 slide-in-from-bottom-4 duration-300 flex flex-col max-h-[80vh]"
                onClick={e => e.stopPropagation()}
            >
                <div className="px-8 py-6 border-b bg-gray-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h3 className="text-xl font-black text-gray-900 uppercase tracking-tight">{title}</h3>
                        <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1">Select from Google Fonts library</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="flex items-center gap-2 bg-white p-2 rounded-xl border border-gray-100 shadow-inner">
                            <span className="text-xs text-gray-400 pl-1">🔍</span>
                            <input
                                type="text"
                                value={searchTerm}
                                onChange={e => setSearchTerm(e.target.value)}
                                placeholder="Search fonts..."
                                className="w-48 bg-transparent border-none p-0 text-xs focus:ring-0"
                            />
                        </div>
                        <select
                            value={categoryFilter}
                            onChange={e => setCategoryFilter(e.target.value)}
                            className="form-select py-2 text-xs font-bold rounded-xl border-gray-200 bg-white"
                        >
                            <option value="all">All Categories</option>
                            {categories.map((cat: string) => (
                                <option key={cat} value={cat}>{cat.charAt(0).toUpperCase() + cat.slice(1)}</option>
                            ))}
                        </select>
                        <button
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="common-close"
                        />
                    </div>
                </div>

                <div className="flex-1 overflow-y-auto p-8 scrollbar-hide">
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {filteredFonts.map((font: IFontMeta) => (
                            <button
                                key={font.id}
                                onClick={() => onSelect(font)}
                                className={`group flex flex-col text-left p-5 rounded-2xl border-2 transition-all hover:shadow-lg ${currentStack === font.stack
                                    ? 'bg-[var(--brand-primary)]/10 border-[var(--brand-primary)] ring-1 ring-[var(--brand-primary)]'
                                    : 'bg-white border-gray-100 hover:border-[var(--brand-primary)]/30'
                                    }`}
                            >
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-black text-gray-900 truncate">{font.name}</span>
                                    {currentStack === font.stack && <span className="text-[var(--brand-primary)]">✅</span>}
                                </div>
                                <div className="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-4">{font.detail}</div>
                                <div
                                    className="text-lg mb-2 truncate wf-font-preview"
                                    style={{ '--preview-font': font.stack } as React.CSSProperties}
                                >
                                    {font.sample}
                                </div>
                                <div className="mt-auto pt-3 border-t border-gray-50 flex items-center justify-between text-[9px] font-mono text-gray-400 group-hover:text-[var(--brand-primary)] transition-colors">
                                    <span className="truncate max-w-[150px]">{font.stack}</span>
                                    <span className="transition-transform group-hover:translate-x-1">▶</span>
                                </div>
                            </button>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
};
