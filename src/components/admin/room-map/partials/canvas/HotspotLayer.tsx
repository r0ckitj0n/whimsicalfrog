import React from 'react';
import { IMapArea } from '../../../../../types/room.js';

interface HotspotLayerProps {
    svgRef: React.RefObject<SVGSVGElement>;
    areas: IMapArea[];
    selectedIds: string[];
    isEditMode: boolean;
    dims: { w: number, h: number };
    stretchToFill?: boolean;
    onMouseDown: (e: React.MouseEvent) => void;
    onMouseMove: (e: React.MouseEvent) => void;
    onMouseUp: (e: React.MouseEvent) => void;
}

export const HotspotLayer: React.FC<HotspotLayerProps> = ({
    svgRef,
    areas,
    selectedIds,
    isEditMode,
    dims,
    stretchToFill = false,
    onMouseDown,
    onMouseMove,
    onMouseUp
}) => {
    const getHandles = (area: IMapArea) => {
        const { left: x1, top: y1, width, height } = area;
        const x2 = x1 + width, y2 = y1 + height;
        const cx = (x1 + x2) / 2, cy = (y1 + y2) / 2;
        return [
            { name: 'nw', x: x1, y: y1 }, { name: 'n', x: cx, y: y1 }, { name: 'ne', x: x2, y: y1 },
            { name: 'e', x: x2, y: cy }, { name: 'se', x: x2, y: y2 }, { name: 's', x: cx, y: y2 },
            { name: 'sw', x: x1, y: y2 }, { name: 'w', x: x1, y: cy },
        ];
    };

    return (
        <svg
            ref={svgRef}
            className="absolute inset-0 w-full h-full cursor-crosshair touch-none overflow-visible"
            style={{
                zIndex: isEditMode ? 30 : 10,
                pointerEvents: isEditMode ? 'auto' : 'none'
            }}
            viewBox={`0 0 ${dims.w} ${dims.h}`}
            onMouseDown={onMouseDown}
            onMouseMove={onMouseMove}
            onMouseUp={onMouseUp}
            onMouseLeave={onMouseUp}
            preserveAspectRatio={stretchToFill ? 'none' : 'xMinYMin meet'}
        >
            {areas.map(area => (
                <g key={area.id}>
                    <rect
                        x={area.left}
                        y={area.top}
                        width={area.width}
                        height={area.height}
                        data-id={area.id}
                        style={{
                            fill: isEditMode
                                ? (selectedIds.includes(area.id) ? 'rgba(99, 102, 241, 0.4)' : 'rgba(255, 255, 255, 0.15)')
                                : 'rgba(0,0,0,0)',
                            stroke: isEditMode ? '#ffffff' : 'var(--brand-primary)',
                            strokeWidth: isEditMode ? 3 : 2,
                            strokeDasharray: isEditMode ? '8 4' : 'none'
                        }}
                        className={`
                            ${selectedIds.includes(area.id) ? 'fill-[var(--brand-primary)]/60 stroke-white stroke-[3px]' : 'hover:opacity-80'} 
                            ${isEditMode ? 'cursor-move' : 'cursor-pointer'}`}
                    />
                    {selectedIds.includes(area.id) && selectedIds.length === 1 && getHandles(area).map(h => (
                        <rect
                            key={h.name}
                            x={h.x - 5}
                            y={h.y - 5}
                            width={10}
                            height={10}
                            fill="white"
                            stroke="var(--brand-primary)"
                            strokeWidth="2"
                            data-id={area.id}
                            data-handle={h.name}
                            className={`cursor-nwse-resize ${isEditMode ? 'opacity-100' : 'opacity-0 pointer-events-none'}`}
                            onMouseDown={onMouseDown}
                        />
                    ))}
                </g>
            ))}
        </svg>
    );
};
