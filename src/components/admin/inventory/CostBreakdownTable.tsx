import React, { useEffect, useMemo } from 'react';
import { useCostBreakdown } from '../../../hooks/admin/useCostBreakdown.js';
import { ICostBreakdown, ICostItem } from '../../../types/index.js';

import { COST_CATEGORY } from '../../../core/constants.js';

interface CostBreakdownTableProps {
    sku: string;
    name: string;
    description: string;
    category: string;
    isReadOnly?: boolean;
    refreshTrigger?: number;
    currentPrice?: number;
    onCurrentPriceChange?: (price: number) => void;
    tier?: string;
    /** Optional raw breakdown from AI generation (simple key-value pairs) */
    cachedBreakdown?: Record<string, unknown> | null;
    /**
     * Signals whether the breakdown total differs from the stored item cost.
     * Used by the parent modal to show a header save button and/or sync prices.
     */
    onDirtyStateChange?: (state: { isDirty: boolean; total: number; stored: number }) => void;
}

export const CostBreakdownTable: React.FC<CostBreakdownTableProps> = ({
    sku,
    name,
    description,
    category,
    isReadOnly = false,
    refreshTrigger = 0,
    currentPrice,
    onCurrentPriceChange,
    tier = 'standard',
    cachedBreakdown,
    onDirtyStateChange
}) => {
    const {
        breakdown: hookBreakdown,
        isLoading,
        error,
        fetchBreakdown,
        saveCostFactor,
        updateCostFactor,
        clearBreakdown,
        populateFromSuggestion,
        applySuggestionLocally
    } = useCostBreakdown(sku);

    useEffect(() => {
        if (sku) {
            fetchBreakdown();
        }
    }, [sku, fetchBreakdown, refreshTrigger]);

    const hasStoredBreakdown = useMemo(() => {
        const count =
            (hookBreakdown.materials?.length || 0)
            + (hookBreakdown.labor?.length || 0)
            + (hookBreakdown.energy?.length || 0)
            + (hookBreakdown.equipment?.length || 0);
        return count > 0;
    }, [hookBreakdown.energy, hookBreakdown.equipment, hookBreakdown.labor, hookBreakdown.materials]);

    // Convert simple key-value breakdown to ICostBreakdown if provided.
    // IMPORTANT: Only use this "pending AI" view when there is no stored breakdown yet.
    // This mirrors PriceBreakdownTable behavior and prevents edits from "not saving" due to the UI
    // continually re-rendering the cached suggestion instead of the DB-backed breakdown.
    const pendingBreakdown: ICostBreakdown | null = useMemo(() => {
        if (!cachedBreakdown || Object.keys(cachedBreakdown).length === 0) return null;

        const createItem = (cat: string, raw: unknown): ICostItem[] => {
            if (typeof raw === 'number' && raw !== 0) {
                return [{
                    id: `ai-${cat}-${Date.now()}`,
                    sku,
                    label: `AI Estimated ${cat}`,
                    cost: raw
                }];
            }
            if (Array.isArray(raw)) {
                return raw
                    .map((item, idx) => {
                        if (!item || typeof item !== 'object') return null;
                        const itemRecord = item as Record<string, unknown>;
                        const itemCost = typeof itemRecord.cost === 'number' ? itemRecord.cost : 0;
                        if (!itemCost) return null;
                        return {
                            id: `ai-${cat}-${Date.now()}-${idx}`,
                            sku,
                            label: typeof itemRecord.label === 'string'
                                ? itemRecord.label
                                : `AI Estimated ${cat} ${idx + 1}`,
                            cost: itemCost
                        } as ICostItem;
                    })
                    .filter((item): item is ICostItem => item !== null);
            }
            return [];
        };

        const materials = createItem('materials', cachedBreakdown.materials);
        const labor = createItem('labor', cachedBreakdown.labor);
        const energy = createItem('energy', cachedBreakdown.energy);
        const equipment = createItem('equipment', cachedBreakdown.equipment);

        const materialsTotal = materials.reduce((sum, it) => sum + it.cost, 0);
        const laborTotal = labor.reduce((sum, it) => sum + it.cost, 0);
        const energyTotal = energy.reduce((sum, it) => sum + it.cost, 0);
        const equipmentTotal = equipment.reduce((sum, it) => sum + it.cost, 0);

        return {
            materials,
            labor,
            energy,
            equipment,
            totals: {
                materials: materialsTotal,
                labor: laborTotal,
                energy: energyTotal,
                equipment: equipmentTotal,
                total: materialsTotal + laborTotal + energyTotal + equipmentTotal,
                stored: hookBreakdown.totals?.stored || 0
            }
        };
    }, [cachedBreakdown, hookBreakdown.totals?.stored, sku]);

    const isPendingOnly = !hasStoredBreakdown && !!pendingBreakdown;
    const breakdown: ICostBreakdown = (hasStoredBreakdown || !pendingBreakdown)
        ? hookBreakdown
        : pendingBreakdown;

    useEffect(() => {
        if (!onDirtyStateChange) return;
        const total = Number(breakdown.totals?.total || 0);
        const stored = Number(breakdown.totals?.stored || 0);
        const isDirtyFromTotals = Math.abs(total - stored) > 0.001;
        const hasCached = !!(cachedBreakdown && Object.keys(cachedBreakdown).length > 0);
        onDirtyStateChange({ isDirty: isDirtyFromTotals || hasCached, total, stored });
    }, [onDirtyStateChange, breakdown.totals?.total, breakdown.totals?.stored, cachedBreakdown]);

    const normalizeIdForUpdate = (rawId?: string): string | null => {
        const id = String(rawId || '').trim();
        if (!id) return null;

        // DB-backed ids are normalized in useCostBreakdown to `${category}-${id}`; accept both.
        if (/^\d+$/.test(id)) return id;
        const m = id.match(/^(materials|labor|energy|equipment)-(\d+)$/);
        if (m?.[2]) return m[2];
        return null; // Avoid accidentally updating some random row for AI temp ids.
    };

    const handleFactorChange = (category: string, value: string, existingId?: string, existingLabel?: string) => {
        const cost = parseFloat(value);
        if (Number.isNaN(cost)) return;

        const label = String(existingLabel || category).trim();
        const normalizedId = normalizeIdForUpdate(existingId);

        if (normalizedId) {
            void updateCostFactor(category, normalizedId, cost, label);
            return;
        }

        void saveCostFactor(category, cost, label);
    };

    const categories = [
        { id: COST_CATEGORY.MATERIALS, label: 'Materials', description: 'Raw materials and supplies used in production' },
        { id: COST_CATEGORY.LABOR, label: 'Labor', description: 'Direct labor costs for manufacturing' },
        { id: COST_CATEGORY.ENERGY, label: 'Energy', description: 'Electricity, gas, and utilities for production' },
        { id: COST_CATEGORY.EQUIPMENT, label: 'Equipment', description: 'Equipment depreciation and maintenance' }
    ];

    const [editingCategory, setEditingCategory] = React.useState<string | null>(null);
    const [editValue, setEditValue] = React.useState<string>('');
    const [isEditingCurrent, setIsEditingCurrent] = React.useState(false);
    const [currentEditValue, setCurrentEditValue] = React.useState('');

    const startEditCategory = (category: string, amount: number) => {
        if (isReadOnly) return;
        setEditingCategory(category);
        setEditValue(amount.toFixed(2));
    };

    const commitEditCategory = (category: string, existingId?: string, existingLabel?: string) => {
        const amount = parseFloat(editValue);
        if (!isNaN(amount)) {
            handleFactorChange(category, String(amount), existingId, existingLabel);
        }
        setEditingCategory(null);
        setEditValue('');
    };

    if (!sku) return null;

    const currentCostValue = typeof currentPrice === 'number'
        ? currentPrice
        : (breakdown.totals?.stored ?? 0);

    const startEditCurrent = () => {
        if (isReadOnly || !onCurrentPriceChange) return;
        setIsEditingCurrent(true);
        setCurrentEditValue(currentCostValue.toFixed(2));
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
        <div className="cost-breakdown-wrapper">

            {error && <div className="p-2 mb-2 bg-[var(--brand-error)]/5 border border-[var(--brand-error)]/20 text-[var(--brand-error)] text-sm rounded">{error}</div>}

            <div id="costSuggestionDisplay" className="ai-data-panel mb-2">
                <div className="flex justify-between items-center">
                    <span className="ai-data-label text-sm font-medium">Current Cost:</span>
                    {isEditingCurrent ? (
                        <div className="flex items-center gap-1">
                            <span className="text-xs text-gray-500">$</span>
                            <input
                                type="number"
                                step="0.01"
                                className="w-24 text-right border border-gray-300 rounded px-2 py-1 text-sm font-bold focus:ring-1 focus:ring-[var(--brand-primary)]/20 focus:border-[var(--brand-primary)]/20"
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
                            className={`ai-data-value--large ${!isReadOnly && onCurrentPriceChange ? 'cursor-pointer hover:underline' : ''}`}
                            onClick={startEditCurrent}
                            title={!isReadOnly && onCurrentPriceChange ? 'Click to edit current cost' : undefined}
                        >
                            ${currentCostValue.toFixed(2)}
                        </span>
                    )}
                </div>
            </div>

            <div className="divide-y divide-gray-200 border border-gray-200 rounded bg-white overflow-hidden">
                <div className="px-3 py-2 space-y-1 min-h-[100px]">
                    {isPendingOnly && (
                        <div className="text-[10px] text-amber-600 italic py-1">
                            Showing pending AI breakdown. Save item to persist to database.
                        </div>
                    )}
                    {categories.map((cat) => {
                        const itemsArray = breakdown[cat.id as keyof ICostBreakdown];
                        // Handle both array format and totals object
                        const items = Array.isArray(itemsArray) ? itemsArray : [];
                        const firstItem = items[0] as ICostItem | undefined;
                        const firstId = firstItem?.id?.toString();
                        const firstLabel = firstItem?.label?.toString() || '';
                        const displayValue = firstItem?.cost ? firstItem.cost.toFixed(2) : '';

                        return (
                            <div key={cat.id} className="flex flex-col py-2 border-b last:border-0">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-gray-800 text-sm font-semibold">{cat.label}</span>
                                    <div className="flex items-center gap-2">
                                        {editingCategory === cat.id ? (
                                            <>
                                                <span className="text-xs text-gray-500">$</span>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    className="w-20 text-right border border-gray-300 rounded px-2 py-1 text-sm font-bold focus:ring-1 focus:ring-[var(--brand-primary)]/20 focus:border-[var(--brand-primary)]/20"
                                                    value={editValue}
                                                    onChange={(e) => setEditValue(e.target.value)}
                                                    onBlur={() => commitEditCategory(cat.id, firstId, firstLabel)}
                                                    onKeyDown={(e) => {
                                                        if (e.key === 'Enter') commitEditCategory(cat.id, firstId, firstLabel);
                                                        if (e.key === 'Escape') {
                                                            setEditingCategory(null);
                                                            setEditValue('');
                                                        }
                                                    }}
                                                    autoFocus
                                                    disabled={isLoading}
                                                />
                                            </>
                                        ) : (
                                            <span
                                                className={`text-sm font-bold text-gray-900 ${!isReadOnly ? 'cursor-pointer hover:text-[var(--brand-primary)] hover:underline' : ''}`}
                                                onClick={() => startEditCategory(cat.id, Number(displayValue || 0))}
                                                title={!isReadOnly ? 'Click to edit' : undefined}
                                            >
                                                ${Number(displayValue || 0).toFixed(2)}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <p className="text-[10px] text-gray-500 mt-1 leading-relaxed">
                                    {cat.description}
                                </p>
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
};
