import React, { useEffect, useId, useMemo, useRef, useState } from 'react';

export type OptionSelectItem = {
    value: string;
    label: string;
    subLabel?: string;
    swatchHex?: string; // only used for color-style options
    disabled?: boolean;
};

interface OptionSelectProps {
    label: string;
    value: string;
    options: OptionSelectItem[];
    placeholder?: string;
    searchPlaceholder?: string;
    onChange: (value: string) => void;
    onClear?: () => void;
    disabled?: boolean;
}

function isHexColor(v: string | undefined): boolean {
    return !!v && /^#[0-9a-fA-F]{6}$/.test(v);
}

export const OptionSelect: React.FC<OptionSelectProps> = ({
    label,
    value,
    options,
    placeholder = 'Select…',
    searchPlaceholder = 'Search…',
    onChange,
    onClear,
    disabled = false,
}) => {
    const listboxId = useId();
    const rootRef = useRef<HTMLDivElement | null>(null);
    const searchRef = useRef<HTMLInputElement | null>(null);
    const listRef = useRef<HTMLDivElement | null>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);

    const selected = useMemo(() => options.find(o => o.value === value) || null, [options, value]);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return options;
        return options.filter(o => {
            const hay = `${o.label} ${o.subLabel || ''}`.toLowerCase();
            return hay.includes(q);
        });
    }, [options, query]);

    useEffect(() => {
        if (!open) return;
        const onDown = (e: MouseEvent) => {
            if (!rootRef.current) return;
            if (!rootRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
    }, [open]);

    useEffect(() => {
        if (!open) return;
        // Reset focus + active row when opening.
        setActiveIndex(0);
        setQuery('');
        queueMicrotask(() => searchRef.current?.focus());
    }, [open]);

    useEffect(() => {
        // Clamp active index when filter results change.
        setActiveIndex((i) => Math.max(0, Math.min(i, Math.max(0, filtered.length - 1))));
    }, [filtered.length]);

    useEffect(() => {
        if (!open) return;
        const el = listRef.current?.querySelector<HTMLElement>(`[data-opt-index="${activeIndex}"]`);
        el?.scrollIntoView({ block: 'nearest' });
    }, [activeIndex, open]);

    const commit = (opt: OptionSelectItem) => {
        if (opt.disabled) return;
        onChange(opt.value);
        setOpen(false);
    };

    return (
        <div ref={rootRef} className="w-full">
            <div className="mb-2 flex items-center justify-between gap-3">
                <label className="block text-[11px] font-black uppercase tracking-[0.12em] text-slate-500 sm:text-xs">
                    {label}
                </label>
                <div className="text-[10px] font-mono text-slate-400">{options.length}</div>
            </div>

            <div className="relative">
                <button
                    type="button"
                    disabled={disabled}
                    onClick={() => setOpen(v => !v)}
                    className={[
                        'group flex w-full items-center justify-between gap-3 rounded-2xl border bg-white px-4 py-3 text-left shadow-sm transition',
                        'focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30',
                        disabled ? 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400 shadow-none' : 'border-slate-200 hover:border-slate-300',
                        open ? 'border-[var(--brand-primary)] shadow-[0_10px_24px_rgba(0,0,0,0.08)]' : ''
                    ].join(' ')}
                    aria-haspopup="listbox"
                    aria-expanded={open}
                    aria-controls={listboxId}
                >
                    <div className="min-w-0">
                        {selected ? (
                            <div className="flex items-center gap-3">
                                {isHexColor(selected.swatchHex) && (
                                    <span
                                        className="h-6 w-6 shrink-0 rounded-full border border-black/10 shadow-[inset_0_2px_4px_rgba(0,0,0,0.08)]"
                                        style={{ backgroundColor: selected.swatchHex }}
                                        aria-hidden="true"
                                    />
                                )}
                                <div className="min-w-0">
                                    <div className="truncate text-sm font-black text-slate-800">
                                        {selected.label}
                                    </div>
                                    {selected.subLabel && (
                                        <div className="truncate text-[11px] font-bold text-slate-400">
                                            {selected.subLabel}
                                        </div>
                                    )}
                                </div>
                            </div>
                        ) : (
                            <div className="truncate text-sm font-black text-slate-400">{placeholder}</div>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        {selected && onClear && !disabled && (
                            <span
                                onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    onClear();
                                }}
                                className="admin-action-btn btn-icon--close !h-7 !w-7 !min-h-7 !min-w-7 opacity-60 hover:opacity-100"
                                aria-label={`Clear ${label}`}
                                role="button"
                                tabIndex={0}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' || e.key === ' ') {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        onClear();
                                    }
                                }}
                            />
                        )}
                        <span className={`btn-icon--down text-slate-400 transition ${open ? 'rotate-180' : ''}`} aria-hidden="true" />
                    </div>
                </button>

                {open && !disabled && (
                    <div
                        className="mt-3 rounded-2xl border border-slate-200 bg-white shadow-[0_24px_48px_rgba(0,0,0,0.16)]"
                        role="listbox"
                        id={listboxId}
                    >
                        <div className="border-b border-slate-100 p-3">
                            <input
                                ref={searchRef}
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Escape') {
                                        e.preventDefault();
                                        setOpen(false);
                                    } else if (e.key === 'ArrowDown') {
                                        e.preventDefault();
                                        setActiveIndex((i) => Math.min(i + 1, filtered.length - 1));
                                    } else if (e.key === 'ArrowUp') {
                                        e.preventDefault();
                                        setActiveIndex((i) => Math.max(i - 1, 0));
                                    } else if (e.key === 'Enter') {
                                        e.preventDefault();
                                        const opt = filtered[activeIndex];
                                        if (opt) commit(opt);
                                    }
                                }}
                                placeholder={searchPlaceholder}
                                className="form-input w-full text-sm font-bold"
                                aria-controls={listboxId}
                            />
                        </div>

                        <div ref={listRef} className="max-h-[320px] overflow-auto p-2">
                            {filtered.length === 0 ? (
                                <div className="px-3 py-6 text-center text-sm font-bold text-slate-400">No matches</div>
                            ) : (
                                filtered.map((opt, idx) => {
                                    const isActive = idx === activeIndex;
                                    const isSelected = opt.value === value;
                                    return (
                                        <button
                                            key={opt.value}
                                            type="button"
                                            disabled={!!opt.disabled}
                                            onMouseEnter={() => setActiveIndex(idx)}
                                            onClick={() => commit(opt)}
                                            role="option"
                                            aria-selected={isSelected}
                                            data-opt-index={idx}
                                            className={[
                                                'flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2 text-left transition',
                                                opt.disabled ? 'cursor-not-allowed opacity-50' : 'hover:bg-slate-50',
                                                isActive ? 'bg-slate-50' : '',
                                            ].join(' ')}
                                        >
                                            <div className="flex min-w-0 items-center gap-3">
                                                {isHexColor(opt.swatchHex) && (
                                                    <span
                                                        className="h-5 w-5 shrink-0 rounded-full border border-black/10"
                                                        style={{ backgroundColor: opt.swatchHex }}
                                                        aria-hidden="true"
                                                    />
                                                )}
                                                <div className="min-w-0">
                                                    <div className="truncate text-sm font-black text-slate-800">
                                                        {opt.label}
                                                    </div>
                                                    {opt.subLabel && (
                                                        <div className="truncate text-[11px] font-bold text-slate-400">
                                                            {opt.subLabel}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                            {isSelected && (
                                                <span className="btn-icon--check text-[var(--brand-primary)]" aria-hidden="true" />
                                            )}
                                        </button>
                                    );
                                })
                            )}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};
