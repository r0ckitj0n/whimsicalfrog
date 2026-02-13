export interface IAISuggestCostRequest {
    sku?: string;
    name: string;
    description: string;
    category: string;
    quality_tier?: string;
    useImages?: boolean;
    imageData?: string;
}

export interface IAISuggestPriceRequest {
    sku: string;
    name: string;
    description: string;
    category: string;
    cost_price: number | string;
    quality_tier?: string;
    useImages?: boolean;
}

export interface IAISuggestFallbackMeta {
    /** True when the response is based on a fallback strategy instead of the primary AI output. */
    fallback_used: boolean;
    /**
     * Human-readable reason explaining why fallback was used.
     * Example: "Primary provider failed (401). Market-average heuristic applied."
     */
    fallback_reason?: string;
    /** The strategy used for fallback when fallback_used is true. */
    fallback_kind?: 'provider_fallback' | 'heuristic' | 'confidence_override' | 'none' | string;
}

export interface IAISuggestCostResponse extends IAISuggestFallbackMeta {
    success: boolean;
    suggested_cost: number;
    reasoning: string;
    confidence: number;
    confidenceLabel?: string;
    breakdown: Record<string, unknown>;
    analysis: Record<string, unknown>;
    created_at: string;
    providerUsed?: string;
}

export interface IAISuggestPriceResponse extends IAISuggestFallbackMeta {
    success: boolean;
    suggested_price: number;
    reasoning: string;
    confidence: number;
    factors: Record<string, unknown>;
    components: Array<Record<string, unknown>>;
    analysis: Record<string, unknown>;
}

