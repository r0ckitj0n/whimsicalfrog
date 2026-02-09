import { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { ApiClient } from '../../core/ApiClient.js';
import { useInventoryAI } from './useInventoryAI.js';
import { useCostBreakdown } from './useCostBreakdown.js';
import { usePriceBreakdown } from './usePriceBreakdown.js';
import { useAIGenerationOrchestrator, type GenerationStep, type GenerationContext } from './useAIGenerationOrchestrator.js';
import { IInventoryItem, IItemDetails, ISkuRegenerateResponse } from '../../types/inventory.js';
import type { MarketingData } from './inventory-ai/useMarketingManager.js';
import type { IAISuggestionsParams, IAISuggestionsResponse } from '../../types/ai.js';

// Re-export for backward compatibility
export type { IAISuggestionsParams, IAISuggestionsResponse } from '../../types/ai.js';



/** The hook accepts either IInventoryItem or IItemDetails since data comes from different sources */
type ItemData = Partial<IInventoryItem> & Partial<IItemDetails> & { sku?: string };

interface UseInventoryItemFormProps {
    sku: string;
    mode: 'edit' | 'view' | 'add' | '';
    item: ItemData | null;
    addItem: (data: Record<string, unknown>) => Promise<{ success: boolean; error?: string }>;
    updateCell: (sku: string, column: string, value: string | number | boolean | null) => Promise<{ success: boolean; error?: string }>;
    // Using generic function type since useInventoryAI has stricter required params
    fetch_all_suggestions: (params: Record<string, unknown>) => Promise<IAISuggestionsResponse | null>;
    // Using generic function type since these are React setState dispatchers
    setCachedPriceSuggestion: (data: unknown) => void;
    setCachedCostSuggestion: (data: unknown) => void;
    onSaved?: () => void;
    onClose: () => void;
    refresh: () => void;
    primaryImage?: string;
    hasUploadedImage?: boolean;
}

export const useInventoryItemForm = ({
    sku,
    mode,
    item,
    addItem,
    updateCell,
    // fetch_all_suggestions, // Removed from props
    // setCachedPriceSuggestion, // Removed from props
    // setCachedCostSuggestion, // Removed from props
    onSaved,
    onClose,
    refresh,
    primaryImage,
    hasUploadedImage = false
}: UseInventoryItemFormProps) => {
    const makeFallbackSku = (): string => {
        const stamp = Date.now().toString().slice(-6);
        return `WF-TMP-${stamp}`;
    };

    const {
        is_busy: aiBusy,
        cached_price_suggestion,
        cached_cost_suggestion,
        fetch_all_suggestions,
        generateMarketing,
        setCachedCostSuggestion,
        setCachedPriceSuggestion
    } = useInventoryAI();

    // New: Use orchestrator for sequential generation
    const {
        isGenerating: orchestratorBusy,
        currentStep: orchestratorStep,
        progress: orchestratorProgress,
        orchestrateFullGeneration,
        generateInfoOnly
    } = useAIGenerationOrchestrator();

    const isReadOnly = mode === 'view';
    const isAdding = mode === 'add';

    const [formData, setFormData] = useState<{
        name: string;
        category: string;
        status: IInventoryItem['status'] | 'archived';
        stock_level: number;
        reorder_point: number;
        cost_price: number;
        retail_price: number;
        description: string;
        is_archived: number;
        weight_oz: number;
        package_length_in: number;
        package_width_in: number;
        package_height_in: number;
    }>({
        name: '',
        category: '',
        status: 'draft',
        stock_level: 0,
        reorder_point: 0,
        cost_price: 0,
        retail_price: 0,
        description: '',
        is_archived: 0,
        weight_oz: 0,
        package_length_in: 0,
        package_width_in: 0,
        package_height_in: 0
    });

    const [isDirty, setIsDirty] = useState(false);
    const [localSku, setLocalSku] = useState(sku || '');
    const [sourceTempSku, setSourceTempSku] = useState('');
    const [isSaving, setIsSaving] = useState(false);
    const [lockedFields, setLockedFields] = useState<Record<string, boolean>>({});
    const [lockedWords, setLockedWords] = useState<Record<string, string>>({});
    const [cachedMarketingData, setCachedMarketingData] = useState<MarketingData | null>(null);

    const { populateFromSuggestion: populateCost } = useCostBreakdown(localSku);
    const { populateFromSuggestion: populatePrice } = usePriceBreakdown(localSku);
    const isDirtyRef = useRef(isDirty);
    const isSavingRef = useRef(isSaving);
    const skuPromotionInFlightRef = useRef(false);

    useEffect(() => {
        isDirtyRef.current = isDirty;
    }, [isDirty]);

    useEffect(() => {
        isSavingRef.current = isSaving;
    }, [isSaving]);

    useEffect(() => {
        if (sku) setLocalSku(sku);
    }, [sku]);

    useEffect(() => {
        if (!isAdding || localSku) return;
        setLocalSku(makeFallbackSku());
    }, [isAdding, localSku]);

    useEffect(() => {
        let isCancelled = false;
        const skuForMarketing = isAdding ? localSku : sku;

        const loadExistingMarketing = async () => {
            if (!skuForMarketing) {
                setCachedMarketingData(null);
                return;
            }
            try {
                const apiUrl = `${window.location.origin}/api/get_marketing_suggestion.php?sku=${encodeURIComponent(skuForMarketing)}`;
                const response = await ApiClient.request<{ success?: boolean; exists?: boolean; data?: MarketingData }>(
                    apiUrl,
                    { method: 'GET' }
                );
                if (isCancelled) return;
                const payload = (response?.data && typeof response.data === 'object')
                    ? response.data
                    : null;
                const exists = response?.exists ?? Boolean(payload);
                if ((response?.success ?? false) && exists && payload) {
                    setCachedMarketingData(payload);
                } else {
                    setCachedMarketingData(null);
                }
            } catch (err) {
                if (isCancelled) return;
                console.error('Failed to load existing marketing data', err);
                setCachedMarketingData(null);
            }
        };

        void loadExistingMarketing();

        return () => {
            isCancelled = true;
        };
    }, [isAdding, localSku, sku]);

    useEffect(() => {
        // Guard: do not clobber in-progress local edits.
        if (isDirtyRef.current || isSavingRef.current) return;

        if (isAdding) {
            setFormData({
                name: '',
                category: '',
                status: 'draft',
                stock_level: 0,
                reorder_point: 5,
                cost_price: 0,
                retail_price: 0,
                description: '',
                is_archived: 0,
                weight_oz: 0,
                package_length_in: 0,
                package_width_in: 0,
                package_height_in: 0
            });
            return;
        }

        if (item) {
            const isArchived = Number(item.is_archived) === 1;
            setFormData({
                name: item.name || '',
                category: item.category || '',
                status: isArchived ? 'archived' : (item.status === 'live' ? 'live' : 'draft'),
                stock_level: Number(item.stock_quantity || item.stock_level) || 0,
                reorder_point: Number(item.reorder_point) || 0,
                cost_price: Number(item.cost_price) || 0,
                retail_price: Number(item.retail_price || item.price) || 0,
                description: item.description || '',
                is_archived: isArchived ? 1 : 0,
                weight_oz: Number(item.weight_oz) || 0,
                package_length_in: Number(item.package_length_in) || 0,
                package_width_in: Number(item.package_width_in) || 0,
                package_height_in: Number(item.package_height_in) || 0
            });
            // Hydrate locked fields from item data
            if ((item as IItemDetails).locked_fields) {
                setLockedFields((item as IItemDetails).locked_fields || {});
            }
            if ((item as IItemDetails).locked_words) {
                setLockedWords((item as IItemDetails).locked_words || {});
            }
        }
    }, [item, isAdding]);

    const handleFieldChange = useCallback((field: string, value: any) => {
        setFormData(prev => ({ ...prev, [field]: value }));
        setIsDirty(true);
    }, []);

    const toggleFieldLock = useCallback((field: string) => {
        setLockedFields(prev => {
            const newLocked = { ...prev, [field]: !prev[field] };
            // Remove false entries to keep the object clean
            if (!newLocked[field]) delete newLocked[field];
            return newLocked;
        });
        setIsDirty(true);
    }, []);

    const updateLockedWords = useCallback((field: string, value: string) => {
        setLockedWords(prev => ({ ...prev, [field]: value }));
        setIsDirty(true);
    }, []);

    const generateSku = async () => {
        const fallbackSku = makeFallbackSku();
        setLocalSku(fallbackSku);
        setSourceTempSku('');
        setIsDirty(true);
        window.WFToast?.info?.('Temporary SKU assigned. Final category SKU is generated when saving.');
    };

    const regenerateSku = useCallback(async (): Promise<{ success: boolean; newSku?: string; updatedCounts?: Record<string, number>; error?: string }> => {
        if (isAdding || isReadOnly || isSaving) {
            return { success: false, error: 'SKU regeneration is only available while editing.' };
        }

        const currentSku = String(item?.sku || localSku || '').trim();
        if (!currentSku) {
            return { success: false, error: 'Current SKU is missing.' };
        }

        try {
            const res = await ApiClient.post<ISkuRegenerateResponse>('/api/regenerate_sku.php', {
                current_sku: currentSku,
                category: formData.category || undefined
            });

            if (!res?.success || !res.new_sku) {
                const error = res?.error || 'Failed to regenerate SKU';
                window.WFToast?.error?.(error);
                return { success: false, error };
            }

            setLocalSku(res.new_sku);
            setIsDirty(false);
            refresh();

            const updatedTargets = Object.values(res.updated_counts || {}).reduce((sum, count) => sum + Number(count || 0), 0);
            window.WFToast?.success?.(`SKU updated to ${res.new_sku} (${updatedTargets} references updated)`);

            return {
                success: true,
                newSku: res.new_sku,
                updatedCounts: res.updated_counts || {}
            };
        } catch (err) {
            const error = err instanceof Error ? err.message : 'Failed to regenerate SKU';
            window.WFToast?.error?.(error);
            return { success: false, error };
        }
    }, [isAdding, isReadOnly, isSaving, item?.sku, localSku, formData.category, refresh]);

    const promoteTempSkuForCategory = useCallback(async (resolvedCategory: string) => {
        if (!isAdding) return;
        if (!localSku || !localSku.startsWith('WF-TMP-')) return;
        if (!resolvedCategory || skuPromotionInFlightRef.current) return;

        skuPromotionInFlightRef.current = true;
        try {
            const response = await ApiClient.get<{
                success?: boolean;
                sku?: string;
                data?: { sku?: string };
                error?: string;
            }>('/api/next_sku.php', { category: resolvedCategory });

            const nextSku = String(response?.data?.sku || response?.sku || '').trim();
            if (!nextSku || nextSku === localSku) return;

            setSourceTempSku(prev => prev || localSku);
            setLocalSku(nextSku);
            setIsDirty(true);
            window.WFToast?.success?.(`SKU assigned: ${nextSku}`);
        } catch (_err) {
            // Keep temp SKU if next_sku is unavailable; save endpoint still finalizes SKU safely.
        } finally {
            skuPromotionInFlightRef.current = false;
        }
    }, [isAdding, localSku]);

    const triggerGenerationChain = async () => {
        if (!localSku) {
            if (window.WFToast) window.WFToast.error('SKU is required for AI analysis');
            return;
        }

        if (!hasUploadedImage || !primaryImage) {
            if (window.WFToast) window.WFToast.error('Please upload an image first for AI analysis');
            return;
        }

        setIsDirty(true);

        // Use the new sequential orchestrator with locked fields support
        const generationLockedWords = lockedWords;

        const result = await orchestrateFullGeneration({
            sku: localSku,
            primaryImageUrl: primaryImage,
            initialName: formData.name,
            initialDescription: formData.description,
            initialCategory: formData.category,
            tier: 'standard',
            lockedFields, // Pass locked fields to orchestrator
            lockedWords: generationLockedWords,
            onStepComplete: (step, context, skippedFields) => {
                // Debug: Log context for troubleshooting
                console.log('[AI Orchestrator] Step completed:', step, 'Context:', context, 'Skipped:', skippedFields);

                if (step === 'info') {
                    const changedDimensions: string[] = [];
                    const hasChanged = (next: number | null, current: number): boolean => {
                        if (next === null) return false;
                        return Math.abs(Number(next.toFixed(2)) - Number(current)) > 0.001;
                    };

                    if (!lockedFields.weight_oz && hasChanged(context.weightOz, formData.weight_oz)) {
                        changedDimensions.push('weight');
                    }
                    if (!lockedFields.package_length_in && hasChanged(context.packageLengthIn, formData.package_length_in)) {
                        changedDimensions.push('length');
                    }
                    if (!lockedFields.package_width_in && hasChanged(context.packageWidthIn, formData.package_width_in)) {
                        changedDimensions.push('width');
                    }
                    if (!lockedFields.package_height_in && hasChanged(context.packageHeightIn, formData.package_height_in)) {
                        changedDimensions.push('height');
                    }

                    if (changedDimensions.length > 0) {
                        window.WFToast?.info?.(`📦 Dimensions updated by AI: ${changedDimensions.join(', ')}`);
                    }

                    if (isAdding && context.category) {
                        void promoteTempSkuForCategory(context.category);
                    }
                }

                // Update form data progressively after each step, respecting locked fields
                setFormData(prev => ({
                    ...prev,
                    // Image-first generation owns these fields; locked words are enforced upstream.
                    name: context.name,
                    description: context.description,
                    category: context.category,
                    weight_oz: (!lockedFields.weight_oz && context.weightOz !== null)
                        ? Number(context.weightOz.toFixed(2))
                        : prev.weight_oz,
                    package_length_in: (!lockedFields.package_length_in && context.packageLengthIn !== null)
                        ? Number(context.packageLengthIn.toFixed(2))
                        : prev.package_length_in,
                    package_width_in: (!lockedFields.package_width_in && context.packageWidthIn !== null)
                        ? Number(context.packageWidthIn.toFixed(2))
                        : prev.package_width_in,
                    package_height_in: (!lockedFields.package_height_in && context.packageHeightIn !== null)
                        ? Number(context.packageHeightIn.toFixed(2))
                        : prev.package_height_in,
                    cost_price: (!lockedFields.cost_price && context.suggestedCost !== null)
                        ? Number(context.suggestedCost.toFixed(2))
                        : prev.cost_price,
                    retail_price: (!lockedFields.retail_price && context.suggestedPrice !== null)
                        ? Number(context.suggestedPrice.toFixed(2))
                        : prev.retail_price
                }));

                // Update cached suggestions for breakdown panels (only if not locked)
                if (step === 'cost' && context.suggestedCost !== null && !lockedFields.cost_price) {
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    (setCachedCostSuggestion as (data: any) => void)({
                        suggested_cost: context.suggestedCost,
                        confidence: context.costConfidence ?? 'N/A',
                        reasoning: context.costReasoning ?? '',
                        breakdown: context.costBreakdown ?? {},
                        analysis: {},
                        created_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
                    });
                }
                if (step === 'price' && context.suggestedPrice !== null && !lockedFields.retail_price) {
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    (setCachedPriceSuggestion as (data: any) => void)({
                        success: true,
                        suggested_price: context.suggestedPrice,
                        confidence: context.priceConfidence ?? 'N/A',
                        reasoning: context.priceReasoning ?? '',
                        components: context.priceComponents ?? [],
                        factors: {},
                        analysis: {},
                        created_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
                    });
                }
                if (step === 'marketing' && context.marketingData) {
                    setCachedMarketingData(context.marketingData);
                }
            }
        });

        if (result) {
            console.log('[AI Orchestrator] Final result:', result);
            setIsDirty(true);
        }
    };

    const handleGenerateAll = async () => {
        // Redirection to the new chain logic
        return triggerGenerationChain();
    };

    const handleGenerateInfoAndMarketing = async () => {
        if (!localSku) {
            window.WFToast?.error?.('SKU is required for AI analysis');
            return;
        }
        if (!hasUploadedImage || !primaryImage) {
            window.WFToast?.error?.('Please upload an image first for AI analysis');
            return;
        }

        setIsDirty(true);
        const result = await generateInfoOnly({
            sku: localSku,
            primaryImageUrl: primaryImage,
            previousName: formData.name,
            lockedFields,
            lockedWords,
            includeMarketingRefinement: true
        });

        if (!result) {
            window.WFToast?.error?.('Failed to generate item information');
            return;
        }

        setFormData(prev => ({
            ...prev,
            name: result.name || prev.name,
            description: result.description || prev.description,
            category: result.category || prev.category,
            weight_oz: (typeof result.weightOz === 'number' && !lockedFields.weight_oz)
                ? Number(result.weightOz.toFixed(2))
                : prev.weight_oz,
            package_length_in: (typeof result.packageLengthIn === 'number' && !lockedFields.package_length_in)
                ? Number(result.packageLengthIn.toFixed(2))
                : prev.package_length_in,
            package_width_in: (typeof result.packageWidthIn === 'number' && !lockedFields.package_width_in)
                ? Number(result.packageWidthIn.toFixed(2))
                : prev.package_width_in,
            package_height_in: (typeof result.packageHeightIn === 'number' && !lockedFields.package_height_in)
                ? Number(result.packageHeightIn.toFixed(2))
                : prev.package_height_in
        }));

        if (result.marketingData) {
            setCachedMarketingData(result.marketingData);
        }

        if (isAdding && result.category) {
            await promoteTempSkuForCategory(result.category);
        }

        window.WFToast?.success?.('Generated item information and marketing');
    };

    const handleSave = useCallback(async (): Promise<boolean> => {
        if (isReadOnly || isSaving) return false;
        if (isAdding && !localSku) {
            if (window.WFToast) window.WFToast.error('SKU is required');
            return false;
        }

        const skuToSave = isAdding ? localSku : (item?.sku || '');
        if (!skuToSave) {
            if (window.WFToast) window.WFToast.error('SKU is required');
            return false;
        }

        setIsSaving(true);
        try {
            if (isAdding) {
                const res = await addItem({
                    ...formData,
                    sku: localSku,
                    source_temp_sku: sourceTempSku || undefined,
                    stock_quantity: formData.stock_level
                });
                if (res.success) {
                    if (window.WFToast) window.WFToast.success('Item created successfully');
                    setIsDirty(false);
                    onSaved?.();
                    onClose();
                    return true;
                } else {
                    if (window.WFToast) window.WFToast.error(res.error || 'Failed to create item');
                    return false;
                }
            }

            const updatePromises: Promise<{ success: boolean; error?: string }>[] = [];
            const originalRetail = Number((item as any)?.retail_price ?? (item as any)?.price) || 0;
            const currentRetail = Number(formData.retail_price) || 0;
            const suggestedRetail = Number((cached_price_suggestion as any)?.suggested_price) || 0;
            const shouldBackfillRetailFromSuggestion =
                !lockedFields.retail_price &&
                suggestedRetail > 0 &&
                Math.abs(currentRetail - originalRetail) <= 0.001;

            const dataToSave = shouldBackfillRetailFromSuggestion
                ? { ...formData, retail_price: Number(suggestedRetail.toFixed(2)) }
                : formData;

            // Main fields handling
            for (const [field, value] of Object.entries(dataToSave)) {
                if (field === 'is_archived') continue;

                const itemKey = field === 'stock_level' ? 'stock_quantity' : field as keyof IInventoryItem;
                const rawOriginalValue = (item as any)?.[itemKey];

                let changed = false;
                if (typeof value === 'number') {
                    const normalizedOriginal = Number(rawOriginalValue) || 0;
                    if (Math.abs(value - normalizedOriginal) > 0.001) changed = true;
                } else if (String(value) !== String(rawOriginalValue ?? '')) {
                    changed = true;
                }

                if (changed) {
                    if (field === 'status') {
                        if (value === 'archived') {
                            if (Number(item?.is_archived) !== 1) {
                                updatePromises.push(updateCell(skuToSave, 'is_archived', 1));
                            }
                            continue;
                        } else if (Number(item?.is_archived) === 1) {
                            updatePromises.push(updateCell(skuToSave, 'is_archived', 0));
                        }
                    }
                    updatePromises.push(updateCell(skuToSave, itemKey as string, value));
                }
            }

            updatePromises.push(updateCell(skuToSave, 'locked_fields', JSON.stringify(lockedFields)));
            updatePromises.push(updateCell(skuToSave, 'locked_words', JSON.stringify(lockedWords)));

            if (updatePromises.length > 0 || cached_cost_suggestion || cached_price_suggestion) {
                if (updatePromises.length > 0) {
                    const results = await Promise.all(updatePromises);
                    const allSuccess = results.every(r => r.success);
                    if (!allSuccess) {
                        if (window.WFToast) window.WFToast.error('Some fields failed to update');
                    }
                }

                // Auto-apply AI suggestion factors to the database tables
                if (cached_cost_suggestion) {
                    await populateCost(cached_cost_suggestion);
                    setCachedCostSuggestion(null);
                }
                if (cached_price_suggestion) {
                    await populatePrice(cached_price_suggestion);
                    setCachedPriceSuggestion(null);
                }

                if (window.WFToast) window.WFToast.success('Changes saved successfully');
                setIsDirty(false);
                onSaved?.();
                refresh();
                return true;
            } else {
                if (window.WFToast) window.WFToast.info('No changes to save');
                return true;
            }
        } catch (err: unknown) {
            console.error('Save failed', err);
            if (window.WFToast) window.WFToast.error('Failed to save changes');
            return false;
        } finally {
            setIsSaving(false);
        }
        return false;
    }, [
        isReadOnly,
        isSaving,
        isAdding,
        localSku,
        sourceTempSku,
        item,
        formData,
        addItem,
        updateCell,
        cached_cost_suggestion,
        cached_price_suggestion,
        populateCost,
        populatePrice,
        setCachedCostSuggestion,
        setCachedPriceSuggestion,
        onSaved,
        refresh,
        onClose,
        lockedFields,
        lockedWords
    ]);

    const handleApplyCost = (cost: number) => {
        handleFieldChange('cost_price', cost);
        setIsDirty(true);
    };
    const handleApplyPrice = (price: number) => {
        handleFieldChange('retail_price', price);
        setIsDirty(true);
    };
    const handleStockChange = (newTotal: number) => handleFieldChange('stock_level', newTotal);

    const handleCostSuggestionUpdated = (suggestion: unknown) => {
        setCachedCostSuggestion(suggestion as Parameters<typeof setCachedCostSuggestion>[0]);
        setIsDirty(true);
    };

    const handlePriceSuggestionUpdated = (suggestion: unknown) => {
        const typedSuggestion = suggestion as Parameters<typeof setCachedPriceSuggestion>[0];
        setCachedPriceSuggestion(typedSuggestion);

        const suggestedPrice = Number((typedSuggestion as any)?.suggested_price);
        if (!lockedFields.retail_price && Number.isFinite(suggestedPrice) && suggestedPrice > 0) {
            setFormData(prev => ({
                ...prev,
                retail_price: Number(suggestedPrice.toFixed(2))
            }));
        }
        setIsDirty(true);
    };

    const [breakdownRefreshTrigger, setBreakdownRefreshTrigger] = useState(0);
    const handleBreakdownApplied = () => {
        setBreakdownRefreshTrigger(prev => prev + 1);
        setIsDirty(true);
    };

    return {
        formData,
        isDirty,
        isSaving,
        cached_cost_suggestion,
        cached_price_suggestion,
        localSku,
        setLocalSku,
        handleFieldChange,
        generateSku,
        handleGenerateAll,
        handleGenerateInfoAndMarketing,
        regenerateSku,
        handleSave,
        handleApplyCost,
        handleApplyPrice,
        handleStockChange,
        handleCostSuggestionUpdated,
        handlePriceSuggestionUpdated,
        breakdownRefreshTrigger,
        handleBreakdownApplied,
        isReadOnly,
        isAdding,
        sourceTempSku,
        lockedFields,
        toggleFieldLock,
        lockedWords,
        updateLockedWords,
        cachedMarketingData
    };
};
