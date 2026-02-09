import { useState, useCallback } from 'react';
import { ApiClient } from '../../../core/ApiClient.js';
import { IRoomData, IRoomSettingsResponse, IRoomOverviewHook } from '../../../types/room.js';
import { useModalContext } from '../../../context/ModalContext.js';

export const useRoomOverview = (): IRoomOverviewHook => {
    const [roomsData, setRoomsData] = useState<IRoomData[]>([]);
    const [editingRoom, setEditingRoom] = useState<IRoomData | null>(null);
    const [isCreating, setIsCreating] = useState(false);
    const [roomForm, setRoomForm] = useState<Partial<IRoomData>>({});
    const { confirm: confirmModal } = useModalContext();

    const fetchAllRooms = useCallback(async () => {
        try {
            const data = await ApiClient.get<IRoomSettingsResponse>('/api/room_settings.php', { action: 'get_all' });
            if (data?.success && data.data?.rooms) {
                setRoomsData(data.data.rooms);
            } else if (data?.rooms) {
                setRoomsData(data.rooms);
            }
        } catch (err: unknown) {
            console.error('Failed to fetch rooms:', err);
            if (window.WFToast) window.WFToast.error('Failed to load rooms list');
        }
    }, []);

    const isProtectedRoom = useCallback((room: IRoomData) =>
        ['0', 'A', 'S', 'X', 'about', 'contact'].includes(String(room.room_number)), []);

    const handleToggleActive = useCallback(async (roomNumber: string, currentActive: boolean | number) => {
        const newActive = !currentActive;
        const room = roomsData.find((r) => String(r.room_number) === String(roomNumber));
        if (!newActive && room && isProtectedRoom(room)) {
            if (window.WFToast) window.WFToast.error(`Cannot deactivate ${room.room_role} room`);
            return;
        }
        try {
            if (window.WFToast) window.WFToast.info('Updating active state...');
            const data = await ApiClient.put<IRoomSettingsResponse>('/api/room_settings.php', {
                action: 'set_active',
                room_number: String(roomNumber),
                is_active: newActive
            });

            if (data?.failed_items && data.failed_items.length > 0) {
                const failedNames = data.failed_items.map(item => item.name || item.sku).join(', ');
                if (window.WFToast) window.WFToast.error(`Failed to update items: ${failedNames}`);
                fetchAllRooms();
                return;
            }

            if (data?.success) {
                if (window.WFToast) window.WFToast.success(`Room ${newActive ? 'activated' : 'deactivated'}`);
                if (roomForm.room_number === roomNumber) {
                    setRoomForm(prev => ({ ...prev, is_active: newActive }));
                }
                if (editingRoom && editingRoom.room_number === roomNumber) {
                    setEditingRoom(prev => prev ? { ...prev, is_active: newActive } : null);
                }
                fetchAllRooms();
            } else {
                if (window.WFToast) window.WFToast.error(data?.error || 'Failed to update room');
            }
        } catch (err: unknown) {
            if (window.WFToast) window.WFToast.error(err instanceof Error ? err.message : 'Network error');
        }
    }, [roomsData, roomForm, editingRoom, fetchAllRooms, isProtectedRoom]);

    const handleSaveRoom = useCallback(async (onSaveSuccess?: () => void) => {
        if (!roomForm.room_number || !roomForm.room_name || !roomForm.door_label) {
            if (window.WFToast) window.WFToast.error('Required fields missing');
            return;
        }
        try {
            const action = isCreating ? 'create_room' : 'update_room';
            const data = await ApiClient.post<IRoomSettingsResponse>('/api/room_settings.php', {
                action,
                ...roomForm,
                room_number: String(roomForm.room_number)
            });

            if (data?.success) {
                if (window.WFToast) window.WFToast.success(isCreating ? 'Room created' : 'Room updated');
                onSaveSuccess?.();
                setEditingRoom(null);
                setIsCreating(false);
                setRoomForm({});
                fetchAllRooms();
            } else {
                if (window.WFToast) window.WFToast.error(data?.error || 'Failed to save room');
            }
        } catch (err: unknown) {
            if (window.WFToast) window.WFToast.error(err instanceof Error ? err.message : 'Network error');
        }
    }, [roomForm, isCreating, fetchAllRooms]);

    const handleChangeRoomRole = useCallback(async (roomNumber: string, newRole: IRoomData['room_role']) => {
        if (newRole && newRole !== 'room') {
            const existingRoom = roomsData.find((r) => r.room_role === newRole && String(r.room_number) !== String(roomNumber));
            if (existingRoom) {
                const confirmed = await confirmModal({
                    title: 'Change Role Assignment',
                    message: `"${existingRoom.room_name}" is currently set as ${newRole}. Reassign?`,
                    confirmText: 'Yes, Reassign',
                    confirmStyle: 'confirm'
                });
                if (!confirmed) return;
                await ApiClient.put<IRoomSettingsResponse>('/api/room_settings.php', {
                    action: 'update_room',
                    room_number: existingRoom.room_number,
                    room_role: 'room'
                });
            }
        }
        try {
            await ApiClient.put<IRoomSettingsResponse>('/api/room_settings.php', {
                action: 'update_room',
                room_number: roomNumber,
                room_role: newRole
            });
            if (window.WFToast) window.WFToast.success('Room role updated');
            fetchAllRooms();
        } catch (err: unknown) {
            if (window.WFToast) window.WFToast.error(err instanceof Error ? err.message : 'Failed to update role');
        }
    }, [roomsData, confirmModal, fetchAllRooms]);

    const handleDeleteRoom = useCallback(async (roomNumber: string) => {
        const room = roomsData.find((r) => String(r.room_number) === String(roomNumber));
        if (room && isProtectedRoom(room)) {
            if (window.WFToast) window.WFToast.error(`Cannot delete ${room.room_role} room`);
            return;
        }

        const confirmed = await confirmModal({
            title: 'Delete Room',
            message: `Are you sure you want to delete room "${room?.room_name || roomNumber}"? All mappings and configurations for this room will be lost.`,
            confirmText: 'Delete Room',
            confirmStyle: 'danger'
        });

        if (!confirmed) return;

        try {
            const data = await ApiClient.post<IRoomSettingsResponse>('/api/room_settings.php', {
                action: 'delete_room',
                room_number: String(roomNumber)
            });

            if (data?.success) {
                if (window.WFToast) window.WFToast.success('Room deleted');
                setEditingRoom(null);
                fetchAllRooms();
            } else {
                if (window.WFToast) window.WFToast.error(data?.error || 'Failed to delete room');
            }
        } catch (err: unknown) {
            if (window.WFToast) window.WFToast.error(err instanceof Error ? err.message : 'Network error');
        }
    }, [roomsData, isProtectedRoom, confirmModal, fetchAllRooms]);

    const createRoom = useCallback(async (room: Partial<IRoomData>): Promise<{ success: boolean; error?: string; room_number?: string }> => {
        const roomNumber = String(room.room_number || '').trim();
        const roomName = String(room.room_name || '').trim();
        const doorLabel = String(room.door_label || '').trim();
        if (!roomNumber || !roomName || !doorLabel) {
            return { success: false, error: 'Room Number, Room Name, and Door Label are required.' };
        }
        try {
            const data = await ApiClient.post<IRoomSettingsResponse>('/api/room_settings.php', {
                action: 'create_room',
                room_number: roomNumber,
                room_name: roomName,
                door_label: doorLabel,
                description: String(room.description || ''),
                display_order: Number(room.display_order) || 0,
                is_active: room.is_active === undefined ? true : Boolean(room.is_active)
            });
            if (data?.success) {
                await fetchAllRooms();
                return { success: true, room_number: roomNumber };
            }
            return { success: false, error: data?.error || 'Failed to create room' };
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Network error';
            return { success: false, error: message };
        }
    }, [fetchAllRooms]);

    return {
        roomsData,
        editingRoom,
        setEditingRoom,
        isCreating,
        setIsCreating,
        roomForm,
        setRoomForm,
        fetchAllRooms,
        handleToggleActive,
        handleSaveRoom,
        handleChangeRoomRole,
        handleDeleteRoom,
        createRoom,
        isProtectedRoom
    };
};
