import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import { useInventoryAI } from './useInventoryAI.js';
import logger from '../../core/logger.js';
import type { IInventoryItemMinimal, IInventoryResponse } from '../../types/inventory.js';

// Re-export for backward compatibility
export type { IInventoryItemMinimal, IInventoryResponse } from '../../types/inventory.js';



export const useAIContentGenerator = () => {
    const [items, setItems] = useState<IInventoryItemMinimal[]>([]);
    const [isLoadingItems, setIsLoadingItems] = useState(false);
    const [generatedContent, setGeneratedContent] = useState<{ title: string; description: string; keywords: string[] } | null>(null);
    const [status, setStatus] = useState<{ message: string; isError?: boolean } | null>(null);

    const { is_busy: isGenerating, generateMarketing } = useInventoryAI();

    const fetchItems = useCallback(async () => {
        setIsLoadingItems(true);
        try {
            const res = await ApiClient.get<IInventoryResponse | IInventoryItemMinimal[]>('/api/inventory.php');
            const data = Array.isArray(res) ? res : (res && Array.isArray(res.data) ? res.data : []);
            setItems(data.map(it => ({
                sku: it.sku || '',
                name: it.name || '',
                description: it.description || '',
                category: it.category || '',
                retail_price: Number(it.retail_price) || 0,
                cost_price: Number(it.cost_price) || 0
            })));
        } catch (err) {
            console.error('[AIContentGenerator] failed to load items', err);
            logger.error('[AIContentGenerator] failed to load items', err);
        } finally {
            setIsLoadingItems(false);
        }
    }, []);

    useEffect(() => {
        fetchItems();
    }, [fetchItems]);

    const generate = useCallback(async (params: {
        sku: string;
        name: string;
        description: string;
        category: string;
        brandVoice?: string;
        contentTone?: string;
    }) => {
        setStatus({ message: 'Generating content...' });
        setGeneratedContent(null);

        try {
            const res = await generateMarketing({
                sku: params.sku,
                name: params.name,
                description: params.description,
                category: params.category,
                brandVoice: params.brandVoice,
                contentTone: params.contentTone
            });

            if (res) {
                let keywords: string[] = [];
                if (Array.isArray(res.seo_keywords)) keywords = res.seo_keywords;
                else if (typeof res.seo_keywords === 'string') { try { keywords = JSON.parse(res.seo_keywords); } catch { /* JSON parse failed */ } }
                else if (Array.isArray(res.keywords)) keywords = res.keywords as string[];

                setGeneratedContent({
                    title: (res.title || res.suggested_title || '').trim(),
                    description: (res.description || res.suggested_description || '').trim(),
                    keywords
                });
                setStatus({ message: 'Generated. Review and apply changes.' });
            } else {
                setStatus({ message: 'Generation failed.', isError: true });
            }
        } catch (err) {
            setStatus({ message: 'Error during generation.', isError: true });
        }
    }, [generateMarketing]);

    const applyField = async (sku: string, field: 'name' | 'description', value: string) => {
        try {
            const res = await ApiClient.post<{ success: boolean }>('/api/update_inventory.php', { sku, field, value });
            if (res && res.success) {
                setStatus({ message: `${field === 'name' ? 'Title' : 'Description'} applied successfully!` });
                return true;
            }
        } catch (err) {
            setStatus({ message: `Failed to apply ${field}.`, isError: true });
        }
        return false;
    };

    return {
        items,
        isLoadingItems,
        isGenerating,
        generatedContent,
        status,
        generate,
        applyField,
        setStatus
    };
};
