export interface IMissingColumnsEntry {
    table: string;
    missing: string[];
}

export interface IDbMigrationsAuditResponse {
    success: boolean;
    generated_at?: string;
    db_name?: string;
    expected_table_count?: number;
    missing_tables?: string[];
    missing_columns?: IMissingColumnsEntry[];
    error?: string;
    message?: string;
    details?: unknown;
}

