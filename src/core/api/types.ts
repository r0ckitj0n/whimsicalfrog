export interface RequestOptions extends RequestInit {
    responseType?: 'json' | 'text';
    onProgress?: (event: ProgressEvent) => void;
}

export interface ApiResponse<T = unknown> {
    success: boolean;
    data?: T;
    message?: string;
    error?: string;
    debug?: unknown;
    details?: unknown;
}
