import React, { useRef, useEffect, useState } from 'react';
import { IMapArea } from '../../../../types/room.js';
import { useMapDragging } from '../../../../hooks/admin/useMapDragging.js';
import { ApiClient } from '../../../../core/ApiClient.js';
import { BoundaryIndicators } from './canvas/BoundaryIndicators.js';
import { SignLayer } from './canvas/SignLayer.js';
import { HotspotLayer } from './canvas/HotspotLayer.js';
import './MapCanvas.css';

interface ISignDestination {
    area_selector: string;
    label: string;
    target: string;
    image: string;
}

interface MapCanvasProps {
    bgUrl: string;
    areas: IMapArea[];
    onAreasChange: (areas: IMapArea[]) => void;
    selectedIds: string[];
    onSelectionChange: (ids: string[]) => void;
    tool: 'select' | 'create';
    snapSize: number;
    fitEntire: boolean;
    aspectRatio?: number;
    roomId?: string;
    renderContext?: string;
    isEditMode?: boolean;
    mapId?: string | number;
    iconPanelColor?: string;
}

export const MapCanvas: React.FC<MapCanvasProps> = ({
    bgUrl,
    areas,
    onAreasChange,
    selectedIds,
    onSelectionChange,
    tool,
    snapSize,
    fitEntire,
    aspectRatio,
    roomId,
    renderContext,
    isEditMode = false,
    mapId,
    iconPanelColor = 'transparent'
}) => {
    const svgRef = useRef<SVGSVGElement>(null);
    const containerRef = useRef<HTMLDivElement>(null);
    const [signDestinations, setSignDestinations] = useState<ISignDestination[]>([]);
    const [containerHeight, setContainerHeight] = useState<number | null>(null);

    // Calculate container height based on width and aspect ratio to enable scrolling
    useEffect(() => {
        const container = containerRef.current;
        if (!container) return;

        const updateHeight = () => {
            const width = container.clientWidth;
            // Calculate height based on width and image aspect ratio (1280/896)
            const imageAspectRatio = 1280 / 896;
            const calculatedHeight = width / imageAspectRatio;
            setContainerHeight(calculatedHeight);
        };

        updateHeight();
        const resizeObserver = new ResizeObserver(updateHeight);
        resizeObserver.observe(container);

        return () => resizeObserver.disconnect();
    }, [bgUrl]);

    const {
        handleMouseDown,
        handleMouseMove,
        handleMouseUp
    } = useMapDragging(
        svgRef,
        areas,
        onAreasChange,
        selectedIds,
        onSelectionChange,
        tool,
        snapSize,
        isEditMode
    );

    // Fetch sign destinations for the current room
    useEffect(() => {
        if (!roomId) {
            setSignDestinations([]);
            return;
        }

        const fetchSignDestinations = async () => {
            try {
                const data = await ApiClient.get<{ destinations: ISignDestination[] }>(
                    '/api/area_mappings.php',
                    { action: 'door_sign_destinations', room: roomId }
                );
                if (data?.destinations) {
                    setSignDestinations(data.destinations);
                }
            } catch (err) {
                console.error('[MapCanvas] Failed to fetch sign destinations', err);
            }
        };

        fetchSignDestinations();
    }, [roomId]);

    const tar = typeof aspectRatio === 'number' ? aspectRatio : (parseFloat(String(aspectRatio)) || (1024 / 768));
    const isFullScale = roomId === 'A' || roomId === '0' || tar > 1.4;
    const dims = isFullScale ? { w: 1280, h: 896 } : { w: 1024, h: 768 };
    const currentAspectRatio = aspectRatio || (dims.w / dims.h);

    const finalBgUrl = bgUrl ? (bgUrl.startsWith('http') || bgUrl.startsWith('/') ? bgUrl : `/images/${bgUrl}`) : '';
    const containerStyle: React.CSSProperties = {
        '--map-aspect-ratio': String(currentAspectRatio),
        backgroundImage: finalBgUrl ? `url(${finalBgUrl})` : 'none',
        backgroundSize: 'contain',
        backgroundPosition: 'top left',
        backgroundRepeat: 'no-repeat',
        height: containerHeight ? `${containerHeight}px` : 'auto',
        minHeight: containerHeight ? `${containerHeight}px` : undefined
    } as React.CSSProperties;

    const headerHeight = 64;
    const footerHeight = 64;

    return (
        <div
            ref={containerRef}
            className="relative bg-black rounded-xl overflow-hidden shadow-2xl border border-gray-800 map-canvas-container"
            style={containerStyle}
        >
            {/* 0. Boundary Indicators - only shown in fullscreen mode */}
            {renderContext === 'fullscreen' && (
                <BoundaryIndicators
                    dims={dims}
                    headerHeight={headerHeight}
                    footerHeight={footerHeight}
                />
            )}

            {/* 1. Live Sign Images Layer */}
            <SignLayer
                areas={areas}
                signDestinations={signDestinations}
                dims={dims}
            />

            {/* 2. Hotspots Layer */}
            <HotspotLayer
                svgRef={svgRef}
                areas={areas}
                selectedIds={selectedIds}
                isEditMode={isEditMode}
                dims={dims}
                onMouseDown={handleMouseDown}
                onMouseMove={handleMouseMove}
                onMouseUp={handleMouseUp}
            />

            {/* 3. Fallback: Show message when no room selected */}
            {!roomId && (
                <div className="absolute inset-0 flex items-center justify-center text-gray-600 font-medium italic z-30 pointer-events-none">
                    Select a room to load preview
                </div>
            )}
        </div>
    );
};
