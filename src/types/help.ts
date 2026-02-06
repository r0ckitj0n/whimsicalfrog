// Help Documentation Types
export interface IHelpDocument {
    title: string;
    content: string;
    path: string;
}

export interface IHelpDocListItem {
    title: string;
    path: string;
    content: string;
}

// API Response Types
export interface IHelpListResponse {
    success: boolean;
    documents?: Array<{
        title: string;
        filename: string;
        content?: string;
    }>;
    error?: string;
}

export interface IHelpDocResponse {
    success: boolean;
    document?: IHelpDocument;
    error?: string;
}
