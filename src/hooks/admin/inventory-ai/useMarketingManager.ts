import { useState, useCallback } from 'react';
import ApiClient from '../../../core/ApiClient.js';
import logger from '../../../core/logger.js';

export interface MarketingData {
    suggested_title?: string;
    suggested_description?: string;
    target_audience?: string;
    seo_keywords?: string[];
    keywords?: string[];
    selling_points?: string[];
    competitive_advantages?: string[];
    call_to_action_suggestions?: string[];
    urgency_factors?: string[];
    customer_benefits?: string[];
    emotional_triggers?: string[];
    marketing_channels?: string[];
    conversion_triggers?: string[];
    demographic_targeting?: string;
    psychographic_profile?: string;
    search_intent?: string;
    seasonal_relevance?: string;
    confidence_score?: number;
    recommendation_reasoning?: string;
    unique_selling_points?: string;
    value_propositions?: string;
    market_positioning?: string;
    brand_voice?: string;
    content_tone?: string;
    pricing_psychology?: string;
    social_proof_elements?: string[];
    objection_handlers?: string[];
    content_themes?: string[];
    pain_points_addressed?: string[];
    lifestyle_alignment?: string[];
    analysis_factors?: Record<string, unknown>;
    market_trends?: string[];
}

export interface MarketingGenerationParams {
    sku: string;
    name: string;
    description: string;
    category: string;
    brandVoice?: string;
    contentTone?: string;
    freshStart?: boolean;
}

export interface UseMarketingManagerResult {
    marketingData: MarketingData | null;
    isLoading: boolean;
    isGenerating: boolean;
    error: string | null;
    fetchExistingMarketing: (sku: string) => Promise<void>;
    generateMarketing: (params: MarketingGenerationParams) => Promise<MarketingData | null>;
    updateMarketingField: (sku: string, field: string, value: string | string[]) => Promise<boolean>;
    applyToItem: (sku: string, field: string, value: string | number) => Promise<boolean>;
    setMarketingData: React.Dispatch<React.SetStateAction<MarketingData | null>>;
}

