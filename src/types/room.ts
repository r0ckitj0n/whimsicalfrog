// Room Manager Types
export interface IRoomMetadata {
    room_name?: string;
    category?: string;
    categories?: string[];
    category_id?: number | null;
    category_ids?: number[];
    room_number?: string | number;
}

export interface IRoomBackground {
    webp_filename?: string;
    image_filename?: string;
}

export interface IRoomData {
    room_number: string;
    room_name: string;
    door_label: string;
    description?: string;
    display_order?: number;
    is_active: boolean | number;
    render_context?: string;
    show_search_bar?: boolean | number;
    background_url?: string;
    target_aspect_ratio?: string | number;
    icon_panel_color?: string;
    room_role?: 'landing' | 'main' | 'shop' | 'settings' | 'room' | 'about' | 'contact';
    [key: string]: unknown;
}

export interface IRoomState {
    isOpen: boolean;
    currentRoom: string | null;
    isLoading: boolean;
    content: string;
    metadata: IRoomMetadata;
    background: IRoomBackground | null;
    panelColor?: string;
    renderContext: 'modal' | 'fullscreen' | 'fixed';
    targetAspectRatio: number | null;
}

export interface IRoomManagerHook extends IRoomState {
    openRoom: (room_number: string | number) => Promise<void>;
    closeRoom: () => void;
}

// Room Coordinates Types
export interface IDoorCoordinate {
    selector: string;
    top: number;
    left: number;
    width: number;
    height: number;
    id?: string; // Legacy field fallback for selector
}

export interface ICoordinatesResponse {
    success: boolean;
    data?: {
        coordinates: IDoorCoordinate[];
    };
    coordinates?: IDoorCoordinate[];
}

// Room Content API Response
export interface IRoomContentResponse {
    content?: string;
    metadata?: IRoomMetadata;
    background?: IRoomBackground;
    panel_color?: string;
    render_context?: 'modal' | 'fullscreen' | 'fixed';
    target_aspect_ratio?: number | null;
}

// Area Mapping Types
export interface ISitemapEntry {
    url: string;
    label: string;
    slug: string;
    kind: 'page' | 'modal' | 'action';
}

export interface IDoorDestination {
    area_selector: string;
    target: string;
    label: string;
    image: string;
}

// Navigation Connection Types
export interface IRoomConnection {
    source_room: string;
    target_room?: string;
    source_name?: string;
    target_name?: string;
    target_url?: string;
    area_selector?: string;
    mapping_type?: string;
    connection_type?: 'internal' | 'external';
}

export interface IRoomHeaderLink {
    slug: string;
    label: string;
    url: string;
}

export interface IRoomOption {
    val: string;
    label: string;
}

export interface IAreaOption {
    val: string;
    label: string;
}

export interface IRoomCategory {
    id?: number;
    category: string;
    item_count: number;
    is_active: boolean;
}

export interface IRoomAssignment {
    id: number;
    room_number: number;
    category_id: number;
    category_name: string;
    is_primary: boolean;
    display_order: number;
}

export interface IRoomOverview {
    room_number: number;
    room_name: string;
    assigned_categories: string[];
    primary_category?: string;
}

// Category API Response Interfaces
export interface ICategoryResponse {
    success: boolean;
    categories?: IRoomCategory[];
    error?: string;
}

export interface IAssignmentsResponse {
    success: boolean;
    assignments?: IRoomAssignment[];
    error?: string;
}

export interface ICategoryOverviewResponse {
    success: boolean;
    summary?: IRoomOverview[];
    error?: string;
}


export interface IRoomSettingsResponse {
    success: boolean;
    data?: {
        rooms: IRoomData[];
    };
    rooms?: IRoomData[];
    error?: string;
    action?: string;
    failed_items?: Array<{ name?: string; sku?: string }>;
}

