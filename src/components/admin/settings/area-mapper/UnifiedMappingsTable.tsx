import React, { useMemo } from 'react';
import { IAreaMapping } from '../../../../types/index.js';

interface IRoomOption {
    val: string;
    label: string;
}

interface UnifiedMappingsTableProps {
    explicitMappings: IAreaMapping[];
    derivedMappings: IAreaMapping[];
    roomOptions: IRoomOption[];
    onEdit: (mapping: IAreaMapping) => void;
    onToggleActive: (id: number, currentActive: boolean | number) => void;
    onDelete: (id: number) => void;
    onConvert: (area: string, sku: string) => void;
}

type SlotStatus = 'explicit' | 'derived' | 'empty';

interface UnifiedSlot {
    area: string;
    status: SlotStatus;
    explicit?: IAreaMapping;
    live?: IAreaMapping;
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

    // Item mapping
    if (m.item_sku || m.sku) {
        const sku = m.item_sku || m.sku || '';
        return {
            primary: m.name || sku,
            secondary: m.name ? sku : undefined
        };
    }

    // Category mapping
    if (type === 'category' && m.category_id) {
        return { primary: m.name || `Category #${m.category_id}` };
    }

    // Content/Shortcut mapping (room navigation)
    if (type === 'content' || type === 'button') {
        const target = m.content_target || '';
        const roomName = resolveRoomName(target, roomOptions);
        return {
            primary: roomName || m.link_label || target,
            secondary: m.link_label && roomName ? m.link_label : undefined
        };
    }

    // Link mapping
    if (type === 'link' && m.link_url) {
        return {
            primary: m.link_label || 'External Link',
            secondary: m.link_url
        };
    }

    // Page/Modal/Action mappings
    if (type === 'page' || type === 'modal' || type === 'action') {
        return { primary: m.content_target || 'Unknown' };
    }

    return { primary: m.content_target || m.link_url || '-' };
};

// Get type badge styling
const getTypeBadge = (mappingType: string | undefined): { label: string; className: string } => {
    const type = mappingType?.toLowerCase() || '';
    const badges: Record<string, { label: string; className: string }> = {
        'item': { label: 'Item', className: 'bg-emerald-100 text-emerald-700' },
        'category': { label: 'Category', className: 'bg-purple-100 text-purple-700' },
        'content': { label: 'Shortcut', className: 'bg-blue-100 text-blue-700' },
        'button': { label: 'Button', className: 'bg-blue-100 text-blue-700' },
        'link': { label: 'Link', className: 'bg-amber-100 text-amber-700' },
        'page': { label: 'Page', className: 'bg-slate-100 text-slate-700' },
        'modal': { label: 'Modal', className: 'bg-slate-100 text-slate-700' },
        'action': { label: 'Action', className: 'bg-rose-100 text-rose-700' }
    };
    return badges[type] || { label: type || '-', className: 'bg-gray-100 text-gray-600' };
};

// Get status indicator
const getStatusIndicator = (status: SlotStatus): { icon: string; label: string; className: string } => {
    switch (status) {
        case 'explicit':
            return { icon: '✓', label: 'Explicit', className: 'bg-emerald-100 text-emerald-700 border-emerald-200' };
        case 'derived':
            return { icon: '↻', label: 'Derived', className: 'bg-blue-100 text-blue-700 border-blue-200' };
        case 'empty':
            return { icon: '○', label: 'Empty', className: 'bg-gray-100 text-gray-500 border-gray-200' };
    }
};

// Get image source
const getImageSource = (m: IAreaMapping | undefined): string => {
    if (!m) return '/images/items/placeholder.webp';
    if (m.content_image) return m.content_image;
    if (m.link_image) return m.link_image;
    if (m.image_url) return m.image_url;
    return '/images/items/placeholder.webp';
};

