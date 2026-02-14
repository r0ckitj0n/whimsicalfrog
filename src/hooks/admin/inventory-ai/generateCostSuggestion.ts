import { toastSuccess, toastError } from '../../../core/toast.js';
import { subscribeToNetworkActivity } from '../../../core/networkActivity.js';
import { ApiClient } from '../../../core/ApiClient.js';
import type { CostSuggestion } from './useCostSuggestions.js';
import type { PriceSuggestion } from './usePriceSuggestions.js';
import { getPriceTierMultiplier } from './usePriceSuggestions.js';
import type { GenerationContext } from '../useAIGenerationOrchestrator.js';
import type { MarketingData } from './useMarketingManager.js';
import type { IRecentSoldPriceResponse } from '../../../types/pricing-history.js';

interface SharedGenerationParams<TSuggestion> {
    sku: string;
    name: string;
    description: string;
    category: string;
    tier?: string;
    isReadOnly?: boolean;
    primaryImageUrl?: string;
    imageUrls?: string[];
    imageData?: string;
    generateInfoOnly?: (params: {
        sku: string;
        primaryImageUrl: string;
        imageUrls?: string[];
        previousName?: string;
        lockedFields?: Record<string, boolean>;
        lockedWords?: Record<string, string>;
        includeMarketingRefinement?: boolean;
    }) => Promise<Partial<GenerationContext> | null>;
    runSuggestion: (params: {
        sku: string;
        name: string;
        description: string;
        category: string;
        tier?: string;
        preferredImage?: string;
        imageUrls?: string[];
    }) => Promise<TSuggestion | null>;
    /** When true, do not abort if no item image is available. */
    allowNoImage?: boolean;
    onSuggestionGenerated?: (suggestion: TSuggestion) => void;
    onApplied?: () => void;
    startToast: string;
    infoToast: string;
    successToast: string;
    failureToast: string;
    notificationMode?: 'verbose' | 'minimal';
}

interface GenerateCostSuggestionParams {
    sku: string;
    name: string;
    description: string;
    category: string;
    tier: string;
    isReadOnly?: boolean;
    showApplyingToast?: boolean;
    notificationMode?: 'verbose' | 'minimal';
    primaryImageUrl?: string;
    imageUrls?: string[];
    imageData?: string;
    /** When true, bypass stored-suggestion caching and run the live AI call. */
    forceRefresh?: boolean;
    fetchCostSuggestion: (params: {
        sku?: string;
        name: string;
        description: string;
        category: string;
        tier?: string;
        useImages?: boolean;
        imageData?: string;
        forceRefresh?: boolean;
    }) => Promise<CostSuggestion | null>;
    generateInfoOnly?: SharedGenerationParams<CostSuggestion>['generateInfoOnly'];
    onSuggestionGenerated?: (suggestion: CostSuggestion) => void;
    onApplied?: () => void;
}

interface GeneratePriceSuggestionParams {
    sku: string;
    name: string;
    description: string;
    category: string;
    costPrice: number | string;
    tier: string;
    isReadOnly?: boolean;
    notificationMode?: 'verbose' | 'minimal';
    primaryImageUrl?: string;
    imageUrls?: string[];
    imageData?: string;
    /** When true, bypass stored-suggestion caching and run the live AI call. */
    forceRefresh?: boolean;
    fetchPriceSuggestion: (params: {
        sku: string;
        name: string;
        description: string;
        category: string;
        cost_price: number | string;
        tier?: string;
        useImages?: boolean;
        forceRefresh?: boolean;
    }) => Promise<PriceSuggestion | null>;
    generateInfoOnly?: SharedGenerationParams<PriceSuggestion>['generateInfoOnly'];
    onSuggestionGenerated?: (suggestion: PriceSuggestion) => void;
    onApplied?: () => void;
}

interface GenerateMarketingSuggestionParams {
    sku: string;
    name: string;
    description: string;
    category: string;
    isReadOnly?: boolean;
    notificationMode?: 'verbose' | 'minimal';
    primaryImageUrl?: string;
    imageUrls?: string[];
    imageData?: string;
    generateInfoOnly?: SharedGenerationParams<MarketingData>['generateInfoOnly'];
    fetchMarketingSuggestion: (params: {
        sku: string;
        name: string;
        description: string;
        category: string;
    }) => Promise<MarketingData | null>;
    onSuggestionGenerated?: (suggestion: MarketingData) => void;
    onApplied?: () => void;
}

