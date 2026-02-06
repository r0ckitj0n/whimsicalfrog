// File Manager Types
export interface IFileItem {
    name: string;
    path: string;
    type: 'file' | 'directory';
    extension?: string;
    size?: number;
    size_formatted?: string;
    modified?: number;
    viewable?: boolean;
    editable?: boolean;
}

export interface IFileContent {
    path: string;
    filename: string;
    content: string;
    editable: boolean;
    size: number;
    modified: number;
}

// API Response Types
export interface IFileDirectoryResponse {
    success: boolean;
    items?: IFileItem[];
    path?: string;
    parent?: string | null;
    error?: string;
}

export interface IFileReadResponse extends IFileContent {
    success: boolean;
    error?: string;
}

export interface IFileActionResponse {
    success: boolean;
    error?: string;
}
