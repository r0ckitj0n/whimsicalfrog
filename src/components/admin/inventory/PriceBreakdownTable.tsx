import React, { useEffect, useState } from 'react';
import { usePriceBreakdown } from '../../../hooks/admin/usePriceBreakdown.js';
import { useInventoryAI } from '../../../hooks/admin/useInventoryAI.js';

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
    tier = 'standard'
}) => {
    const { is_busy, fetch_price_suggestion } = useInventoryAI();
    const { breakdown, is_busy: isPriceLoading, error, fetchBreakdown, populateFromSuggestion, applySuggestionLocally, updateFactor } = usePriceBreakdown(sku);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editValue, setEditValue] = useState<string>('');
    const [isEditingCurrent, setIsEditingCurrent] = useState(false);
    const [currentEditValue, setCurrentEditValue] = useState<string>('');

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

    const startEditCurrent = () => {
        if (isReadOnly || currentPrice === undefined || onCurrentPriceChange === undefined) return;
        setIsEditingCurrent(true);
        const safeCurrent = Number.isFinite(currentPrice) ? currentPrice : (breakdown.totals?.stored ?? 0);
        setCurrentEditValue(safeCurrent.toFixed(2));
    };

    const commitEditCurrent = () => {
        const amount = parseFloat(currentEditValue);
        if (!isNaN(amount) && onCurrentPriceChange) {
            onCurrentPriceChange(amount);
        }
        setIsEditingCurrent(false);
        setCurrentEditValue('');
    };

    if (!sku) return null;

    return (
        <div className="price-breakdown-wrapper">

            {error && <div className="p-2 mb-2 bg-[var(--brand-error)]/5 border border-[var(--brand-error)]/20 text-[var(--brand-error)] text-sm rounded">{error}</div>}

            <div className={`ai-data-panel ai-data-panel--inverted mb-2 ${currentPrice !== undefined && Math.abs(currentPrice - (breakdown.totals?.stored || 0)) > 0.01 ? 'border-l-4 border-yellow-400' : ''}`}>
                <div className="flex justify-between items-center">
                    <span className="ai-data-label text-sm font-medium text-white/80">
                        {currentPrice !== undefined ? 'Current Retail:' : 'Stored Retail:'}
                    </span>
                    {isEditingCurrent ? (
                        <div className="flex items-center gap-1">
                            <span className="text-xs text-gray-500">$</span>
                            <input
                                type="number"
                                step="0.01"
                                className="w-24 text-right border border-gray-300 rounded px-2 py-1 text-sm font-bold focus:ring-1 focus:ring-[var(--brand-primary)]/20 focus:border-[var(--brand-primary)]/20 bg-white text-gray-900"
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
                                disabled={isReadOnly}
                            />
                        </div>
                    ) : (
                        <span
                            className={`ai-data-value--large text-white ${!isReadOnly && currentPrice !== undefined ? 'cursor-pointer hover:underline' : ''}`}
                            onClick={startEditCurrent}
                            title={!isReadOnly && currentPrice !== undefined ? 'Click to edit' : undefined}
                        >
                            ${(currentPrice !== undefined ? currentPrice : (breakdown.totals?.stored ?? 0)).toFixed(2)}
                        </span>
                    )}
                </div>
                {currentPrice !== undefined && Math.abs(currentPrice - (breakdown.totals?.stored || 0)) > 0.01 && (
                    <div className="text-[10px] text-yellow-200/60 mt-1 italic">
                        Unsaved: DB reflects ${(breakdown.totals?.stored || 0).toFixed(2)}
                    </div>
                )}
            </div>

            <div className="divide-y divide-gray-200 border border-gray-200 rounded bg-white overflow-hidden">
                <div className="px-3 py-2 space-y-1 min-h-[100px]">
                    {breakdown.factors.length === 0 ? (
                        <div className="text-center py-8 text-gray-400 italic text-xs">
                            No pricing breakdown components found.
                        </div>
                    ) : (
                        breakdown.factors.map((f) => (
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
                        ))
                    )}
                </div>
            </div>
        </div>
    );
};
