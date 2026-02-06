import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { ICostBreakdown, ICostItem } from '../../types/index.js';
import { CostSuggestion } from './useInventoryAI.js';

type CostBreakdownResponse = ICostBreakdown;

type CostActionResponse = {
    success: boolean;
    message?: string;
    error?: string;
};

type SuggestionBreakdown = Partial<Record<'materials' | 'labor' | 'energy' | 'equipment', unknown>>;

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const parseNumber = (value: unknown): number | null => {
    if (typeof value === 'number') return value;
    if (typeof value === 'string') {
        const parsed = parseFloat(value);
        return Number.isNaN(parsed) ? null : parsed;
    }
    return null;
};

const normalizeCategoryItems = (category: string, items: ICostItem[]): ICostItem[] =>
    items.map((item, idx) => {
        const existingId = item.id ?? idx;
        const prefixedId = String(existingId).startsWith(category) ? existingId : `${category}-${existingId}`;

        // Map database field names to 'label':
        // - 'name' is used for materials table
        // - 'description' is used for labor/energy/equipment tables
        const rawItem = item as unknown as Record<string, unknown>;
        const label = (rawItem.label as string)
            || (rawItem.name as string)
            || (rawItem.description as string)
            || '';

        return {
            ...item,
            id: prefixedId,
            label
        };
    });

