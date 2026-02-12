// Health Check Types
export interface IHealthStatus {
    backgrounds: {
        missingActive: string[];
        missingFiles: string[];
    };
    items: {
        noPrimary: number;
        missingFiles: number;
    };
    page: {
        hasBackground: boolean;
        missingImagesCount: number;
    };
}

// DB Status Types
export interface IDbStatus {
    online: boolean;
    items?: number;
    rooms?: number;
    host?: string;
    database?: string;
    error?: string;
    mysql_version?: string;
    table_count?: number;
}

export interface ISyncStatus {
    inSync: boolean;
    diffs: {
        cssRules: number;
        items: number;
        rooms: number;
    };
}

// Site Maintenance Types
export interface IBackupDetails {
    success: boolean;
    filename?: string;
    filepath?: string;
    size?: number;
    timestamp?: number;
    destinations?: string[];
    error?: string;
}

export interface IDatabaseInfo {
    total_active: number;
    total_backup: number;
    organized: Record<string, Array<{ name: string; rows?: number; row_count?: number; field_count?: number }>>;
}

export interface ISystemConfig {
    system_info: {
        primary_identifier: string;
        sku_format: string;
        main_entity: string;
    };
    sample_skus: string[];
    category_codes: Record<string, string>;
    categories: string[];
    statistics: {
        total_items: number;
        total_images: number;
        total_orders: number;
        total_order_items: number;
        categories_count: number;
        last_order_date: string | null;
    };
    id_formats: {
        recent_customers: Array<{ id: string; username: string }>;
        recent_orders: string[];
        recent_order_items: string[];
    };
}

export interface IScanResult {
    success: boolean;
    total_files: number;
    needs_conversion: number;
    converted: number;
    files?: string[];
    message?: string;
}

export interface IDbQueryResults {
    rows: Record<string, unknown>[];
    columns: string[];
    rowCount: number;
    executionTime?: number;
}

// Image Cleanup Types
export interface IImageCleanupArchivedFile {
    rel_path: string; // e.g. images/signs/foo.webp
    archived_rel_path: string; // e.g. backups/image_cleanup/.../images/signs/foo.webp
    bytes: number;
}

export interface IImageCleanupReport {
    job_id: string;
    started_at: string;
    finished_at?: string;
    archive_root_rel: string; // backups/image_cleanup/<timestamp>-<jobid>
    dry_run: boolean;
    total_files: number;
    referenced_files: number;
    archived_files: IImageCleanupArchivedFile[];
    skipped_whitelist: string[];
    errors: string[];
}

export interface IImageCleanupStartResponse {
    success: boolean;
    job_id?: string;
    error?: string;
    message?: string;
}

export interface IImageCleanupStepResponse {
    success: boolean;
    job_id?: string;
    phase?: 'init' | 'building_references' | 'archiving' | 'complete' | 'error';
    status?: string;
    progress?: {
        processed: number;
        total: number;
        archived: number;
        referenced: number;
        whitelisted: number;
    };
    report?: IImageCleanupReport;
    error?: string;
    message?: string;
}

// ============================================================================
// API Response Interfaces
// ============================================================================

/** Response for DB status endpoint */
export interface IDbStatusResponse {
    success: boolean;
    message?: string;
    local?: IDbStatus;
    live?: IDbStatus;
}

/** Response for DB query endpoint */
export interface IDbQueryResponse {
    success: boolean;
    results?: IDbQueryResults;
    error?: string;
}

/** Response for health check backgrounds endpoint */
export interface IHealthBackgroundResponse {
    success: boolean;
    data?: {
        missingActive: string[];
        missingFiles: string[];
    };
}

/** Response for health check items endpoint */
export interface IHealthItemResponse {
    success: boolean;
    data?: {
        counts?: {
            noPrimary: number;
            missingFiles: number;
        };
    };
}

