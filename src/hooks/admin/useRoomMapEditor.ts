import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { IRoomData } from '../../types/index.js';
import {
    IMapArea,
    IRoomMap,
    IRoomMapResponse,
    IRoomListResponse,
    IRoomMapEditorHook
} from '../../types/room.js';

export const useRoomMapEditor = (): IRoomMapEditorHook => {
    const [isLoading, setIsLoading] = useState(false);
    const [rooms, setRooms] = useState<Array<{ value: string; label: string }>>([]);
    const [savedMaps, setSavedMaps] = useState<IRoomMap[]>([]);
    const [error, setError] = useState<string | null>(null);

    const normalizeSavedMap = useCallback((map: IRoomMap): IRoomMap => ({
        ...map,
        is_active: map.is_active === true || map.is_active === 1 || (map.is_active as unknown) === '1'
    }), []);

    const sortMapsChronologically = useCallback((maps: IRoomMap[]): IRoomMap[] => {
        return [...maps].sort((a, b) => {
            const ta = a.created_at ? Date.parse(a.created_at) : NaN;
            const tb = b.created_at ? Date.parse(b.created_at) : NaN;

            if (!Number.isNaN(ta) && !Number.isNaN(tb)) {
                return tb - ta;
            }
            if (!Number.isNaN(ta)) return 1;
            if (!Number.isNaN(tb)) return -1;

            return Number(b.id ?? 0) - Number(a.id ?? 0);
        });
    }, []);

    const fetchRooms = useCallback(async () => {
        try {
            const res = await ApiClient.get<IRoomListResponse[]>('/api/get_rooms.php');
            const options = [
                { value: 'A', label: 'Landing Page' },
                { value: '0', label: 'Main Room' },
                ...(res || []).map(r => ({
                    value: String(r.room_number || r.id),
                    label: r.room_name || r.door_label || `Room ${r.room_number || r.id}`
                }))
            ].sort((a, b) => a.label.localeCompare(b.label));
            setRooms(options);
        } catch (err) {
            logger.error('[useRoomMapEditor] fetchRooms failed', err);
        }
    }, []);

    const fetchSavedMaps = useCallback(async (room: string) => {
        if (!room) return;
        setIsLoading(true);
        try {
            const res = await ApiClient.get<IRoomMapResponse>('/api/room_maps.php', { action: 'list', room });
            if (res.success) {
                setSavedMaps(sortMapsChronologically((res.maps || []).map(normalizeSavedMap)));
            }
        } catch (err) {
            logger.error('[useRoomMapEditor] fetchSavedMaps failed', err);
        } finally {
            setIsLoading(false);
        }
    }, [normalizeSavedMap, sortMapsChronologically]);

    const loadActiveMap = useCallback(async (room: string) => {
        if (!room) return null;
        try {
            const res = await ApiClient.get<IRoomMapResponse>('/api/room_maps.php', { action: 'get_active', room });
            return res.success ? (res.map ? normalizeSavedMap(res.map) : null) : null;
        } catch (err) {
            logger.error('[useRoomMapEditor] loadActiveMap failed', err);
            return null;
        }
    }, [normalizeSavedMap]);

    const saveMap = async (room: string, name: string, areas: IMapArea[]) => {
        try {
            const res = await ApiClient.post<{ success: boolean; message?: string; error?: string; map_id?: string | number; updated_existing?: boolean; map?: IRoomMap }>('/api/room_maps.php', {
                action: 'save',
                room,
                map_name: name,
                coordinates: JSON.stringify({ rectangles: areas })
            });
            if (res.success) {
                await fetchSavedMaps(room);
            }
            return res;
        } catch (err) {
            logger.error('[useRoomMapEditor] saveMap failed', err);
            return { success: false, error: 'Network error' };
        }
    };

    const deleteMap = async (id: string | number) => {
        try {
            const res = await ApiClient.post<{ success: boolean; error?: string }>('/api/room_maps.php', {
                action: 'delete',
                id
            });
            if (res.success) {
                setSavedMaps(prev => prev.filter(m => String(m.id) !== String(id)));
            }
            return res;
        } catch (err) {
            logger.error('[useRoomMapEditor] deleteMap failed', err);
            return { success: false, error: 'Network error' };
        }
    };

    const renameMap = async (id: string | number, newName: string) => {
        try {
            const res = await ApiClient.post<{ success: boolean; error?: string }>('/api/room_maps.php', {
                action: 'rename',
                id,
                map_name: newName
            });
            return res;
        } catch (err) {
            logger.error('[useRoomMapEditor] renameMap failed', err);
            return { success: false, error: 'Network error' };
        }
    };

    const activateMap = async (id: string | number, room: string) => {
        try {
            const res = await ApiClient.post<{ success: boolean; error?: string }>('/api/room_maps.php', {
                action: 'activate',
                id,
                room
            });
            if (res.success) {
                setSavedMaps(prev => prev.map(m => ({
                    ...m,
                    is_active: String(m.id) === String(id)
                })));
                await fetchSavedMaps(room);
            }
            return res;
        } catch (err) {
            logger.error('[useRoomMapEditor] activateMap failed', err);
            return { success: false, error: 'Network error' };
        }
    };

    const updateRoomSettings = async (room: string, settings: Record<string, unknown>) => {
        try {
            const res = await ApiClient.post<{ success: boolean; error?: string }>('/api/room_settings.php', {
                action: 'update_room',
                room_number: room,
                ...settings
            });
            return res;
        } catch (err) {
            logger.error('[useRoomMapEditor] updateRoomSettings failed', err);
            return { success: false, error: 'Network error' };
        }
    };

    const getRoomSettings = useCallback(async (room: string): Promise<IRoomData | null> => {
        if (!room) return null;
        try {
            const res = await ApiClient.get<{ success: boolean; room?: IRoomData; data?: { room: IRoomData } }>('/api/room_settings.php', { action: 'get_room', room_number: room });
            return res?.room || res?.data?.room || null;
        } catch (err) {
            logger.error('[useRoomMapEditor] getRoomSettings failed', err);
            return null;
        }
    }, []);

    return {
        isLoading,
        rooms,
        savedMaps,
        error,
        fetchRooms,
        fetchSavedMaps,
        loadActiveMap,
        saveMap,
        deleteMap,
        renameMap,
        activateMap,
        updateRoomSettings,
        getRoomSettings
    };
};
