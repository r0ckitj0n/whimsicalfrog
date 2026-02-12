export interface IGenerateShortcutImageRequest {
    room_number: string;
    content_target: string;
    link_label?: string;
    size?: '1024x1024';
    provider?: 'openai';
    model?: string;
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
