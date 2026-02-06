/**
 * Marketing Types
 * Centralized interfaces for marketing settings and features
 * Migrated from: useMarketingSettings.ts, useMarketingSelfCheck.ts
 */

export interface IMarketingIntelligence {
    target_audience?: string;
    demographic_targeting?: string;
    psychographic_profile?: string;
    search_intent?: string;
    seasonal_relevance?: string;
    selling_points?: string[];
    keywords?: string[];
}

export interface ICartButtonText {
    id: number;
    text: string;
    is_active: boolean;
}

export interface IShopEncouragement {
    id: number;
    text: string;
    category: string;
}

export interface ISelfCheckMetric {
    id: string;
    label: string;
    status: 'pass' | 'fail' | 'warn';
    details: Record<string, string | number | boolean>;
}

export interface ISelfCheckData {
    success: boolean;
    summary: {
        pass: number;
        fail: number;
        warn: number;
    };
    checks: ISelfCheckMetric[];
}

export interface ISelfCheckResponse extends ISelfCheckData {
    message?: string;
}
