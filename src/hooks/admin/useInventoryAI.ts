import { usePriceSuggestions, PriceSuggestion, PriceComponent } from './inventory-ai/usePriceSuggestions.js';
import { useCostSuggestions, CostSuggestion } from './inventory-ai/useCostSuggestions.js';
import { useMarketingGeneration } from './inventory-ai/useMarketingGeneration.js';
import { AiManager } from '../../core/ai/AiManager.js';

export type { PriceSuggestion, PriceComponent, CostSuggestion };

export const useInventoryAI = () => {
    const {
        is_busy: priceBusy,
        cached_price_suggestion,
        setCachedPriceSuggestion,
        fetch_price_suggestion,
        retier_price_suggestion
    } = usePriceSuggestions();

    const {
        is_busy: costBusy,
        cached_cost_suggestion,
        setCachedCostSuggestion,
        fetch_cost_suggestion,
        retier_cost_suggestion
    } = useCostSuggestions();

    const {
        is_busy: marketingBusy,
        generateMarketing
    } = useMarketingGeneration();

    const is_busy = priceBusy || costBusy || marketingBusy;

    const fetch_all_suggestions = async (params: {
        sku: string;
        name: string;
        description: string;
        category: string;
        cost_price: string | number;
        tier: string;
        imageData?: string;
    }) => {
        try {
            const data = await AiManager.getCombinedSuggestions({
                ...params,
                quality_tier: params.tier || 'standard',
                useImages: true
            });

            if (data && data.success) {
                // Return the raw data so the component can update its form fields
                return data;
            }
            return null;
        } catch (err) {
            console.error('fetch_all_suggestions failed', err);
            return null;
        }
    };

    return {
        is_busy,
        cached_price_suggestion,
        cached_cost_suggestion,
        fetch_price_suggestion,
        fetch_cost_suggestion,
        fetch_all_suggestions,
        generateMarketing,
        retier_price_suggestion,
        retier_cost_suggestion,
        setCachedPriceSuggestion,
        setCachedCostSuggestion
    };
};
