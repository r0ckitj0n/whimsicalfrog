import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useAISuggestions } from './useAISuggestions.js';
import { useAIContentGenerator } from './useAIContentGenerator.js';
import { useAIGenerationOrchestrator, type GenerationContext } from './useAIGenerationOrchestrator.js';
import { CostSuggestion, PriceSuggestion } from './useInventoryAI.js';
import { MarketingData } from './inventory-ai/useMarketingManager.js';
import { AiManager } from '../../core/ai/AiManager.js';
import { ApiClient } from '../../core/ApiClient.js';
import { ItemSelector } from '../../components/admin/settings/ai-suggestions/ItemSelector.js';
// Reuse the same panels from Inventory Modal
import { AICostPanel } from '../../components/admin/inventory/AICostPanel.js';
import { AIPricingPanel } from '../../components/admin/inventory/AIPricingPanel.js';
import { AIMarketingPanel } from '../../components/admin/inventory/AIMarketingPanel.js';
import { MarketingManagerModal } from '../../components/modals/admin/inventory/MarketingManagerModal.js';
import { CostBreakdownTable } from '../../components/admin/inventory/CostBreakdownTable.js';
import { PriceBreakdownTable } from '../../components/admin/inventory/PriceBreakdownTable.js';
import { FieldLockIcon } from '../../components/admin/inventory/FieldLockIcon.js';

interface AISuggestionsProps {
    onClose?: () => void;
    title?: string;
}

