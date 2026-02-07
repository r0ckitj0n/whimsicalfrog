import React, { useRef, useEffect, useMemo, useState } from 'react';
import { IMapArea } from '../../../../types/room.js';
import { useMapDragging } from '../../../../hooks/admin/useMapDragging.js';
import { ApiClient } from '../../../../core/ApiClient.js';
import { BoundaryIndicators } from './canvas/BoundaryIndicators.js';
import { SignLayer } from './canvas/SignLayer.js';
import { HotspotLayer } from './canvas/HotspotLayer.js';
import './MapCanvas.css';

interface IPreviewItem {
    area_selector: string;
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
    const [previewItems, setPreviewItems] = useState<IPreviewItem[]>([]);
    const [activeAreaSelectors, setActiveAreaSelectors] = useState<string[]>([]);
    const [containerHeight, setContainerHeight] = useState<number | null>(null);

    const normalizeSelector = (selector: string) => {
        const value = String(selector || '').trim().toLowerCase();
        if (!value) return '';
        return value.startsWith('.') ? value : `.${value}`;
    };

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

    // Fetch preview items from live view mappings only (no fallback sources).
    useEffect(() => {
        let cancelled = false;

        if (!roomId) {
            setPreviewItems([]);
            setActiveAreaSelectors([]);
            return () => {
                cancelled = true;
            };
        }

        const fetchPreviewItems = async () => {
            try {
                const data = await ApiClient.get<{ success: boolean; mappings?: Array<Record<string, unknown>>; data?: { mappings?: Array<Record<string, unknown>> } }>(
                    '/api/area_mappings.php',
                    { action: 'get_live_view', room: roomId }
                );
                const rows = data?.mappings || data?.data?.mappings || [];
                const normalizedRows = rows.map((row) => {
                    const mappingType = String(row.mapping_type || '').toLowerCase();
                    const mappingIsActive = row.is_active === true || row.is_active === 1 || row.is_active === '1' || row.derived === true;
                    const areaSelector = normalizeSelector(String(row.area_selector || ''));
                    let image = '';
                    if (mappingType === 'item' || mappingType === 'category') {
                        image = String(row.image_url || '');
                    } else if (
                        mappingType === 'content'
                        || mappingType === 'button'
                        || mappingType === 'link'
                        || mappingType === 'page'
                        || mappingType === 'modal'
                        || mappingType === 'action'
                    ) {
                        image = String(row.content_image || row.link_image || '');
                    }
                    return {
                        area_selector: areaSelector,
                        image,
                        mappingIsActive,
                    };
                });

                const activeSelectors = normalizedRows
                    .filter((row) => row.mappingIsActive && row.area_selector)
                    .map((row) => row.area_selector);

                const mapped = normalizedRows
                    .filter((row) => row.mappingIsActive && row.area_selector && row.image)
                    .map(({ area_selector, image }) => ({ area_selector, image }));

                if (!cancelled) {
                    setPreviewItems([]);
                    setActiveAreaSelectors([]);
                    if (mapped.length > 0) {
                        setPreviewItems(mapped);
                    }
                    if (activeSelectors.length > 0) {
                        setActiveAreaSelectors(Array.from(new Set(activeSelectors)));
                    }
                }
            } catch (err) {
                console.error('[MapCanvas] Failed to fetch preview items', err);
                if (!cancelled) {
                    setPreviewItems([]);
                    setActiveAreaSelectors([]);
                }
            }
        };

        fetchPreviewItems();

        return () => {
            cancelled = true;
        };
    }, [roomId]);

    const filteredAreas = useMemo(() => {
        if (activeAreaSelectors.length === 0) {
            return [];
        }
        const activeSet = new Set(activeAreaSelectors);
        return areas.filter((area) => activeSet.has(normalizeSelector(area.selector)));
    }, [areas, activeAreaSelectors]);

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
                areas={filteredAreas}
                signDestinations={previewItems}
                dims={dims}
                iconPanelColor={iconPanelColor}
            />

            {/* 2. Hotspots Layer */}
            <HotspotLayer
                svgRef={svgRef}
                areas={filteredAreas}
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
