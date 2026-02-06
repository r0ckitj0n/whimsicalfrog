/**
 * Common API Response Types
 * 
 * Provides standard response shapes for API interactions.
 * Used across all domain-specific response interfaces.
 */

/** Base API response with success flag and optional error */
export interface IApiResponse<T = unknown> {
    success: boolean;
    error?: string;
    message?: string;
    data?: T;
}

/** Simple action response (create, update, delete operations) */
export interface IActionResponse {
    success: boolean;
    error?: string;
}
