import React, { useMemo, useState } from 'react';
import { IAreaMapping } from '../../../../types/index.js';

interface IRoomOption {
    val: string;
    label: string;
}

interface UnifiedMappingsTableProps {
    explicitMappings: IAreaMapping[];
    derivedMappings: IAreaMapping[];
    availableAreas: Array<{ val: string; label: string }>;
    derivedCategory: string;
    roomOptions: IRoomOption[];
    onEdit: (mapping: IAreaMapping) => void;
    onToggleActive: (id: number, currentActive: boolean | number) => void;
    onDelete: (id: number, area: string) => void;
    onConvert: (area: string, sku: string) => void;
    onPreviewImage: (mapping: IAreaMapping) => void;
}

type RowStatus = 'explicit' | 'derived' | 'inactive' | 'empty';

interface DisplayRow {
    key: string;
    area: string;
    areaLabel: string;
    areaRowIndex: number;
    areaCount: number;
    status: RowStatus;
    mapping?: IAreaMapping;
    liveMapping?: IAreaMapping;
    isExplicit: boolean;
    isActive: boolean;
    isItemDerived: boolean;
}

// Resolve a content_target (room number) to a room name
const resolveRoomName = (target: string | undefined, roomOptions: IRoomOption[]): string => {
    if (!target) return '';
    const room = roomOptions.find(r => r.val === target);
    return room?.label || `Room ${target}`;
};

// Get human-readable destination for a mapping
const getDestinationLabel = (m: IAreaMapping | undefined, roomOptions: IRoomOption[]): { primary: string; secondary?: string } => {
    if (!m) return { primary: '-' };

    const type = m.mapping_type?.toLowerCase();

    if (m.item_sku || m.sku) {
        const sku = m.item_sku || m.sku || '';
        return {
            primary: m.name || sku,
            secondary: m.name ? sku : undefined
        };
    }
    if (type === 'item') {
        return { primary: '-' };
    }

    if (type === 'category' && m.category_id) {
        return { primary: m.name || `Category #${m.category_id}` };
    }

    if (type === 'content' || type === 'button') {
        const rawTarget = m.content_target || '';
        const target = rawTarget.toLowerCase().startsWith('room:') ? rawTarget.slice(5) : rawTarget;
        const roomName = resolveRoomName(target, roomOptions);
        return {
            primary: roomName || m.link_label || target,
            secondary: m.link_label && roomName ? m.link_label : undefined
        };
    }

    if (type === 'link' && m.link_url) {
        return {
            primary: m.link_label || 'External Link',
            secondary: m.link_url
        };
    }

    if (type === 'page' || type === 'modal' || type === 'action') {
        return { primary: m.content_target || 'Unknown' };
    }

    return { primary: m.content_target || m.link_url || '-' };
};

// Get type badge styling
const getTypeBadge = (mappingType: string | undefined): { label: string; className: string } => {
    const type = mappingType?.toLowerCase() || '';
    const badges: Record<string, { label: string; className: string }> = {
        item: { label: 'Item', className: 'bg-emerald-100 text-emerald-700' },
        category: { label: 'Category', className: 'bg-purple-100 text-purple-700' },
        content: { label: 'Shortcut', className: 'bg-blue-100 text-blue-700' },
        button: { label: 'Button', className: 'bg-blue-100 text-blue-700' },
        link: { label: 'Link', className: 'bg-amber-100 text-amber-700' },
        page: { label: 'Page', className: 'bg-slate-100 text-slate-700' },
        modal: { label: 'Modal', className: 'bg-slate-100 text-slate-700' },
        action: { label: 'Action', className: 'bg-rose-100 text-rose-700' }
    };
    return badges[type] || { label: type || '-', className: 'bg-gray-100 text-gray-600' };
};

