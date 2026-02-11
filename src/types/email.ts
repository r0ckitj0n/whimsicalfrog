// Email Template Types
export interface IEmailTemplate {
    id: number;
    template_name: string;
    template_type: string;
    subject: string;
    html_content: string;
    text_content?: string;
    description?: string;
    is_active: boolean | number;
    created_at?: string;
    updated_at?: string;
}

export interface IEmailAssignment {
    [key: string]: number | string;
}

// API Response Types
export interface IEmailTemplatesResponse {
    success: boolean;
    templates?: IEmailTemplate[];
}

export interface IEmailAssignmentsResponse {
    success: boolean;
    assignments?: IEmailAssignment;
    data?: IEmailAssignment;
}

export interface IEmailTemplateActionResponse {
    success: boolean;
    message?: string;
    error?: string;
}

// Email History Types
export interface IEmailLog {
    id: number;
    to_email: string;
    from_email: string;
    subject: string;
    content?: string;
    cc_email?: string | null;
    bcc_email?: string | null;
    reply_to?: string | null;
    is_html?: boolean;
    headers?: Record<string, unknown> | null;
    attachments?: unknown[] | null;
    type: string;
    status: string;
    error_message: string | null;
    sent_at: string;
    order_id: string | null;
    created_by: string | null;
}

export interface IPagination {
    current_page: number;
    per_page: number;
    total: number;
    total_pages: number;
}

export interface IEmailHistoryResponse {
    success: boolean;
    data?: IEmailLog[];
    pagination?: IPagination;
    error?: string;
}

export interface IEmailLogDetailResponse {
    success: boolean;
    data?: IEmailLog;
}

// Email Settings Types (migrated from useEmailSettings.ts)
export interface IEmailSettings {
    fromEmail: string;
    fromName: string;
    adminEmail: string;
    supportEmail: string;
    bccEmail: string;
    replyTo: string;
    smtpEnabled: boolean;
    smtpHost: string;
    smtpPort: string | number;
    smtpUsername: string;
    smtpEncryption: string;
    smtpAuth: boolean;
    smtpTimeout: string | number;
    smtpDebug: boolean;
    returnPath: string;
    dkimDomain: string;
    dkimSelector: string;
    dkimIdentity: string;
    smtpAllowSelfSigned: boolean;
}
