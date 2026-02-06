import React from 'react';
import { TRoomManagerTab } from '../../../../types/room.js';

/** Tab definition for Room Manager navigation */
interface ITabDef {
    id: TRoomManagerTab;
    label: string;
    icon: string;
    isDirty?: boolean;
}

/** Get tab definitions with current dirty states */
export const getRoomManagerTabs = (
    isRoomFormDirty: boolean,
    isContentDirty: boolean,
    isSettingsDirty: boolean,
    isBoundaryDirty: boolean
): ITabDef[] => [
        { id: 'overview', label: 'Overview', icon: '📋', isDirty: isRoomFormDirty },
        { id: 'navigation', label: 'Navigation', icon: '🔗' },
        { id: 'categories', label: 'Categories', icon: '🏷️' },
        { id: 'visuals', label: 'Backgrounds', icon: '🖼️' },
        { id: 'content', label: 'Shortcuts', icon: '🍱', isDirty: isContentDirty },
        { id: 'boundaries', label: 'Placement', icon: '🗺️', isDirty: isSettingsDirty || isBoundaryDirty }
    ];

/** Empty state shown when no room selected */
export const RoomSelectPrompt: React.FC = () => (
    <div className="h-full flex flex-col items-center justify-center text-center p-12 bg-slate-50/30">
        <div className="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-sm mb-6 animate-bounce">
            <span className="text-3xl">🧭</span>
        </div>
        <h3 className="text-lg font-black text-slate-800 uppercase tracking-widest">Navigation Required</h3>
        <p className="text-slate-400 font-bold text-sm mt-2 max-w-sm">Please select a room from the dropdown above to begin managing its content and visuals.</p>
    </div>
);
