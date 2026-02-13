export type TAiImageEditTarget = 'item' | 'background' | 'shortcut_sign';

export interface IAiImageEditRequest {
    target_type: TAiImageEditTarget;
    source_image_url: string;
    instructions: string;
    item_sku?: string;
    room_number?: string;
    source_background_id?: number;
    shortcut_mapping_id?: number;
}

export interface IAiImageEditResponse {
    success: boolean;
    data?: {
        target_type: TAiImageEditTarget;
        item_image?: {
            sku: string;
            image_path: string;
            png_path?: string;
            webp_path?: string;
        };
        background?: {
            id: number;
            room_number: string;
            name: string;
            image_filename: string;
            webp_filename?: string;
            is_active: 0 | 1;
            image_url: string;
            webp_url?: string | null;
        };
        shortcut_sign?: {
            name: string;
            image_url: string;
            png_url: string;
            webp_url?: string | null;
            mapping_id?: number;
            id?: number;
        };
    };
    error?: string;
    message?: string;
}