export const UnifiedMappingsTable: React.FC<UnifiedMappingsTableProps> = ({
    explicitMappings,
    derivedMappings,
    roomOptions,
    onEdit,
    onToggleActive,
    onDelete,
    onConvert
}) => {
    // Merge mappings into unified slots
    const unifiedSlots = useMemo<UnifiedSlot[]>(() => {
        const slotMap = new Map<string, UnifiedSlot>();

        // Add explicit mappings first
        for (const m of explicitMappings) {
            slotMap.set(m.area_selector, {
                area: m.area_selector,
                status: 'explicit',
                explicit: m,
                live: m // Explicit mappings are also the live view
            });
        }

        // Add derived mappings (override live if explicit exists, or create new)
        for (const m of derivedMappings) {
            const existing = slotMap.get(m.area_selector);
            if (existing) {
                // Slot already has explicit, update live reference
                existing.live = m;
            } else if (m.derived) {
                // Purely derived slot
                slotMap.set(m.area_selector, {
                    area: m.area_selector,
                    status: 'derived',
                    explicit: undefined,
                    live: m
                });
            } else {
                // Explicit from live view (fallback)
                slotMap.set(m.area_selector, {
                    area: m.area_selector,
                    status: 'explicit',
                    explicit: m,
                    live: m
                });
            }
        }

        // Sort by area number
        return Array.from(slotMap.values()).sort((a, b) => {
            const numA = parseInt(a.area.replace(/\D/g, '')) || 0;
            const numB = parseInt(b.area.replace(/\D/g, '')) || 0;
            return numA - numB;
        });
    }, [explicitMappings, derivedMappings]);

    return (
        <section>
            <h4 className="font-bold text-gray-700 mb-4 uppercase text-xs tracking-widest">Content Slots</h4>
            <div className="border rounded-xl overflow-hidden bg-white shadow-sm">
                <table className="w-full table-fixed divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase" style={{ width: '10%' }}>Area</th>
                            <th className="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase" style={{ width: '12%' }}>Status</th>
                            <th className="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase" style={{ width: '12%' }}>Type</th>
                            <th className="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase" style={{ width: '30%' }}>Destination</th>
                            <th className="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase" style={{ width: '10%' }}>Preview</th>
                            <th className="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase" style={{ width: '12%' }}>Active</th>
                            <th className="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase" style={{ width: '14%' }}>Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {unifiedSlots.map(slot => {
                            const statusInfo = getStatusIndicator(slot.status);
                            const mapping = slot.explicit || slot.live;
                            const typeBadge = getTypeBadge(mapping?.mapping_type);
                            const destination = getDestinationLabel(mapping, roomOptions);
                            const isItemDerived = slot.status === 'derived' && (slot.live?.item_sku || slot.live?.sku);
                            const isExplicitSlot = slot.status === 'explicit' && !!slot.explicit;
                            const isActive = Boolean(Number(mapping?.is_active));

                            return (
                                <tr key={slot.area} className="hover:bg-[var(--brand-primary)]/5 group transition-colors">
                                    <td className="px-4 py-3">
                                        <span className="text-sm font-bold text-gray-900">{slot.area}</span>
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
                                        <img
                                            src={getImageSource(slot.live)}
                                            alt={`Preview for ${slot.area}`}
                                            className="w-10 h-10 rounded border border-black/5 object-contain bg-white shadow-sm mx-auto"
                                            loading="lazy"
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        {isExplicitSlot && slot.explicit ? (
                                            <label className="relative inline-flex items-center cursor-pointer" data-help-id="mapping-active-toggle">
                                                <input
                                                    type="checkbox"
                                                    checked={isActive}
                                                    onChange={() => onToggleActive(slot.explicit!.id, slot.explicit!.is_active)}
                                                    className="sr-only peer"
                                                />
                                                <div className={`w-9 h-5 rounded-full peer-focus:ring-2 peer-focus:ring-blue-200 transition-colors ${isActive ? 'bg-emerald-500' : 'bg-slate-300'} peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-transform after:shadow-sm`}></div>
                                            </label>
                                        ) : (
                                            <span className="text-[10px] font-black uppercase tracking-widest text-slate-300">Derived</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-2">
                                            {slot.status === 'explicit' && slot.explicit && (
                                                <>
                                                    <button
                                                        type="button"
                                                        onClick={() => onEdit(slot.explicit!)}
                                                        className="admin-action-btn btn-icon--edit"
                                                        data-help-id="mapping-edit-btn"
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() => onDelete(slot.explicit!.id)}
                                                        className="admin-action-btn btn-icon--delete"
                                                        data-help-id="mapping-delete-btn"
                                                    />
                                                </>
                                            )}
                                            {isItemDerived && slot.live && (
                                                <button
                                                    type="button"
                                                    onClick={() => onConvert(slot.area, (slot.live!.item_sku || slot.live!.sku)!)}
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
                        {unifiedSlots.length === 0 && (
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
