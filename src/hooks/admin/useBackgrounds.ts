import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IBackground, IBackgroundRoomOption, IBackgroundsResponse } from '../../types/backgrounds.js';
import type { IRoomImageGenerationRequest, IRoomImageGenerationResponse } from '../../types/room-generation.js';

// Re-export for backward compatibility
export type { IBackground, IBackgroundRoomOption as IRoomOption } from '../../types/backgrounds.js';

// Internal alias for use within this hook
type IRoomOption = IBackgroundRoomOption;

export const useBackgrounds = () => {
    const [backgrounds, setBackgrounds] = useState<IBackground[]>([]);
    const [activeBackground, setActiveBackground] = useState<IBackground | null>(null);
    const [rooms, setRooms] = useState<IRoomOption[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchRooms = useCallback(async () => {
        try {
            const [roomsRes, summaryRes, settingsRes] = await Promise.all([
                ApiClient.get<{ success: boolean; rooms?: Array<{ id: number | string; name: string }> }>('/api/get_rooms.php'),
                ApiClient.get<IBackgroundsResponse>('/api/backgrounds.php'),
                ApiClient.get<{ success: boolean; data?: { rooms: Array<{ room_number: number; room_name?: string; door_label?: string; description?: string }> }; rooms?: Array<{ room_number: number; room_name?: string; door_label?: string; description?: string }> }>('/api/room_settings.php', { action: 'get_all' })
            ]);

            const known = new Map<string, string>();

            // From room settings
            const settingsRooms = settingsRes?.data?.rooms || settingsRes?.rooms || [];
            settingsRooms.forEach((r) => {
                const id = String(r.room_number);
                const name = r.room_name || r.door_label || r.description || `Room ${id}`;
                known.set(id, name);
            });

            // From active rooms
            const activeRooms = Array.isArray(roomsRes) ? roomsRes : (roomsRes?.rooms || []);
            activeRooms.forEach((r) => {
                const id = String(r.id);
                if (!known.has(id)) known.set(id, r.name || `Room ${id}`);
            });

            // From backgrounds summary
            const summary = summaryRes?.data?.summary || summaryRes?.summary || [];
            summary.forEach((s) => {
                const k = String(s.room_key ?? s.room_number ?? '');
                if (k && !known.has(k)) {
                    known.set(k, s.room_name || s.room_key || String(s.room_number) || k);
                }
            });

            const entries = Array.from(known.entries())
                .map(([id, name]) => ({ id, name }))
                .sort((a, b) => a.name.localeCompare(b.name));

            setRooms(entries);
        } catch (err) {
            logger.error('fetchRooms failed', err);
        }
    }, []);

    const fetchBackgroundsForRoom = useCallback(async (room: string) => {
        setIsLoading(true);
        setError(null);
        try {
            const [listRes, activeRes] = await Promise.all([
                ApiClient.get<IBackgroundsResponse>('/api/backgrounds.php', { room }),
                ApiClient.get<IBackgroundsResponse>('/api/backgrounds.php', { room, active_only: true })
            ]);

            setBackgrounds(listRes?.data?.backgrounds || listRes?.backgrounds || []);
            setActiveBackground(activeRes?.data?.background || activeRes?.background || null);
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchBackgroundsForRoom failed', err);
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const applyBackground = useCallback(async (room: string, backgroundId: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success?: boolean; error?: string }>('/api/backgrounds.php', {
                action: 'apply',
                room,
                background_id: backgroundId
            });
            if (res) {
                await fetchBackgroundsForRoom(room);
                return true;
            } else {
                throw new Error('Failed to apply background');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('applyBackground failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchBackgroundsForRoom]);

    const uploadBackground = useCallback(async (file: File, roomNumber: string, name?: string): Promise<{ success: boolean; data?: { success?: boolean }; error?: string }> => {
        setIsLoading(true);
        setError(null);
        try {
            const formData = new FormData();
            formData.append('room', roomNumber);
            formData.append('background_image', file);
            if (name) formData.append('name', name);

            const res = await ApiClient.upload<{ success?: boolean; error?: string; message?: string; details?: { error?: number } }>('/api/upload_background.php', formData);

            if (res?.success) {
                await fetchBackgroundsForRoom(roomNumber);
                return { success: true, data: res };
            } else {
                // Provide user-friendly error messages for common issues
                let errorMsg = res?.error || res?.message || 'Upload failed';

                // Check for specific PHP upload errors
                if (res?.details?.error === 1 || res?.details?.error === 2) {
                    const maxSize = '10MB'; // Match .user.ini setting
                    errorMsg = `File too large. Maximum size is ${maxSize}. Your file: ${(file.size / 1024 / 1024).toFixed(1)}MB`;
                } else if (res?.details?.error === 3) {
                    errorMsg = 'Upload interrupted. Please try again.';
                } else if (res?.details?.error === 4) {
                    errorMsg = 'No file was uploaded. Please select an image.';
                } else if (errorMsg.includes('file type')) {
                    errorMsg = `Unsupported file type "${file.type}". Use JPG, PNG, or WebP.`;
                }

                setError(errorMsg);
                return { success: false, error: errorMsg };
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Network error - check your connection';
            logger.error('uploadBackground failed', err);
            setError(message);
            return { success: false, error: message };
        } finally {
            setIsLoading(false);
        }
    }, [fetchBackgroundsForRoom]);

    const generateRoomBackground = useCallback(async (request: IRoomImageGenerationRequest): Promise<{ success: boolean; data?: IRoomImageGenerationResponse['data']; error?: string }> => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<IRoomImageGenerationResponse>('/api/generate_room_image.php', request);
            if (!res?.success) {
                throw new Error(res?.error || 'Failed to generate room background');
            }

            const room = request.room_number;
            if (room) {
                await fetchBackgroundsForRoom(room);
            }

            return { success: true, data: res.data };
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to generate room background';
            logger.error('generateRoomBackground failed', err);
            setError(message);
            return { success: false, error: message };
        } finally {
            setIsLoading(false);
        }
    }, [fetchBackgroundsForRoom]);

    const deleteBackground = useCallback(async (backgroundId: number, room: string) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.delete<{ success?: boolean; error?: string }>(`/api/backgrounds.php?background_id=${backgroundId}`);
            if (res) {
                await fetchBackgroundsForRoom(room);
                return true;
            } else {
                throw new Error('Failed to delete background');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('deleteBackground failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchBackgroundsForRoom]);

    return {
        backgrounds,
        activeBackground,
        rooms,
        isLoading,
        error,
        fetchRooms,
        fetchBackgroundsForRoom,
        uploadBackground,
        generateRoomBackground,
        applyBackground,
        deleteBackground
    };
};
