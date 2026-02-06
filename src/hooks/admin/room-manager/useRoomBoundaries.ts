import { useState, useCallback, useMemo } from 'react';
import { IMapArea, IRoomMapEditorHook, IRoomBoundariesHook, IRoomMap } from '../../../types/room.js';
import { isDraftDirty } from '../../../core/utils.js';
import { useModalContext } from '../../../context/ModalContext.js';

export const useRoomBoundaries = (selectedRoom: string, boundaries: IRoomMapEditorHook): IRoomBoundariesHook => {
    const { prompt: promptModal, confirm: confirmModal } = useModalContext();
    const [areas, setAreas] = useState<IMapArea[]>([]);
    const [lastSavedAreas, setLastSavedAreas] = useState<IMapArea[]>([]);
    const [renderContext, setRenderContext] = useState<string>('modal');
    const [bgUrl, setBgUrl] = useState<string>('');
    const [iconPanelColor, setIconPanelColor] = useState<string>('transparent');
    const [targetAspectRatio, setTargetAspectRatio] = useState<number>(1024 / 768);
    const [currentMapId, setCurrentMapId] = useState<string | number | undefined>(undefined);
    const [previewKey, setPreviewKey] = useState(0);

    const [initialSettings, setInitialSettings] = useState({
        renderContext: 'modal',
        bgUrl: '',
        iconPanelColor: 'transparent',
        targetAspectRatio: 1024 / 768
    });

    const handleSaveBoundaries = useCallback(async () => {
        if (!selectedRoom) return;
        const name = await promptModal({
            title: 'Save Map',
            message: 'Enter map name:',
            input: { defaultValue: `Map ${new Date().toLocaleString()}` }
        });
        if (!name) return;
        const res = await boundaries.saveMap(selectedRoom, name, areas);
        if (res.success) {
            if (window.WFToast) window.WFToast.success('Map saved');
            setLastSavedAreas([...areas]);
            boundaries.fetchSavedMaps(selectedRoom);
        }
    }, [selectedRoom, areas, promptModal, boundaries]);

    const handleSaveSettings = useCallback(async (onSuccess?: () => void) => {
        if (!selectedRoom) return;
        const res = await boundaries.updateRoomSettings(selectedRoom, {
            render_context: renderContext,
            background_url: bgUrl,
            icon_panel_color: iconPanelColor,
            target_aspect_ratio: targetAspectRatio
        });
        if (res.success) {
            if (window.WFToast) window.WFToast.success('Settings updated');
            setInitialSettings({ renderContext, bgUrl, iconPanelColor, targetAspectRatio });
            setPreviewKey((prev: number) => prev + 1);
            onSuccess?.();
        }
    }, [selectedRoom, renderContext, bgUrl, iconPanelColor, targetAspectRatio, boundaries]);

    const isSettingsDirty = useMemo(() =>
        renderContext !== initialSettings.renderContext ||
        bgUrl !== initialSettings.bgUrl ||
        iconPanelColor !== initialSettings.iconPanelColor ||
        Math.abs(targetAspectRatio - initialSettings.targetAspectRatio) > 0.0001
        , [renderContext, bgUrl, iconPanelColor, targetAspectRatio, initialSettings]);

    const isBoundaryDirty = useMemo(() => isDraftDirty(areas, lastSavedAreas), [areas, lastSavedAreas]);

    const handleDeleteMap = useCallback(async (id: string | number) => {
        const confirmed = await confirmModal({ title: 'Delete Map', message: 'Delete?', confirmText: 'Delete', confirmStyle: 'danger' });
        if (confirmed) {
            const res = await boundaries.deleteMap(id);
            if (res.success) {
                if (window.WFToast) window.WFToast.success('Deleted');
                boundaries.fetchSavedMaps(selectedRoom);
            }
        }
    }, [selectedRoom, boundaries, confirmModal]);

    const handleRenameMap = useCallback(async (id: string | number) => {
        const map = boundaries.savedMaps.find((m: IRoomMap) => String(m.id) === String(id));
        if (!map) return;
        const newName = await promptModal({ title: 'Rename', message: 'Name:', input: { defaultValue: map.map_name } });
        if (newName && newName !== map.map_name) {
            const res = await boundaries.renameMap(id, newName);
            if (res.success) {
                if (window.WFToast) window.WFToast.success('Renamed');
                boundaries.fetchSavedMaps(selectedRoom);
            }
        }
    }, [selectedRoom, boundaries, promptModal]);

    const handleLoadMap = useCallback((id: string | number) => {
        const map = boundaries.savedMaps.find((m: IRoomMap) => String(m.id) === String(id));
        if (!map) return;
        const rawCoords = map.coordinates;
        try {
            const coords = typeof rawCoords === 'string' ? JSON.parse(rawCoords) : rawCoords;
            const loaded = (Array.isArray(coords) ? coords : (coords?.rectangles || coords?.polygons || [])).map((a: Partial<IMapArea>, idx: number) => ({
                ...a,
                id: a.id || String(Date.now() + idx),
                selector: a.selector || `.area-${idx + 1}`,
                top: a.top ?? 0,
                left: a.left ?? 0,
                width: a.width ?? 100,
                height: a.height ?? 100
            })) as IMapArea[];
            setAreas(loaded);
            setCurrentMapId(map.id);
        } catch (err) {
            console.error('[Boundaries] Failed to load map coordinates', err);
        }
    }, [boundaries]);

    return {
        areas, setAreas,
        lastSavedAreas, setLastSavedAreas,
        renderContext, setRenderContext,
        bgUrl, setBgUrl,
        iconPanelColor, setIconPanelColor,
        targetAspectRatio, setTargetAspectRatio,
        currentMapId, setCurrentMapId,
        previewKey, setPreviewKey,
        initialSettings, setInitialSettings,
        handleSaveBoundaries,
        handleSaveSettings,
        isSettingsDirty,
        isBoundaryDirty,
        handleDeleteMap,
        handleRenameMap,
        handleLoadMap
    };
};
