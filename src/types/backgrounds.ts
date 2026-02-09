/**
 * Background and Room Visual Types
 * Centralized type definitions for room backgrounds and visual settings
 */

export interface IBackground {
    id: number;
    name: string;
    image_filename: string;
    webp_filename?: string;
    is_active: boolean | number;
    room_number?: number | string;
}

export interface IBackgroundRoomOption {
    id: string;
    name: string;
}

export interface IBackgroundsResponse {
    success: boolean;
    data?: {
        backgrounds?: IBackground[];
        background?: IBackground;
        summary?: Array<{ room_number?: number; room_key?: string; room_name?: string }>;
    };
    backgrounds?: IBackground[];
    background?: IBackground;
    summary?: Array<{ room_number?: number; room_key?: string; room_name?: string }>;
}

export interface IBackgroundsHook {
    backgrounds: IBackground[];
    activeBackground: IBackground | null;
    rooms: IBackgroundRoomOption[];
    isLoading: boolean;
    error: string | null;
    fetchRooms: () => Promise<void>;
    fetchBackgroundsForRoom: (room: string) => Promise<void>;
    uploadBackground: (file: File, roomNumber: string, name?: string) => Promise<{ success: boolean; data?: { success?: boolean }; error?: string }>;
    generateRoomBackground: (request: import('./room-generation.js').IRoomImageGenerationRequest) => Promise<{ success: boolean; data?: import('./room-generation.js').IRoomImageGenerationResponse['data']; error?: string }>;
    applyBackground: (room: string, backgroundId: number) => Promise<boolean>;
    deleteBackground: (backgroundId: number, room: string) => Promise<boolean>;
}
