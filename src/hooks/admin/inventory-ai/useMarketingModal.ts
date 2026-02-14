import { useState, useEffect, useCallback } from 'react';
import { useMarketingManager, MarketingData } from './useMarketingManager.js';
import { useAIGenerationOrchestrator } from '../useAIGenerationOrchestrator.js';
import { generateMarketingSuggestion } from './generateCostSuggestion.js';
import { useAICostEstimateConfirm } from '../useAICostEstimateConfirm.js';

interface UseMarketingModalParams {
    sku: string;
    itemName: string;
    itemDescription: string;
    category: string;
    initialMarketingData?: MarketingData | null;
    onApplyField: (field: string, value: string) => void;
}

export const useMarketingModal = ({
    sku,
    itemName,
    itemDescription,
    category,
    initialMarketingData,
    onApplyField
}: UseMarketingModalParams) => {
    const {
        marketingData,
        isLoading,
        isGenerating,
        error,
        fetchExistingMarketing,
        generateMarketing,
        setMarketingData
    } = useMarketingManager();
    const { generateInfoOnly } = useAIGenerationOrchestrator();
    const { confirmWithEstimate } = useAICostEstimateConfirm();

    // Local editable state
    const [editedTitle, setEditedTitle] = useState('');
    const [editedDescription, setEditedDescription] = useState('');
    const [editedKeywords, setEditedKeywords] = useState<string[]>([]);
    const [editedAudience, setEditedAudience] = useState('');
    const [newKeyword, setNewKeyword] = useState('');
    const [showIntelligence, setShowIntelligence] = useState(false);
    const [applyStatus, setApplyStatus] = useState<{ message: string; success: boolean } | null>(null);
    const [dataSource, setDataSource] = useState<'none' | 'existing' | 'generated'>('none');

    // Seed with any provided initial data
    useEffect(() => {
        if (initialMarketingData) {
            setMarketingData(initialMarketingData);
            setDataSource('existing');
        }
    }, [initialMarketingData, setMarketingData]);

    // Load existing data on mount
    useEffect(() => {
        fetchExistingMarketing(sku);
    }, [sku, fetchExistingMarketing]);

    // Sync local state when marketing data loads
    useEffect(() => {
        if (marketingData) {
            setEditedTitle(marketingData.suggested_title || '');
            setEditedDescription(marketingData.suggested_description || '');
            setEditedKeywords(marketingData.seo_keywords || marketingData.keywords || []);
            setEditedAudience(marketingData.target_audience || '');
            if (dataSource === 'none') {
                setDataSource('existing');
            }
        }
    }, [marketingData, dataSource]);

    const handleGenerateAll = useCallback(async () => {
        const confirmed = await confirmWithEstimate({
            action_key: 'inventory_generate_marketing',
            action_label: 'Generate marketing content with AI',
            operations: [
                { key: 'info_from_images', label: 'Image analysis + item info' },
                { key: 'marketing_generation', label: 'Marketing generation' }
            ],
            mode: 'minimal',
            context: {
                image_count: 1,
                name_length: itemName.length,
                description_length: itemDescription.length,
                category_length: category.length
            },
            confirmText: 'Generate'
        });
        if (!confirmed) return;

        setApplyStatus({ message: 'Generating AI content... This may take 10-30 seconds.', success: true });
        const result = await generateMarketingSuggestion({
            sku,
            name: itemName,
            description: itemDescription,
            category,
            generateInfoOnly,
            fetchMarketingSuggestion: async ({ sku: nextSku, name: nextName, description: nextDescription, category: nextCategory }) =>
                generateMarketing({
                    sku: nextSku,
                    name: nextName,
                    description: nextDescription,
                    category: nextCategory,
                    freshStart: true
                })
        });

        if (result) {
            setDataSource('generated');
            setApplyStatus({ message: '✨ AI generated new marketing content! Review and apply to your item.', success: true });
            setTimeout(() => setApplyStatus(null), 5000);
        } else {
            setApplyStatus({ message: 'Failed to generate content. Check console for details.', success: false });
            setTimeout(() => setApplyStatus(null), 5000);
        }
    }, [category, confirmWithEstimate, generateInfoOnly, generateMarketing, itemDescription, itemName, sku]);

    const handleRefreshFromDb = useCallback(async () => {
        setApplyStatus({ message: 'Refreshing saved marketing data...', success: true });
        await fetchExistingMarketing(sku);
        setDataSource('existing');
        setTimeout(() => setApplyStatus(null), 4000);
    }, [fetchExistingMarketing, sku]);

    const handleApplyTitle = useCallback(() => {
        if (editedTitle) {
            onApplyField('name', editedTitle);
            setApplyStatus({ message: '✓ Title copied to item name field. Remember to save the item!', success: true });
            setTimeout(() => setApplyStatus(null), 4000);
        }
    }, [editedTitle, onApplyField]);

    const handleApplyDescription = useCallback(() => {
        if (editedDescription) {
            onApplyField('description', editedDescription);
            setApplyStatus({ message: '✓ Description copied to item. Remember to save the item!', success: true });
            setTimeout(() => setApplyStatus(null), 4000);
        }
    }, [editedDescription, onApplyField]);

    const handleApplyAll = useCallback(() => {
        let applied: string[] = [];
        if (editedTitle) {
            onApplyField('name', editedTitle);
            applied.push('title');
        }
        if (editedDescription) {
            onApplyField('description', editedDescription);
            applied.push('description');
        }
        if (applied.length > 0) {
            setApplyStatus({
                message: `✓ Applied ${applied.join(' and ')} to item fields. Remember to save the item!`,
                success: true
            });
            setTimeout(() => setApplyStatus(null), 4000);
        }
    }, [editedTitle, editedDescription, onApplyField]);

    const handleAddKeyword = useCallback(() => {
        const keyword = newKeyword.trim();
        if (keyword && !editedKeywords.includes(keyword)) {
            setEditedKeywords(prev => [...prev, keyword]);
            setNewKeyword('');
        }
    }, [newKeyword, editedKeywords]);

    const handleRemoveKeyword = useCallback((keyword: string) => {
        setEditedKeywords(prev => prev.filter(k => k !== keyword));
    }, []);

    const hasContent = !!(editedTitle || editedDescription);

    return {
        marketingData,
        isLoading,
        isGenerating,
        error,
        editedTitle,
        setEditedTitle,
        editedDescription,
        setEditedDescription,
        editedKeywords,
        editedAudience,
        setEditedAudience,
        newKeyword,
        setNewKeyword,
        showIntelligence,
        setShowIntelligence,
        applyStatus,
        setApplyStatus,
        dataSource,
        hasContent,
        handleGenerateAll,
        handleApplyTitle,
        handleApplyDescription,
        handleApplyAll,
        handleAddKeyword,
        handleRemoveKeyword,
        handleRefreshFromDb
    };
};
