import React from 'react';
import { MapSidebar } from '../../../room-map/partials/MapSidebar.js';
import { MapCanvas } from '../../../room-map/partials/MapCanvas.js';
import { IMapArea, IRoomMap } from '../../../../../types/room.js';

interface BoundariesTabProps {
    selectedRoom: string;
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
    onSelectionChange: (ids: string[]) => void;
    savedMaps: IRoomMap[];
    currentMapId: string | number | undefined;
    onLoadActive: () => void;
    onSaveMap: () => void;
    onToggleTool: (tool: 'select' | 'create') => void;
    activeTool: 'select' | 'create';
    snapSize: number;
    onSnapSizeChange: (size: number) => void;
    onActivateMap: (id: string | number) => void;
    onDeleteMap: (id: string | number) => void;
    onRefreshSaved: () => void;
    onLoadMap: (id: string | number) => void;
    onRenameMap: (id: string | number) => void;
    getImageUrl: (bg: { webp_filename?: string; image_filename?: string }) => string;
    activeBackground: import('../../../../../types/backgrounds.js').IBackground | null;
    isEditMode: boolean;
    previewKey: number;
}

export const BoundariesTab: React.FC<BoundariesTabProps> = ({
    selectedRoom,
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
    onSelectionChange,
    savedMaps,
    currentMapId,
    onLoadActive,
    onSaveMap,
    onToggleTool,
    activeTool,
    snapSize,
    onSnapSizeChange,
    onActivateMap,
    onDeleteMap,
    onRefreshSaved,
    onLoadMap,
    onRenameMap,
    getImageUrl,
    activeBackground,
    isEditMode,
    previewKey
}) => {
    const resolvedBackgroundUrl = bgUrl || getImageUrl(activeBackground || {});

    return (
        <div className="flex-1 h-full flex overflow-hidden min-h-0">
            <MapSidebar
                bgUrl={resolvedBackgroundUrl}
                onBgUrlChange={onBgUrlChange}
                iconPanelColor={iconPanelColor}
                onIconPanelColorChange={onIconPanelColorChange}
                renderContext={renderContext}
                onRenderContextChange={onRenderContextChange}
                targetAspectRatio={targetAspectRatio}
                onTargetAspectRatioChange={onTargetAspectRatioChange}
                areas={areas}
                onAreasChange={onAreasChange}
                selectedIds={selectedIds}
                selectedMapId={currentMapId}
                onSelectionChange={onSelectionChange}
                savedMaps={savedMaps}
                onLoadActive={onLoadActive}
                onSaveMap={onSaveMap}
                onToggleTool={onToggleTool}
                activeTool={activeTool}
                snapSize={snapSize}
                onSnapSizeChange={onSnapSizeChange}
                onClearAll={() => onAreasChange([])}
                onActivateMap={onActivateMap}
                onDeleteMap={onDeleteMap}
                onRefreshSaved={onRefreshSaved}
                onLoadMap={onLoadMap}
                onRenameMap={onRenameMap}
            />
            <main className="flex-1 h-full relative bg-slate-900/50 overflow-y-auto overflow-x-hidden min-h-0 p-1 pointer-events-auto">
                <div className="flex items-start justify-center min-h-full pointer-events-auto">
                    <MapCanvas
                        bgUrl={resolvedBackgroundUrl}
                        areas={areas}
                        onAreasChange={onAreasChange}
                        selectedIds={selectedIds}
                        onSelectionChange={onSelectionChange}
                        tool={activeTool}
                        snapSize={snapSize}
                        fitEntire={renderContext === 'fullscreen'}
                        aspectRatio={targetAspectRatio}
                        roomId={selectedRoom}
                        renderContext={renderContext}
                        isEditMode={isEditMode}
                        mapId={currentMapId}
                        iconPanelColor={iconPanelColor}
                        key={`${selectedRoom}-${previewKey}`}
                    />
                </div>
            </main>
        </div>
    );
};
