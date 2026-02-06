/**
 * AI Abstraction Layer
 * Following .windsurfrules: All calls to external AI services must go through src/core/ai/*
 */

import ApiClient from '../ApiClient.js';

export interface AiSuggestion {
    suggested_text: string;
    confidence: number;
    reasoning?: string;
}

export interface MarketingIntelligence {
    title: string;
    description: string;
    keywords: string[];
    target_audience: string;
    suggested_title?: string;
    suggested_description?: string;
    seo_keywords?: string[] | string;
    unique_selling_points?: string;
    value_propositions?: string;
}

export class AiManager {
    /**
     * Get marketing intelligence for an item (triggers generation and persistence)
     */
    static async getMarketingIntelligence(params: {
        sku: string;
        name: string;
        description: string;
        category: string;
        brandVoice?: string;
        contentTone?: string;
        freshStart?: boolean;
    }): Promise<MarketingIntelligence> {
        // suggest_marketing.php handles generation and persistence via POST
        return ApiClient.post<MarketingIntelligence>('suggest_marketing.php', params);
    }

    /**
     * Specifically fetch stored marketing suggestions without triggering generation
     */
    static async getStoredMarketingSuggestion(sku: string): Promise<any> {
        return ApiClient.get<any>('get_marketing_suggestion.php', { sku });
    }

    /**
     * Get pricing suggestion for an item
     */
    static async getPricingSuggestion(params: {
        sku: string;
        name: string;
        description: string;
        category: string;
        cost_price: string | number;
        useImages?: boolean;
        quality_tier?: string;
    }): Promise<Record<string, unknown>> {
        return ApiClient.post<Record<string, unknown>>('suggest_price.php', params);
    }

    /**
     * Get cost suggestion for an item
     */
    static async getCostSuggestion(params: {
        sku?: string;
        name: string;
        description: string;
        category: string;
        quality_tier?: string;
        useImages?: boolean;
    }): Promise<Record<string, unknown>> {
        return ApiClient.post<Record<string, unknown>>('suggest_cost.php', params);
    }

    /**
     * Get combined cost and price suggestions (optimizes image analysis)
     */
    static async getCombinedSuggestions(params: {
        sku: string;
        name: string;
        description: string;
        category: string;
        cost_price: string | number;
        useImages?: boolean;
        quality_tier?: string;
    }): Promise<Record<string, any>> {
        return ApiClient.post<Record<string, any>>('suggest_all.php', params);
    }

    /**
     * Get stored pricing suggestion
     */
    static async getStoredPricingSuggestion(sku: string): Promise<Record<string, unknown>> {
        return ApiClient.get<Record<string, unknown>>('get_price_suggestion.php', { sku });
    }

    /**
     * Get stored cost suggestion
     */
    static async getStoredCostSuggestion(sku: string): Promise<Record<string, unknown>> {
        return ApiClient.get<Record<string, unknown>>('get_cost_suggestion.php', { sku });
    }

    /**
     * Update AI settings
     */
    static async updateSettings(settings: Record<string, unknown>): Promise<{ success: boolean; message?: string }> {
        return ApiClient.post<{ success: boolean; message?: string }>('ai_settings.php?action=update_settings', settings);
    }

    /**
     * Get content tone options
     */
    static async getContentToneOptions(): Promise<unknown[]> {
        return ApiClient.get<unknown[]>('content_tone_options.php?action=list');
    }

    /**
     * Get brand voice options
     */
    static async getBrandVoiceOptions(): Promise<unknown[]> {
        return ApiClient.get<unknown[]>('brand_voice_options.php?action=list');
    }

    /**
     * Run image analysis via AI
     */
    static async analyzeImage(imageData: string | File): Promise<Record<string, unknown>> {
        const formData = new FormData();
        if (typeof imageData === 'string') {
            formData.append('image_url', imageData);
        } else {
            formData.append('image_file', imageData);
        }
        return ApiClient.upload<Record<string, unknown>>('run_image_analysis.php', formData);
    }
}

export default AiManager;