export const useMarketingManager = (): UseMarketingManagerResult => {
    const [marketingData, setMarketingData] = useState<MarketingData | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [isGenerating, setIsGenerating] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchExistingMarketing = useCallback(async (sku: string) => {
        setIsLoading(true);
        setError(null);
        try {
            const response = await ApiClient.get<{ success: boolean; data?: MarketingData; exists?: boolean }>(
                'get_marketing_suggestion.php',
                { sku }
            );
            if (response.success && response.data) {
                setMarketingData(response.data);
            } else if (response.exists === false) {
                // No existing data, that's fine
                setMarketingData(null);
            }
        } catch (err) {
            logger.error('Failed to fetch existing marketing data', err);
            setError('Failed to load existing marketing data');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const generateMarketing = useCallback(async (params: MarketingGenerationParams): Promise<MarketingData | null> => {
        setIsGenerating(true);
        setError(null);
        try {
            const response = await ApiClient.post<{
                success: boolean;
                title?: string;
                description?: string;
                keywords?: string[];
                targetAudience?: string;
                marketingIntelligence?: {
                    selling_points?: string[];
                    competitive_advantages?: string[];
                    call_to_action_suggestions?: string[];
                    urgency_factors?: string[];
                    customer_benefits?: string[];
                    emotional_triggers?: string[];
                    seo_keywords?: string[];
                    marketing_channels?: string[];
                    conversion_triggers?: string[];
                    demographic_targeting?: string;
                    psychographic_profile?: string;
                    search_intent?: string;
                    seasonal_relevance?: string;
                    unique_selling_points?: string;
                    value_propositions?: string;
                    market_positioning?: string;
                    brand_voice?: string;
                    content_tone?: string;
                    pricing_psychology?: string;
                    social_proof_elements?: string[];
                    objection_handlers?: string[];
                    content_themes?: string[];
                    pain_points_addressed?: string[];
                    lifestyle_alignment?: string[];
                    analysis_factors?: Record<string, unknown>;
                    market_trends?: string[];
                };
                confidence?: number;
                reasoning?: string;
            }>('suggest_marketing.php', params);

            if (response.success) {
                const newData: MarketingData = {
                    suggested_title: response.title || '',
                    suggested_description: response.description || '',
                    target_audience: response.targetAudience || '',
                    keywords: response.keywords || [],
                    seo_keywords: response.marketingIntelligence?.seo_keywords || response.keywords || [],
                    selling_points: response.marketingIntelligence?.selling_points || [],
                    competitive_advantages: response.marketingIntelligence?.competitive_advantages || [],
                    call_to_action_suggestions: response.marketingIntelligence?.call_to_action_suggestions || [],
                    urgency_factors: response.marketingIntelligence?.urgency_factors || [],
                    marketing_channels: response.marketingIntelligence?.marketing_channels || [],
                    conversion_triggers: response.marketingIntelligence?.conversion_triggers || [],
                    demographic_targeting: response.marketingIntelligence?.demographic_targeting || '',
                    psychographic_profile: response.marketingIntelligence?.psychographic_profile || '',
                    search_intent: response.marketingIntelligence?.search_intent || '',
                    seasonal_relevance: response.marketingIntelligence?.seasonal_relevance || '',
                    customer_benefits: response.marketingIntelligence?.customer_benefits || [],
                    emotional_triggers: response.marketingIntelligence?.emotional_triggers || [],
                    unique_selling_points: response.marketingIntelligence?.unique_selling_points || '',
                    value_propositions: response.marketingIntelligence?.value_propositions || '',
                    market_positioning: response.marketingIntelligence?.market_positioning || '',
                    brand_voice: response.marketingIntelligence?.brand_voice || '',
                    content_tone: response.marketingIntelligence?.content_tone || '',
                    pricing_psychology: response.marketingIntelligence?.pricing_psychology || '',
                    social_proof_elements: response.marketingIntelligence?.social_proof_elements || [],
                    objection_handlers: response.marketingIntelligence?.objection_handlers || [],
                    content_themes: response.marketingIntelligence?.content_themes || [],
                    pain_points_addressed: response.marketingIntelligence?.pain_points_addressed || [],
                    lifestyle_alignment: response.marketingIntelligence?.lifestyle_alignment || [],
                    analysis_factors: response.marketingIntelligence?.analysis_factors || {},
                    market_trends: response.marketingIntelligence?.market_trends || [],
                    confidence_score: response.confidence,
                    recommendation_reasoning: response.reasoning
                };
                setMarketingData(newData);
                return newData;
            } else {
                setError('Failed to generate marketing content');
                return null;
            }
        } catch (err) {
            logger.error('Failed to generate marketing', err);
            setError('Failed to generate marketing content');
            return null;
        } finally {
            setIsGenerating(false);
        }
    }, []);

    const updateMarketingField = useCallback(async (
        sku: string,
        field: string,
        value: string | string[]
    ): Promise<boolean> => {
        try {
            const response = await ApiClient.post<{ success: boolean }>(
                'marketing_manager.php?action=update_field',
                { sku, field, value: typeof value === 'string' ? value : JSON.stringify(value) }
            );
            return response.success;
        } catch (err) {
            logger.error('Failed to update marketing field', err);
            return false;
        }
    }, []);

    const applyToItem = useCallback(async (
        sku: string,
        field: string,
        value: string | number
    ): Promise<boolean> => {
        try {
            const response = await ApiClient.post<{ success: boolean }>(
                'inventory.php?action=update_field',
                { sku, field, value }
            );
            return response.success;
        } catch (err) {
            logger.error('Failed to apply field to item', err);
            return false;
        }
    }, []);

    return {
        marketingData,
        isLoading,
        isGenerating,
        error,
        fetchExistingMarketing,
        generateMarketing,
        updateMarketingField,
        applyToItem,
        setMarketingData
    };
};

export default useMarketingManager;
