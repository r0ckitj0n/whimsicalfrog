import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { IAreaMapping, IItem, ICategory } from '../../types/index.js';
import { API_ACTION } from '../../core/constants.js';

import {
    ISitemapEntry,
    IDoorDestination,
    IRoomOption,
    IAreaOption,
    IMappingsResponse,
    IAreaMappingsHook
} from '../../types/room.js';

export const useAreaMappings = (): IAreaMappingsHook => {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [explicitMappings, setExplicitMappings] = useState<IAreaMapping[]>([]);
    const [derivedMappings, setDerivedMappings] = useState<IAreaMapping[]>([]);
    const [derivedCategory, setDerivedCategory] = useState<string>('');
    const [unrepresentedItems, setUnrepresentedItems] = useState<IItem[]>([]);
    const [unrepresentedCategories, setUnrepresentedCategories] = useState<ICategory[]>([]);

    const [sitemapEntries, setSitemapEntries] = useState<ISitemapEntry[]>([]);
    const [doorDestinations, setDoorDestinations] = useState<IDoorDestination[]>([]);
    const [roomOptions, setRoomOptions] = useState<IRoomOption[]>([]);
    const [availableAreas, setAvailableAreas] = useState<IAreaOption[]>([]);

    const fetchUnrepresented = useCallback(async () => {
        try {
            const [itemsRes, catsRes] = await Promise.all([
                ApiClient.get<{ success: boolean; items: IItem[] }>(`/api/unrepresented_items.php?limit=500`),
                ApiClient.get<{ success: boolean; categories: ICategory[] }>(`/api/unrepresented_categories.php`)
            ]);
            if (itemsRes?.success) setUnrepresentedItems(itemsRes.items || []);
            if (catsRes?.success) setUnrepresentedCategories(catsRes.categories || []);
        } catch (err) {
            logger.error('fetchUnrepresented failed', err);
        }
    }, []);

    const fetchMappings = useCallback(async (room: string) => {
        if (!room) return;
        setIsLoading(true);
        setError(null);
        try {
            const ts = Date.now();
            const [exp, live] = await Promise.all([
                ApiClient.get<IMappingsResponse>('/api/area_mappings.php', {
                    action: 'list_room_raw',
                    room: room,
                    room_number: room,
                    _: ts
                }),
                ApiClient.get<IMappingsResponse>('/api/area_mappings.php', {
                    action: API_ACTION.GET_LIVE_VIEW,
                    room: room,
                    room_number: room,
                    _: ts
                }),
                fetchUnrepresented()
            ]);

            if (exp?.success) {
                setExplicitMappings(exp.data?.mappings || exp.mappings || []);
            }
            if (live?.success) {
                setDerivedMappings(live.data?.mappings || live.mappings || []);
                setDerivedCategory(live.data?.category || live.category || '');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchMappings failed', err);
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, [fetchUnrepresented]);

    const fetchLookupData = useCallback(async () => {
        try {
            const [sitemap, doors, roomsRes] = await Promise.all([
                ApiClient.get<{ success: boolean; entries: ISitemapEntry[] }>(`/api/area_mappings.php?action=${API_ACTION.GET_SITEMAP}`),
                ApiClient.get<{ success: boolean; destinations: IDoorDestination[] }>(`/api/area_mappings.php?action=${API_ACTION.GET_DOOR_DESTINATIONS}`),
                ApiClient.get<{ success: boolean; data?: { rooms: Array<{ room_number: number; room_name?: string; door_label?: string; description?: string }> }; rooms?: Array<{ room_number: number; room_name?: string; door_label?: string; description?: string }> }>('/api/room_settings.php?action=get_all')
            ]);

            if (sitemap?.success) setSitemapEntries(sitemap.entries || []);
            if (doors?.success) setDoorDestinations(doors.destinations || []);

            const roomList = roomsRes?.data?.rooms || roomsRes?.rooms || [];
            setRoomOptions(roomList.map((r) => ({
                val: String(r.room_number),
                label: r.room_name || r.door_label || `Room ${r.room_number}`
            })).sort((a, b) => a.label.localeCompare(b.label)));
        } catch (err) {
            logger.error('fetchLookupData failed', err);
        }
    }, []);

    const fetchAvailableAreas = useCallback(async (room: string) => {
        if (!room) return;
        try {
            const res = await ApiClient.get<{ success: boolean; data?: { coordinates: unknown }; coordinates?: unknown }>('/api/area_mappings.php', {
                action: API_ACTION.GET_ROOM_COORDINATES,
                room
            });
            if (res?.success) {
                let coords: Array<{ selector: string }> = [];
                const rawCoords = res.data?.coordinates || res.coordinates || [];

                // Handle nested JSON structure: coordinates may be ["{\"rectangles\":[...]}"]
                if (Array.isArray(rawCoords) && rawCoords.length > 0) {
                    const first = rawCoords[0];
                    if (typeof first === 'string') {
                        // Parse JSON string and extract rectangles
                        try {
                            const parsed = JSON.parse(first);
                            coords = parsed.rectangles || parsed.coordinates || [];
                        } catch {
                            coords = [];
                        }
                    } else if (typeof first === 'object' && first !== null) {
                        // Already an array of coordinate objects
                        coords = rawCoords;
                    }
                }

                setAvailableAreas(coords.map((c, i) => ({
                    val: c.selector || `.area-${i + 1}`,
                    label: c.selector || `.area-${i + 1}`
                })));
            }
        } catch (err) {
            logger.error('fetchAvailableAreas failed', err);
        }
    }, []);

    const saveMapping = useCallback(async (mapping: Partial<IAreaMapping>) => {
        setIsLoading(true);
        try {
            const isEdit = !!mapping.id;
            const action = isEdit ? API_ACTION.UPDATE_MAPPING : API_ACTION.ADD_MAPPING;
            const res = await ApiClient.post<IMappingsResponse>('/api/area_mappings.php', {
                action,
                ...mapping
            });

            if (res) {
                if (mapping.room_number) {
                    await fetchMappings(String(mapping.room_number));
                    // Invalidate the room modal cache so fresh content is loaded
                    if (window.roomModalManager?.invalidateRoom) {
                        window.roomModalManager.invalidateRoom(mapping.room_number);
                    }
                }
                return true;
            } else {
                throw new Error('Failed to save mapping');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveMapping failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchMappings]);

    const deleteMapping = useCallback(async (id: number, room: string) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; message?: string }>('/api/area_mappings.php', {
                action: API_ACTION.DELETE_MAPPING,
                id
            });
            if (res?.success) {
                await fetchMappings(room);
                // Invalidate the room modal cache so fresh content is loaded
                if (window.roomModalManager?.invalidateRoom) {
                    window.roomModalManager.invalidateRoom(room);
                }
                return true;
            } else {
                throw new Error(res?.message || 'Failed to delete mapping');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('deleteMapping failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchMappings]);

    const toggleMappingActive = useCallback(async (room: string, id: number, currentActive: boolean | number) => {
        setIsLoading(true);
        try {
            const nextActive = !(currentActive === true || currentActive === 1);
            const res = await ApiClient.post<{ success: boolean; message?: string; error?: string }>('/api/area_mappings.php', {
                action: API_ACTION.UPDATE_MAPPING,
                id,
                is_active: nextActive ? 1 : 0
            });

            if (!res?.success) {
                throw new Error(res?.error || res?.message || 'Failed to update mapping status');
            }

            await fetchMappings(room);
            if (window.roomModalManager?.invalidateRoom) {
                window.roomModalManager.invalidateRoom(room);
            }
            return true;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('toggleMappingActive failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchMappings]);

    const uploadImage = useCallback(async (file: File) => {
        const formData = new FormData();
        formData.append('image', file);
        try {
            const res = await ApiClient.upload<{ success: boolean; image_url?: string; message?: string }>(`/api/area_mappings.php?action=${API_ACTION.UPLOAD_CONTENT_IMAGE}`, formData);
            if (res?.success && res.image_url) {
                return res.image_url;
            } else {
                throw new Error(res?.message || 'Upload failed');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('uploadImage failed', err);
            setError(message);
            return null;
        }
    }, []);

    return {
        isLoading,
        error,
        explicitMappings,
        derivedMappings,
        derivedCategory,
        unrepresentedItems,
        unrepresentedCategories,
        sitemapEntries,
        doorDestinations,
        roomOptions,
        availableAreas,
        fetchMappings,
        fetchLookupData,
        fetchAvailableAreas,
        saveMapping,
        toggleMappingActive,
        deleteMapping,
        uploadImage
    };
};
