/**
 * AI Generation Orchestrator Hook
 * 
 * Orchestrates sequential AI generation for "Generate All" functionality.
 * Each step builds on the output of the previous step.
 * 
 * Chain Order:
 * 1. Generate Title (from primary image)
 * 2. Generate Description (from image + title)
 * 3. Assign Category (from image, title, description)
 * 4. Generate Cost Breakdown (from all above)
 * 5. Calculate Cost Suggestion (sum of breakdown)
 * 6. Generate Marketing Info (from all above)
 * 7. Generate Price Suggestion (from all above)
 */

import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import { toastSuccess, toastError } from '../../core/toast.js';
import logger from '../../core/logger.js';
import type { MarketingData } from './inventory-ai/useMarketingManager.js';

export interface GenerationContext {
    sku: string;
    primaryImageUrl: string;
    imageUrls: string[];
    name: string;
    description: string;
    category: string;
    tier: string;
    weightOz: number | null;
    packageLengthIn: number | null;
    packageWidthIn: number | null;
    packageHeightIn: number | null;
    costBreakdown: Record<string, number> | null;
    suggestedCost: number | null;
    costConfidence: number | null;
    costReasoning: string | null;
    marketingData: MarketingData | null;
    suggestedPrice: number | null;
    priceConfidence: number | null;
    priceReasoning: string | null;
    priceComponents: Array<{ type: string; amount: number; label: string; explanation: string }> | null;
}

export interface GenerationStepResult {
    success: boolean;
    stepName: string;
    data: Partial<GenerationContext>;
    error?: string;
}

export type GenerationStep = 'info' | 'cost' | 'price' | 'marketing';

interface UseAIGenerationOrchestratorReturn {
    isGenerating: boolean;
    currentStep: GenerationStep | null;
    progress: number; // 0-100
    orchestrateFullGeneration: (params: {
        sku: string;
        primaryImageUrl: string;
        imageUrls?: string[];
        initialName?: string;
        initialDescription?: string;
        initialCategory?: string;
        tier?: string;
        lockedFields?: Record<string, boolean>;
        lockedWords?: Record<string, string>;
        onStepComplete?: (step: GenerationStep, context: GenerationContext, skippedFields?: string[]) => void;
    }) => Promise<GenerationContext | null>;
    generateInfoOnly: (params: {
        sku: string;
        primaryImageUrl: string;
        imageUrls?: string[];
        previousName?: string;
        lockedFields?: Record<string, boolean>;
        lockedWords?: Record<string, string>;
        includeMarketingRefinement?: boolean;
    }) => Promise<Partial<GenerationContext> | null>;
    generateCostOnly: (params: {
        sku: string;
        name: string;
        description: string;
        category: string;
        tier?: string;
    }) => Promise<Partial<GenerationContext> | null>;
    generatePriceOnly: (params: {
        sku: string;
        name: string;
        description: string;
        category: string;
        costPrice: number;
        tier?: string;
    }) => Promise<Partial<GenerationContext> | null>;
    generateMarketingOnly: (params: {
        sku: string;
        name: string;
        description: string;
        category: string;
        freshStart?: boolean;
    }) => Promise<Partial<GenerationContext> | null>;
}

