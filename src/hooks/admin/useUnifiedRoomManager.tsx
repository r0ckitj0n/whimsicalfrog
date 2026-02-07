import { useState, useEffect, useMemo, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import { useAreaMappings } from '../../hooks/admin/useAreaMappings.js';
import { useBackgrounds } from '../../hooks/admin/useBackgrounds.js';
import { useRoomMapEditor } from '../../hooks/admin/useRoomMapEditor.js';
import { useModalContext } from '../../context/ModalContext.js';
import { useCategories } from '../../hooks/admin/useCategories.js';
import {
    IRoomData,
    IRoomConnectionsResponse,
    IMapArea,
    IUnifiedRoomManagerHook,
    IRoomMap,
    ISitemapEntry
} from '../../types/room.js';

// Sub-Hooks
import { useRoomOverview } from './room-manager/useRoomOverview.js';
import { useRoomNavigation } from './room-manager/useRoomNavigation.js';
import { useRoomVisuals } from './room-manager/useRoomVisuals.js';
import { useRoomShortcuts } from './room-manager/useRoomShortcuts.js';
import { useRoomBoundaries } from './room-manager/useRoomBoundaries.js';

interface UseUnifiedRoomManagerProps {
    onClose?: () => void;
    initialTab?: IUnifiedRoomManagerHook['activeTab'];
}

export const useUnifiedRoomManager = ({
    initialTab = 'overview'
}: UseUnifiedRoomManagerProps): IUnifiedRoomManagerHook => {
    const [activeTab, setActiveTab] = useState(initialTab);
    const [selectedRoom, setSelectedRoom] = useState<string>('');
    const { confirm: confirmModal } = useModalContext();

    // Base Domain Hooks
    const mappings = useAreaMappings();
    const backgrounds = useBackgrounds();
    const boundaries = useRoomMapEditor();
    const categoriesHook = useCategories();

    // Orchestrated Sub-Hooks
    const overview = useRoomOverview();
    const navigation = useRoomNavigation();
    const visuals = useRoomVisuals();
    const shortcuts = useRoomShortcuts(selectedRoom, mappings);
    const boundariesTab = useRoomBoundaries(selectedRoom, boundaries);

    // Navigation Tab State (Legacy Bridge - can be moved to sub-hook fully if needed)
    const [selectedIds, setSelectedIds] = useState<string[]>([]);
    const [activeTool, setActiveTool] = useState<'select' | 'create'>('select');
    const [snapSize, setSnapSize] = useState(5);
    const [isEditMode, setIsEditMode] = useState(false);

    // Initial hydration - runs once on mount
    // eslint-disable-next-line react-hooks/exhaustive-deps
    useEffect(() => {
        mappings.fetchLookupData();
        backgrounds.fetchRooms();
        boundaries.fetchRooms();
        overview.fetchAllRooms();
        navigation.fetchConnections();
    }, []);

    // Room Change Orchestrator
    const handleRoomChange = useCallback(async (roomId: string) => {
        setSelectedRoom(roomId);
        if (!roomId) {
            boundariesTab.setAreas([]);
            visuals.setPreviewImage(null);
            return;
        }

        mappings.fetchMappings(roomId);
        mappings.fetchAvailableAreas(roomId);
        backgrounds.fetchBackgroundsForRoom(roomId);

        const [settings, activeMap] = await Promise.all([
            boundaries.getRoomSettings(roomId),
            boundaries.loadActiveMap(roomId)
        ]);

        if (settings) {
            const context = settings.render_context || 'modal';
            const ratio = parseFloat(String(settings.target_aspect_ratio)) || (context === 'fullscreen' ? 1280 / 896 : 1024 / 768);

            boundariesTab.setRenderContext(context);
            boundariesTab.setBgUrl(settings.background_url || '');
            boundariesTab.setIconPanelColor(settings.icon_panel_color || 'transparent');
            boundariesTab.setTargetAspectRatio(ratio);

            boundariesTab.setInitialSettings({
                renderContext: context,
                bgUrl: settings.background_url || '',
                iconPanelColor: settings.icon_panel_color || 'transparent',
                targetAspectRatio: ratio
            });

            overview.setRoomForm(prev => ({
                ...prev,
                ...settings,
                room_number: roomId
            }));
        } else {
            boundariesTab.setInitialSettings({
                renderContext: 'modal',
                bgUrl: '',
                iconPanelColor: 'transparent',
                targetAspectRatio: 1024 / 768
            });
        }

        if (activeMap) {
            boundariesTab.setCurrentMapId(activeMap.id);
            const rawCoords = activeMap.coordinates;
            try {
                const coords = typeof rawCoords === 'string' ? JSON.parse(rawCoords) : rawCoords;
                const activeAreas = (Array.isArray(coords) ? coords : (coords?.rectangles || coords?.polygons || [])).map((a: Partial<IMapArea>, idx: number) => ({
                    ...a,
                    id: a.id || String(Date.now() + idx),
                    selector: a.selector || `.area-${idx + 1}`,
                    top: a.top ?? 0,
                    left: a.left ?? 0,
                    width: a.width ?? 100,
                    height: a.height ?? 100
                })) as IMapArea[];
                boundariesTab.setAreas(activeAreas);
                boundariesTab.setLastSavedAreas(activeAreas);
            } catch (_) {
                boundariesTab.setAreas([]);
                boundariesTab.setLastSavedAreas([]);
            }
        } else {
            boundariesTab.setAreas([]);
            boundariesTab.setLastSavedAreas([]);
            boundariesTab.setCurrentMapId(undefined);
        }

        boundaries.fetchSavedMaps(roomId);
    }, [mappings, backgrounds, boundaries]);

    // Composite Dirty State
    const isRoomFormDirty = useMemo(() =>
        (overview.editingRoom || overview.isCreating) && (
            Object.keys(overview.roomForm).length > 0 && (
                overview.editingRoom
                    ? Object.entries(overview.roomForm).some(([k, v]) => (overview.editingRoom as Record<string, unknown>)[k] !== v)
                    : true
            )
        )
        , [overview.editingRoom, overview.isCreating, overview.roomForm]);

    const isGlobalDirty = boundariesTab.isSettingsDirty || shortcuts.isContentDirty || boundariesTab.isBoundaryDirty || isRoomFormDirty;

    const handleGlobalSave = useCallback(async (): Promise<boolean> => {
        if (isRoomFormDirty) await overview.handleSaveRoom();
        if (boundariesTab.isBoundaryDirty) await boundariesTab.handleSaveBoundaries();
        if (boundariesTab.isSettingsDirty) await boundariesTab.handleSaveSettings();
        if (shortcuts.isContentDirty) await shortcuts.handleContentSave();
        setIsEditMode(false);

        if (!isGlobalDirty && window.WFToast) {
            window.WFToast.info('No changes to save');
        }
        return true;
    }, [isRoomFormDirty, boundariesTab.isBoundaryDirty, boundariesTab.isSettingsDirty, shortcuts.isContentDirty, isGlobalDirty, setIsEditMode]);

    // Destination Options for Shortcuts
    const destinationOptions = useMemo(() => {
        const type = shortcuts.newMapping.mapping_type;
        if (type === 'item') {
            return mappings.unrepresentedItems.map(i => <option key={i.sku} value={i.sku}>{i.name} ({i.sku})</option>);
        }
        if (type === 'category') {
            return (mappings.unrepresentedCategories || []).map(c => <option key={c.id} value={c.id}>{c.name}</option>);
        }
        if (type === 'content' || type === 'button') {
            return (mappings.roomOptions || []).map(r => <option key={`room-${r.val}`} value={`room:${r.val}`}>Go to {r.label}</option>);
        }
        if (type === 'page' || type === 'modal' || type === 'action') {
            return (mappings.sitemapEntries || [])
                .filter(s => s.kind === (type as ISitemapEntry['kind']))
                .map(s => <option key={s.slug} value={s.slug}>{s.label}</option>);
        }
        return [<option key="none" value="">None</option>];
    }, [shortcuts.newMapping.mapping_type, mappings]);

    return {
        // State
        activeTab, setActiveTab,
        selectedRoom,
        ...overview,
        ...navigation,
        ...visuals,
        ...shortcuts,
        ...boundariesTab,
        selectedIds, setSelectedIds,
        activeTool, setActiveTool,
        snapSize, setSnapSize,
        isEditMode, setIsEditMode,
        isGlobalDirty,
        isRoomFormDirty,
        destinationOptions,

        // Sub-hook objects for dirty checking
        shortcuts,
        boundariesTab,

        // Hooks (Base Domain Hooks)
        mappings, backgrounds, boundaries, categoriesHook,

        // Handlers - Visuals (delegated to base backgrounds hook with room scope)
        handleApplyBackground: useCallback(async (bgId: number) => {
            if (selectedRoom) await backgrounds.applyBackground(selectedRoom, bgId);
        }, [selectedRoom, backgrounds]),

        handleDeleteBackground: useCallback(async (bgId: number) => {
            if (selectedRoom) await backgrounds.deleteBackground(bgId, selectedRoom);
        }, [selectedRoom, backgrounds]),

        handleBackgroundUpload: useCallback(async (e: React.ChangeEvent<HTMLInputElement>) => {
            const file = e.target.files?.[0];
            if (file && selectedRoom) {
                await backgrounds.uploadBackground(file, selectedRoom);
            }
        }, [selectedRoom, backgrounds]),

        // Handlers - Boundaries (delegated to base boundaries hook with room scope)
        handleActivateMap: useCallback(async (id: string | number) => {
            if (selectedRoom) {
                const res = await boundaries.activateMap(id, selectedRoom);
                if (res.success) {
                    if (window.WFToast) window.WFToast.success('Map activated');
                    boundaries.fetchSavedMaps(selectedRoom);
                    boundariesTab.setCurrentMapId(id);
                } else if (window.WFToast) {
                    window.WFToast.error(res.error || 'Failed to activate map');
                }
            }
        }, [selectedRoom, boundaries, boundariesTab]),

        handleDeleteMap: useCallback(async (id: string | number) => {
            if (selectedRoom) await boundaries.deleteMap(id);
        }, [selectedRoom, boundaries]),

        // Unified Handlers
        handleRoomChange,
        handleGlobalSave,
        startEditRoom: (room: IRoomData) => {
            overview.setEditingRoom(room);
            overview.setRoomForm({ ...room });
            overview.setIsCreating(false);
            handleRoomChange(String(room.room_number));
        },
        startCreateRoom: () => {
            overview.setIsCreating(true);
            overview.setEditingRoom(null);
            overview.setRoomForm({ is_active: true, display_order: 0 });
        },
        cancelRoomEdit: () => {
            overview.setEditingRoom(null);
            overview.setIsCreating(false);
            overview.setRoomForm({});
        }
    };
};
