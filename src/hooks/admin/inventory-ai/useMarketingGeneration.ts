import { useState, useCallback } from 'react';
import { AiManager } from '../../../core/ai/AiManager.js';
import logger from '../../../core/logger.js';

export const useMarketingGeneration = () => {
    const [is_busy, setIsBusy] = useState(false);

    const generateMarketing = useCallback(async (params: {
        sku: string;
        name: string;
        description: string;
        category: string;
        brandVoice?: string;
        contentTone?: string;
    }) => {
        setIsBusy(true);
        try {
            const res = await AiManager.getMarketingIntelligence({
                sku: params.sku,
                name: params.name,
                description: params.description,
                category: params.category,
                brandVoice: params.brandVoice,
                contentTone: params.contentTone
            });
            return res;
        } catch (err) {
            logger.error('generateMarketing failed', err);
            return null;
        } finally {
            setIsBusy(false);
        }
    }, []);

    return { is_busy, generateMarketing };
};