export const useAIGenerationOrchestrator = (): UseAIGenerationOrchestratorReturn => {
    const [isGenerating, setIsGenerating] = useState(false);
    const [currentStep, setCurrentStep] = useState<GenerationStep | null>(null);
    const [progress, setProgress] = useState(0);

    const toNumber = (value: unknown): number | null => {
        if (typeof value === 'number' && Number.isFinite(value)) return value;
        if (typeof value === 'string' && value.trim() !== '') {
            const parsed = Number(value);
            if (Number.isFinite(parsed)) return parsed;
        }
        return null;
    };

    const normalizeCostBreakdown = (raw?: Record<string, unknown> | null): Record<string, number> | null => {
        if (!raw || typeof raw !== 'object') return null;

        const normalized: Record<string, number> = {};
        const keyMap: Record<string, string> = {
            materials: 'materials',
            material: 'materials',
            labor: 'labor',
            labour: 'labor',
            energy: 'energy',
            utilities: 'energy',
            power: 'energy',
            equipment: 'equipment',
            machinery: 'equipment',
            tools: 'equipment'
        };

        Object.entries(raw).forEach(([key, value]) => {
            const num = toNumber(value);
            if (num === null) return;
            const lowered = key.toLowerCase().trim();
            const mapped = keyMap[lowered] || lowered;
            normalized[mapped] = num;
        });

        return Object.keys(normalized).length > 0 ? normalized : null;
    };

    const parseLockedTokens = (lockedValue?: string): { phrases: string[]; words: string[] } => {
        if (!lockedValue) return { phrases: [], words: [] };
        const phrases: string[] = [];
        const phraseMatches = lockedValue.match(/\"([^\"]+)\"/g) || [];
        phraseMatches.forEach((m) => phrases.push(m.replace(/\"/g, '').trim()));
        const remaining = lockedValue.replace(/\"([^\"]+)\"/g, ' ').trim();
        const words = remaining.split(/\s+/).filter(Boolean);
        return { phrases, words };
    };

    const containsToken = (text: string, token: string): boolean => new RegExp(`\\b${token.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&')}\\b`, 'i').test(text);

    const applyLockedWordsToText = (value: string, lockedValue?: string): string => {
        if (!lockedValue) return value;
        const { phrases, words } = parseLockedTokens(lockedValue);
        let result = value || '';
        phrases.forEach((p) => {
            if (p && !result.toLowerCase().includes(p.toLowerCase())) {
                result = `${result} ${p}`.trim();
            }
        });
        words.forEach((w) => {
            if (w && !containsToken(result, w)) {
                result = `${result} ${w}`.trim();
            }
        });
        return result;
    };

    const applyGeneratedTextWithPolicy = (
        generatedValue: string,
        previousValue: string,
        wordConstraint?: string
    ): string => {
        const candidate = generatedValue || previousValue || '';
        return applyLockedWordsToText(candidate, wordConstraint);
    };

    const applyGeneratedNameWithPolicy = (
        generatedName: string,
        previousName: string,
        fieldLocked: boolean,
        wordConstraint: string | undefined,
        contextText: string
    ): string => {
        let nextName = applyGeneratedTextWithPolicy(generatedName, previousName, wordConstraint);
        if (fieldLocked && wordConstraint) {
            nextName = enforceNameVariation(nextName, previousName, contextText);
        }
        return nextName;
    };

    const enforceNameVariation = (
        nextName: string,
        previousName: string,
        contextText: string
    ): string => {
        const sameAsPrevious = previousName.trim().toLowerCase() === nextName.trim().toLowerCase();
        if (!sameAsPrevious) return nextName;
        const loweredContext = contextText.toLowerCase();
        const descriptor = loweredContext.includes('holiday') || loweredContext.includes('christmas')
            ? 'Holiday Edition'
            : (loweredContext.includes('hand') || loweredContext.includes('craft') ? 'Handcrafted' : 'Signature');
        return `${nextName} - ${descriptor}`.trim();
    };

    /**
     * Step 1: Generate Info (title, description, category) from image
     */
    const executeInfoStep = useCallback(async (
        sku: string,
        primaryImageUrl: string,
        imageUrls: string[] = [],
        lockedWords?: Record<string, string>
    ): Promise<GenerationStepResult> => {
        const mapInfoResponse = (response: {
            success: boolean;
            info_suggestion?: {
                name?: string;
                description?: string;
                category?: string;
                weight_oz?: number | string;
                package_length_in?: number | string;
                package_width_in?: number | string;
                package_height_in?: number | string;
            };
            error?: string;
        }): GenerationStepResult => {
            if (response && response.success && response.info_suggestion) {
                return {
                    success: true,
                    stepName: 'info',
                    data: {
                        name: response.info_suggestion.name || '',
                        description: response.info_suggestion.description || '',
                        category: response.info_suggestion.category || '',
                        weightOz: toNumber(response.info_suggestion.weight_oz),
                        packageLengthIn: toNumber(response.info_suggestion.package_length_in),
                        packageWidthIn: toNumber(response.info_suggestion.package_width_in),
                        packageHeightIn: toNumber(response.info_suggestion.package_height_in)
                    }
                };
            }
            return {
                success: false,
                stepName: 'info',
                data: {},
                error: response?.error || 'Failed to generate item info from image'
            };
        };

        try {
            const normalizedImageUrls = imageUrls
                .map((url) => (typeof url === 'string' ? url.trim() : ''))
                .filter((url) => url.length > 0);
            const imagePayload: string | string[] = normalizedImageUrls.length > 0
                ? normalizedImageUrls
                : primaryImageUrl;

            console.log('[AI Orchestrator] executeInfoStep called with:', {
                sku,
                primaryImageUrl: primaryImageUrl?.substring(0, 100),
                imageCount: Array.isArray(imagePayload) ? imagePayload.length : 1
            });

            const response = await ApiClient.post<{
                success: boolean;
                info_suggestion?: {
                    name?: string;
                    description?: string;
                    category?: string;
                    weight_oz?: number | string;
                    package_length_in?: number | string;
                    package_width_in?: number | string;
                    package_height_in?: number | string;
                    confidence?: string | number;
                    reasoning?: string;
                };
                error?: string;
            }>('/api/suggest_all.php', {
                sku,
                imageData: imagePayload,
                useImages: true,
                step: 'info',
                locked_words: lockedWords || {},
                image_first_priority: true
            });

            console.log('[AI Orchestrator] executeInfoStep response:', response);
            const firstPass = mapInfoResponse(response);
            if (firstPass.success) {
                return firstPass;
            }

            const shouldFallbackToTextOnly = (firstPass.error || '').toLowerCase().includes('vision-capable model');
            if (shouldFallbackToTextOnly) {
                const fallback = await ApiClient.post<{
                    success: boolean;
                    info_suggestion?: {
                        name?: string;
                        description?: string;
                        category?: string;
                        weight_oz?: number | string;
                        package_length_in?: number | string;
                        package_width_in?: number | string;
                        package_height_in?: number | string;
                    };
                    error?: string;
                }>('/api/suggest_all.php', {
                    sku,
                    useImages: false,
                    step: 'info',
                    locked_words: lockedWords || {},
                    image_first_priority: false
                });
                const fallbackResult = mapInfoResponse(fallback);
                if (fallbackResult.success) {
                    window.WFToast?.info?.('Vision model unavailable; generated item info using text-only fallback.');
                    return fallbackResult;
                }
            }

            return firstPass;
        } catch (err) {
            logger.error('Info generation step failed', err);
            return {
                success: false,
                stepName: 'info',
                data: {},
                error: String(err)
            };
        }
    }, []);

    /**
     * Step 2: Generate Cost Breakdown
     */
    const executeCostStep = useCallback(async (
        sku: string,
        name: string,
        description: string,
        category: string,
        tier: string = 'standard',
        imageUrls: string[] = [],
        primaryImageUrl?: string
    ): Promise<GenerationStepResult> => {
        try {
            const normalizedImageUrls = imageUrls
                .map((url) => (typeof url === 'string' ? url.trim() : ''))
                .filter((url) => url.length > 0);
            const imagePayload: string | string[] | undefined = normalizedImageUrls.length > 0
                ? normalizedImageUrls
                : primaryImageUrl;

            const response = await ApiClient.post<{
                success: boolean;
                cost_suggestion?: {
                    success?: boolean;
                    suggested_cost?: number;
                    confidence?: string | number;
                    reasoning?: string;
                    breakdown?: Record<string, number>;
                };
                error?: string;
            }>('/api/suggest_all.php', {
                sku,
                name,
                description,
                category,
                quality_tier: tier,
                step: 'cost',
                useImages: Boolean(imagePayload),
                imageData: imagePayload
            });

            if (response && response.success && response.cost_suggestion) {
                const cost = response.cost_suggestion;
                const suggestedCost = toNumber(cost.suggested_cost);
                const costConfidence = toNumber(cost.confidence);
                const normalizedBreakdown = normalizeCostBreakdown(cost.breakdown as Record<string, unknown> | undefined);
                return {
                    success: true,
                    stepName: 'cost',
                    data: {
                        suggestedCost,
                        costConfidence,
                        costReasoning: cost.reasoning || null,
                        costBreakdown: normalizedBreakdown
                    }
                };
            }
            return {
                success: false,
                stepName: 'cost',
                data: {},
                error: response?.error || 'Failed to generate cost suggestion'
            };
        } catch (err) {
            logger.error('Cost generation step failed', err);
            return {
                success: false,
                stepName: 'cost',
                data: {},
                error: String(err)
            };
        }
    }, []);

    const executeCostFallback = useCallback(async (
        sku: string,
        name: string,
        description: string,
        category: string,
        tier: string = 'standard'
    ): Promise<GenerationStepResult> => {
        try {
            const response = await ApiClient.post<{
                success: boolean;
                suggested_cost?: number | string;
                confidence?: number | string;
                reasoning?: string;
                breakdown?: Record<string, unknown>;
                error?: string;
            }>('/api/suggest_cost.php', {
                sku,
                name,
                description,
                category,
                quality_tier: tier,
                useImages: true
            });

            if (response && response.success) {
                const suggestedCost = toNumber(response.suggested_cost);
                const costConfidence = toNumber(response.confidence);
                const normalizedBreakdown = normalizeCostBreakdown(response.breakdown);
                return {
                    success: true,
                    stepName: 'cost',
                    data: {
                        suggestedCost,
                        costConfidence,
                        costReasoning: response.reasoning || null,
                        costBreakdown: normalizedBreakdown
                    }
                };
            }
            return {
                success: false,
                stepName: 'cost',
                data: {},
                error: response?.error || 'Failed to generate cost suggestion'
            };
        } catch (err) {
            logger.error('Cost fallback generation failed', err);
            return {
                success: false,
                stepName: 'cost',
                data: {},
                error: String(err)
            };
        }
    }, []);

    /**
     * Step 3: Generate Marketing Content
     */
    const executeMarketingStep = useCallback(async (
        sku: string,
        name: string,
        description: string,
        category: string,
        freshStart: boolean = true
    ): Promise<GenerationStepResult> => {
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
                    customer_benefits?: string[];
                    call_to_action_suggestions?: string[];
                    urgency_factors?: string[];
                    emotional_triggers?: string[];
                    seo_keywords?: string[];
                    unique_selling_points?: string;
                    value_propositions?: string;
                };
                confidence?: number;
                reasoning?: string;
                error?: string;
            }>('/api/suggest_marketing.php', {
                sku,
                name,
                description,
                category,
                useImages: true,
                fresh_start: freshStart,
                whimsicalTheme: true,
                contentTone: 'whimsical_frog'
            });

            if (response && response.success) {
                const intelligence = response.marketingIntelligence || {};
                return {
                    success: true,
                    stepName: 'marketing',
                    data: {
                        marketingData: {
                            suggested_title: response.title,
                            suggested_description: response.description,
                            target_audience: response.targetAudience || '',
                            keywords: response.keywords || [],
                            seo_keywords: intelligence.seo_keywords || response.keywords || [],
                            selling_points: intelligence.selling_points || [],
                            competitive_advantages: intelligence.competitive_advantages || [],
                            customer_benefits: intelligence.customer_benefits || [],
                            call_to_action_suggestions: intelligence.call_to_action_suggestions || [],
                            urgency_factors: intelligence.urgency_factors || [],
                            emotional_triggers: intelligence.emotional_triggers || [],
                            marketing_channels: (intelligence as Record<string, unknown>).marketing_channels as string[] | undefined || [],
                            conversion_triggers: (intelligence as Record<string, unknown>).conversion_triggers as string[] | undefined || [],
                            demographic_targeting: (intelligence as Record<string, unknown>).demographic_targeting as string | undefined || '',
                            psychographic_profile: (intelligence as Record<string, unknown>).psychographic_profile as string | undefined || '',
                            search_intent: (intelligence as Record<string, unknown>).search_intent as string | undefined || '',
                            seasonal_relevance: (intelligence as Record<string, unknown>).seasonal_relevance as string | undefined || '',
                            unique_selling_points: intelligence.unique_selling_points || '',
                            value_propositions: intelligence.value_propositions || '',
                            confidence_score: response.confidence ?? 0,
                            recommendation_reasoning: response.reasoning || ''
                        }
                    }
                };
            }
            return {
                success: false,
                stepName: 'marketing',
                data: {},
                error: response?.error || 'Failed to generate marketing content'
            };
        } catch (err) {
            logger.error('Marketing generation step failed', err);
            return {
                success: false,
                stepName: 'marketing',
                data: {},
                error: String(err)
            };
        }
    }, []);

    /**
     * Step 4: Generate Price Suggestion
     */
    const executePriceStep = useCallback(async (
        sku: string,
        name: string,
        description: string,
        category: string,
        costPrice: number,
        tier: string = 'standard'
    ): Promise<GenerationStepResult> => {
        try {
            const response = await ApiClient.post<{
                success: boolean;
                price_suggestion?: {
                    success?: boolean;
                    suggested_price?: number;
                    confidence?: string | number;
                    reasoning?: string;
                    components?: Array<{ type: string; amount: number; label: string; explanation: string }>;
                };
                error?: string;
            }>('/api/suggest_all.php', {
                sku,
                name,
                description,
                category,
                cost_price: costPrice,
                quality_tier: tier,
                step: 'price'
            });

            if (response && response.success && response.price_suggestion?.success) {
                const price = response.price_suggestion;
                return {
                    success: true,
                    stepName: 'price',
                    data: {
                        suggestedPrice: price.suggested_price || null,
                        priceConfidence: typeof price.confidence === 'number' ? price.confidence : null,
                        priceReasoning: price.reasoning || null,
                        priceComponents: price.components || null
                    }
                };
            }
            return {
                success: false,
                stepName: 'price',
                data: {},
                error: response?.error || 'Failed to generate price suggestion'
            };
        } catch (err) {
            logger.error('Price generation step failed', err);
            return {
                success: false,
                stepName: 'price',
                data: {},
                error: String(err)
            };
        }
    }, []);

    /**
     * Main orchestration function - runs all steps sequentially
     */
    const orchestrateFullGeneration = useCallback(async (params: {
        sku: string;
        primaryImageUrl: string;
        imageUrls?: string[];
        initialName?: string;
        initialDescription?: string;
        initialCategory?: string;
        tier?: string;
        lockedFields?: Record<string, boolean>;
        lockedWords?: Record<string, string>;
        onStepComplete?: (step: GenerationStep, context: GenerationContext, skippedFields?: string[]) => void;
    }): Promise<GenerationContext | null> => {
        const { sku, primaryImageUrl, imageUrls = [], tier = 'standard', lockedFields = {}, lockedWords = {}, onStepComplete } = params;

        if (!sku) {
            toastError('SKU is required for AI generation');
            return null;
        }

        setIsGenerating(true);
        setProgress(0);

        const context: GenerationContext = {
            sku,
            primaryImageUrl,
            imageUrls,
            name: '',
            description: '',
            category: '',
            tier,
            weightOz: null,
            packageLengthIn: null,
            packageWidthIn: null,
            packageHeightIn: null,
            costBreakdown: null,
            suggestedCost: null,
            costConfidence: null,
            costReasoning: null,
            marketingData: null,
            suggestedPrice: null,
            priceConfidence: null,
            priceReasoning: null,
            priceComponents: null
        };

        try {
            // Step 1: Generate Info from Image (title, description, category)
            setCurrentStep('info');
            window.WFToast?.info?.('🖼️ Analyzing image for item details...');

            const infoResult = await executeInfoStep(sku, primaryImageUrl, imageUrls, lockedWords);
            setProgress(25);

            if (infoResult.success) {
                const infoContextText = `${infoResult.data.description || ''} ${infoResult.data.category || ''}`;
                context.name = applyGeneratedNameWithPolicy(
                    infoResult.data.name || '',
                    context.name || '',
                    Boolean(lockedFields.name),
                    lockedWords.name,
                    infoContextText
                );
                context.description = applyGeneratedTextWithPolicy(
                    infoResult.data.description || '',
                    context.description || '',
                    lockedWords.description
                );
                context.category = applyGeneratedTextWithPolicy(
                    infoResult.data.category || '',
                    context.category || '',
                    lockedWords.category
                );
                context.weightOz = infoResult.data.weightOz ?? context.weightOz;
                context.packageLengthIn = infoResult.data.packageLengthIn ?? context.packageLengthIn;
                context.packageWidthIn = infoResult.data.packageWidthIn ?? context.packageWidthIn;
                context.packageHeightIn = infoResult.data.packageHeightIn ?? context.packageHeightIn;
                toastSuccess('✅ Generated title, description, and category');
                onStepComplete?.('info', { ...context }, []);
            } else {
                // If info generation fails but we have initial values, continue
                if (!context.name) {
                    toastError(infoResult.error || 'Image analysis failed. Switch to a vision-capable model in AI Settings and run Test Provider.');
                    setIsGenerating(false);
                    setCurrentStep(null);
                    return null;
                }
                window.WFToast?.info?.('Using existing item info, continuing...');
            }

            // Step 2: Generate Cost Breakdown using title, description, category
            setCurrentStep('cost');
            window.WFToast?.info?.('💰 Generating cost breakdown...');

            let costResult = await executeCostStep(
                sku,
                context.name,
                context.description,
                context.category,
                tier,
                imageUrls,
                primaryImageUrl
            );
            setProgress(50);

            if (!costResult.success) {
                costResult = await executeCostFallback(
                    sku,
                    context.name,
                    context.description,
                    context.category,
                    tier
                );
            }

            if (costResult.success) {
                // Respect locked cost_price field
                if (!lockedFields.cost_price) {
                    context.suggestedCost = costResult.data.suggestedCost ?? null;
                    context.costConfidence = costResult.data.costConfidence ?? null;
                    context.costReasoning = costResult.data.costReasoning ?? null;
                    context.costBreakdown = costResult.data.costBreakdown ?? null;
                    toastSuccess('✅ Generated cost suggestion');
                } else {
                    toastSuccess('✅ Cost step complete (cost_price locked, skipped)');
                }
                onStepComplete?.('cost', { ...context }, lockedFields.cost_price ? ['cost_price'] : []);
            } else {
                logger.warn('Cost step failed, continuing with other steps');
            }

            // Step 3: Generate Marketing Content
            setCurrentStep('marketing');
            window.WFToast?.info?.('📢 Generating marketing intelligence...');

            const marketingResult = await executeMarketingStep(
                sku,
                context.name,
                context.description,
                context.category,
                true
            );
            setProgress(75);

            if (marketingResult.success) {
                context.marketingData = marketingResult.data.marketingData ?? null;
                // Refine item copy after marketing intelligence is generated.
                if (context.marketingData) {
                    const refinedNameSource = context.marketingData.suggested_title || context.name || '';
                    const refinedDescriptionSource = context.marketingData.suggested_description || context.description || '';
                    const marketingContextText = `${refinedDescriptionSource} ${context.category || ''}`;
                    context.name = applyGeneratedNameWithPolicy(
                        refinedNameSource,
                        context.name || '',
                        Boolean(lockedFields.name),
                        lockedWords.name,
                        marketingContextText
                    );
                    context.description = applyGeneratedTextWithPolicy(
                        refinedDescriptionSource,
                        context.description || '',
                        lockedWords.description
                    );
                }
                toastSuccess('✅ Generated marketing content');
                onStepComplete?.('marketing', { ...context });
            } else {
                logger.warn('Marketing step failed, continuing with price step');
            }

            // Step 4: Generate Price Suggestion using cost
            setCurrentStep('price');
            window.WFToast?.info?.('💵 Generating price suggestion...');

            const priceResult = await executePriceStep(
                sku,
                context.name,
                context.description,
                context.category,
                context.suggestedCost || 0,
                tier
            );
            setProgress(100);

            if (priceResult.success) {
                // Respect locked retail_price field
                if (!lockedFields.retail_price) {
                    context.suggestedPrice = priceResult.data.suggestedPrice ?? null;
                    context.priceConfidence = priceResult.data.priceConfidence ?? null;
                    context.priceReasoning = priceResult.data.priceReasoning ?? null;
                    context.priceComponents = priceResult.data.priceComponents ?? null;
                    toastSuccess('✅ Generated price suggestion');
                } else {
                    toastSuccess('✅ Price step complete (retail_price locked, skipped)');
                }
                onStepComplete?.('price', { ...context }, lockedFields.retail_price ? ['retail_price'] : []);
            } else {
                logger.warn('Price step failed');
            }

            toastSuccess('🎉 AI generation complete!');
            return context;

        } catch (err) {
            logger.error('Orchestration failed', err);
            toastError('AI generation failed. Please try again.');
            return null;
        } finally {
            setIsGenerating(false);
            setCurrentStep(null);
        }
    }, [executeInfoStep, executeCostStep, executeCostFallback, executeMarketingStep, executePriceStep]);

    /**
     * Individual step functions for standalone use (called by individual buttons)
     */
    const generateInfoOnly = useCallback(async (params: {
        sku: string;
        primaryImageUrl: string;
        imageUrls?: string[];
        previousName?: string;
        lockedFields?: Record<string, boolean>;
        lockedWords?: Record<string, string>;
        includeMarketingRefinement?: boolean;
    }): Promise<Partial<GenerationContext> | null> => {
        setIsGenerating(true);
        setCurrentStep('info');
        try {
            const result = await executeInfoStep(
                params.sku,
                params.primaryImageUrl,
                params.imageUrls || [],
                params.lockedWords || {}
            );
            if (!result.success) return null;
            const lockedWords = params.lockedWords || {};
            const lockedFields = params.lockedFields || {};
            if (!params.lockedWords && !params.lockedFields) return result.data;

            const previousName = params.previousName || '';
            const nextCategory = result.data.category || '';
            let nextDescription = applyGeneratedTextWithPolicy(
                result.data.description || '',
                '',
                lockedWords.description
            );
            let nextName = applyGeneratedNameWithPolicy(
                result.data.name || '',
                previousName,
                Boolean(lockedFields.name),
                lockedWords.name,
                `${nextDescription} ${nextCategory}`
            );
            let marketingData: MarketingData | null = null;

            if (params.includeMarketingRefinement) {
                const marketingResult = await executeMarketingStep(
                    params.sku,
                    nextName,
                    nextDescription,
                    nextCategory,
                    true
                );
                if (marketingResult.success && marketingResult.data.marketingData) {
                    marketingData = marketingResult.data.marketingData ?? null;
                    const refinedNameSource = marketingData?.suggested_title || nextName;
                    const refinedDescriptionSource = marketingData?.suggested_description || nextDescription;
                    nextName = applyGeneratedNameWithPolicy(
                        refinedNameSource,
                        nextName,
                        Boolean(lockedFields.name),
                        lockedWords.name,
                        `${refinedDescriptionSource} ${nextCategory}`
                    );
                    nextDescription = applyGeneratedTextWithPolicy(
                        refinedDescriptionSource,
                        nextDescription,
                        lockedWords.description
                    );
                }
            }

            return {
                ...result.data,
                name: nextName,
                description: nextDescription,
                category: nextCategory,
                marketingData
            };
        } finally {
            setIsGenerating(false);
            setCurrentStep(null);
        }
    }, [executeInfoStep, executeMarketingStep]);

    const generateCostOnly = useCallback(async (params: {
        sku: string;
        name: string;
        description: string;
        category: string;
        tier?: string;
    }): Promise<Partial<GenerationContext> | null> => {
        setIsGenerating(true);
        setCurrentStep('cost');
        try {
            let result = await executeCostStep(
                params.sku,
                params.name,
                params.description,
                params.category,
                params.tier || 'standard'
            );
            if (!result.success) {
                result = await executeCostFallback(
                    params.sku,
                    params.name,
                    params.description,
                    params.category,
                    params.tier || 'standard'
                );
            }
            return result.success ? result.data : null;
        } finally {
            setIsGenerating(false);
            setCurrentStep(null);
        }
    }, [executeCostStep, executeCostFallback]);

    const generatePriceOnly = useCallback(async (params: {
        sku: string;
        name: string;
        description: string;
        category: string;
        costPrice: number;
        tier?: string;
    }): Promise<Partial<GenerationContext> | null> => {
        setIsGenerating(true);
        setCurrentStep('price');
        try {
            const result = await executePriceStep(
                params.sku,
                params.name,
                params.description,
                params.category,
                params.costPrice,
                params.tier || 'standard'
            );
            return result.success ? result.data : null;
        } finally {
            setIsGenerating(false);
            setCurrentStep(null);
        }
    }, [executePriceStep]);

    const generateMarketingOnly = useCallback(async (params: {
        sku: string;
        name: string;
        description: string;
        category: string;
        freshStart?: boolean;
    }): Promise<Partial<GenerationContext> | null> => {
        setIsGenerating(true);
        setCurrentStep('marketing');
        try {
            const result = await executeMarketingStep(
                params.sku,
                params.name,
                params.description,
                params.category,
                params.freshStart ?? true
            );
            return result.success ? result.data : null;
        } finally {
            setIsGenerating(false);
            setCurrentStep(null);
        }
    }, [executeMarketingStep]);

    return {
        isGenerating,
        currentStep,
        progress,
        orchestrateFullGeneration,
        generateInfoOnly,
        generateCostOnly,
        generatePriceOnly,
        generateMarketingOnly
    };
};
