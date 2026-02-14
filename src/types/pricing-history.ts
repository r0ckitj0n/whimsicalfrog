/**
 * Historical pricing types (shared between frontend + backend).
 * Kept minimal on purpose: this is for "recent sold price" lookups.
 */

export interface IRecentSoldPriceResponse {
    success: boolean;
    sku: string;
    avg_price: number | null;
    min_price?: number | null;
    max_price?: number | null;
    line_count: number;
    last_sold_at: string | null;
    error?: string;
}

