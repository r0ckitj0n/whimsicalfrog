import { useState, useCallback, useEffect } from 'react';
import { AiManager } from '../../core/ai/AiManager.js';
import { useInventoryAI } from './useInventoryAI.js';
import { useAIContentGenerator, IInventoryItemMinimal } from './useAIContentGenerator.js';
import logger from '../../core/logger.js';

import { ApiClient } from '../../core/ApiClient.js';

export const useAISuggestions = () => {
    const { items } = useAIContentGenerator();
    const LAST_SELECTED_SKU_KEY = 'wf_last_ai_suggestions_sku';
    const [selectedSkuState, setSelectedSkuState] = useState(() => {
        try {
            return localStorage.getItem(LAST_SELECTED_SKU_KEY) || '';
        } catch (_err) {
            return '';
        }
    });
    const setSelectedSku = useCallback((sku: string) => {
        setSelectedSkuState(sku);
        try {
            if (sku) {
                localStorage.setItem(LAST_SELECTED_SKU_KEY, sku);
            } else {
                localStorage.removeItem(LAST_SELECTED_SKU_KEY);
            }
        } catch (_err) {
            // Ignore storage failures; state update still succeeds.
        }
    }, []);
    const [currentItem, setCurrentItem] = useState<IInventoryItemMinimal | null>(null);
    const [suggestions, setSuggestions] = useState<{
        title: string;
        description: string;
        cost: number | null;
        price: number | null;
        priceReasoning?: string;
        costBreakdown?: Record<string, number>;
    }>({
        title: '',
        description: '',
        cost: null,
        price: null
    });

    const [marketing, setMarketing] = useState<{
        targetAudience?: string;
        sellingPoints?: string[];
        marketingChannels?: string[];
        emotionalTriggers?: string[];
        competitiveAdvantages?: string[];
        confidenceScore?: string;
    } | null>(null);

    const [status, setStatus] = useState<{ message: string; isError?: boolean } | null>(null);
    const {
        is_busy: isGenerating,
        generateMarketing,
        fetch_price_suggestion,
        fetch_cost_suggestion,
        setCachedCostSuggestion,
        setCachedPriceSuggestion
    } = useInventoryAI();

    useEffect(() => {
        const item = items.find(it => it.sku === selectedSkuState) || null;
        setCurrentItem(item);

        if (selectedSkuState) {
            fetchStoredSuggestions(selectedSkuState);
        }
    }, [selectedSkuState, items]);

    const fetchStoredSuggestions = useCallback(async (sku: string) => {
        setStatus({ message: 'Checking for stored suggestions...' });
        try {
            const mktRes = await AiManager.getStoredMarketingSuggestion(sku);
            const priceRes = await AiManager.getStoredPricingSuggestion(sku);
            const costRes = await AiManager.getStoredCostSuggestion(sku);

            interface ICostSuggestionResponse {
                success?: boolean;
                suggested_cost?: number;
                breakdown?: Record<string, number>;
            }
            interface IPriceSuggestionResponse {
                success?: boolean;
                suggested_price?: number;
                reasoning?: string;
            }

            const costData = costRes as ICostSuggestionResponse | null;
            const priceData = priceRes as IPriceSuggestionResponse | null;
            const marketingResponse = mktRes as {
                exists?: boolean;
                data?: Record<string, unknown>;
                sku?: string;
                target_audience?: string;
                demographic_targeting?: string;
                selling_points?: string[];
                marketing_channels?: string[];
                emotional_triggers?: string[];
                competitive_advantages?: string[];
                confidence_score?: string;
                suggested_title?: string;
                suggested_description?: string;
            } | null;
            const marketingData = (marketingResponse?.data && typeof marketingResponse.data === 'object')
                ? marketingResponse.data as Record<string, unknown>
                : marketingResponse as unknown as Record<string, unknown> | null;
            const marketingExists = marketingResponse?.exists ?? Boolean(marketingData?.sku);

            if (marketingExists || priceData?.success || costData?.success) {
                setSuggestions({
                    title: String(marketingData?.suggested_title || ''),
                    description: String(marketingData?.suggested_description || ''),
                    cost: costData?.suggested_cost ?? null,
                    price: priceData?.suggested_price ?? null,
                    priceReasoning: priceData?.reasoning || '',
                    costBreakdown: costData?.breakdown || undefined
                });

                // Store marketing data separately
                if (marketingExists && marketingData) {
                    setMarketing({
                        targetAudience: String(marketingData.target_audience || marketingData.demographic_targeting || ''),
                        sellingPoints: (marketingData.selling_points as string[]) || [],
                        marketingChannels: (marketingData.marketing_channels as string[]) || [],
                        emotionalTriggers: (marketingData.emotional_triggers as string[]) || [],
                        competitiveAdvantages: (marketingData.competitive_advantages as string[]) || [],
                        confidenceScore: marketingData.confidence_score as string | undefined
                    });
                } else {
                    setMarketing(null);
                }

                setStatus({ message: 'Loaded stored suggestions.' });
            } else {
                setSuggestions({ title: '', description: '', cost: null, price: null });
                setMarketing(null);
                setStatus(null);
            }
        } catch (err) {
            logger.error('[AISuggestions] fetchStored failed', err);
            setStatus({ message: 'Failed to load suggestions', isError: true });
        }
    }, []);

    const generateAll = async (item: IInventoryItemMinimal) => {
        setStatus({ message: 'Generating all suggestions...' });
        if (window.WFToast) window.WFToast.info('🚀 Starting AI generation...');

        try {
            // Use the combined endpoint for single image upload/load optimization
            if (window.WFToast) window.WFToast.info('📊 Analyzing cost & price...');
            const combinedData = await AiManager.getCombinedSuggestions({
                sku: item.sku,
                name: item.name,
                description: item.description,
                category: item.category,
                cost_price: item.cost_price || 0,
                useImages: true,
                quality_tier: 'standard'
            });

            if (combinedData && combinedData.success) {
                if (window.WFToast) window.WFToast.success('✅ Cost & price analysis complete');

                const cost = combinedData.cost_suggestion;
                const price_raw = combinedData.price_suggestion;

                // For price, we need to normalize it through the existing logic in usePriceSuggestions if possible,
                // or just handle it here if it's already structured correctly.
                // The suggest_all.php response for price is similar to suggest_price.php.

                if (window.WFToast) window.WFToast.info('📝 Generating marketing content...');
                const genMkt = await generateMarketing({
                    sku: item.sku,
                    name: item.name,
                    description: item.description,
                    category: item.category
                });

                if (genMkt) {
                    if (window.WFToast) window.WFToast.success('✅ Marketing content generated');
                    // Update marketing state with new data (cast to any since API response may have different property names)
                    const mktData = genMkt as unknown as Record<string, unknown>;
                    setMarketing({
                        targetAudience: (mktData.target_audience || mktData.demographic_targeting) as string | undefined,
                        sellingPoints: (mktData.selling_points || []) as string[],
                        marketingChannels: (mktData.marketing_channels || []) as string[],
                        emotionalTriggers: (mktData.emotional_triggers || []) as string[],
                        competitiveAdvantages: (mktData.competitive_advantages || []) as string[],
                        confidenceScore: mktData.confidence_score as string | undefined
                    });
                }

                const genTitle = genMkt?.title || genMkt?.suggested_title;
                const genDesc = genMkt?.description || genMkt?.suggested_description;

                setSuggestions({
                    title: genTitle || item.name,
                    description: genDesc || item.description,
                    cost: cost?.success ? (cost.suggested_cost || null) : null,
                    price: price_raw?.success ? (price_raw.suggested_price || null) : null,
                    priceReasoning: price_raw?.reasoning,
                    costBreakdown: cost?.breakdown as Record<string, number>
                });

                // Update individual hook caches if possible
                if (cost?.success) {
                    setCachedCostSuggestion({
                        suggested_cost: cost.suggested_cost,
                        reasoning: cost.reasoning || '',
                        confidence: cost.confidence,
                        created_at: cost.created_at,
                        breakdown: cost.breakdown,
                        analysis: cost.analysis,
                        _cachedAt: Date.now()
                    });
                }

                setStatus({ message: 'Generation complete!' });
                if (window.WFToast) window.WFToast.success('🎉 All AI suggestions ready!');
            } else {
                // Fallback to individual parallel calls if combined fails
                if (window.WFToast) window.WFToast.info('⚡ Using fallback generation...');

                const [genMkt, price, cost] = await Promise.all([
                    generateMarketing({
                        sku: item.sku,
                        name: item.name,
                        description: item.description,
                        category: item.category
                    }),
                    fetch_price_suggestion({
                        sku: item.sku,
                        name: item.name,
                        description: item.description,
                        category: item.category,
                        cost_price: item.cost_price || 0,
                        useImages: true
                    }),
                    fetch_cost_suggestion({
                        sku: item.sku,
                        name: item.name,
                        description: item.description,
                        category: item.category,
                        useImages: true
                    })
                ]);

                const genTitle = genMkt?.title || genMkt?.suggested_title;
                const genDesc = genMkt?.description || genMkt?.suggested_description;
                const suggested_cost = cost?.suggested_cost || 0;

                setSuggestions({
                    title: genTitle || item.name,
                    description: genDesc || item.description,
                    cost: suggested_cost > 0 ? suggested_cost : null,
                    price: price?.suggested_price || null,
                    priceReasoning: price?.reasoning,
                    costBreakdown: cost?.breakdown as Record<string, number>
                });

                // Update marketing from fallback
                if (genMkt) {
                    const mktData = genMkt as unknown as Record<string, unknown>;
                    setMarketing({
                        targetAudience: (mktData.target_audience || mktData.demographic_targeting) as string | undefined,
                        sellingPoints: (mktData.selling_points || []) as string[],
                        marketingChannels: (mktData.marketing_channels || []) as string[],
                        emotionalTriggers: (mktData.emotional_triggers || []) as string[],
                        competitiveAdvantages: (mktData.competitive_advantages || []) as string[],
                        confidenceScore: mktData.confidence_score as string | undefined
                    });
                }

                setStatus({ message: 'Generation complete (via fallback)!' });
                if (window.WFToast) window.WFToast.success('🎉 All AI suggestions ready!');
            }
        } catch (err) {
            logger.error('[AISuggestions] generateAll failed', err);
            setStatus({ message: 'Generation failed', isError: true });
            if (window.WFToast) window.WFToast.error('❌ AI generation failed - check console for details');
        }
    };

    const applyField = async (sku: string, field: string, value: string | number | boolean | null) => {
        try {
            const res = await ApiClient.post<{ success: boolean }>('/api/update_inventory.php', { sku, field, value });
            return res && res.success;
        } catch (err) {
            logger.error(`[AISuggestions] apply ${field} failed`, err);
            return false;
        }
    };


    return {
        selectedSku: selectedSkuState,
        setSelectedSku,
        currentItem,
        suggestions,
        setSuggestions,
        marketing,
        status,
        setStatus,
        isGenerating,
        isLoading: isGenerating, // Alias for compatibility
        generateAll,
        applyField,
        fetchStoredSuggestions,
        fetchItems: fetchStoredSuggestions // Alias for consistency
    };
};
