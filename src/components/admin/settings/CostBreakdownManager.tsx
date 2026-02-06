import React, { useEffect, useState, useCallback, useRef } from 'react';
import { createPortal } from 'react-dom';
import { useCostBreakdown } from '../../../hooks/admin/useCostBreakdown.js';
import { useInventoryAI } from '../../../hooks/admin/useInventoryAI.js';
import { useAIContentGenerator } from '../../../hooks/admin/useAIContentGenerator.js';
import { ApiClient } from '../../../core/ApiClient.js';
import { COST_CATEGORY, CostCategory } from '../../../core/constants.js';
import { CostSummary } from './cost-breakdown/CostSummary.js';
import { FactorList } from './cost-breakdown/FactorList.js';

interface CostBreakdownManagerProps {
    sku?: string;
    onClose?: () => void;
    title?: string;
}

export const CostBreakdownManager: React.FC<CostBreakdownManagerProps> = ({ sku: propSku, onClose, title }) => {
    const [selectedSku, setSelectedSku] = useState(propSku || '');
    const [hasUserChanges, setHasUserChanges] = useState(false);

    const {
        breakdown,
        isLoading,
        error,
        fetchBreakdown,
        saveCostFactor,
        updateCostFactor,
        deleteCostFactor,
        clearBreakdown,
        populateFromSuggestion,
        applySuggestionLocally,
        hasPendingSuggestion
    } = useCostBreakdown(selectedSku);

    const { items, isLoadingItems } = useAIContentGenerator();
    const { fetch_cost_suggestion, is_busy: isGenerating } = useInventoryAI();
    const [activeCategory, setActiveCategory] = useState<CostCategory>(COST_CATEGORY.MATERIALS);

    useEffect(() => {
        if (propSku) setSelectedSku(propSku);
    }, [propSku]);

    // Reset dirty state when SKU changes
    useEffect(() => {
        setHasUserChanges(false);
    }, [selectedSku]);

    const hasFetchedRef = useRef<string | null>(null);

    useEffect(() => {
        // Only fetch when SKU changes and we haven't already fetched for this SKU
        if (selectedSku && hasFetchedRef.current !== selectedSku) {
            hasFetchedRef.current = selectedSku;
            fetchBreakdown();
        }
    }, [selectedSku, fetchBreakdown]);

    const handleGenerateAI = async () => {
        if (!selectedSku) return;

        const currentItem = items.find(it => it.sku === selectedSku);
        if (!currentItem) {
            if (window.WFToast) window.WFToast.error('Could not find details for selected item');
            return;
        }

        try {
            const suggestion = await fetch_cost_suggestion({
                sku: currentItem.sku,
                name: currentItem.name,
                description: currentItem.description || '',
                category: currentItem.category || ''
            });
            if (suggestion) {
                applySuggestionLocally(suggestion);
                setHasUserChanges(true);
                if (window.WFToast) window.WFToast.success('AI suggestion generated (unsaved preview)');
            }
        } catch (err) {
            if (window.WFToast) window.WFToast.error('Failed to generate AI suggestion');
        }
    };

    const handleAddFactor = async (category: string) => {
        const label = window.prompt(`Enter ${category} description:`);
        if (!label) return;
        const costStr = window.prompt('Enter cost amount:', '0.00');
        if (costStr === null) return;
        const cost = parseFloat(costStr);
        if (isNaN(cost)) return;

        const success = await saveCostFactor(category, cost, label);
        if (success) {
            setHasUserChanges(true);
            if (window.WFToast) window.WFToast.success('Factor added');
        }
    };

    const handleSaveAll = useCallback(async () => {
        try {
            let latestTotal = breakdown.totals.total;

            if (hasPendingSuggestion) {
                const refreshed = await populateFromSuggestion();
                if (refreshed) {
                    latestTotal = refreshed.totals.total;
                }
            }

            await ApiClient.post('/api/database_tables.php?action=update_cell', {
                table: 'items',
                column: 'cost_price',
                new_value: latestTotal,
                row_data: { sku: selectedSku }
            });

            await fetchBreakdown();
            setHasUserChanges(false);
            if (window.WFToast) window.WFToast.success('All changes saved to inventory');
        } catch (err) {
            if (window.WFToast) window.WFToast.error('Failed to update inventory cost');
        }
    }, [selectedSku, hasPendingSuggestion, populateFromSuggestion, fetchBreakdown, breakdown.totals.total]);

    const categories = [
        { id: COST_CATEGORY.MATERIALS, label: 'Materials', emoji: '🧪' },
        { id: COST_CATEGORY.LABOR, label: 'Labor', emoji: '👥' },
        { id: COST_CATEGORY.ENERGY, label: 'Energy & overhead', emoji: '⚡' },
        { id: COST_CATEGORY.EQUIPMENT, label: 'Equipment', emoji: '⚙️' }
    ];

    const currentFactors = breakdown[activeCategory] || [];

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
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1200px] max-w-[95vw] h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">💰</span> {title || 'Cost Suggestions'}
                    </h2>

                    <div className="flex-1 flex justify-center px-4">
                        <div className="relative inline-flex items-center w-full max-w-md">
                            <select
                                value={selectedSku}
                                onChange={(e) => setSelectedSku(e.target.value)}
                                className="w-full text-xs font-bold p-2.5 px-4 pr-10 border-2 border-slate-50 rounded-xl bg-slate-50 text-slate-600 outline-none focus:border-brand-primary/30 transition-all cursor-pointer appearance-none truncate"
                                disabled={isLoadingItems || isGenerating}
                            >
                                <option value="">Select Item...</option>
                                {items.map(it => (
                                    <option key={it.sku} value={it.sku}>{it.sku} — {it.name}</option>
                                ))}
                            </select>
                            <div className="absolute right-4 pointer-events-none text-slate-400">
                                <span className="text-[10px]">▼</span>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <button
                            onClick={handleGenerateAI}
                            disabled={isGenerating || isLoading || !selectedSku}
                            className="btn-text-secondary flex items-center gap-2"
                            data-help-id="ai-generate-cost"
                        >
                            {isGenerating ? 'Analyzing...' : 'Generate All'}
                        </button>

                        <div className="h-8 border-l border-gray-100 mx-2" />

                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={handleSaveAll}
                                disabled={isLoading || !selectedSku || !hasUserChanges}
                                className={`admin-action-btn btn-icon--save ${hasUserChanges ? 'is-dirty' : ''}`}
                                data-help-id="common-save"
                            />
                            <button
                                onClick={onClose}
                                className="admin-action-btn btn-icon--close"
                                data-help-id="common-close"
                            />
                        </div>
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-hidden p-0 flex flex-col">
                    {isLoading && !breakdown.totals.total ? (
                        <div className="flex-1 flex flex-col items-center justify-center p-12 text-gray-500 gap-4">
                            <span className="text-4xl animate-bounce">💰</span>
                            <p className="font-bold text-xs uppercase tracking-widest text-slate-400">Calculating cost structures...</p>
                        </div>
                    ) : (
                        <>
                            <CostSummary totals={breakdown.totals} />

                            <div className="flex-1 flex overflow-hidden">
                                {/* Side Tabs */}
                                <div className="w-64 bg-slate-50 border-r border-slate-100 flex flex-col p-4 space-y-1">
                                    <label className="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-4 ml-1">Analysis Views</label>
                                    {categories.map((cat) => {
                                        const isActive = activeCategory === cat.id;
                                        return (
                                            <button
                                                key={cat.id}
                                                onClick={() => setActiveCategory(cat.id)}
                                                className={`flex items-center gap-3 px-4 py-3 rounded-2xl text-[13px] font-black uppercase tracking-tight transition-all duration-300 ${isActive
                                                    ? 'bg-white text-brand-primary shadow-sm border border-brand-primary/10'
                                                    : 'text-slate-500 hover:bg-white/50'
                                                    }`}
                                                type="button"
                                            >
                                                <span className="text-base grayscale-[0.5]">{cat.emoji}</span>
                                                {cat.label}
                                            </button>
                                        );
                                    })}

                                    <div className="mt-auto p-6 rounded-3xl bg-slate-900 text-white shadow-xl shadow-slate-200">
                                        <div className="text-[9px] font-black uppercase tracking-widest opacity-40 mb-1">Unit Cost</div>
                                        <div className="text-3xl font-black tracking-tighter">${(breakdown.totals.total || 0).toFixed(2)}</div>
                                    </div>
                                </div>

                                {/* Content Area */}
                                <div className="flex-1 p-10 overflow-y-auto scrollbar-hide">
                                    {!selectedSku ? (
                                        <div className="flex flex-col items-center justify-center h-full text-slate-300 gap-4">
                                            <span className="text-6xl grayscale">🔍</span>
                                            <p className="font-bold text-xs uppercase tracking-widest">Select an item to begin analysis</p>
                                        </div>
                                    ) : (
                                        <div className="animate-in fade-in slide-in-from-bottom-2 duration-500">
                                            <div className="flex justify-between items-center mb-10">
                                                <h3 className="text-2xl font-black text-slate-800 uppercase tracking-tight">{activeCategory} Mapping</h3>
                                                <div className="flex gap-2">
                                                    <button
                                                        onClick={() => fetchBreakdown()}
                                                        className="admin-action-btn btn-icon--refresh"
                                                        type="button"
                                                        data-help-id="common-refresh"
                                                    />
                                                    <button
                                                        onClick={() => handleAddFactor(activeCategory)}
                                                        className="btn bg-brand-primary hover:bg-brand-secondary text-white px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-brand-primary/20 transition-all flex items-center gap-2"
                                                        type="button"
                                                        data-help-id="cost-add-factor"
                                                    >
                                                        Add New Factor
                                                    </button>
                                                </div>
                                            </div>

                                            <FactorList
                                                category={activeCategory}
                                                factors={currentFactors}
                                                onDelete={deleteCostFactor}
                                                onUpdate={updateCostFactor}
                                                onAdd={handleAddFactor}
                                                onRefresh={fetchBreakdown}
                                            />
                                        </div>
                                    )}
                                </div>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