export interface IRoomConnectionsResponse {
    success: boolean;
    data?: {
        connections?: IRoomConnection[];
        external_links?: IRoomConnection[];
        header_links?: IRoomHeaderLink[];
        connections_found?: number;
    };
    connections?: IRoomConnection[];
    external_links?: IRoomConnection[];
    header_links?: IRoomHeaderLink[];
    connections_found?: number;
    error?: string;
}

// Room Mapping & Visuals Editor Types
export interface IMapArea {
    id: string;
    selector: string;
    top: number;
    left: number;
    width: number;
    height: number;
}

export interface IRoomMap {
    id?: string | number;
    room_number: string;
    map_name: string;
    coordinates: string; // JSON string
    is_active: boolean;
    is_default?: number | boolean; // Optional legacy field from database
    created_at?: string;
}

export interface IRoomMapResponse {
    success: boolean;
    maps?: IRoomMap[];
    map?: IRoomMap;
    saved_map?: IRoomMap;
    map_id?: string | number;
    updated_existing?: boolean;
    error?: string;
    message?: string;
}

export interface IRoomListResponse {
    room_number?: number | string;
    id?: number | string;
    room_name?: string;
    door_label?: string;
}

export interface IMappingsResponse {
    success: boolean;
    data?: {
        mappings: import('./admin.js').IAreaMapping[];
        category?: string;
    };
    mappings?: import('./admin.js').IAreaMapping[];
    category?: string;
}

// Orchestration Hook Interfaces
export interface IAreaMappingsHook {
    isLoading: boolean;
    error: string | null;
    explicitMappings: import('./admin.js').IAreaMapping[];
    derivedMappings: import('./admin.js').IAreaMapping[];
    derivedCategory: string;
    unrepresentedItems: import('./inventory.js').IItem[];
    unrepresentedCategories: import('./inventory.js').ICategory[];
    sitemapEntries: ISitemapEntry[];
    doorDestinations: IDoorDestination[];
    roomOptions: IRoomOption[];
    availableAreas: IAreaOption[];
    fetchMappings: (room: string) => Promise<void>;
    fetchLookupData: () => Promise<void>;
    fetchAvailableAreas: (room: string) => Promise<void>;
    saveMapping: (mapping: Partial<import('./admin.js').IAreaMapping>) => Promise<boolean>;
    toggleMappingActive: (room: string, id: number, currentActive: boolean | number) => Promise<boolean>;
    deleteMapping: (id: number, room: string) => Promise<boolean>;
    uploadImage: (file: File) => Promise<string | null>;
    generateShortcutImage: (request: import('./room-shortcuts.js').IGenerateShortcutImageRequest) => Promise<import('./room-shortcuts.js').IGenerateShortcutImageResult | null>;
    fetchShortcutSignAssets: (mappingId: number, room: string) => Promise<import('./room-shortcuts.js').IShortcutSignAsset[]>;
    setShortcutSignActive: (mappingId: number, assetId: number, room: string) => Promise<boolean>;
    deleteShortcutSignAsset: (mappingId: number, assetId: number, room: string) => Promise<boolean>;
}

export interface IRoomMapEditorHook {
    isLoading: boolean;
    rooms: Array<{ value: string; label: string }>;
    savedMaps: IRoomMap[];
    error: string | null;
    fetchRooms: () => Promise<void>;
    fetchSavedMaps: (room: string) => Promise<void>;
    loadActiveMap: (room: string) => Promise<IRoomMap | null>;
    saveMap: (room: string, name: string, areas: IMapArea[], mapId?: string | number) => Promise<{ success: boolean; message?: string; error?: string; map_id?: string | number; updated_existing?: boolean; map?: IRoomMap }>;
    deleteMap: (id: string | number) => Promise<{ success: boolean; error?: string }>;
    renameMap: (id: string | number, newName: string) => Promise<{ success: boolean; error?: string }>;
    activateMap: (id: string | number, room: string) => Promise<{ success: boolean; error?: string }>;
    updateRoomSettings: (room: string, settings: Record<string, unknown>) => Promise<{ success: boolean; error?: string }>;
    getRoomSettings: (room: string) => Promise<import('./room.js').IRoomData | null>;
}

