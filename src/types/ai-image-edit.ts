export type TAiImageEditTarget = 'item' | 'background';

export interface IAiImageEditRequest {
    target_type: TAiImageEditTarget;
    source_image_url: string;
    instructions: string;
    item_sku?: string;
    room_number?: string;
    source_background_id?: number;
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
    };
    error?: string;
    message?: string;
}
