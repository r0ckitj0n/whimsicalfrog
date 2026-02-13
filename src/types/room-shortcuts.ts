export interface IGenerateShortcutImageRequest {
    room_number: string;
    content_target: string;
    link_label?: string;
    size?: '1024x1024';
    provider?: 'openai';
    model?: string;
    mapping_id?: number;
}

export interface IGenerateShortcutImageResult {
    image_url: string;
    png_url: string;
    webp_url: string | null;
    room_number: string;
    target_room_number: string;
    prompt_text: string;
    provider: string;
    model: string;
}

export interface IGenerateShortcutImageResponse {
    success: boolean;
    data?: IGenerateShortcutImageResult;
    image_url?: string;
    png_url?: string;
    webp_url?: string | null;
    room_number?: string;
    target_room_number?: string;
    prompt_text?: string;
    provider?: string;
    model?: string;
    error?: string;
    message?: string;
}

export interface IShortcutSignAsset {
    id: number;
    mapping_id: number;
    room_number: string;
    image_url: string;
    png_url?: string | null;
    webp_url?: string | null;
    source: string;
    is_active: 0 | 1;
    created_at?: string;
}

export interface IShortcutSignAssetsResponse {
    success: boolean;
    data?: {
        assets: IShortcutSignAsset[];
    };
    assets?: IShortcutSignAsset[];
    message?: string;
    error?: string;
}

export interface IShortcutSignAssetActionResponse {
    success: boolean;
    asset?: IShortcutSignAsset;
    assets?: IShortcutSignAsset[];
    message?: string;
    error?: string;
}

export interface IShortcutSignAsset {
    id: number;
    mapping_id: number;
    room_number: string;
    image_url: string;
    png_url?: string | null;
    webp_url?: string | null;
    source: string;
    is_active: 0 | 1;
    created_at?: string;
}

export interface IShortcutSignAssetsResponse {
    success: boolean;
    data?: {
        assets: IShortcutSignAsset[];
    };
    assets?: IShortcutSignAsset[];
    message?: string;
    error?: string;
}

export interface IShortcutSignAssetActionResponse {
    success: boolean;
    asset?: IShortcutSignAsset;
    assets?: IShortcutSignAsset[];
    message?: string;
    error?: string;
}