const waitForGlobalNetworkIdle = (timeoutMs = 15000, idleWindowMs = 250): Promise<boolean> =>
    new Promise((resolve) => {
        let settled = false;
        let idleTimer: number | null = null;
        let timeoutTimer: number | null = null;

        const cleanup = () => {
            if (idleTimer) window.clearTimeout(idleTimer);
            if (timeoutTimer) window.clearTimeout(timeoutTimer);
            unsubscribe();
        };

        const done = (result: boolean) => {
            if (settled) return;
            settled = true;
            cleanup();
            resolve(result);
        };

        const unsubscribe = subscribeToNetworkActivity(({ isActive }) => {
            if (!isActive) {
                if (!idleTimer) {
                    idleTimer = window.setTimeout(() => done(true), idleWindowMs);
                }
                return;
            }

            if (idleTimer) {
                window.clearTimeout(idleTimer);
                idleTimer = null;
            }
        });

        timeoutTimer = window.setTimeout(() => done(false), timeoutMs);
    });

const normalizePath = (path: string): string => {
    const trimmed = String(path || '').trim();
    if (!trimmed) return '';
    if (/^https?:\/\//i.test(trimmed) || trimmed.startsWith('data:')) return trimmed;
    return `/${trimmed.replace(/^\/+/, '')}`;
};

const hasLikelyImageExt = (path: string): boolean => /\.(png|webp|jpe?g)$/i.test(path);

const imageRank = (path: string): number => {
    const normalized = String(path || '').toLowerCase();
    if (normalized.startsWith('data:image/png') || /\.png(?:$|[?#])/.test(normalized)) return 0;
    if (normalized.startsWith('data:image/webp') || /\.webp(?:$|[?#])/.test(normalized)) return 1;
    if (normalized.startsWith('data:image/jpeg') || normalized.startsWith('data:image/jpg') || /\.jpe?g(?:$|[?#])/.test(normalized)) return 2;
    return 3;
};

const gatherImageCandidates = async (params: {
    sku: string;
    primaryImageUrl?: string;
    imageUrls?: string[];
    imageData?: string;
}): Promise<string[]> => {
    const deduped = new Set<string>();
    const addCandidate = (candidate: string) => {
        const normalized = normalizePath(candidate);
        if (!normalized) return;
        if (!hasLikelyImageExt(normalized) && !normalized.startsWith('data:') && !/^https?:\/\//i.test(normalized)) return;
        deduped.add(normalized);
    };

    (params.imageUrls || []).forEach(addCandidate);
    if (params.primaryImageUrl) addCandidate(params.primaryImageUrl);
    if (params.imageData) addCandidate(params.imageData);

    if (params.sku) {
        try {
            const imageRes = await ApiClient.get<{ success?: boolean; images?: Array<{ image_path?: string; is_primary?: boolean }> }>(
                '/api/get_item_images.php',
                { sku: params.sku }
            );
            const dbImages = Array.isArray(imageRes?.images) ? imageRes.images : [];
            dbImages
                .sort((a, b) => Number(Boolean(b?.is_primary)) - Number(Boolean(a?.is_primary)))
                .forEach((img) => addCandidate(String(img?.image_path || '')));
        } catch (_err) {
            // Non-fatal: fallback candidates below.
        }
    }

    if (deduped.size === 0 && params.sku) {
        addCandidate(`/images/items/${params.sku}A.png`);
        addCandidate(`/images/items/${params.sku}A.webp`);
        addCandidate(`/images/items/${params.sku}A.jpg`);
        addCandidate(`/images/items/${params.sku}A.jpeg`);
    }

    return Array.from(deduped).sort((a, b) => imageRank(a) - imageRank(b));
};

const runImageFirstSuggestion = async <TSuggestion>({
    sku,
    name,
    description,
    category,
    tier,
    isReadOnly = false,
    primaryImageUrl,
    imageUrls = [],
    imageData,
    generateInfoOnly,
    runSuggestion,
    allowNoImage = false,
    onSuggestionGenerated,
    onApplied,
    startToast,
    infoToast,
    successToast,
    failureToast,
    notificationMode = 'verbose'
}: SharedGenerationParams<TSuggestion>): Promise<TSuggestion | null> => {
    const shouldToast = notificationMode !== 'minimal';
    if (shouldToast) toastSuccess(startToast);

    try {
        const resolvedImageUrls = await gatherImageCandidates({
            sku,
            primaryImageUrl,
            imageUrls,
            imageData
        });
        const preferredImage = resolvedImageUrls[0];

        if (!preferredImage && !allowNoImage) {
            toastError('No usable item image found. Tried PNG, WebP, and JPEG/JPG. Upload an item image and try again.');
            return null;
        }

        let nextName = name;
        let nextDescription = description;
        let nextCategory = category;

        if (generateInfoOnly) {
            if (shouldToast) toastSuccess(infoToast);
            const infoResult = await generateInfoOnly({
                sku,
                primaryImageUrl: preferredImage,
                imageUrls: resolvedImageUrls,
                previousName: name,
                includeMarketingRefinement: false
            });

            if (!infoResult) {
                toastError('Image-first analysis failed. Generation aborted.');
                return null;
            }

            nextName = String(infoResult.name || nextName || '');
            nextDescription = String(infoResult.description || nextDescription || '');
            nextCategory = String(infoResult.category || nextCategory || '');
        }

        const suggestion = await runSuggestion({
            sku,
            name: nextName,
            description: nextDescription,
            category: nextCategory,
            tier,
            preferredImage: preferredImage || undefined,
            imageUrls: resolvedImageUrls
        });

        if (!suggestion) {
            toastError(failureToast);
            return null;
        }

        if (shouldToast) toastSuccess(successToast);
        onSuggestionGenerated?.(suggestion);
        if (!isReadOnly) onApplied?.();
        return suggestion;
    } catch (_err) {
        toastError(failureToast);
        return null;
    }
};

export const generateCostSuggestion = async ({
    sku,
    name,
    description,
    category,
    tier,
    isReadOnly = false,
    showApplyingToast = false,
    notificationMode = 'verbose',
    primaryImageUrl,
    imageUrls = [],
    imageData,
    forceRefresh = false,
    fetchCostSuggestion,
    generateInfoOnly,
    onSuggestionGenerated,
    onApplied
}: GenerateCostSuggestionParams): Promise<CostSuggestion | null> => {
    const suggestion = await runImageFirstSuggestion<CostSuggestion>({
        sku,
        name,
        description,
        category,
        tier,
        isReadOnly,
        primaryImageUrl,
        imageUrls,
        imageData,
        generateInfoOnly,
        allowNoImage: true,
        runSuggestion: async ({ sku: nextSku, name: nextName, description: nextDescription, category: nextCategory, tier: nextTier, preferredImage }) => {
            return fetchCostSuggestion({
                sku: nextSku,
                name: nextName,
                description: nextDescription,
                category: nextCategory,
                tier: nextTier,
                useImages: true,
                imageData: preferredImage || imageData,
                forceRefresh
            });
        },
        onSuggestionGenerated,
        onApplied,
        startToast: 'Starting AI cost analysis...',
        infoToast: 'Analyzing item image before cost calculation...',
        successToast: showApplyingToast ? 'AI analysis complete. Building preview...' : 'AI analysis complete.',
        failureToast: 'Failed to generate cost suggestion',
        notificationMode
    });

    if (!suggestion) return null;

    const shouldToast = notificationMode !== 'minimal';

    if (suggestion.fallback_used && shouldToast) {
        const reason = (suggestion.fallback_reason || '').trim();
        const kind = String((suggestion.fallback_kind || '')).trim();
        if (kind === 'provider_fallback') {
            const msg = `Primary AI provider failed; used local provider instead.${reason ? ` Reason: ${reason}` : ''}`;
            if (window.WFToast?.info) window.WFToast.info(msg);
            else toastSuccess(msg);
        } else {
            const msg = `AI cost couldn't be generated. Fallback costs are in effect.${reason ? ` Reason: ${reason}` : ''}`;
            if (window.WFToast?.warning) window.WFToast.warning(msg);
            else toastError(msg);
        }
    } else if (shouldToast) {
        toastSuccess(showApplyingToast ? 'Cost preview ready (unsaved).' : 'Cost suggestion generated');
    }
    if (showApplyingToast) {
        const settled = await waitForGlobalNetworkIdle();
        if (settled && shouldToast) toastSuccess('All cost generation tasks finished.');
    }

    return suggestion;
};

export const generatePriceSuggestion = async ({
    sku,
    name,
    description,
    category,
    costPrice,
    tier,
    isReadOnly = false,
    notificationMode = 'verbose',
    primaryImageUrl,
    imageUrls = [],
    imageData,
    forceRefresh = false,
    fetchPriceSuggestion,
    generateInfoOnly,
    onSuggestionGenerated,
    onApplied
}: GeneratePriceSuggestionParams): Promise<PriceSuggestion | null> => {
    // Prefer real-world price signals when they are fresh.
    // If we have sold prices in the last 7 days, use them as the base price and apply tier scaling.
    // But when the user explicitly requested a live AI refresh, do not bypass with this shortcut.
    const fetchRecentSoldPrice = async (skuToCheck: string): Promise<IRecentSoldPriceResponse | null> => {
        if (!skuToCheck) return null;
        try {
            const res = await ApiClient.get<IRecentSoldPriceResponse>('/api/get_recent_sold_price.php', { sku: skuToCheck });
            return res ?? null;
        } catch (_err) {
            return null;
        }
    };

    const recent = forceRefresh ? null : await fetchRecentSoldPrice(sku);
    if (!forceRefresh && recent?.success && typeof recent.avg_price === 'number' && Number.isFinite(recent.avg_price) && recent.avg_price > 0) {
        const base = Number(recent.avg_price.toFixed(2));
        const tierMult = getPriceTierMultiplier(tier || 'standard') || 1;
        const tiered = Number((base * tierMult).toFixed(2));
        const lineCount = Number(recent.line_count || 0);

        const synthesized: PriceSuggestion = {
            success: true,
            suggested_price: tiered,
            confidence: 0.85,
            factors: {
                requested_pricing_tier: (tier || 'standard').toLowerCase(),
                tier_multiplier: tierMult,
                final_before_tier: base,
                recent_sold_price_lines: lineCount,
                recent_sold_price_last_at: recent.last_sold_at ?? null
            },
            components: [
                {
                    type: 'historical_price',
                    amount: tiered,
                    label: 'Recent Sold Price (7d avg)',
                    explanation: `Derived from ${lineCount} order line(s) in the last 7 days.`
                }
            ],
            analysis: {
                requested_pricing_tier: (tier || 'standard').toLowerCase(),
                requested_tier_multiplier: tierMult
            },
            reasoning: `Used recent sold prices (last 7 days) as the base signal; tier multiplier applied.`,
            created_at: recent.last_sold_at ?? new Date().toISOString().slice(0, 19).replace('T', ' ')
        };

        if (notificationMode !== 'minimal' && window.WFToast?.info) {
            window.WFToast.info('Using recent sold prices (fresh).');
        }
        onSuggestionGenerated?.(synthesized);
        if (!isReadOnly) onApplied?.();
        return synthesized;
    }

    const suggestion = await runImageFirstSuggestion<PriceSuggestion>({
        sku,
        name,
        description,
        category,
        tier,
        isReadOnly,
        primaryImageUrl,
        imageUrls,
        imageData,
        generateInfoOnly,
        allowNoImage: true,
        runSuggestion: async ({ sku: nextSku, name: nextName, description: nextDescription, category: nextCategory, tier: nextTier }) => {
            return fetchPriceSuggestion({
                sku: nextSku,
                name: nextName,
                description: nextDescription,
                category: nextCategory,
                cost_price: costPrice,
                tier: nextTier,
                useImages: true,
                forceRefresh
            });
        },
        onSuggestionGenerated,
        onApplied,
        startToast: 'Starting AI price analysis...',
        infoToast: 'Analyzing item image before price calculation...',
        successToast: 'AI analysis complete.',
        failureToast: 'Failed to generate price suggestion',
        notificationMode
    });

    if (!suggestion) return null;
    const shouldToast = notificationMode !== 'minimal';

    if ((suggestion as any).fallback_used && shouldToast) {
        const reason = String((suggestion as any).fallback_reason || '').trim();
        const kind = String((suggestion as any).fallback_kind || '').trim();
        if (kind === 'provider_fallback') {
            const msg = `Primary AI provider failed; used local provider instead.${reason ? ` Reason: ${reason}` : ''}`;
            if (window.WFToast?.info) window.WFToast.info(msg);
            else toastSuccess(msg);
        } else {
            const msg = `AI price couldn't be generated. Fallback pricing is in effect.${reason ? ` Reason: ${reason}` : ''}`;
            if (window.WFToast?.warning) window.WFToast.warning(msg);
            else toastError(msg);
        }
    } else if (shouldToast) {
        toastSuccess('Price suggestion generated');
    }
    return suggestion;
};

export const generateMarketingSuggestion = async ({
    sku,
    name,
    description,
    category,
    isReadOnly = false,
    notificationMode = 'verbose',
    primaryImageUrl,
    imageUrls = [],
    imageData,
    generateInfoOnly,
    fetchMarketingSuggestion,
    onSuggestionGenerated,
    onApplied
}: GenerateMarketingSuggestionParams): Promise<MarketingData | null> => {
    const suggestion = await runImageFirstSuggestion<MarketingData>({
        sku,
        name,
        description,
        category,
        isReadOnly,
        primaryImageUrl,
        imageUrls,
        imageData,
        generateInfoOnly,
        runSuggestion: async ({ sku: nextSku, name: nextName, description: nextDescription, category: nextCategory }) => {
            return fetchMarketingSuggestion({
                sku: nextSku,
                name: nextName,
                description: nextDescription,
                category: nextCategory
            });
        },
        onSuggestionGenerated,
        onApplied,
        startToast: 'Starting AI marketing analysis...',
        infoToast: 'Analyzing item image before marketing generation...',
        successToast: 'AI analysis complete.',
        failureToast: 'Failed to generate marketing suggestion',
        notificationMode
    });

    if (!suggestion) return null;
    if (notificationMode !== 'minimal') toastSuccess('Marketing suggestion generated');
    return suggestion;
};