const getStatusIndicator = (status: RowStatus): { icon: string; label: string; className: string } => {
    switch (status) {
        case 'explicit':
            return { icon: '✓', label: 'Active', className: 'bg-emerald-100 text-emerald-700 border-emerald-200' };
        case 'derived':
            return { icon: '↻', label: 'Derived', className: 'bg-blue-100 text-blue-700 border-blue-200' };
        case 'inactive':
            return { icon: '●', label: 'Saved', className: 'bg-slate-100 text-slate-500 border-slate-200' };
        case 'empty':
            return { icon: '○', label: 'Empty', className: 'bg-gray-100 text-gray-500 border-gray-200' };
    }
};

const getImageSource = (m: IAreaMapping | undefined): string => {
    if (!m) return '/images/items/placeholder.webp';
    if (m.content_image) return m.content_image;
    if (m.link_image) return m.link_image;
    if (m.image_url) return m.image_url;
    return '/images/items/placeholder.webp';
};

const sortAreaSelectors = (a: string, b: string): number => {
    const mA = a.match(/\.area-(\d+)/i);
    const mB = b.match(/\.area-(\d+)/i);
    const numA = mA ? Number(mA[1]) : Number.POSITIVE_INFINITY;
    const numB = mB ? Number(mB[1]) : Number.POSITIVE_INFINITY;
    if (numA !== numB) return numA - numB;
    return a.localeCompare(b);
};