export const useCostBreakdown = (sku: string) => {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [breakdown, setBreakdown] = useState<ICostBreakdown>({
        materials: [],
        labor: [],
        energy: [],
        equipment: [],
        totals: {
            materials: 0,
            labor: 0,
            energy: 0,
            equipment: 0,
            total: 0
        }
    });
    const [confidence, setConfidence] = useState<number | null>(null);
    const [appliedAt, setAppliedAt] = useState<string | null>(null);
    const [is_busy, setIsBusy] = useState(false);


    const fetchBreakdown = useCallback(async () => {
        if (!sku) return;
        setIsLoading(true);
        setError(null);
        try {
            // JsonResponseParser unwraps the 'data' field, so 'res' is directly the ICostBreakdown
            const res = await ApiClient.get<CostBreakdownResponse>('/api/get_cost_breakdown.php', { sku });
            if (res) {
                const processed: ICostBreakdown = {
                    ...res,
                    materials: normalizeCategoryItems('materials', res.materials || []),
                    labor: normalizeCategoryItems('labor', res.labor || []),
                    energy: normalizeCategoryItems('energy', res.energy || []),
                    equipment: normalizeCategoryItems('equipment', res.equipment || [])
                };
                setBreakdown(processed);
                setConfidence(res.totals?.ai_confidence !== undefined ? Number(res.totals.ai_confidence) : null);
                setAppliedAt(res.totals?.ai_at ?? null);
                return processed;
            } else {
                throw new Error('No data returned for cost breakdown');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchBreakdown failed', err);
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, [sku]);

    const saveCostFactor = useCallback(async (category: string, cost: number, label: string, details?: unknown) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<CostActionResponse>('/api/save_cost_factor.php', {
                sku,
                category,
                cost,
                label,
                details
            });
            if (res?.success) {
                await fetchBreakdown();
                return true;
            } else {
                throw new Error('Failed to save cost factor');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveCostFactor failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [sku, fetchBreakdown]);

    const deleteCostFactor = useCallback(async (category: string, id: number | string) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<CostActionResponse>('/api/delete_cost_factor.php', {
                sku,
                category,
                id
            });
            if (res?.success) {
                await fetchBreakdown();
                return true;
            } else {
                throw new Error(res?.error || res?.message || 'Failed to delete cost factor');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('deleteCostFactor failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [sku, fetchBreakdown]);

    const updateCostFactor = useCallback(async (category: string, id: number | string, cost: number, newLabel: string) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<CostActionResponse>('/api/save_cost_factor.php', {
                sku,
                category,
                cost,
                label: newLabel,
                id
            });
            if (res?.success) {
                await fetchBreakdown();
                return true;
            } else {
                throw new Error('Failed to update cost factor');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('updateCostFactor failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [sku, fetchBreakdown]);

    const clearBreakdown = useCallback(async () => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<CostActionResponse>('/api/clear_cost_breakdown.php', { sku });
            if (res?.success) {
                await fetchBreakdown();
                return true;
            } else {
                throw new Error(res?.error || res?.message || 'Failed to clear breakdown');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('clearBreakdown failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [sku, fetchBreakdown]);

    const [pendingSuggestion, setPendingSuggestion] = useState<CostSuggestion | null>(null);

    const applySuggestionLocally = useCallback((suggestion: CostSuggestion) => {
        setPendingSuggestion(suggestion);

        const suggestionBreakdown = (suggestion.breakdown || {}) as SuggestionBreakdown;

        // Map suggestion to ICostBreakdown format - handle both arrays and single numbers
        const mapCategory = (data: unknown, catName: string): ICostItem[] => {
            if (Array.isArray(data)) {
                return data.map((it, idx) => {
                    const label = isRecord(it) && typeof it.label === 'string'
                        ? it.label
                        : isRecord(it) && typeof it.name === 'string'
                            ? it.name
                            : `AI Estimated ${catName}`;
                    const costValue = isRecord(it) ? parseNumber(it.cost) : null;
                    return {
                        id: `temp-${catName}-${idx}-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
                        sku,
                        label,
                        cost: costValue ?? 0
                    };
                });
            }

            const cost = parseNumber(data);
            if (!cost || cost === 0) return [];
            return [{
                id: `temp-${catName}-single-${Date.now()}`,
                sku,
                label: `AI Estimated ${catName} Total`,
                cost
            }];
        };

        const materials = mapCategory(suggestionBreakdown.materials, 'materials');
        const labor = mapCategory(suggestionBreakdown.labor, 'labor');
        const energy = mapCategory(suggestionBreakdown.energy, 'energy');
        const equipment = mapCategory(suggestionBreakdown.equipment, 'equipment');

        const totals = {
            materials: materials.reduce((sum, it) => sum + it.cost, 0),
            labor: labor.reduce((sum, it) => sum + it.cost, 0),
            energy: energy.reduce((sum, it) => sum + it.cost, 0),
            equipment: equipment.reduce((sum, it) => sum + it.cost, 0),
            total: 0
        };
        totals.total = totals.materials + totals.labor + totals.energy + totals.equipment;

        setBreakdown({
            materials: materials as ICostItem[],
            labor: labor as ICostItem[],
            energy: energy as ICostItem[],
            equipment: equipment as ICostItem[],
            totals: {
                ...totals,
                stored: breakdown.totals.stored // Preserve the stored value from DB
            }
        });
    }, [sku, breakdown.totals.stored]);

    const populateFromSuggestion = useCallback(async (suggestion?: CostSuggestion) => {
        const suggestionToUse = suggestion || pendingSuggestion;
        if (!suggestionToUse) return false;

        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; message?: string; error?: string }>('/api/populate_cost_from_ai.php', {
                sku,
                suggestion: suggestionToUse
            });
            if (res?.success) {
                setPendingSuggestion(null);
                return await fetchBreakdown();
            } else {
                throw new Error(res?.error || res?.message || 'Failed to populate from AI');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('populateFromSuggestion failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [sku, fetchBreakdown, pendingSuggestion]);

    const isDirty = Math.abs((breakdown.totals.total || 0) - (breakdown.totals.stored || 0)) > 0.001 || !!pendingSuggestion;

    return {
        breakdown,
        isLoading: is_busy,
        error,
        fetchBreakdown,
        saveCostFactor,
        updateCostFactor,
        deleteCostFactor,
        clearBreakdown,
        populateFromSuggestion,
        applySuggestionLocally,
        hasPendingSuggestion: !!pendingSuggestion,
        isDirty,
        confidence,
        appliedAt
    };
};
