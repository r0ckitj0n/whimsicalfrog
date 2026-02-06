import { useState, useCallback } from 'react';
import { IMapArea } from '../../types/room.js';

export const useMapDragging = (
    svgRef: React.RefObject<SVGSVGElement>,
    areas: IMapArea[],
    onAreasChange: (areas: IMapArea[]) => void,
    selectedIds: string[],
    onSelectionChange: (ids: string[]) => void,
    tool: 'select' | 'create',
    snapSize: number,
    isEditMode: boolean = false
) => {
    const [dragState, setDragState] = useState<{
        mode: 'none' | 'creating' | 'moving' | 'resizing';
        startX: number;
        startY: number;
        orig?: { top: number; left: number; width: number; height: number };
        handle?: string;
        targetId?: string;
        group?: Array<{ id: string; left: number; top: number }>;
    }>({ mode: 'none', startX: 0, startY: 0 });

    const clientToSvgCoords = useCallback((clientX: number, clientY: number): [number, number] => {
        if (!svgRef.current) return [0, 0];
        const pt = svgRef.current.createSVGPoint();
        pt.x = clientX;
        pt.y = clientY;
        const ctm = svgRef.current.getScreenCTM();
        if (!ctm) return [0, 0];
        const transformed = pt.matrixTransform(ctm.inverse());
        return [transformed.x, transformed.y];
    }, [svgRef]);

    const handleMouseDown = (e: React.MouseEvent) => {
        const [x, y] = clientToSvgCoords(e.clientX, e.clientY);
        const target = e.target as SVGElement;
        const handle = target.getAttribute('data-handle');
        const targetId = target.getAttribute('data-id');

        // RESIZING - Only allowed in Edit Mode
        if (handle && targetId && isEditMode) {
            const area = areas.find(a => a.id === targetId);
            if (area) {
                setDragState({
                    mode: 'resizing',
                    startX: x,
                    startY: y,
                    orig: { ...area },
                    handle,
                    targetId
                });
                onSelectionChange([targetId]);
                return;
            }
        }

        // MOVING - Only allowed in Edit Mode
        if (targetId && isEditMode) {
            const area = areas.find(a => a.id === targetId);
            if (area) {
                const isAdditive = e.metaKey || e.ctrlKey;
                let nextSelection = [targetId];
                if (isAdditive) {
                    nextSelection = selectedIds.includes(targetId)
                        ? selectedIds.filter(id => id !== targetId)
                        : [...selectedIds, targetId];
                }
                onSelectionChange(nextSelection);

                const group = nextSelection.map(id => {
                    const a = areas.find(l => l.id === id);
                    return a ? { id: a.id, left: a.left, top: a.top } : null;
                }).filter((l): l is { id: string; left: number; top: number } => l !== null);

                setDragState({
                    mode: 'moving',
                    startX: x,
                    startY: y,
                    orig: { ...area },
                    targetId,
                    group
                });
                return;
            }
        }

        if (tool === 'create') {
            const newId = String(Date.now());
            const newArea: IMapArea = {
                id: newId,
                selector: `.area-${areas.length + 1}`,
                top: y,
                left: x,
                width: 0,
                height: 0
            };
            onAreasChange([...areas, newArea]);
            onSelectionChange([newId]);
            setDragState({
                mode: 'creating',
                startX: x,
                startY: y,
                orig: { top: y, left: x, width: 0, height: 0 },
                targetId: newId
            });
            return;
        }

        onSelectionChange([]);
    };

    const handleMouseMove = (e: React.MouseEvent) => {
        if (dragState.mode === 'none') return;
        const [x, y] = clientToSvgCoords(e.clientX, e.clientY);
        const dx = x - dragState.startX;
        const dy = y - dragState.startY;

        const updatedAreas = [...areas];

        if (dragState.mode === 'moving' && dragState.group) {
            dragState.group.forEach(g => {
                const idx = updatedAreas.findIndex(a => a.id === g.id);
                if (idx > -1) {
                    let nl = g.left + dx;
                    let nt = g.top + dy;
                    if (e.shiftKey) {
                        if (Math.abs(dx) > Math.abs(dy)) nt = g.top;
                        else nl = g.left;
                    }
                    if (snapSize > 0) {
                        nl = Math.round(nl / snapSize) * snapSize;
                        nt = Math.round(nt / snapSize) * snapSize;
                    } else {
                        nl = Math.round(nl);
                        nt = Math.round(nt);
                    }
                    updatedAreas[idx] = { ...updatedAreas[idx], left: Math.max(0, nl), top: Math.max(0, nt) };
                }
            });
            onAreasChange(updatedAreas);
        } else if (dragState.mode === 'creating' && dragState.targetId && dragState.orig) {
            const idx = updatedAreas.findIndex(a => a.id === dragState.targetId);
            if (idx > -1) {
                let l = Math.min(dragState.startX, x);
                let t = Math.min(dragState.startY, y);
                let w = Math.abs(x - dragState.startX);
                let h = Math.abs(y - dragState.startY);
                if (e.shiftKey) {
                    if (w > h) h = 1; else w = 1;
                }
                if (snapSize > 0) {
                    l = Math.round(l / snapSize) * snapSize;
                    t = Math.round(t / snapSize) * snapSize;
                    w = Math.max(1, Math.round(w / snapSize) * snapSize);
                    h = Math.max(1, Math.round(h / snapSize) * snapSize);
                } else {
                    l = Math.round(l);
                    t = Math.round(t);
                    w = Math.round(w);
                    h = Math.round(h);
                }
                updatedAreas[idx] = { ...updatedAreas[idx], left: l, top: t, width: w, height: h };
                onAreasChange(updatedAreas);
            }
        } else if (dragState.mode === 'resizing' && dragState.targetId && dragState.orig && dragState.handle) {
            const idx = updatedAreas.findIndex(a => a.id === dragState.targetId);
            if (idx > -1) {
                const h = dragState.handle;
                const { top, left, width, height } = dragState.orig;
                const right = left + width;
                const bottom = top + height;
                let nx1 = left, ny1 = top, nx2 = right, ny2 = bottom;

                if (h.includes('n')) ny1 = Math.min(y, bottom - 1);
                if (h.includes('s')) ny2 = Math.max(y, top + 1);
                if (h.includes('w')) nx1 = Math.min(x, right - 1);
                if (h.includes('e')) nx2 = Math.max(x, left + 1);

                let nl = nx1, nt = ny1, nw = nx2 - nx1, nh = ny2 - ny1;
                if (e.shiftKey) {
                    if (Math.abs(nw) > Math.abs(nh)) nh = 1; else nw = 1;
                }
                if (snapSize > 0) {
                    nl = Math.round(nl / snapSize) * snapSize;
                    nt = Math.round(nt / snapSize) * snapSize;
                    nw = Math.max(1, Math.round(nw / snapSize) * snapSize);
                    nh = Math.max(1, Math.round(nh / snapSize) * snapSize);
                } else {
                    nl = Math.round(nl);
                    nt = Math.round(nt);
                    nw = Math.round(nw);
                    nh = Math.round(nh);
                }
                updatedAreas[idx] = { ...updatedAreas[idx], left: nl, top: nt, width: nw, height: nh };
                onAreasChange(updatedAreas);
            }
        }
    };

    const handleMouseUp = () => {
        setDragState({ mode: 'none', startX: 0, startY: 0 });
    };

    return {
        dragState,
        handleMouseDown,
        handleMouseMove,
        handleMouseUp
    };
};