export const UnifiedMappingsTable: React.FC<UnifiedMappingsTableProps> = ({
    explicitMappings,
    derivedMappings,
    availableAreas,
    derivedCategory,
    roomOptions,
    onEdit,
    onToggleActive,
    onDelete,
    onConvert,
    onPreviewImage
}) => {
    const [showAlternates, setShowAlternates] = useState(false);

    const hasLiveDerivedContent = (mapping: IAreaMapping | undefined): boolean => {
        if (!mapping || !mapping.derived) return false;
        const type = String(mapping.mapping_type || '').toLowerCase();
        if (type === 'item') {
            return String(mapping.item_sku || mapping.sku || '').trim() !== '';
        }
        if (type === 'content' || type === 'button' || type === 'page' || type === 'modal' || type === 'action') {
            return String(mapping.content_target || '').trim() !== '' || String(mapping.link_url || '').trim() !== '';
        }
        if (type === 'link') {
            return String(mapping.link_url || '').trim() !== '';
        }
        if (type === 'category') {
            return Number(mapping.category_id || 0) > 0;
        }
        return false;
    };

    const rows = useMemo<DisplayRow[]>(() => {
        const explicitByArea = new Map<string, IAreaMapping[]>();
        for (const mapping of explicitMappings) {
            const key = String(mapping.area_selector || '').trim();
            if (!key) continue;
            const current = explicitByArea.get(key) || [];
            current.push(mapping);
            explicitByArea.set(key, current);
        }

        for (const [key, mappings] of explicitByArea.entries()) {
            mappings.sort((a, b) => {
                const aActive = a.is_active === true || Number(a.is_active) === 1;
                const bActive = b.is_active === true || Number(b.is_active) === 1;
                if (aActive !== bActive) return aActive ? -1 : 1;

                const aOrder = Number(a.display_order || 0);
                const bOrder = Number(b.display_order || 0);
                if (aOrder !== bOrder) return aOrder - bOrder;

                return Number(b.id || 0) - Number(a.id || 0);
            });
        }

        const liveByArea = new Map<string, IAreaMapping>();
        for (const mapping of derivedMappings) {
            const key = String(mapping.area_selector || '').trim();
            if (!key) continue;
            liveByArea.set(key, mapping);
        }

        const hasCategoryDerivation = String(derivedCategory || '').trim() !== '';
        const allAreas = new Set<string>();
        for (const area of explicitByArea.keys()) allAreas.add(area);
        for (const area of liveByArea.keys()) allAreas.add(area);
        if (hasCategoryDerivation) {
            for (const area of availableAreas) {
                const key = String(area?.val || '').trim();
                if (key) allAreas.add(key);
            }
        }

        const sortedAreas = Array.from(allAreas).sort(sortAreaSelectors);
        const nextRows: DisplayRow[] = [];

        for (const area of sortedAreas) {
            const explicit = explicitByArea.get(area) || [];
            const live = liveByArea.get(area);

            if (explicit.length > 0) {
                explicit.forEach((mapping, index) => {
                    const isActive = mapping.is_active === true || Number(mapping.is_active) === 1;
                    nextRows.push({
                        key: `explicit-${String(mapping.id || `${area}-${index}`)}`,
                        area,
                        areaLabel: index === 0 ? area : `Alt ${index + 1}`,
                        areaRowIndex: index,
                        areaCount: explicit.length,
                        status: isActive ? 'explicit' : 'inactive',
                        mapping,
                        liveMapping: mapping,
                        isExplicit: true,
                        isActive,
                        isItemDerived: false
                    });
                });
                continue;
            }

            if (live) {
                const isDerived = !!live.derived;
                const hasDerivedContent = hasLiveDerivedContent(live);
                const isActive = isDerived ? hasDerivedContent : true;
                nextRows.push({
                    key: `live-${area}`,
                    area,
                    areaLabel: area,
                    areaRowIndex: 0,
                    areaCount: 1,
                    status: isDerived ? (hasDerivedContent ? 'derived' : 'inactive') : 'explicit',
                    mapping: live,
                    liveMapping: live,
                    isExplicit: !isDerived,
                    isActive,
                    isItemDerived: isDerived && !!(live.item_sku || live.sku)
                });
                continue;
            }

            if (hasCategoryDerivation) {
                const placeholder = {
                    area_selector: area,
                    mapping_type: 'item',
                    derived: true,
                    image_url: '/images/items/placeholder.webp'
                } as IAreaMapping;

                nextRows.push({
                    key: `placeholder-${area}`,
                    area,
                    areaLabel: area,
                    areaRowIndex: 0,
                    areaCount: 1,
                    status: 'inactive',
                    mapping: placeholder,
                    liveMapping: placeholder,
                    isExplicit: false,
                    isActive: false,
                    isItemDerived: false
                });
            }
        }

        return nextRows;
    }, [availableAreas, derivedCategory, derivedMappings, explicitMappings]);

    const visibleRows = useMemo(() => {
        if (showAlternates) return rows;
        return rows.filter((row) => row.areaRowIndex === 0);
    }, [rows, showAlternates]);

    return (
        <section>
            <div className="mb-4 flex items-center justify-between gap-4">
                <h4 className="font-bold text-gray-700 uppercase text-xs tracking-widest">Content Slots</h4>
                <label className="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-slate-600 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        checked={showAlternates}
                        onChange={(e) => setShowAlternates(e.target.checked)}
                        className="h-3.5 w-3.5 rounded border-slate-300 text-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                    />
                    Show Alternates
                </label>
            </div>
            <div className="border rounded-xl overflow-hidden bg-white shadow-sm">
                <table className="w-full table-fixed divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase" style={{ width: '12%' }}>Area</th>
                            <th className="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase" style={{ width: '12%' }}>Status</th>
                            <th className="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase" style={{ width: '12%' }}>Type</th>
                            <th className="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase" style={{ width: '28%' }}>Destination</th>
                            <th className="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase" style={{ width: '10%' }}>Preview</th>
                            <th className="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase" style={{ width: '12%' }}>Active</th>
                            <th className="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase" style={{ width: '14%' }}>Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {visibleRows.map((row) => {
                            const mapping = row.mapping;
                            const liveMapping = row.liveMapping;
                            const statusInfo = getStatusIndicator(row.status);
                            const typeBadge = getTypeBadge(mapping?.mapping_type);
                            const destination = getDestinationLabel(mapping, roomOptions);
                            const mappingId = Number(mapping?.id || 0);
                            const canSetActive = row.isExplicit && !!mapping && mappingId > 0 && !row.isActive;

                            return (
                                <tr key={row.key} className="hover:bg-[var(--brand-primary)]/5 group transition-colors">
                                    <td className="px-4 py-3">
                                        {row.areaRowIndex === 0 ? (
                                            <div>
                                                <span className="text-sm font-bold text-gray-900">{row.areaLabel}</span>
                                                {row.areaCount > 1 && (
                                                    <div className="text-[10px] text-slate-400 font-black uppercase tracking-widest">
                                                        {row.areaCount} saved
                                                    </div>
                                                )}
                                            </div>
                                        ) : (
                                            <span className="text-xs font-black uppercase tracking-widest text-slate-400">{row.areaLabel}</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full font-black border ${statusInfo.className}`}>
                                            <span>{statusInfo.icon}</span>
                                            <span>{statusInfo.label}</span>
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        {mapping ? (
                                            <span className={`text-[10px] px-2 py-0.5 rounded-full font-black ${typeBadge.className}`}>
                                                {typeBadge.label}
                                            </span>
                                        ) : (
                                            <span className="text-gray-300">-</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="text-sm text-gray-700 truncate" data-help-id="mapping-destination">
                                            {destination.primary}
                                        </div>
                                        {destination.secondary && (
                                            <div className="text-[10px] text-gray-400 font-mono truncate">
                                                {destination.secondary}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        {liveMapping ? (
                                            <button
                                                type="button"
                                                onClick={() => onPreviewImage(liveMapping)}
                                                className="group"
                                                title="View image"
                                            >
                                                <img
                                                    src={getImageSource(liveMapping)}
                                                    alt={`Preview for ${row.area}`}
                                                    className="w-10 h-10 rounded border border-black/5 object-contain bg-white shadow-sm mx-auto group-hover:shadow-md transition-shadow"
                                                    loading="lazy"
                                                />
                                            </button>
                                        ) : (
                                            <img
                                                src={getImageSource(liveMapping)}
                                                alt={`Preview for ${row.area}`}
                                                className="w-10 h-10 rounded border border-black/5 object-contain bg-white shadow-sm mx-auto"
                                                loading="lazy"
                                            />
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        {row.isExplicit && mapping ? (
                                            canSetActive ? (
                                                <button
                                                    type="button"
                                                    onClick={() => onToggleActive(mappingId, 0)}
                                                    className="btn btn-secondary px-2 py-1 text-[9px] font-black uppercase tracking-wide"
                                                    data-help-id="mapping-active-toggle"
                                                >
                                                    Set Active
                                                </button>
                                            ) : (
                                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-emerald-50 text-emerald-600 border border-emerald-100">
                                                    Active
                                                </span>
                                            )
                                        ) : (
                                            <span className={`text-[10px] font-black uppercase tracking-widest ${row.isActive ? 'text-emerald-600' : 'text-slate-300'}`}>
                                                {row.isActive ? 'Auto' : 'Disabled'}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-2">
                                            {row.isExplicit && mapping && mappingId > 0 && (
                                                <>
                                                    <button
                                                        type="button"
                                                        onClick={() => onEdit(mapping)}
                                                        className="admin-action-btn btn-icon--edit"
                                                        data-help-id="mapping-edit-btn"
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() => onDelete(mappingId, row.area)}
                                                        className="admin-action-btn btn-icon--delete"
                                                        data-help-id="mapping-delete-btn"
                                                    />
                                                </>
                                            )}
                                            {row.isItemDerived && mapping && (
                                                <button
                                                    type="button"
                                                    onClick={() => onConvert(row.area, (mapping.item_sku || mapping.sku)!)}
                                                    className="btn btn-secondary px-2 py-1 text-[9px] font-black uppercase tracking-wide shadow-sm"
                                                    data-help-id="mapping-convert-btn"
                                                >
                                                    Convert
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                        {visibleRows.length === 0 && (
                            <tr>
                                <td colSpan={7} className="px-4 py-12 text-center text-gray-400 italic text-sm">
                                    No content mappings for this room
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );
};
