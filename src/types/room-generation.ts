export interface IRoomImageGenerationRequest {
    room_number: string;
    template_key: string;
    variables?: Record<string, string>;
    provider?: 'openai';
    model?: string;
    size?: '1024x1024' | '1536x1024' | '1024x1536';
    background_name?: string;
}

export interface IRoomImageGenerationResult {
    id: number;
    room_number: string;
    name: string;
    image_filename: string;
    webp_filename?: string;
    is_active: number | boolean;
    image_url: string;
    webp_url?: string | null;
}

export interface IRoomImageGenerationResponse {
    success: boolean;
    data?: {
        background?: IRoomImageGenerationResult;
        history_id?: number | null;
        template_key?: string;
        provider?: string;
        model?: string;
        prompt_text?: string;
        resolved_variables?: Record<string, string>;
    };
    background?: IRoomImageGenerationResult;
    history_id?: number | null;
    template_key?: string;
    provider?: string;
    model?: string;
    prompt_text?: string;
    resolved_variables?: Record<string, string>;
    message?: string;
    error?: string;
}
