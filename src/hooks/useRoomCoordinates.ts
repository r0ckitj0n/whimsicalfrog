import { useState, useCallback, useEffect, useMemo } from 'react';
import { ApiClient } from '../core/ApiClient.js';
import logger from '../core/logger.js';
import { IDoorCoordinate, ICoordinatesResponse } from '../types/room.js';
import { PAGE } from '../core/constants.js';

// Re-export for backward compatibility
export type { IDoorCoordinate } from '../types/room.js';

interface IRoomSettings {
    render_context?: string;
    target_aspect_ratio?: number | string | null;
    icon_panel_color?: string;
}

export const useRoomCoordinates = (roomType: string = '0') => {
    const [coordinates, setCoordinates] = useState<IDoorCoordinate[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [containerSize, setContainerSize] = useState({
        width: typeof window !== 'undefined' ? window.innerWidth : 0,
        height: typeof window !== 'undefined' ? window.innerHeight : 0
    });
    const defaultContext = (roomType === 'A' || roomType === '0' || roomType === 'main') ? 'fullscreen' : 'modal';
    const [renderContext, setRenderContext] = useState<string>(defaultContext);
    const [roomSettings, setRoomSettings] = useState<IRoomSettings>({});

    const normalizeApiCoordinates = useCallback((raw: unknown): IDoorCoordinate[] => {
        let parsed = raw;

        for (let i = 0; i < 4 && typeof parsed === 'string'; i += 1) {
            try {
                parsed = JSON.parse(parsed);
            } catch {
                break;
            }
        }

        const obj = (parsed && typeof parsed === 'object' && !Array.isArray(parsed))
            ? parsed as Record<string, unknown>
            : null;

        const list = Array.isArray(parsed)
            ? parsed
            : (Array.isArray(obj?.rectangles)
                ? obj?.rectangles
                : (Array.isArray(obj?.polygons)
                    ? obj?.polygons
                    : (Array.isArray(obj?.coordinates)
                        ? obj?.coordinates
                        : [])));

        if (!Array.isArray(list)) {
            return [];
        }

        return list
            .map((entry, idx) => {
                if (!entry || typeof entry !== 'object') return null;
                const coord = entry as Partial<IDoorCoordinate> & { id?: string | number };
                const rawSelector = coord.selector || coord.id || `area-${idx + 1}`;
                const cleanSelector = String(rawSelector).replace(/^\.+/, '');
                return {
                    ...coord,
                    selector: `.${cleanSelector}`
                } as IDoorCoordinate;
            })
            .filter((coord): coord is IDoorCoordinate => !!coord);
    }, []);

    // Compute originalSize from database settings instead of hardcoding
    const originalSize = useMemo(() => {
        const context = renderContext;
        const targetRatio = roomSettings.target_aspect_ratio;

        // Parse target_aspect_ratio if available
        const ratio = typeof targetRatio === 'number'
            ? targetRatio
            : (parseFloat(String(targetRatio)) || null);

        if (context === 'fixed' && ratio) {
            // For fixed mode, derive dimensions from aspect ratio
            // Use 1024 as base width for consistency with Room Manager
            const baseWidth = 1024;
            const height = Math.round(baseWidth / ratio);
            return { width: baseWidth, height };
        }

        if (context === 'fullscreen') {
            // Fullscreen mode - use high-res dimensions
            // If ratio is provided, use it; otherwise default 1280x896
            if (ratio) {
                return { width: 1280, height: Math.round(1280 / ratio) };
            }
            return { width: 1280, height: 896 };
        }

        // Modal mode - standard dimensions
        if (ratio) {
            return { width: 1024, height: Math.round(1024 / ratio) };
        }
        return { width: 1024, height: 768 };
    }, [renderContext, roomSettings.target_aspect_ratio]);

    const fetchCoordinates = useCallback(async () => {
        let apiRoom = roomType;
        if (roomType === PAGE.ROOM_MAIN || roomType === 'main') apiRoom = '0';

        // 1. Fetch Room Settings to get render_context, target_aspect_ratio, icon_panel_color
        try {
            const settingsRes = await ApiClient.get<{ success: boolean; room: Record<string, any> }>('/api/room_settings.php', {
                action: 'get_room',
                room_number: apiRoom
            });
            if (settingsRes?.success && settingsRes.room) {
                const room = settingsRes.room;
                setRenderContext(room.render_context || 'modal');
                setRoomSettings({
                    render_context: room.render_context || 'modal',
                    target_aspect_ratio: room.target_aspect_ratio,
                    icon_panel_color: room.icon_panel_color
                });
            } else {
                // Fallback for known high-res rooms if settings not found
                if (['A', '0', 'X', 'S'].includes(apiRoom)) setRenderContext('fullscreen');
            }
        } catch (e) {
            console.error('[useRoomCoordinates] Failed to fetch room settings', e);
        }

        // 1. Try to hydrate from DOM first (Parity with PHP shell)
        const shellId = roomType === 'A' ? 'landingPage' : 'mainRoomPage';
        const shellEl = document.getElementById(shellId);
        if (shellEl) {
            const rawCoords = shellEl.getAttribute('data-coords');
            if (rawCoords) {
                try {
                    const parsed = JSON.parse(rawCoords);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        setCoordinates(parsed.map((coord: IDoorCoordinate, idx: number) => {
                            // Handle missing selector - fallback to id or generate one
                            const rawSelector = coord.selector || coord.id || `area-${idx + 1}`;
                            const selectorStr = String(rawSelector);
                            return {
                                ...coord,
                                selector: selectorStr.startsWith('.') ? selectorStr : `.${selectorStr}`
                            };
                        }));
                        // We still want to fetch fresh ones from API to be safe, 
                        // but we have immediate data now.
                    }
                } catch (e) {
                    console.warn('[useRoomCoordinates] Failed to parse data-coords from shell', e);
                }
            }
        }

        setIsLoading(true);
        try {
            const data = await ApiClient.get<ICoordinatesResponse>('/api/area_mappings.php', {
                action: 'get_room_coordinates',
                room: apiRoom
            });

            const coords = normalizeApiCoordinates(data.coordinates ?? data.data?.coordinates ?? []);
            setCoordinates(coords);
        } catch (err) {
            console.error('[useRoomCoordinates] Failed to fetch coordinates', err);
            logger.error('[useRoomCoordinates] Failed to fetch coordinates', err);
        } finally {
            setIsLoading(false);
        }
    }, [roomType, normalizeApiCoordinates]);

    useEffect(() => {
        fetchCoordinates();
    }, [fetchCoordinates]);

    const getScaledStyles = useCallback((coord: IDoorCoordinate) => {
        if (containerSize.width === 0 || containerSize.height === 0) {
            return {};
        }

        // FILL mode: Scale X and Y independently to fill 100% width and 100% height
        // This matches background-size: 100% 100%
        const scaleX = containerSize.width / originalSize.width;
        const scaleY = containerSize.height / originalSize.height;

        const style = {
            position: 'absolute' as const,
            top: `${coord.top * scaleY}px`,
            left: `${coord.left * scaleX}px`,
            width: `${coord.width * scaleX}px`,
            // Height is intentionally NOT set - allows images to extend beyond coordinate bottom
            zIndex: 'var(--wf-z-elevated)',
            opacity: 1,
            visibility: 'visible' as const,
            display: 'block' as const,
            '--door-top': `${coord.top * scaleY}px`,
            '--door-left': `${coord.left * scaleX}px`,
            '--door-width': `${coord.width * scaleX}px`,
            '--door-height': `${coord.height * scaleY}px`
        } as React.CSSProperties;

        return style;
    }, [containerSize, originalSize, renderContext]);

    return {
        coordinates,
        isLoading,
        containerSize,
        setContainerSize,
        getScaledStyles,
        refresh: fetchCoordinates,
        renderContext,
        roomSettings
    };
};
