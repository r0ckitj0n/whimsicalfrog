import React from 'react';
import { IMapArea, IRoomMap } from '../../../../types/room.js';
import { RoomEnvironmentSection } from './sidebar/RoomEnvironmentSection.js';
import { ToolsetSection } from './sidebar/ToolsetSection.js';
import { SnapGridSection } from './sidebar/SnapGridSection.js';
import { AreaListSection } from './sidebar/AreaListSection.js';
import { SavedMapsSection } from './sidebar/SavedMapsSection.js';

interface MapSidebarProps {
    bgUrl: string;
    onBgUrlChange: (val: string) => void;
    iconPanelColor: string;
    onIconPanelColorChange: (val: string) => void;
    renderContext: string;
    onRenderContextChange: (val: string) => void;
    targetAspectRatio: number;
    onTargetAspectRatioChange: (val: number) => void;
    areas: IMapArea[];
    onAreasChange: (areas: IMapArea[]) => void;
    selectedIds: string[];
    selectedMapId?: string | number;
    onSelectionChange: (ids: string[]) => void;
    savedMaps: IRoomMap[];
    onLoadActive: () => void;
    onSaveMap: () => void;
    onToggleTool: (tool: 'select' | 'create') => void;
    activeTool: 'select' | 'create';
    snapSize: number;
    onSnapSizeChange: (size: number) => void;
    onClearAll: () => void;
    onActivateMap: (id: string | number) => void;
    onDeleteMap: (id: string | number) => void;
    onRefreshSaved: () => void;
    onLoadMap: (id: string | number) => void;
    onRenameMap: (id: string | number) => void;
}

export const MapSidebar: React.FC<MapSidebarProps> = ({
    bgUrl,
    onBgUrlChange,
    iconPanelColor,
    onIconPanelColorChange,
    renderContext,
    onRenderContextChange,
    targetAspectRatio,
    onTargetAspectRatioChange,
    areas,
    onAreasChange,
    selectedIds,
    selectedMapId,
    onSelectionChange,
    savedMaps,
    onLoadActive,
    onSaveMap,
    onToggleTool,
    activeTool,
    snapSize,
    onSnapSizeChange,
    onClearAll,
    onActivateMap,
    onDeleteMap,
    onRefreshSaved,
    onLoadMap,
    onRenameMap
}) => {
    return (
        <aside
            className="w-80 h-full flex-shrink-0 bg-gray-50 border-r overflow-y-auto p-4 space-y-6 scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-transparent"
            style={{ scrollbarGutter: 'stable' }}
        >
            <RoomEnvironmentSection
                bgUrl={bgUrl}
                onBgUrlChange={onBgUrlChange}
                iconPanelColor={iconPanelColor}
                onIconPanelColorChange={onIconPanelColorChange}
                renderContext={renderContext}
                onRenderContextChange={onRenderContextChange}
                targetAspectRatio={targetAspectRatio}
                onTargetAspectRatioChange={onTargetAspectRatioChange}
            />

            <SavedMapsSection
                savedMaps={savedMaps}
                selectedMapId={selectedMapId}
                onRefreshSaved={onRefreshSaved}
                onActivateMap={onActivateMap}
                onDeleteMap={onDeleteMap}
                onRenameMap={onRenameMap}
                onLoadMap={onLoadMap}
            />

            <ToolsetSection
                onLoadActive={onLoadActive}
                onSaveMap={onSaveMap}
                onToggleTool={onToggleTool}
                activeTool={activeTool}
                onClearAll={onClearAll}
            />

            <AreaListSection
                areas={areas}
                onAreasChange={onAreasChange}
                selectedIds={selectedIds}
                onSelectionChange={onSelectionChange}
            />

            <SnapGridSection
                snapSize={snapSize}
                onSnapSizeChange={onSnapSizeChange}
            />
        </aside>
    );
};

