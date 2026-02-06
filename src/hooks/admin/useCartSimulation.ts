import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IShopperProfile, IRecommendation, ISimulationResult } from '../../types/commerce.js';

// Re-export for backward compatibility
export type { IShopperProfile, IRecommendation, ISimulationResult } from '../../types/commerce.js';

export const useCartSimulation = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<ISimulationResult | null>(null);

    const runSimulation = useCallback(async (profile: IShopperProfile) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<{ success: boolean; data?: ISimulationResult }>('/api/cart_upsell_simulation.php', {
                limit: 4,
                profile
            });

            // Handle both flattened (from JsonResponseParser) and nested responses
            const resultData = (res as any)?.data || res;

            if (resultData && (resultData.recommendations || resultData.profile)) {
                const finalResult = resultData as ISimulationResult;
                setResult(finalResult);
                return finalResult;
            }
            throw new Error('Empty response from simulation');
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('runSimulation failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, []);

    return {
        isLoading,
        error,
        result,
        runSimulation,
        setResult
    };
};
