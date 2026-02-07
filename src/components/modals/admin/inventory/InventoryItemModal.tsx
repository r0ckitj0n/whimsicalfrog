import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { useSearchParams } from 'react-router-dom';
import { useItemDetails } from '../../../../hooks/useItemDetails.js';
import { useInventory } from '../../../../hooks/admin/useInventory.js';
import { useInventoryAI } from '../../../../hooks/admin/useInventoryAI.js';
import { useInventoryItemForm } from '../../../../hooks/admin/useInventoryItemForm.js';
import { useModalContext } from '../../../../context/ModalContext.js';
import { AiManager } from '../../../../core/ai/AiManager.js';
import type { MarketingData } from '../../../../hooks/admin/inventory-ai/useMarketingManager.js';

// Partials
import { ItemInfoColumn } from '../../../admin/inventory/partials/ItemInfoColumn.js';
import { CostAnalysisColumn } from '../../../admin/inventory/partials/CostAnalysisColumn.js';
import { PriceAnalysisColumn } from '../../../admin/inventory/partials/PriceAnalysisColumn.js';
import { MediaAndVariantsSection } from '../../../admin/inventory/partials/MediaAndVariantsSection.js';
import { AIMarketingPanel } from '../../../admin/inventory/AIMarketingPanel.js';

interface InventoryItemModalProps {
    sku: string;
    mode: 'edit' | 'view' | 'add' | '';
    onClose: () => void;
    onSaved?: () => void;
    onEdit?: () => void;
}

