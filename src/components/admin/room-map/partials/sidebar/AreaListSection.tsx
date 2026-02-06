import React from 'react';
import { IMapArea } from '../../../../../types/room.js';

interface AreaListSectionProps {
    areas: IMapArea[];
    onAreasChange: (areas: IMapArea[]) => void;
    selectedIds: string[];
    onSelectionChange: (ids: string[]) => void;
}

export const AreaListSection: React.FC<AreaListSectionProps> = ({
    areas,
    onAreasChange,
    selectedIds,
    onSelectionChange
}) => {
    const handleReorder = (idx: number, dir: number) => {
        const next = [...areas];
        const target = idx + dir;
        if (target < 0 || target >= next.length) return;
        [next[idx], next[target]] = [next[target], next[idx]];
        onAreasChange(next);
    };

    const handleDuplicate = (area: IMapArea) => {
        const newArea = {
            ...area,
            id: String(Date.now()),
            selector: `${area.selector}-copy`,
            left: area.left + 20,
            top: area.top + 20
        };
        onAreasChange([...areas, newArea]);
    };

    const handleRemove = (id: string) => {
        onAreasChange(areas.filter(a => a.id !== id));
        onSelectionChange(selectedIds.filter(x => x !== id));
    };

    const handleSelectorChange = (id: string, val: string) => {
        onAreasChange(areas.map(a => a.id === id ? { ...a, selector: val } : a));
    };

    return (
        <section className="space-y-4">
            <div className="flex items-center justify-between border-b pb-2">
                <div className="flex items-center gap-2 text-xs font-black text-gray-400 uppercase tracking-widest">
                    Map Areas ({areas.length})
                </div>
            </div>
            <div className="space-y-2 max-h-64 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-transparent">
                {areas.map((area, idx) => (
                    <div
                        key={area.id}
                        onClick={() => onSelectionChange([area.id])}
                        className={`p-2 border rounded-xl transition-all cursor-pointer ${selectedIds.includes(area.id) ? 'bg-[var(--brand-primary)]/5 border-[var(--brand-primary)]/30 ring-1 ring-[var(--brand-primary)]/30' : 'bg-white hover:border-gray-300'}`}
                    >
                        <div className="flex items-center gap-2 mb-2">
                            <input
                                value={area.selector}
                                onChange={(e) => handleSelectorChange(area.id, e.target.value)}
                                className="flex-1 bg-transparent border-0 p-0 text-xs font-bold text-gray-900 focus:ring-0 focus:outline-none truncate"
                                onClick={e => e.stopPropagation()}
                            />
                            <div className="flex items-center gap-0.5">
                                <button
                                    onClick={(e) => { e.stopPropagation(); handleReorder(idx, -1); }}
                                    className="admin-action-btn btn-icon--up"
                                    type="button"
                                    data-help-id="map-area-move-up"
                                />
                                <button
                                    onClick={(e) => { e.stopPropagation(); handleReorder(idx, 1); }}
                                    className="admin-action-btn btn-icon--down"
                                    type="button"
                                    data-help-id="map-area-move-down"
                                />
                            </div>
                        </div>
                        <div className="flex items-center justify-between">
                            <div className="text-[10px] text-gray-400 font-mono">
                                {Math.round(area.left)},{Math.round(area.top)} {Math.round(area.width)}×{Math.round(area.height)}
                            </div>
                            <div className="flex gap-1">
                                <button
                                    onClick={(e) => { e.stopPropagation(); handleDuplicate(area); }}
                                    className="admin-action-btn btn-icon--duplicate"
                                    type="button"
                                    data-help-id="map-area-duplicate"
                                />
                                <button
                                    onClick={(e) => { e.stopPropagation(); handleRemove(area.id); }}
                                    className="admin-action-btn btn-icon--delete"
                                    data-help-id="map-area-delete"
                                    type="button"
                                />
                            </div>
                        </div>
                    </div>
                ))}
                {areas.length === 0 && (
                    <div className="text-center py-8 bg-gray-50 border-2 border-dashed border-gray-200 rounded-xl text-gray-400 text-[10px] uppercase font-black tracking-widest">
                        No areas drawn
                    </div>
                )}
            </div>
        </section>
    );
};
