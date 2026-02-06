import React from 'react';
import { IAreaOption } from '../../../../types/room.js';

interface CoordinatesViewProps {
    availableAreas: IAreaOption[];
    selectedRoom?: string;
}

export const CoordinatesView: React.FC<CoordinatesViewProps> = ({ availableAreas, selectedRoom }) => {
    return (
        <div className="space-y-4 animate-in fade-in duration-300">
            <h3 className="font-bold text-gray-700 mb-2 flex items-center gap-2">
                Room Map
            </h3>
            <div className="p-4 bg-gray-50 border rounded-xl shadow-sm">
                <p className="text-xs text-gray-500 mb-4 font-medium uppercase tracking-tight">Interactive room layout and mapped areas.</p>

                {selectedRoom ? (
                    <div className="bg-gray-900 rounded-xl overflow-hidden h-[500px] shadow-inner border border-gray-800 relative">
                        <iframe
                            src={`/?bare=1&room_id=${selectedRoom}&_t=${Date.now()}`}
                            className="w-full h-full border-none pointer-events-auto"
                            title="Room Map View"
                        />
                    </div>
                ) : (
                    <div className="bg-gray-900 rounded-xl p-6 font-mono text-[11px] leading-relaxed text-[var(--brand-accent)]/80 overflow-x-auto h-[450px] shadow-inner border border-gray-800 flex items-center justify-center italic">
                        Select a room to view the map
                    </div>
                )}

                <div className="mt-8">
                    <h4 className="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Mapped Areas Raw Data</h4>
                    <div className="bg-gray-900 rounded-xl p-6 font-mono text-[11px] leading-relaxed text-[var(--brand-accent)]/80 overflow-x-auto max-h-[300px] shadow-inner border border-gray-800">
                        {availableAreas.length > 0 ? (
                            <ul className="space-y-2">
                                {availableAreas.map(a => (
                                    <li key={a.val} className="hover:bg-white/5 px-2 py-1 rounded transition-colors border-l-2 border-transparent hover:border-[var(--brand-accent)] group">
                                        <span className="text-[var(--brand-primary)] font-black">{a.val}</span>:
                                        <span className="text-gray-400 group-hover:text-[var(--brand-accent)] transition-colors"> {'{'} "label": <span className="text-orange-300">"{a.label}"</span> {'}'}</span>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <div className="h-full flex items-center justify-center text-gray-600 italic">
                                No coordinates found for this room
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};
