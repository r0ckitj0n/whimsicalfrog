export interface IVersionInfo {
    commit_hash: string | null;
    commit_short_hash: string | null;
    commit_subject: string | null;
    built_at: string | null;
    deployed_for_live_at: string | null;
    server_time: string;
    mode: 'dev' | 'prod';
    source: 'git' | 'artifact' | 'mixed' | 'unknown';
}

export interface IVersionInfoResponse {
    success: boolean;
    data?: IVersionInfo;
    message?: string;
}