export const AISuggestions: React.FC<AISuggestionsProps> = ({ onClose, title }) => {
    const {
        selectedSku,
        setSelectedSku,
        currentItem,
        isLoading,
        marketing,
        applyField,
    } = useAISuggestions();

    const { items, isLoadingItems } = useAIContentGenerator();

    // Use the same orchestrator as Inventory Modal for consistent generation
    const {
        isGenerating,
        orchestrateFullGeneration,
        generateInfoOnly,
        generatePriceOnly
    } = useAIGenerationOrchestrator();

    // LOCAL cached suggestions that we pass as props to the panels
    const [cachedCostSuggestion, setCachedCostSuggestion] = useState<CostSuggestion | null>(null);
    const [cachedMarketingData, setCachedMarketingData] = useState<MarketingData | null>(null);
    const [cachedPriceSuggestion, setCachedPriceSuggestion] = useState<PriceSuggestion | null>(null);
    const [hasGeneratedCostAnalysis, setHasGeneratedCostAnalysis] = useState(false);
    const [hasGeneratedPriceAnalysis, setHasGeneratedPriceAnalysis] = useState(false);

    // Track if form has been modified (determines Save button visibility)
    const [isDirty, setIsDirty] = useState(false);

    // Tier settings for cost and price panels
    const [costTier, setCostTier] = useState('standard');
    const [priceTier, setPriceTier] = useState('standard');

    // Trigger for refreshing breakdown tables
    const [breakdownRefreshTrigger, setBreakdownRefreshTrigger] = useState(0);

    // Local form state for item fields
    const [localFormData, setLocalFormData] = useState({
        name: '',
        description: '',
        category: '',
        cost_price: 0,
        retail_price: 0
    });

    const [lockedFields, setLockedFields] = useState<Record<string, boolean>>({});
    const [lockedWords, setLockedWords] = useState<Record<string, string>>({});

    // Marketing data state
    const [localMarketing, setLocalMarketing] = useState<{
        targetAudience?: string;
        sellingPoints?: string[];
        marketingChannels?: string[];
    } | null>(null);
    const [showMarketingManager, setShowMarketingManager] = useState(false);

    // Initialize local form from current item when item changes
    useEffect(() => {
        if (currentItem) {
            setLocalFormData({
                name: currentItem.name || '',
                description: currentItem.description || '',
                category: currentItem.category || '',
                cost_price: currentItem.cost_price || 0,
                retail_price: currentItem.retail_price || 0
            });
        }
        // Reset dirty state when item changes
        setIsDirty(false);
        // Clear cached suggestions when changing items
        setCachedCostSuggestion(null);
        setCachedMarketingData(null);
        setCachedPriceSuggestion(null);
        setHasGeneratedCostAnalysis(false);
        setHasGeneratedPriceAnalysis(false);
    }, [selectedSku, currentItem]);

    useEffect(() => {
        if (marketing) {
            setLocalMarketing(marketing);
        }
    }, [marketing]);

    const loadStoredSuggestions = React.useCallback(async () => {
        if (!selectedSku) return;
        try {
            const [marketingRes, itemDetailsRes] = await Promise.all([
                AiManager.getStoredMarketingSuggestion(selectedSku),
                ApiClient.get<{ success: boolean; item?: { locked_fields?: Record<string, boolean>; locked_words?: Record<string, string>; quality_tier?: string; cost_quality_tier?: string; price_quality_tier?: string } }>('get_item_details.php', { sku: selectedSku })
            ]);

            const marketingResponse = marketingRes as {
                success?: boolean;
                exists?: boolean;
                data?: MarketingData;
                sku?: string;
                target_audience?: string;
                demographic_targeting?: string;
                selling_points?: string[];
                marketing_channels?: string[];
            } | null;
            const marketingPayload: MarketingData | null =
                (marketingResponse?.data && typeof marketingResponse.data === 'object')
                    ? marketingResponse.data
                    : (marketingResponse as MarketingData | null);
            const marketingExists = marketingResponse?.exists ?? Boolean(marketingPayload);

            if (marketingExists && marketingPayload) {
                setCachedMarketingData(marketingPayload);
                setLocalMarketing({
                    targetAudience: marketingPayload.target_audience || marketingPayload.demographic_targeting,
                    sellingPoints: marketingPayload.selling_points || [],
                    marketingChannels: marketingPayload.marketing_channels || []
                });
            }

            if (itemDetailsRes?.success) {
                if (itemDetailsRes.item?.locked_fields) {
                    setLockedFields(itemDetailsRes.item.locked_fields || {});
                }
                if (itemDetailsRes.item?.locked_words) {
                    setLockedWords(itemDetailsRes.item.locked_words || {});
                }
                // Load saved quality tier
                const savedCostTier = itemDetailsRes.item?.cost_quality_tier || 'standard';
                const savedPriceTier = itemDetailsRes.item?.price_quality_tier || 'standard';
                if (savedCostTier)
                    setCostTier(savedCostTier);
                if (savedPriceTier)
                    setPriceTier(savedPriceTier);
            }
        } catch (_err) {
            // Non-fatal: allow UI to function without stored data
        }
    }, [selectedSku]);

    useEffect(() => {
        loadStoredSuggestions();
    }, [loadStoredSuggestions]);

    const handleGenerate = async () => {
        if (!currentItem) return;

        // Build primary image URL from the item's SKU
        const primaryImageUrl = `/images/items/${currentItem.sku}A.webp`;

        // Use the orchestrator which shows toast notifications for each step
        const generationLockedWords = lockedWords;

        const result = await orchestrateFullGeneration({
            sku: currentItem.sku,
            primaryImageUrl,
            initialName: currentItem.name,
            initialDescription: currentItem.description || '',
            initialCategory: currentItem.category || '',
            tier: costTier || 'standard',
            lockedFields,
            lockedWords: generationLockedWords,
            onStepComplete: (step: string, context: GenerationContext) => {
                // Update LOCAL form state progressively as each step completes
                setLocalFormData(prev => ({
                    ...prev,
                    // Name locks constrain wording via lockedWords; they do not freeze regeneration.
                    name: context.name || prev.name,
                    description: context.description || prev.description,
                    category: context.category || prev.category,
                    cost_price: (!lockedFields.cost_price && context.suggestedCost !== null) ? context.suggestedCost : prev.cost_price,
                    retail_price: (!lockedFields.retail_price && context.suggestedPrice !== null) ? context.suggestedPrice : prev.retail_price
                }));

                // Update cached suggestions for breakdown panels - this is what AICostPanel/AIPricingPanel read from!
                if (step === 'cost' && context.suggestedCost !== null && !lockedFields.cost_price) {
                    setHasGeneratedCostAnalysis(true);
                    setCachedCostSuggestion({
                        suggested_cost: context.suggestedCost,
                        confidence: context.costConfidence ?? 0,
                        reasoning: context.costReasoning ?? '',
                        breakdown: context.costBreakdown ?? {},
                        analysis: {},
                        created_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
                    });
                }

                if (step === 'price' && context.suggestedPrice !== null && !lockedFields.retail_price) {
                    setHasGeneratedPriceAnalysis(true);
                    setCachedPriceSuggestion({
                        success: true,
                        suggested_price: context.suggestedPrice,
                        confidence: context.priceConfidence ?? 0,
                        reasoning: context.priceReasoning ?? '',
                        components: context.priceComponents ?? [],
                        factors: {},
                        analysis: {},
                        created_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
                    });
                }

                // Update marketing data - populate full marketing data for AIMarketingPanel
                if (context.marketingData) {
                    setCachedMarketingData(context.marketingData);
                    setLocalMarketing({
                        targetAudience: context.marketingData.target_audience,
                        sellingPoints: context.marketingData.selling_points,
                        marketingChannels: []
                    });
                }

                // Mark as dirty since we have new generated data
                setIsDirty(true);
                // Trigger refresh of breakdown tables
                setBreakdownRefreshTrigger(prev => prev + 1);
            }
        });

        // Final update from result if available
        if (result) {
            setLocalFormData(prev => ({
                ...prev,
                name: result.name || prev.name,
                description: result.description || prev.description,
                category: result.category || prev.category,
                cost_price: (!lockedFields.cost_price && result.suggestedCost !== null) ? result.suggestedCost : prev.cost_price,
                retail_price: (!lockedFields.retail_price && result.suggestedPrice !== null) ? result.suggestedPrice : prev.retail_price
            }));

            if (result.suggestedCost !== null && !cachedCostSuggestion && !lockedFields.cost_price) {
                setHasGeneratedCostAnalysis(true);
                setCachedCostSuggestion({
                    suggested_cost: result.suggestedCost,
                    confidence: result.costConfidence ?? 0,
                    reasoning: result.costReasoning ?? '',
                    breakdown: result.costBreakdown ?? {},
                    analysis: {},
                    created_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
                });
            }

            if (result.suggestedPrice !== null && !cachedPriceSuggestion && !lockedFields.retail_price) {
                setHasGeneratedPriceAnalysis(true);
                setCachedPriceSuggestion({
                    success: true,
                    suggested_price: result.suggestedPrice,
                    confidence: result.priceConfidence ?? 0,
                    reasoning: result.priceReasoning ?? '',
                    components: result.priceComponents ?? [],
                    factors: {},
                    analysis: {},
                    created_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
                });
            }

            if (result.marketingData) {
                setCachedMarketingData(result.marketingData);
                setLocalMarketing({
                    targetAudience: result.marketingData.target_audience,
                    sellingPoints: result.marketingData.selling_points,
                    marketingChannels: []
                });
            }

            setIsDirty(true);
            setBreakdownRefreshTrigger(prev => prev + 1);

            // If cost/price tiers differ, regenerate price using the selected price tier.
            if (priceTier !== costTier) {
                const priceOnlyResult = await generatePriceOnly({
                    sku: currentItem.sku,
                    name: result.name || localFormData.name || currentItem.name,
                    description: result.description || localFormData.description || currentItem.description || '',
                    category: result.category || localFormData.category || currentItem.category || '',
                    costPrice: result.suggestedCost ?? localFormData.cost_price ?? currentItem.cost_price ?? 0,
                    tier: priceTier
                });
                if (priceOnlyResult?.suggestedPrice !== null && priceOnlyResult?.suggestedPrice !== undefined) {
                    setHasGeneratedPriceAnalysis(true);
                    setCachedPriceSuggestion({
                        success: true,
                        suggested_price: priceOnlyResult.suggestedPrice,
                        confidence: priceOnlyResult.priceConfidence ?? 0,
                        reasoning: priceOnlyResult.priceReasoning ?? '',
                        components: priceOnlyResult.priceComponents ?? [],
                        factors: {},
                        analysis: {},
                        created_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
                    });
                    setLocalFormData(prev => ({
                        ...prev,
                        retail_price: !lockedFields.retail_price ? priceOnlyResult.suggestedPrice! : prev.retail_price
                    }));
                    setBreakdownRefreshTrigger(prev => prev + 1);
                }
            }
        }

    };

    const handleGenerateItemInfo = async () => {
        if (!currentItem) return;

        const primaryImageUrl = `/images/items/${currentItem.sku}A.webp`;
        const infoLockedWords = lockedWords;

        const infoResult = await generateInfoOnly({
            sku: currentItem.sku,
            primaryImageUrl,
            previousName: localFormData.name || currentItem.name || '',
            lockedFields,
            lockedWords: infoLockedWords,
            includeMarketingRefinement: true
        });

        if (infoResult) {
            setLocalFormData(prev => ({
                ...prev,
                name: infoResult.name || prev.name,
                description: infoResult.description || prev.description,
                category: infoResult.category || prev.category
            }));
            setIsDirty(true);
            if (infoResult.marketingData) {
                setCachedMarketingData(infoResult.marketingData);
            }
        }

        if (infoResult?.marketingData) {
            setLocalMarketing({
                targetAudience: infoResult.marketingData.target_audience,
                sellingPoints: infoResult.marketingData.selling_points,
                marketingChannels: infoResult.marketingData.marketing_channels || []
            });
            setIsDirty(true);
        }
    };

    const toggleFieldLock = async (field: string) => {
        if (!selectedSku) return;
        if (field === 'category') return;
        const updated = { ...lockedFields, [field]: !lockedFields[field] };
        if (!updated[field]) delete updated[field];
        setLockedFields(updated);
        setIsDirty(true);
        await applyField(selectedSku, 'locked_fields', JSON.stringify(updated));

    };

    const handleApplyCost = (cost: number) => {
        setLocalFormData(prev => ({ ...prev, cost_price: cost }));
        setIsDirty(true);
    };

    const handleApplyPrice = (price: number) => {
        setLocalFormData(prev => ({ ...prev, retail_price: price }));
        setIsDirty(true);
    };

    const handleNameChange = (value: string) => {
        setLocalFormData(prev => ({ ...prev, name: value }));
        setIsDirty(true);
    };

    const handleDescriptionChange = (value: string) => {
        setLocalFormData(prev => ({ ...prev, description: value }));
        setIsDirty(true);
    };

    const handleCategoryChange = (value: string) => {
        setLocalFormData(prev => ({ ...prev, category: value }));
        setIsDirty(true);
    };

    const handleLockedWordsChange = async (field: string, value: string) => {
        if (!selectedSku) return;
        const next = { ...lockedWords, [field]: value };
        setLockedWords(next);
        setIsDirty(true);
        await applyField(selectedSku, 'locked_words', JSON.stringify(next));
    };

    const handleBreakdownApplied = () => {
        setBreakdownRefreshTrigger(prev => prev + 1);
        setIsDirty(true);
    };

    const categoryOptions = React.useMemo(() => {
        const set = new Set<string>();
        items.forEach(it => {
            if (it.category) set.add(it.category);
        });
        if (localFormData.category) set.add(localFormData.category);
        return Array.from(set).sort((a, b) => a.localeCompare(b));
    }, [items, localFormData.category]);

    const handleSaveAll = async () => {
        if (!selectedSku || !isDirty) return;

        const tasks: Promise<boolean>[] = [];
        const appliedFields: string[] = [];

        // Apply all changed fields to the database
        if (localFormData.name) {
            tasks.push(applyField(selectedSku, 'name', localFormData.name));
            appliedFields.push('name');
        }
        if (localFormData.description) {
            tasks.push(applyField(selectedSku, 'description', localFormData.description));
            appliedFields.push('description');
        }
        if (localFormData.category) {
            tasks.push(applyField(selectedSku, 'category', localFormData.category));
            appliedFields.push('category');
        }
        if (typeof localFormData.cost_price === 'number' && localFormData.cost_price > 0) {
            tasks.push(applyField(selectedSku, 'cost_price', localFormData.cost_price));
            appliedFields.push('cost_price');
        }
        if (typeof localFormData.retail_price === 'number' && localFormData.retail_price > 0) {
            tasks.push(applyField(selectedSku, 'retail_price', localFormData.retail_price));
            appliedFields.push('retail_price');
        }

        if (cachedMarketingData) {
            const payload = {
                suggested_title: cachedMarketingData.suggested_title || '',
                suggested_description: cachedMarketingData.suggested_description || '',
                target_audience: cachedMarketingData.target_audience || '',
                demographic_targeting: cachedMarketingData.demographic_targeting || '',
                psychographic_profile: cachedMarketingData.psychographic_profile || '',
                search_intent: cachedMarketingData.search_intent || '',
                seasonal_relevance: cachedMarketingData.seasonal_relevance || '',
                confidence_score: cachedMarketingData.confidence_score || 0,
                recommendation_reasoning: cachedMarketingData.recommendation_reasoning || '',
                unique_selling_points: cachedMarketingData.unique_selling_points || [],
                value_propositions: cachedMarketingData.value_propositions || [],
                market_positioning: cachedMarketingData.market_positioning || '',
                brand_voice: cachedMarketingData.brand_voice || '',
                content_tone: cachedMarketingData.content_tone || '',
                pricing_psychology: cachedMarketingData.pricing_psychology || '',
                keywords: cachedMarketingData.keywords || [],
                seo_keywords: cachedMarketingData.seo_keywords || [],
                selling_points: cachedMarketingData.selling_points || [],
                competitive_advantages: cachedMarketingData.competitive_advantages || [],
                customer_benefits: cachedMarketingData.customer_benefits || [],
                call_to_action_suggestions: cachedMarketingData.call_to_action_suggestions || [],
                urgency_factors: cachedMarketingData.urgency_factors || [],
                emotional_triggers: cachedMarketingData.emotional_triggers || [],
                marketing_channels: cachedMarketingData.marketing_channels || [],
                conversion_triggers: cachedMarketingData.conversion_triggers || [],
                social_proof_elements: cachedMarketingData.social_proof_elements || [],
                objection_handlers: cachedMarketingData.objection_handlers || [],
                content_themes: cachedMarketingData.content_themes || [],
                pain_points_addressed: cachedMarketingData.pain_points_addressed || [],
                lifestyle_alignment: cachedMarketingData.lifestyle_alignment || [],
                analysis_factors: cachedMarketingData.analysis_factors || {},
                market_trends: cachedMarketingData.market_trends || []
            };

            const saveMarketing = ApiClient.post<{ success: boolean }>(
                'marketing_manager.php?action=bulk_update',
                { sku: selectedSku, data: payload }
            ).then(res => res?.success === true);

            tasks.push(saveMarketing);
        }

        if (cachedCostSuggestion) {
            const saveCostBreakdown = ApiClient.post<{ success: boolean; error?: string }>(
                '/api/populate_cost_from_ai.php',
                { sku: selectedSku, suggestion: cachedCostSuggestion }
            ).then(res => res?.success === true);
            tasks.push(saveCostBreakdown);
        }

        if (cachedPriceSuggestion) {
            const savePriceBreakdown = ApiClient.post<{ success: boolean; error?: string }>(
                '/api/populate_price_from_ai.php',
                { sku: selectedSku, suggestion: cachedPriceSuggestion }
            ).then(res => res?.success === true);
            tasks.push(savePriceBreakdown);
        }

        // Always save quality tier if neither breakdown save was triggered
        // This ensures tier changes are persisted even without re-generating
        tasks.push(applyField(selectedSku, 'cost_quality_tier', costTier));
        tasks.push(applyField(selectedSku, 'price_quality_tier', priceTier));

        if (tasks.length === 0) {
            if (window.WFToast) window.WFToast.info('No changes to save');
            return;
        }

        const results = await Promise.all(tasks);
        if (results.every(r => r)) {
            if (window.WFToast) window.WFToast.success('All changes saved successfully!');
            setIsDirty(false);
            // local state already updated; DB rehydration handled elsewhere
            loadStoredSuggestions();
            setBreakdownRefreshTrigger(prev => prev + 1);
        } else {
            if (window.WFToast) window.WFToast.error('Some changes failed to save');
        }
    };

    const modalContent = (
        <div
            className="admin-modal-overlay over-header show topmost"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose?.();
            }}
        >
            <div
                className="admin-modal admin-modal-content show bg-slate-50 rounded-2xl shadow-2xl ring-1 ring-slate-200 w-[1100px] max-w-[95vw] h-[85vh] flex flex-col overflow-hidden"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-slate-200 gap-4 px-6 py-4 sticky top-0 bg-white/95 backdrop-blur-sm z-20">
                    <h2 className="text-xl font-black text-slate-800 flex items-center gap-3">
                        <span className="text-2xl">✨</span> {title || 'Price Suggestions'}
                    </h2>

                    <div className="flex-1 flex justify-center px-4">
                        <div className="relative inline-flex items-center w-full max-w-sm">
                            <select
                                value={selectedSku}
                                onChange={(e) => setSelectedSku(e.target.value)}
                                className="w-full text-xs font-bold p-2.5 px-4 pr-10 border border-slate-300 rounded-xl bg-white text-slate-700 outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 transition-all cursor-pointer appearance-none truncate shadow-sm"
                                disabled={isLoadingItems || isLoading || isGenerating}
                            >
                                <option value="">Select Item...</option>
                                {items.map(it => (
                                    <option key={it.sku} value={it.sku}>{it.sku} — {it.name}</option>
                                ))}
                            </select>
                            <div className="absolute right-4 pointer-events-none text-slate-500">
                                <span className="text-[10px]">▼</span>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <button
                            type="button"
                            onClick={handleGenerate}
                            disabled={isLoading || isGenerating || !selectedSku}
                            className="btn-text-secondary flex items-center gap-2"
                            data-help-id="settings-ai-summon"
                        >
                            {isGenerating ? 'Thinking...' : 'Generate All'}
                        </button>

                        <div className="h-8 border-l border-slate-200 mx-2" />

                        <div className="flex items-center gap-2">
                            {/* Save button - only visible when there are changes */}
                            {isDirty && (
                                <button
                                    onClick={handleSaveAll}
                                    className="admin-action-btn btn-icon--save is-dirty"
                                    data-help-id="settings-ai-trust"
                                    type="button"
                                />
                            )}
                            <button
                                onClick={onClose}
                                className="admin-action-btn btn-icon--close"
                                data-help-id="settings-ai-escape"
                            />
                        </div>
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto">
                    <div className="p-6 bg-gradient-to-b from-slate-50 to-slate-100/70 space-y-5">
                        {/* 3-column grid layout */}
                        <div className="grid grid-cols-3 gap-5">
                            {/* Column 1: Item Info */}
                            <div>
                                {/* Item Information Section */}
                                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                                    <div className="px-4 py-2.5 bg-slate-100/80 border-b border-slate-200">
                                        <h3 className="text-[10px] font-bold text-slate-700 uppercase tracking-widest flex items-center gap-2">
                                            <span>📦</span> Item Information
                                        </h3>
                                    </div>
                                    {currentItem ? (
                                        <div className="px-4 py-3 border-b border-slate-200 bg-slate-50/50">
                                            <img
                                                src={`/images/items/${currentItem.sku}A.webp`}
                                                alt={`${currentItem.name} primary`}
                                                className="w-full h-40 object-cover rounded-xl border border-slate-200 shadow-sm"
                                                onError={(event) => {
                                                    event.currentTarget.style.display = 'none';
                                                }}
                                            />
                                        </div>
                                    ) : null}
                                    <div className="px-4 py-3 border-b border-slate-200 bg-white">
                                        <button
                                            type="button"
                                            onClick={handleGenerateItemInfo}
                                            disabled={isLoading || isGenerating || !selectedSku}
                                            className="btn btn-primary text-xs py-2 px-3 w-full"
                                        >
                                            {isGenerating ? 'Generating...' : 'Generate'}
                                        </button>
                                    </div>
                                    <div className="p-3 bg-white">
                                        <ItemSelector
                                            items={items}
                                            selectedSku={selectedSku}
                                            onSelectedSkuChange={setSelectedSku}
                                            isLoadingItems={isLoadingItems}
                                            isLoadingSuggestions={isLoading || isGenerating}
                                            currentItem={currentItem}
                                            nameValue={localFormData.name}
                                            descriptionValue={localFormData.description}
                                            categoryValue={localFormData.category}
                                            categoryOptions={categoryOptions}
                                            onNameChange={handleNameChange}
                                            onDescriptionChange={handleDescriptionChange}
                                            onCategoryChange={handleCategoryChange}
                                            lockedFields={lockedFields}
                                            onToggleFieldLock={toggleFieldLock}
                                            lockedWords={lockedWords}
                                            onLockedWordsChange={handleLockedWordsChange}
                                            isReadOnly={false}
                                            onGenerate={handleGenerate}
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Column 2: Cost Analysis - Reusing Inventory Modal's components */}
                            <div className="bg-gradient-to-br from-amber-50 via-orange-50/70 to-white rounded-2xl p-5 border border-amber-200 shadow-sm">
                                <h3 className="text-[11px] font-black text-amber-800 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <span>💰</span> Cost Analysis
                                    <FieldLockIcon
                                        isLocked={!!lockedFields.cost_price}
                                        onToggle={() => toggleFieldLock('cost_price')}
                                        fieldName="Cost Price"
                                        disabled={false}
                                    />
                                </h3>
                                {selectedSku && currentItem ? (
                                    <div className="space-y-4">
                                        <AICostPanel
                                            sku={selectedSku}
                                            name={localFormData.name}
                                            description={localFormData.description}
                                            category={localFormData.category}
                                            isReadOnly={false}
                                            onApplyCost={handleApplyCost}
                                            onApplied={handleBreakdownApplied}
                                            tier={costTier}
                                            onTierChange={(tier) => {
                                                setCostTier(tier);
                                                setIsDirty(true);
                                            }}
                                            cachedSuggestion={hasGeneratedCostAnalysis ? cachedCostSuggestion : null}
                                            onSuggestionUpdated={(suggestion) => {
                                                setHasGeneratedCostAnalysis(true);
                                                setCachedCostSuggestion(suggestion);
                                            }}
                                            showStoredMetadata={false}
                                        />
                                        <div className="bg-white rounded-xl border border-amber-200 overflow-hidden shadow-sm">
                                            <div className="px-3 py-2 bg-amber-50/80 border-b border-amber-200">
                                                <h4 className="text-[10px] font-bold text-amber-800 uppercase tracking-widest">Cost Breakdown</h4>
                                            </div>
                                            <div className="p-3">
                                                <CostBreakdownTable
                                                    sku={selectedSku}
                                                    name={localFormData.name}
                                                    description={localFormData.description}
                                                    category={localFormData.category}
                                                    isReadOnly={false}
                                                    refreshTrigger={breakdownRefreshTrigger}
                                                    currentPrice={localFormData.cost_price}
                                                    onCurrentPriceChange={handleApplyCost}
                                                    tier={costTier}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex-1 flex items-center justify-center py-12 text-amber-500">
                                        <p className="text-sm italic text-center">Select an item to see cost analysis</p>
                                    </div>
                                )}
                            </div>

                            {/* Column 3: Price Analysis - Reusing Inventory Modal's components */}
                            <div className="bg-gradient-to-br from-emerald-50 via-green-50/70 to-white rounded-2xl p-5 border border-emerald-200 shadow-sm">
                                <h3 className="text-[11px] font-black text-emerald-800 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <span>💵</span> Price Analysis
                                    <FieldLockIcon
                                        isLocked={!!lockedFields.retail_price}
                                        onToggle={() => toggleFieldLock('retail_price')}
                                        fieldName="Retail Price"
                                        disabled={false}
                                    />
                                </h3>
                                {selectedSku && currentItem ? (
                                    <div className="space-y-4">
                                        <AIPricingPanel
                                            sku={selectedSku}
                                            name={localFormData.name}
                                            description={localFormData.description}
                                            category={localFormData.category}
                                            cost_price={localFormData.cost_price}
                                            isReadOnly={false}
                                            onApplyPrice={handleApplyPrice}
                                            onApplied={handleBreakdownApplied}
                                            tier={priceTier}
                                            onTierChange={(tier) => {
                                                setPriceTier(tier);
                                                setIsDirty(true);
                                            }}
                                            cachedSuggestion={hasGeneratedPriceAnalysis ? cachedPriceSuggestion : null}
                                            onSuggestionUpdated={(suggestion) => {
                                                setHasGeneratedPriceAnalysis(true);
                                                setCachedPriceSuggestion(suggestion);
                                            }}
                                        />
                                        <div className="bg-white rounded-xl border border-emerald-200 overflow-hidden shadow-sm">
                                            <div className="px-3 py-2 bg-emerald-50/80 border-b border-emerald-200">
                                                <h4 className="text-[10px] font-bold text-emerald-800 uppercase tracking-widest">Pricing Breakdown</h4>
                                            </div>
                                            <div className="p-3">
                                                <PriceBreakdownTable
                                                    sku={selectedSku}
                                                    name={localFormData.name}
                                                    description={localFormData.description}
                                                    category={localFormData.category}
                                                    isReadOnly={false}
                                                    refreshTrigger={breakdownRefreshTrigger}
                                                    currentPrice={localFormData.retail_price}
                                                    onCurrentPriceChange={handleApplyPrice}
                                                    tier={priceTier}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex-1 flex items-center justify-center py-12 text-emerald-500">
                                        <p className="text-sm italic text-center">Select an item to see price analysis</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Full-width Marketing Intelligence Section */}
                        <div className="bg-white rounded-2xl border border-emerald-200 shadow-sm overflow-hidden">
                            <div className="px-4 py-2.5 bg-emerald-50 border-b border-emerald-200 flex items-center justify-between gap-2">
                                <h3 className="text-[10px] font-bold text-emerald-800 uppercase tracking-widest flex items-center gap-2">
                                    <span>📢</span> Marketing Intelligence
                                </h3>
                            </div>
                            <div className="p-3 bg-white">
                                {selectedSku && currentItem ? (
                                    <AIMarketingPanel
                                        sku={selectedSku}
                                        name={localFormData.name}
                                        description={localFormData.description}
                                        category={localFormData.category}
                                        isReadOnly={false}
                                        cachedMarketing={cachedMarketingData}
                                        simpleMarketing={localMarketing}
                                        isGenerating={isGenerating}
                                    />
                                ) : (
                                    <div className="flex items-center justify-center py-8 text-slate-400">
                                        <p className="text-sm italic text-center">Select an item to see marketing data</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );

    return (
        <>
            {createPortal(modalContent, document.body)}
            {showMarketingManager && selectedSku && currentItem && (
                <MarketingManagerModal
                    sku={selectedSku}
                    itemName={localFormData.name}
                    itemDescription={localFormData.description}
                    category={localFormData.category}
                    initialMarketingData={cachedMarketingData}
                    onClose={() => setShowMarketingManager(false)}
                    onApplyField={(field, value) => {
                        if (field === 'name') handleNameChange(value);
                        if (field === 'description') handleDescriptionChange(value);
                    }}
                />
            )}
        </>
    );
};
