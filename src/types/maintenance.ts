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
    scope?: {
        type: 'full' | 'images' | 'database_tables';
        image_groups?: Array<'items' | 'backgrounds' | 'signs'>;
        data_groups?: Array<'room_maps' | 'customers' | 'inventory' | 'orders'>;
        tables?: string[];
    };
    error?: string;
}

export type BackupArtifactType = 'website' | 'database';

export interface IMaintenanceBackupFile {
    name: string;
    size: number;
    mtime: number;
    rel: string;
    type: BackupArtifactType;
}

export interface IBackupListResponse {
    success: boolean;
    files: IMaintenanceBackupFile[];
}

export interface IRestoreResult {
    success: boolean;
    message?: string;
    error?: string;
    restored_file?: string;
    restore_time_seconds?: number;
    extracted_files?: number;
    tables_restored?: number;
    records_restored?: number;
    statements_executed?: number;
    pre_restore_backup?: string | null;
    preflight?: {
        statements: number;
        tables_touched: number;
        tables_recreated: number;
        tables_data_restored: number;
    };
}

export interface IRestoreDatabaseRequest {
    server_backup_path: string;
    ignore_errors?: '1' | '0';
    table_whitelist?: string[];
    data_groups?: DatabaseDataGroup[];
}

export interface IRestoreDatabaseUploadOptions {
    ignore_errors?: boolean;
    table_whitelist?: string[];
    data_groups?: DatabaseDataGroup[];
}

export type WebsiteImageGroup = 'items' | 'backgrounds' | 'signs';
export type DatabaseDataGroup = 'room_maps' | 'customers' | 'inventory' | 'orders';

export interface IWebsiteBackupScope {
    mode: 'full' | 'images';
    image_groups?: WebsiteImageGroup[];
}

export interface IDatabaseBackupScope {
    mode: 'full' | 'tables';
    data_groups?: DatabaseDataGroup[];
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
