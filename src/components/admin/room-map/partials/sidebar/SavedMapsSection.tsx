import React from 'react';
import { IRoomMap } from '../../../../../types/room.js';

interface SavedMapsSectionProps {
    savedMaps: IRoomMap[];
    selectedMapId?: string | number;
    onRefreshSaved: () => void;
    onActivateMap: (id: string | number) => void;
    onDeleteMap: (id: string | number) => void;
    onLoadMap: (id: string | number) => void;
    onRenameMap: (id: string | number) => void;
}

export const SavedMapsSection: React.FC<SavedMapsSectionProps> = ({
    savedMaps,
    selectedMapId,
    onRefreshSaved,
    onActivateMap,
    onDeleteMap,
    onLoadMap,
    onRenameMap
}) => {
    return (
        <section className="space-y-4">
            <div className="flex items-center justify-between border-b pb-2">
                <div className="flex items-center gap-2 text-xs font-black text-gray-400 uppercase tracking-widest">
                    Saved Maps
                </div>
                <button
                    onClick={onRefreshSaved}
                    className="admin-action-btn btn-icon--refresh"
                    data-help-id="refresh-map-list"
                    type="button"
                />
            </div>
            <div className="space-y-2">
                {savedMaps.map(map => (
                    <div
                        key={map.id}
                        className={`flex items-center justify-between p-2 rounded-lg group transition-all cursor-pointer border-2
                            ${String(map.id) === String(selectedMapId) ? 'ring-2 ring-blue-500 ring-offset-1' : ''}
                            ${map.is_active
                                ? 'bg-[var(--brand-primary)]/5 border-[var(--brand-primary)] shadow-sm'
                                : 'bg-white border-gray-100 hover:border-gray-300'}`}
                        onClick={() => onLoadMap(map.id!)}
                    >
                        <div className="min-w-0">
                            <div className="flex items-center gap-2">
                                <div className="text-xs font-bold text-gray-900 truncate">{map.map_name}</div>
                                {!!map.is_active && (
                                    <span className="px-1.5 py-0.5 rounded-full text-[8px] font-black uppercase tracking-tighter bg-[var(--brand-primary)] text-white shadow-sm leading-none flex items-center">
                                        Active
                                    </span>
                                )}
                            </div>
                            <div className="text-[9px] text-gray-400">{map.created_at}</div>
                        </div>
                        <div
                            className="flex gap-1 items-center"
                            onClick={(e) => e.stopPropagation()}
                            onMouseDown={(e) => e.stopPropagation()}
                        >
                            {map.map_name !== 'Original' && (
                                <button
                                    onClick={(e) => { e.stopPropagation(); onRenameMap(map.id!); }}
                                    className="admin-action-btn btn-icon--edit"
                                    data-help-id="rename-map"
                                    type="button"
                                />
                            )}
                            <input
                                type="checkbox"
                                checked={!!map.is_active}
                                readOnly
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onActivateMap(map.id!);
                                }}
                                onPointerDown={(e) => e.stopPropagation()}
                                style={{ accentColor: 'var(--brand-primary)' }}
                                className="relative z-10 w-4 h-4 cursor-pointer"
                                data-help-id={map.is_active ? 'map-active' : 'map-activate'}
                            />
                            <button
                                onClick={(e) => { e.stopPropagation(); onDeleteMap(map.id!); }}
                                className="admin-action-btn btn-icon--delete"
                                data-help-id="delete-map"
                                type="button"
                            />
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
};
