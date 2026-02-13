export interface IAICostEstimateOperation {
    key: string;
    label?: string;
    count?: number;
    image_count?: number;
    image_generations?: number;
}

export interface IAICostEstimateRequest {
    action_key: string;
    action_label?: string;
    operations?: IAICostEstimateOperation[];
    context?: {
        image_count?: number;
        prompt_length?: number;
        name_length?: number;
        description_length?: number;
        category_length?: number;
    };
}

export type AICostJobType = 'text_generation' | 'image_analysis' | 'image_creation';

export interface IAICostJobCounts {
    text_generation: number;
    image_analysis: number;
    image_creation: number;
}

export interface IAICostPricingRate {
    job_type: AICostJobType | string;
    unit_cost_cents: number;
    unit_cost_usd: number;
    currency: 'USD' | string;
    source: 'stored' | 'stored_copy' | 'fallback' | string;
    note?: string;
}

export interface IAICostPricingInfo {
    week_start: string;
    provider: string;
    rates: IAICostPricingRate[];
    is_fallback_pricing: boolean;
    fallback_note?: string;
    /** Human-readable, specific reasons for why fallback pricing is in effect (if any). */
    fallback_reasons?: string[];
}

export interface IAICostEstimateLineItem {
    key: string;
    label: string;
    estimated_input_tokens: number;
    estimated_output_tokens: number;
    image_count: number;
    image_generations: number;
    job_counts?: IAICostJobCounts;
    expected_cost: number;
    min_cost: number;
    max_cost: number;
    reasoning?: string;
}

export interface IAICostEstimatePayload {
    provider: string;
    model: string;
    currency: 'USD';
    source: 'stored';
    pricing?: IAICostPricingInfo;
    expected_cost: number;
    min_cost: number;
    max_cost: number;
    operation_count: number;
    line_items: IAICostEstimateLineItem[];
    assumptions: string[];
}

export interface IAICostEstimateResponse {
    success: boolean;
    estimate?: IAICostEstimatePayload;
    error?: string;
}
