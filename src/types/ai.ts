// AI Settings Types
export interface IAISettings {
    ai_provider: string;
    openai_api_key?: string;
    openai_model?: string;
    anthropic_api_key?: string;
    anthropic_model?: string;
    google_api_key?: string;
    google_model?: string;
    meta_api_key?: string;
    meta_model?: string;
    ai_temperature: number;
    ai_max_tokens: number;
    ai_timeout: number;
    fallback_to_local: boolean;
    ai_brand_voice?: string;
    ai_content_tone: string;
    ai_cost_temperature: number;
    ai_price_temperature: number;
    ai_cost_multiplier_base: number;
    ai_price_multiplier_base: number;
    ai_conservative_mode: boolean;
    ai_market_research_weight: number;
    ai_cost_plus_weight: number;
    ai_value_based_weight: number;
    openai_key_present?: boolean;
    openai_key_suffix?: string;
    anthropic_key_present?: boolean;
    anthropic_key_suffix?: string;
    google_key_present?: boolean;
    google_key_suffix?: string;
    meta_key_present?: boolean;
    meta_key_suffix?: string;
}

export interface IAISettingsResponse {
    success?: boolean;
    settings?: IAISettings;
    data?: IAISettings;
    error?: string;
    message?: string;
}

export interface IAIProviderTestResponse {
    success: boolean;
    message: string;
}

export interface IAIModel {
    id: string;
    name: string;
    description?: string;
    supportsVision?: boolean;
}

export interface IAIModelsResponse {
    success?: boolean;
    models?: IAIModel[];
    data?: IAIModel[];
}

// Intent Heuristics Types (migrated from useIntentHeuristics.ts)
export interface IIntentWeights {
    popularity_cap: number;
    kw_positive: number;
    cat_positive: number;
    seasonal: number;
    same_category: number;
    upgrade_price_ratio_threshold: number;
    upgrade_price_boost: number;
    upgrade_label_boost: number;
    replacement_label_boost: number;
    gift_set_boost: number;
    gift_price_boost: number;
    teacher_price_ceiling: number;
    teacher_price_boost: number;
    budget_proximity_mult: number;
    neg_keyword_penalty: number;
    intent_badge_threshold: number;
}

export interface IBudgetRanges {
    low: [number, number];
    mid: [number, number];
    high: [number, number];
}

export interface IIntentHeuristics {
    weights: IIntentWeights;
    budget_ranges: IBudgetRanges;
    keywords: {
        positive: Record<string, string[]>;
        negative: Record<string, string[]>;
        categories: Record<string, string[]>;
    };
    seasonal_months: Record<string, string[]>;
}

// Price Breakdown Types (migrated from usePriceBreakdown.ts)
export interface IPriceFactor {
    id: number;
    sku: string;
    label: string;
    amount: number;
    type: string;
    explanation: string;
    source: 'manual' | 'ai';
    created_at: string;
}

export interface IPriceBreakdown {
    factors: IPriceFactor[];
    totals: {
        total: number;
        stored: number;
        ai_confidence?: number;
        ai_at?: string;
    };
}

// ============================================================================
// Additional API Response Interfaces
// ============================================================================

/** Response for intent heuristics endpoint */
export interface IHeuristicsResponse {
    success: boolean;
    setting?: {
        setting_value: string | IIntentHeuristics;
    };
}

/** Response for AI suggestions endpoint */
export interface IAISuggestionsResponse {
    success: boolean;
    info_suggestion?: { name?: string; description?: string; category?: string };
    cost_suggestion?: { suggested_cost?: number };
    price_suggestion?: { suggested_price?: number };
}

/** Params for AI suggestions request */
export interface IAISuggestionsParams {
    sku: string;
    name?: string;
    description?: string;
    category?: string;
    cost_price?: number | string;
    tier?: string;
}



