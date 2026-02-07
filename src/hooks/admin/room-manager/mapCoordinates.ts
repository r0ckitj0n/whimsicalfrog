import { IMapArea } from '../../../types/room.js';

const parseJsonString = (value: string): unknown => {
    try {
        return JSON.parse(value);
    } catch {
        return value;
    }
};

const unwrapRawCoordinates = (raw: unknown): unknown => {
    let current = raw;
    for (let i = 0; i < 4; i += 1) {
        if (typeof current !== 'string') break;
        const next = parseJsonString(current);
        if (next === current) break;
        current = next;
    }

    if (
        current &&
        typeof current === 'object' &&
        !Array.isArray(current)
    ) {
        const obj = current as Record<string, unknown>;

        if (typeof obj.rectangles === 'string') {
            obj.rectangles = unwrapRawCoordinates(obj.rectangles);
        }

        if (typeof obj.polygons === 'string') {
            obj.polygons = unwrapRawCoordinates(obj.polygons);
        }
    }

    return current;
};

export const normalizeMapAreas = (rawCoords: unknown): IMapArea[] => {
    const coords = unwrapRawCoordinates(rawCoords);
    const base = Array.isArray(coords)
        ? coords
        : ((coords as { rectangles?: unknown; polygons?: unknown } | null)?.rectangles
            || (coords as { rectangles?: unknown; polygons?: unknown } | null)?.polygons
            || []);

    if (!Array.isArray(base)) return [];

    return base.map((a: Partial<IMapArea>, idx: number) => ({
        ...a,
        id: a.id || String(Date.now() + idx),
        selector: a.selector || `.area-${idx + 1}`,
        top: a.top ?? 0,
        left: a.left ?? 0,
        width: a.width ?? 100,
        height: a.height ?? 100
    })) as IMapArea[];
};
