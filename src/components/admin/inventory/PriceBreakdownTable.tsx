import React, { useEffect, useState } from 'react';
import { usePriceBreakdown } from '../../../hooks/admin/usePriceBreakdown.js';
import type { PriceSuggestion } from '../../../hooks/admin/useInventoryAI.js';
import type { IPriceFactor } from '../../../hooks/admin/usePriceBreakdown.js';

interface PriceBreakdownTableProps {
    sku: string;
    name: string;
    description: string;
    category: string;
    isReadOnly?: boolean;
    refreshTrigger?: number;
    currentPrice?: number;
    onCurrentPriceChange?: (price: number) => void;
    tier?: string;
    cachedSuggestion?: PriceSuggestion | null;
}

export const PriceBreakdownTable: React.FC<PriceBreakdownTableProps> = ({
    sku,
    name,
    description,
    category,
    isReadOnly = false,
    refreshTrigger = 0,
    currentPrice,
    onCurrentPriceChange,
    tier = 'standard',
    cachedSuggestion = null
}) => {
    const { breakdown, is_busy: isPriceLoading, error, fetchBreakdown, populateFromSuggestion, applySuggestionLocally, updateFactor } = usePriceBreakdown(sku);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editValue, setEditValue] = useState<string>('');
    const [isEditingCurrent, setIsEditingCurrent] = useState(false);
    const [currentEditValue, setCurrentEditValue] = useState('');

    useEffect(() => {
        if (sku) {
            fetchBreakdown();
        }
    }, [sku, fetchBreakdown, refreshTrigger]);

    const handleStartEdit = (id: number, amount: number) => {
        if (isReadOnly) return;
        setEditingId(id);
        setEditValue(amount.toFixed(2));
    };

    const handleSaveEdit = async () => {
        if (editingId !== null) {
            const amount = parseFloat(editValue);
            if (!isNaN(amount)) {
                await updateFactor(editingId, amount);
            }
            setEditingId(null);
            setEditValue('');
        }
    };

    const handleCancelEdit = () => {
        setEditingId(null);
        setEditValue('');
    };

    const pendingFactors: IPriceFactor[] = (cachedSuggestion?.components || []).map((comp, idx) => ({
        id: -(idx + 1),
        sku,
        label: comp.label || 'AI Component',
        amount: comp.amount || 0,
        type: comp.type || 'ai',
        explanation: comp.explanation || '',
        source: 'ai',
        created_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
    }));

    const displayFactors = breakdown.factors.length > 0 ? breakdown.factors : pendingFactors;
    const isPendingOnly = breakdown.factors.length === 0 && pendingFactors.length > 0;

    if (!sku) return null;

    const currentRetailValue = typeof currentPrice === 'number'
        ? currentPrice
        : (breakdown.totals?.stored ?? 0);

    const startEditCurrent = () => {
        if (isReadOnly || !onCurrentPriceChange) return;
        setIsEditingCurrent(true);
        setCurrentEditValue(currentRetailValue.toFixed(2));
    };

    const commitEditCurrent = () => {
        if (!onCurrentPriceChange) {
            setIsEditingCurrent(false);
            return;
        }
        const value = parseFloat(currentEditValue);
        if (!Number.isNaN(value)) {
            onCurrentPriceChange(value);
        }
        setIsEditingCurrent(false);
        setCurrentEditValue('');
    };

    return (
        <div className="price-breakdown-wrapper">

            {error && <div className="p-2 mb-2 bg-[var(--brand-error)]/5 border border-[var(--brand-error)]/20 text-[var(--brand-error)] text-sm rounded">{error}</div>}

            <div className="ai-data-panel ai-data-panel--inverted mb-2">
                <div className="flex justify-between items-center">
                    <span className="ai-data-label text-sm font-medium text-white/80">Current Retail:</span>
                    {isEditingCurrent ? (
                        <div className="flex items-center gap-1">
                            <span className="text-xs text-white/80">$</span>
                            <input
                                type="number"
                                step="0.01"
                                className="w-24 text-right border border-white/30 bg-white/95 rounded px-2 py-1 text-sm font-bold text-slate-900 focus:ring-1 focus:ring-white/40 focus:border-white/40"
                                value={currentEditValue}
                                onChange={(e) => setCurrentEditValue(e.target.value)}
                                onBlur={commitEditCurrent}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') commitEditCurrent();
                                    if (e.key === 'Escape') {
                                        setIsEditingCurrent(false);
                                        setCurrentEditValue('');
                                    }
                                }}
                                autoFocus
                            />
                        </div>
                    ) : (
                        <span
                            className={`ai-data-value--large text-white ${!isReadOnly && onCurrentPriceChange ? 'cursor-pointer hover:underline' : ''}`}
                            onClick={startEditCurrent}
                            title={!isReadOnly && onCurrentPriceChange ? 'Click to edit current retail price' : undefined}
                        >
                            ${currentRetailValue.toFixed(2)}
                        </span>
                    )}
                </div>
            </div>

            <div className="divide-y divide-gray-200 border border-gray-200 rounded bg-white overflow-hidden">
                <div className="px-3 py-2 space-y-1 min-h-[100px]">
                    {displayFactors.length === 0 ? (
                        <div className="text-center py-8 text-gray-400 italic text-xs">
                            No pricing breakdown components found.
                        </div>
                    ) : (
                        <>
                            {isPendingOnly && (
                                <div className="text-[10px] text-amber-600 italic py-1">
                                    Showing pending AI breakdown. Save item to persist to database.
                                </div>
                            )}
                            {displayFactors.map((f: { id: number; label: string; amount: number; explanation?: string }) => (
                            <div key={f.id} className="flex flex-col py-2 border-b last:border-0">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-gray-800 text-sm font-semibold">{f.label}</span>
                                    {editingId === f.id ? (
                                        <div className="flex items-center gap-1">
                                            <span className="text-xs text-gray-500">$</span>
                                            <input
                                                type="number"
                                                step="0.01"
                                                className="w-20 text-right border border-gray-300 rounded px-2 py-1 text-sm font-bold focus:ring-1 focus:ring-[var(--brand-primary)]/20 focus:border-[var(--brand-primary)]/20"
                                                value={editValue}
                                                onChange={(e) => setEditValue(e.target.value)}
                                                onBlur={handleSaveEdit}
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') handleSaveEdit();
                                                    if (e.key === 'Escape') handleCancelEdit();
                                                }}
                                                autoFocus
                                                disabled={isPriceLoading}
                                            />
                                        </div>
                                    ) : (
                                        <span
                                            className={`text-sm font-bold text-gray-900 ${!isReadOnly ? 'cursor-pointer hover:text-[var(--brand-primary)] hover:underline' : ''}`}
                                            onClick={() => handleStartEdit(f.id, f.amount)}
                                            title={!isReadOnly ? 'Click to edit' : undefined}
                                        >
                                            ${f.amount.toFixed(2)}
                                        </span>
                                    )}
                                </div>
                                {f.explanation && (
                                    <p className="text-[10px] text-gray-500 mt-1 leading-relaxed">
                                        {f.explanation}
                                    </p>
                                )}
                            </div>
                            ))}
                        </>
                    )}
                </div>
            </div>
        </div>
    );
};
