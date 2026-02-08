export interface SessionViewerSessionRecord {
    session_id: string;
    user_id: number | null;
    ip_address: string | null;
    user_agent: string | null;
    landing_page: string | null;
    referrer: string | null;
    started_at: string;
    last_activity: string;
    total_page_views: number;
    converted: boolean | number;
    conversion_value: number;
}

export interface SessionViewerData {
    session: Record<string, unknown>;
    cookies: Record<string, string>;
    server: Record<string, string>;
    session_id: string;
    session_status: number;
    php_version: string;
    recent_sessions: SessionViewerSessionRecord[];
}

export interface SessionViewerResponse {
    success: boolean;
    error?: string;
    data?: SessionViewerData;
}