export const InventoryItemModal: React.FC<InventoryItemModalProps> = ({
    sku,
    mode,
    onClose,
    onSaved,
    onEdit
}) => {
    const isAdding = mode === 'add';
    const [, setSearchParams] = useSearchParams();
    const { confirm: confirmModal } = useModalContext();
    const { item, images, isLoading: detailsLoading, error, refresh } = useItemDetails(sku);
    const { items: inventoryItems, categories, updateCell, addItem } = useInventory();
    const {
        is_busy,
        fetch_all_suggestions,
        setCachedPriceSuggestion,
        setCachedCostSuggestion
    } = useInventoryAI();

    // Wrapper functions to bridge type differences between hooks
    const wrappedFetchSuggestions = async (params: Record<string, unknown>) => {
        const result = await fetch_all_suggestions(params as Parameters<typeof fetch_all_suggestions>[0]);
        return result as {
            success: boolean;
            info_suggestion?: {
                name?: string;
                description?: string;
                category?: string;
                weight_oz?: number;
                package_length_in?: number;
                package_width_in?: number;
                package_height_in?: number;
            };
            cost_suggestion?: { suggested_cost?: number };
            price_suggestion?: { suggested_price?: number };
        } | null;
    };
    const wrappedSetPriceSuggestion = (data: unknown) => setCachedPriceSuggestion(data as Parameters<typeof setCachedPriceSuggestion>[0]);
    const wrappedSetCostSuggestion = (data: unknown) => setCachedCostSuggestion(data as Parameters<typeof setCachedCostSuggestion>[0]);

    const primaryImage = (!isAdding && images.find(img => img.is_primary)?.image_path) ||
        (!isAdding && images[0]?.image_path) ||
        item?.image ||
        (sku ? `/images/items/${sku}A.webp` : '/images/placeholder.webp');

    // Custom hook for form state and logic
    const {
        formData,
        isDirty,
        localSku,
        cached_cost_suggestion,
        cached_price_suggestion,
        setLocalSku,
        handleFieldChange,
        generateSku,
        handleGenerateAll,
        handleSave,
        handleApplyCost,
        handleApplyPrice,
        handleStockChange,
        handleCostSuggestionUpdated,
        handlePriceSuggestionUpdated,
        breakdownRefreshTrigger,
        handleBreakdownApplied,
        isReadOnly,
        isSaving,
        lockedFields,
        toggleFieldLock,
        lockedWords,
        updateLockedWords,
        cachedMarketingData
    } = useInventoryItemForm({
        sku,
        mode,
        item,
        addItem,
        updateCell,
        fetch_all_suggestions: wrappedFetchSuggestions,
        setCachedPriceSuggestion: wrappedSetPriceSuggestion,
        setCachedCostSuggestion: wrappedSetCostSuggestion,
        onSaved,
        onClose,
        refresh,
        primaryImage
    });

    const isLoading = detailsLoading && !isAdding;
    const [costTier, setCostTier] = useState('standard');
    const [priceTier, setPriceTier] = useState('standard');
    const [storedMarketingData, setStoredMarketingData] = useState<MarketingData | null>(null);
    const [storedSimpleMarketing, setStoredSimpleMarketing] = useState<{
        targetAudience?: string;
        sellingPoints?: string[];
        marketingChannels?: string[];
    } | null>(null);

    useEffect(() => {
        let isCancelled = false;
        const skuForMarketing = isAdding ? localSku : sku;

        const loadStoredMarketing = async () => {
            if (!skuForMarketing) {
                setStoredMarketingData(null);
                setStoredSimpleMarketing(null);
                return;
            }
            try {
                const mktRes = await AiManager.getStoredMarketingSuggestion(skuForMarketing);
                if (isCancelled) return;

                const marketingResponse = mktRes as {
                    exists?: boolean;
                    data?: Record<string, unknown>;
                    sku?: string;
                    target_audience?: string;
                    demographic_targeting?: string;
                    selling_points?: string[];
                    marketing_channels?: string[];
                } | null;

                const marketingPayload = (marketingResponse?.data && typeof marketingResponse.data === 'object')
                    ? marketingResponse.data as Record<string, unknown>
                    : marketingResponse as unknown as Record<string, unknown> | null;
                const marketingExists = marketingResponse?.exists ?? Boolean(marketingPayload?.sku);

                if (marketingExists && marketingPayload) {
                    setStoredMarketingData(marketingPayload as unknown as MarketingData);
                    setStoredSimpleMarketing({
                        targetAudience: String(marketingPayload.target_audience || marketingPayload.demographic_targeting || ''),
                        sellingPoints: (marketingPayload.selling_points as string[]) || [],
                        marketingChannels: (marketingPayload.marketing_channels as string[]) || []
                    });
                } else {
                    setStoredMarketingData(null);
                    setStoredSimpleMarketing(null);
                }
            } catch (_err) {
                if (isCancelled) return;
                setStoredMarketingData(null);
                setStoredSimpleMarketing(null);
            }
        };

        void loadStoredMarketing();

        return () => {
            isCancelled = true;
        };
    }, [isAdding, localSku, sku]);

    useEffect(() => {
        if (isAdding || !item) {
            setCostTier('standard');
            setPriceTier('standard');
            return;
        }

        const itemWithTier = item as IItemDetails & { cost_quality_tier?: string; price_quality_tier?: string };
        setCostTier(itemWithTier.cost_quality_tier || 'standard');
        setPriceTier(itemWithTier.price_quality_tier || 'standard');
    }, [item, isAdding]);

    const persistTierSelection = async (field: 'cost_quality_tier' | 'price_quality_tier', value: string) => {
        if (isAdding || !sku || isReadOnly) return;
        try {
            const res = await updateCell(sku, field, value);
            if (!res?.success && window.WFToast) {
                window.WFToast.error(res?.error || 'Failed to save tier selection');
            }
        } catch (err) {
            if (window.WFToast) window.WFToast.error('Failed to save tier selection');
        }
    };

    const handleSkuSelect = (nextSku: string) => {
        if (!nextSku || nextSku === sku || isAdding) return;
        setSearchParams(prev => {
            prev.delete('add');
            if (mode === 'view') {
                prev.set('view', nextSku);
                prev.delete('edit');
            } else {
                prev.set('edit', nextSku);
                prev.delete('view');
            }
            return prev;
        });
    };

    const attemptClose = async () => {
        if (isSaving) return;
        if (!isDirty) {
            onClose();
            return;
        }

        const shouldSave = await confirmModal({
            title: 'Unsaved Changes',
            message: 'Save changes before closing this modal?',
            subtitle: 'Choose Save to keep edits, or Discard to close without saving.',
            confirmText: 'Save',
            cancelText: 'Discard',
            confirmStyle: 'warning',
            iconKey: 'warning'
        });

        if (shouldSave) {
            const didSave = await handleSave();
            if (didSave && !isAdding) onClose();
            return;
        }

        onClose();
    };

    if (isLoading) {
        return createPortal(
            <div className="fixed inset-0 z-[var(--wf-z-modal)] flex items-center justify-center bg-black/60 backdrop-blur-sm">
                <div className="flex flex-col items-center justify-center p-12 text-white">
                    <span className="wf-emoji-loader text-4xl">📝</span>
                    <p className="mt-4 font-medium">Loading item details...</p>
                </div>
            </div>,
            document.body
        );
    }

    if (error || (!item && sku && !isAdding)) {
        return createPortal(
            <div className="fixed inset-0 z-[var(--wf-z-modal)] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                <div className="bg-white rounded-2xl p-6 max-w-md">
                    <div className="p-4 bg-[var(--brand-error)]/5 text-[var(--brand-error)] rounded-xl border border-[var(--brand-error)]/20">
                        {error || `Item ${sku} not found.`}
                    </div>
                    <button
                        onClick={onClose}
                        className="mt-4 px-6 py-2 bg-gray-100 rounded-full font-bold text-gray-600 hover:bg-gray-200 transition-colors"
                    >
                        Close
                    </button>
                </div>
            </div>,
            document.body
        );
    }


    const modalContent = (
        <div
            className="fixed inset-0 z-[var(--wf-z-modal)] flex items-center justify-center bg-black/60 backdrop-blur-sm inventory-modal"
            onClick={(e) => { if (e.target === e.currentTarget) void attemptClose(); }}
        >
            <div
                className="bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] max-h-[95vh] mx-4 my-auto animate-in fade-in zoom-in-95 overflow-hidden flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Modal Header */}
                <div className="wf-modal-header modal-header px-6 py-4 border-b bg-gradient-to-r from-gray-50 to-white flex items-center justify-between flex-shrink-0">
                    <div className="flex items-center gap-4 min-w-[250px]">
                        <div className="w-10 h-10 bg-[var(--brand-primary)]/10 rounded-xl flex items-center justify-center text-xl">
                            📦
                        </div>
                        <div>
                            <h2 className="text-lg font-semibold text-gray-900">
                                {isAdding ? '✨ Add New Item' : (mode === 'edit' ? '✏️ Item Information' : '👁️ Item Details')}
                            </h2>
                            <div className="text-xs font-medium text-[var(--brand-primary)] uppercase tracking-widest">
                                {isAdding ? 'New Inventory Record' : sku}
                            </div>
                        </div>
                    </div>

                    <div className="flex-1 flex justify-center px-4">
                        <div className="relative inline-flex items-center w-full max-w-sm">
                            <select
                                value={sku}
                                onChange={(e) => handleSkuSelect(e.target.value)}
                                className="w-full text-xs font-bold p-2.5 px-4 pr-10 border border-slate-300 rounded-xl bg-white text-slate-700 outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 transition-all cursor-pointer appearance-none truncate shadow-sm disabled:opacity-60 disabled:cursor-not-allowed"
                                disabled={isAdding || isSaving || is_busy}
                            >
                                <option value="">Select Item...</option>
                                {inventoryItems.map(it => (
                                    <option key={it.sku} value={it.sku}>{it.sku} — {it.name}</option>
                                ))}
                            </select>
                            <div className="absolute right-4 pointer-events-none text-slate-500">
                                <span className="text-[10px]">▼</span>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 min-w-[320px]">
                        {!isReadOnly && (
                            <>
                                <button
                                    type="button"
                                    onClick={handleGenerateAll}
                                    disabled={is_busy}
                                    className="btn-text-secondary flex items-center gap-2"
                                    data-help-id="inventory-item-generate-all-header"
                                >
                                    {is_busy ? 'Thinking...' : 'Generate All'}
                                </button>
                                <div className="h-8 border-l border-slate-200 mx-2" />
                            </>
                        )}
                        {(isReadOnly && !isDirty && onEdit && !isAdding) && (
                            <button
                                onClick={onEdit}
                                className="admin-action-btn btn-icon--edit"
                                data-help-id="inventory-item-edit"
                            />
                        )}
                        {(isDirty || isAdding) && (
                            <button
                                onClick={handleSave}
                                disabled={isSaving}
                                className={`admin-action-btn ${isAdding ? 'btn-icon--plus' : 'btn-icon--save'} is-dirty ${isSaving ? 'is-loading' : ''}`}
                                data-help-id={isAdding ? 'inventory-item-create' : 'inventory-item-save'}
                            />
                        )}
                        <button
                            onClick={() => { void attemptClose(); }}
                            className="admin-action-btn btn-icon--close inventory-modal-close-btn shrink-0"
                            data-help-id="common-close"
                            data-emoji="×"
                            aria-label="Close item information modal"
                            title="Close"
                        >
                            <span className="inventory-modal-close-glyph" aria-hidden="true">×</span>
                        </button>
                    </div>
                </div>

                {/* Three-Column Layout */}
                <div className="modal-body wf-admin-modal-body p-0">
                    <div className="p-6 bg-gradient-to-b from-slate-50 to-slate-100/70 space-y-5">
                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                            <div>
                            <ItemInfoColumn
                                sku={sku}
                                localSku={localSku}
                                mode={mode}
                                isReadOnly={isReadOnly}
                                isAdding={isAdding}
                                formData={formData}
                                categories={categories}
                                onLocalSkuChange={setLocalSku}
                                onFieldChange={handleFieldChange}
                                onGenerateSku={generateSku}
                                onGenerateAll={handleGenerateAll}
                                isBusy={is_busy}
                                primaryImage={primaryImage}
                                lockedFields={lockedFields}
                                onToggleFieldLock={toggleFieldLock}
                                lockedWords={lockedWords}
                                onLockedWordsChange={updateLockedWords}
                            />
                            </div>

                            <CostAnalysisColumn
                                sku={sku}
                                formData={formData}
                                isReadOnly={isReadOnly}
                                onApplyCost={handleApplyCost}
                                onCurrentCostChange={handleApplyCost}
                                onBreakdownApplied={handleBreakdownApplied}
                                tier={costTier}
                                onTierChange={(tier) => {
                                    setCostTier(tier);
                                    void persistTierSelection('cost_quality_tier', tier);
                                }}
                                breakdownRefreshTrigger={breakdownRefreshTrigger}
                                lockedFields={lockedFields}
                                onToggleFieldLock={toggleFieldLock}
                                cachedSuggestion={cached_cost_suggestion}
                                onSuggestionUpdated={handleCostSuggestionUpdated}
                            />

                            <PriceAnalysisColumn
                                sku={sku}
                                formData={formData}
                                isReadOnly={isReadOnly}
                                onApplyPrice={handleApplyPrice}
                                onCurrentRetailChange={handleApplyPrice}
                                onBreakdownApplied={handleBreakdownApplied}
                                tier={priceTier}
                                onTierChange={(tier) => {
                                    setPriceTier(tier);
                                    void persistTierSelection('price_quality_tier', tier);
                                }}
                                breakdownRefreshTrigger={breakdownRefreshTrigger}
                                lockedFields={lockedFields}
                                onToggleFieldLock={toggleFieldLock}
                                cachedSuggestion={cached_price_suggestion}
                                onSuggestionUpdated={handlePriceSuggestionUpdated}
                            />
                        </div>

                        <MediaAndVariantsSection
                            sku={sku}
                            isAdding={isAdding}
                            mode={mode}
                            isReadOnly={isReadOnly}
                            onStockChange={handleStockChange}
                            formData={formData}
                            onFieldChange={handleFieldChange}
                            lockedFields={lockedFields}
                            onToggleFieldLock={toggleFieldLock}
                        />

                        <div className="bg-white rounded-2xl border border-emerald-200 shadow-sm overflow-hidden">
                            <div className="px-4 py-2.5 bg-emerald-50 border-b border-emerald-200">
                                <h3 className="text-[10px] font-bold text-emerald-800 uppercase tracking-widest flex items-center gap-2">
                                    <span>📢</span> Marketing Intelligence
                                </h3>
                            </div>
                            <div className="p-3 bg-white">
                                <AIMarketingPanel
                                    sku={localSku || sku}
                                    name={formData.name}
                                    description={formData.description}
                                    category={formData.category}
                                    isReadOnly={isReadOnly}
                                    cachedMarketing={cachedMarketingData ?? storedMarketingData}
                                    simpleMarketing={storedSimpleMarketing}
                                />
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
        </>
    );
};
