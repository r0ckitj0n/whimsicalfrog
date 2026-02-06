import React from 'react';
import { IRoomOverview } from '../../../../hooks/admin/useCategories.js';

interface OverviewPanelProps {
    overview: IRoomOverview[];
}

export const OverviewPanel: React.FC<OverviewPanelProps> = ({ overview }) => {
    return (
        <div id="tabPanelOverview" role="tabpanel" className="space-y-6">
            <div className="bg-white border rounded-3xl p-6 shadow-sm">
                <h3 className="text-sm font-black text-gray-900 uppercase tracking-wider mb-4">Per-Room Overview</h3>
                <div id="rcOverviewContainer" className="space-y-3">
                    {!overview || overview.length === 0 ? (
                        <div className="text-gray-400 text-sm italic">No assignments found.</div>
                    ) : (
                        overview.map((room) => (
                            <div key={room.room_number} className="p-3 bg-gray-50 rounded-xl border border-black/5">
                                <div className="flex justify-between items-center mb-2">
                                    <span className="text-xs font-black text-gray-900 uppercase tracking-tight">
                                        Room {room.room_number}: {room.room_name}
                                    </span>
                                    {room.primary_category && (
                                        <span className="px-2 py-0.5 bg-[var(--brand-primary)]/10 text-[var(--brand-primary)] rounded text-[9px] font-black uppercase">
                                            Primary: {room.primary_category}
                                        </span>
                                    )}
                                </div>
                                <div className="flex flex-wrap gap-1.5">
                                    {room.assigned_categories?.map((cat: string, idx: number) => (
                                        <span key={idx} className="px-2 py-0.5 bg-white border border-gray-200 rounded text-[10px] font-bold text-gray-600">
                                            {cat}
                                        </span>
                                    ))}
                                    {(!room.assigned_categories || room.assigned_categories.length === 0) && (
                                        <span className="text-[10px] text-gray-400 italic">No categories assigned</span>
                                    )}
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </div>
    );
};