export interface IRoomOverviewHook {
    roomsData: IRoomData[];
    editingRoom: IRoomData | null;
    setEditingRoom: (room: IRoomData | null) => void;
    isCreating: boolean;
    setIsCreating: (val: boolean) => void;
    roomForm: Partial<IRoomData>;
    setRoomForm: React.Dispatch<React.SetStateAction<Partial<IRoomData>>>;
    fetchAllRooms: () => Promise<void>;
    handleToggleActive: (roomNumber: string, currentActive: boolean | number) => Promise<void>;
    handleSaveRoom: (onSaveSuccess?: () => void) => Promise<void>;
    handleChangeRoomRole: (roomNumber: string, newRole: IRoomData['room_role']) => Promise<void>;
    handleDeleteRoom: (roomNumber: string) => Promise<void>;
    createRoom: (room: Partial<IRoomData>) => Promise<{ success: boolean; error?: string; room_number?: string }>;
    isProtectedRoom: (room: IRoomData) => boolean;
}

export interface IRoomNavigationHook {
    connections: IRoomConnection[];
    setConnections: (val: IRoomConnection[]) => void;
    externalLinks: IRoomConnection[];
    setExternalLinks: (val: IRoomConnection[]) => void;
    headerLinks: IRoomHeaderLink[];
    setHeaderLinks: (val: IRoomHeaderLink[]) => void;
    isDetecting: boolean;
    fetchConnections: () => Promise<void>;
    handleDetectConnections: () => Promise<void>;
    handleSaveConnections: (roomId: string, currentTabConnections: IRoomConnection[]) => Promise<void>;
}

export interface IRoomVisualsHook {
    preview_image: {
        url: string;
        name: string;
        target_type?: 'background' | 'shortcut_sign';
        room_number?: string;
        source_background_id?: number;
        source_shortcut_image_url?: string;
        shortcut_mapping_id?: number;
        shortcut_images?: import('./room-shortcuts.js').IShortcutSignAsset[];
    } | null;
    setPreviewImage: React.Dispatch<React.SetStateAction<{
        url: string;
        name: string;
        target_type?: 'background' | 'shortcut_sign';
        room_number?: string;
        source_background_id?: number;
        source_shortcut_image_url?: string;
        shortcut_mapping_id?: number;
        shortcut_images?: import('./room-shortcuts.js').IShortcutSignAsset[];
    } | null>>;
    getImageUrl: (bg: { webp_filename?: string; image_filename?: string }) => string;
}

export interface IRoomShortcutsHook {
    newMapping: Partial<import('./admin.js').IAreaMapping>;
    setNewMapping: React.Dispatch<React.SetStateAction<Partial<import('./admin.js').IAreaMapping>>>;
    handleContentSave: (e?: React.FormEvent) => Promise<void>;
    handleContentConvert: (area: string, sku: string) => Promise<void>;
    handleToggleMappingActive: (id: number, currentActive: boolean | number) => Promise<void>;
    handleContentUpload: (e: React.ChangeEvent<HTMLInputElement>, field: 'content_image' | 'link_image') => Promise<void>;
    handleGenerateContentImage: () => Promise<void>;
    handleContentEdit: (mapping: import('./admin.js').IAreaMapping) => void;
    isGeneratingImage: boolean;
    isContentDirty: boolean;
}

