export interface IApiResponse<T = unknown> {
    success: boolean;
    data?: T;
    message?: string;
    error?: string;
    details?: unknown;
}

export interface IAreaMappingUpsertResult {
    id?: number;
    updated?: boolean;
    message?: string;
}

export type IAreaMappingUpsertResponse = IApiResponse<IAreaMappingUpsertResult>;