export interface IRoomBoundariesHook {
    areas: IMapArea[];
    setAreas: (val: IMapArea[]) => void;
    lastSavedAreas: IMapArea[];
    setLastSavedAreas: (val: IMapArea[]) => void;
    renderContext: string;
    setRenderContext: (val: string) => void;
    bgUrl: string;
    setBgUrl: (val: string) => void;
    iconPanelColor: string;
    setIconPanelColor: (val: string) => void;
    targetAspectRatio: number;
    setTargetAspectRatio: (val: number) => void;
    currentMapId: string | number | undefined;
    setCurrentMapId: (val: string | number | undefined) => void;
    previewKey: number;
    setPreviewKey: (val: number | ((prev: number) => number)) => void;
    initialSettings: {
        renderContext: string;
        bgUrl: string;
        iconPanelColor: string;
        targetAspectRatio: number;
    };
    setInitialSettings: (val: IRoomBoundariesHook['initialSettings']) => void;
    handleSaveBoundaries: () => Promise<void>;
    handleSaveSettings: (onSuccess?: () => void) => Promise<void>;
    isSettingsDirty: boolean;
    isBoundaryDirty: boolean;
    handleDeleteMap: (id: string | number) => Promise<void>;
    handleRenameMap: (id: string | number) => Promise<void>;
    handleLoadMap: (id: string | number) => void;
}

export interface ICategoriesHook {
    categories: IRoomCategory[];
    assignments: IRoomAssignment[];
    overview: IRoomOverview[];
    isLoading: boolean;
    error: string | null;
    createCategory: (name: string) => Promise<{ success: boolean; error?: string; message?: string }>;
    renameCategory: (oldName: string, newName: string) => Promise<{ success: boolean; error?: string; message?: string }>;
    deleteCategory: (name: string) => Promise<{ success: boolean; error?: string; message?: string }>;
    addAssignment: (roomNumber: number, categoryId: number) => Promise<{ success: boolean; error?: string; message?: string }>;
    deleteAssignment: (id: number) => Promise<{ success: boolean; error?: string; message?: string }>;
    updateAssignment: (id: number, data: { room_number?: number; category_id?: number; is_primary?: number }) => Promise<{ success: boolean; error?: string; message?: string }>;
    refresh: () => Promise<any[]>;
}

export type TRoomManagerTab = 'overview' | 'navigation' | 'categories' | 'content' | 'visuals' | 'boundaries';

export interface IUnifiedRoomManagerHook extends IRoomOverviewHook, IRoomNavigationHook, IRoomVisualsHook, IRoomShortcutsHook, IRoomBoundariesHook {
    activeTab: TRoomManagerTab;
    setActiveTab: (tab: TRoomManagerTab) => void;
    selectedRoom: string;
    selectedIds: string[];
    setSelectedIds: (val: string[]) => void;
    activeTool: 'select' | 'create';
    setActiveTool: (val: 'select' | 'create') => void;
    snapSize: number;
    setSnapSize: (val: number) => void;
    isEditMode: boolean;
    setIsEditMode: (val: boolean) => void;
    isGlobalDirty: boolean;
    isRoomFormDirty: boolean;
    destinationOptions: JSX.Element[];
    shortcuts: IRoomShortcutsHook;
    boundariesTab: IRoomBoundariesHook;
    mappings: IAreaMappingsHook;
    backgrounds: import('./backgrounds.js').IBackgroundsHook;
    boundaries: IRoomMapEditorHook;
    categoriesHook: ICategoriesHook;
    handleRoomChange: (roomId: string) => Promise<void>;
    handleGlobalSave: () => Promise<void>;
    handleApplyBackground: (bgId: number) => Promise<void>;
    handleDeleteBackground: (bgId: number) => Promise<void>;
    handleBackgroundUpload: (e: React.ChangeEvent<HTMLInputElement>) => Promise<void>;
    handleGenerateBackground: (request: import('./room-generation.js').IRoomImageGenerationRequest) => Promise<{ success: boolean; data?: import('./room-generation.js').IRoomImageGenerationResponse['data']; error?: string }>;
    handleActivateMap: (id: string | number) => Promise<void>;
    handleDeleteMap: (id: string | number) => Promise<void>;
    startEditRoom: (room: IRoomData) => void;
    startCreateRoom: () => void;
    cancelRoomEdit: () => void;
}
