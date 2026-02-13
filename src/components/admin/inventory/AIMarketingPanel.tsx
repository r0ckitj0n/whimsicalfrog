import React from 'react';
import { MarketingData } from '../../../hooks/admin/inventory-ai/useMarketingManager.js';

interface AIMarketingPanelProps {
    sku: string;
    name: string;
    description: string;
    category: string;
    isReadOnly?: boolean;
    /** Optional cached marketing data from orchestrated generation */
    cachedMarketing?: MarketingData | null;
    /** Simple marketing data format from useAISuggestions */
    simpleMarketing?: {
        targetAudience?: string;
        sellingPoints?: string[];
        marketingChannels?: string[];
    } | null;
    onGenerate?: () => void;
    isGenerating?: boolean;
}

export const AIMarketingPanel: React.FC<AIMarketingPanelProps> = ({
    isReadOnly = false,
    cachedMarketing,
    simpleMarketing,
    onGenerate,
    isGenerating = false
}) => {
    const normalizeList = (value: unknown): string[] => {
        if (Array.isArray(value)) return value.map((v) => String(v)).filter(Boolean);
        if (typeof value === 'string') {
            const trimmed = value.trim();
            if (!trimmed) return [];
            return trimmed
                .split(/[,\n]/)
                .map((part) => part.trim())
                .filter(Boolean);
        }
        return [];
    };

    // Merge simple marketing into full marketing data format
    const marketing: MarketingData = cachedMarketing || {
        selling_points: simpleMarketing?.sellingPoints || [],
        target_audience: simpleMarketing?.targetAudience || '',
        competitive_advantages: [],
        customer_benefits: [],
        call_to_action_suggestions: [],
        urgency_factors: [],
        emotional_triggers: [],
        marketing_channels: [],
        conversion_triggers: [],
        demographic_targeting: '',
        psychographic_profile: '',
        search_intent: '',
        seasonal_relevance: '',
        seo_keywords: [],
        unique_selling_points: '',
        value_propositions: '',
        recommendation_reasoning: '',
        confidence_score: 0
    };

    const suggestedTitle = typeof marketing.suggested_title === 'string' ? marketing.suggested_title : '';
    const suggestedDescription = typeof marketing.suggested_description === 'string' ? marketing.suggested_description : '';
    const keywords = normalizeList(marketing.keywords);
    const sellingPoints = normalizeList(marketing.selling_points);
    const marketingChannels = normalizeList(marketing.marketing_channels);
    const seoKeywords = normalizeList(marketing.seo_keywords);
    const competitiveAdvantages = normalizeList(marketing.competitive_advantages);
    const customerBenefits = normalizeList(marketing.customer_benefits);
    const emotionalTriggers = normalizeList(marketing.emotional_triggers);
    const urgencyFactors = normalizeList(marketing.urgency_factors);
    const callsToAction = normalizeList(marketing.call_to_action_suggestions);
    const conversionTriggers = normalizeList(marketing.conversion_triggers);
    const uniqueSellingPoints = normalizeList(marketing.unique_selling_points);
    const valuePropositions = normalizeList(marketing.value_propositions);

    const hasData = !!suggestedTitle ||
        !!suggestedDescription ||
        keywords.length > 0 ||
        sellingPoints.length > 0 ||
        competitiveAdvantages.length > 0 ||
        customerBenefits.length > 0 ||
        callsToAction.length > 0 ||
        urgencyFactors.length > 0 ||
        emotionalTriggers.length > 0 ||
        marketingChannels.length > 0 ||
        conversionTriggers.length > 0 ||
        seoKeywords.length > 0 ||
        marketing.target_audience ||
        marketing.demographic_targeting ||
        marketing.psychographic_profile ||
        marketing.search_intent ||
        marketing.seasonal_relevance ||
        uniqueSellingPoints.length > 0 ||
        valuePropositions.length > 0;

    const renderMetricRow = (rowKey: string, label: string, value?: string | number, helper?: string) => {
        if (value === undefined || value === null || value === '') return null;
        return (
            <div key={rowKey} className="flex flex-col py-2 border-b last:border-0">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-gray-800 text-sm font-semibold">{label}</span>
                    <span className="text-sm font-bold text-gray-900 text-right">{String(value)}</span>
                </div>
                {helper && (
                    <p className="text-[10px] text-gray-500 mt-1 leading-relaxed">
                        {helper}
                    </p>
                )}
            </div>
        );
    };

    const renderListRow = (rowKey: string, label: string, items?: string[]) => {
        if (!items || items.length === 0) return null;
        return renderMetricRow(rowKey, label, items.join(', '));
    };

    const firstOrEmpty = (items?: string[]) => (items && items.length > 0 ? items[0] : '');

    const metrics = [
        renderMetricRow('suggested-title', 'Suggested Title', suggestedTitle),
        renderMetricRow('suggested-description', 'Suggested Description', suggestedDescription),
        renderListRow('keywords', 'Keywords', keywords),
        renderMetricRow('target-audience', 'Target Audience', marketing.target_audience),
        renderMetricRow('top-selling-point', 'Top Selling Point', firstOrEmpty(sellingPoints)),
        renderMetricRow('top-marketing-channel', 'Top Marketing Channel', firstOrEmpty(marketingChannels)),
        renderMetricRow('top-seo-keyword', 'Top SEO Keyword', firstOrEmpty(seoKeywords)),
        renderListRow('selling-points', 'Selling Points', sellingPoints),
        renderListRow('marketing-channels', 'Marketing Channels', marketingChannels),
        renderListRow('seo-keywords', 'SEO Keywords', seoKeywords),
        renderListRow('competitive-advantages', 'Competitive Advantages', competitiveAdvantages),
        renderListRow('customer-benefits', 'Customer Benefits', customerBenefits),
        renderListRow('emotional-triggers', 'Emotional Triggers', emotionalTriggers),
        renderListRow('urgency-factors', 'Urgency Factors', urgencyFactors),
        renderListRow('calls-to-action', 'Calls To Action', callsToAction),
        renderListRow('conversion-triggers', 'Conversion Triggers', conversionTriggers),
        renderMetricRow('search-intent', 'Search Intent', marketing.search_intent),
        renderMetricRow('seasonal-relevance', 'Seasonal Relevance', marketing.seasonal_relevance),
        renderMetricRow('demographic-target', 'Demographic Target', marketing.demographic_targeting),
        renderMetricRow('psychographic-profile', 'Psychographic Profile', marketing.psychographic_profile),
        renderListRow('unique-selling-points', 'Unique Selling Points', uniqueSellingPoints),
        renderListRow('value-propositions', 'Value Proposition', valuePropositions),
        renderMetricRow('confidence', 'Confidence', `${Math.round((marketing.confidence_score || 0) * 100)}%`),
        marketing.recommendation_reasoning ? (
            <div className="flex flex-col py-2" key="ai-reasoning">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-gray-800 text-sm font-semibold">AI Reasoning</span>
                </div>
                <p className="text-[10px] text-gray-500 mt-1 leading-relaxed">
                    {marketing.recommendation_reasoning}
                </p>
            </div>
        ) : null
    ].filter((node): node is React.ReactElement => Boolean(node));

    const midpoint = Math.ceil(metrics.length / 2);
    const leftMetrics = metrics.slice(0, midpoint);
    const rightMetrics = metrics.slice(midpoint);

    return (
        <div className="ai-marketing-panel">
            {/* Generate button - removed header since parent provides it */}
            {!isReadOnly && onGenerate && (
                <div className="mb-3">
                    <button
                        type="button"
                        className="btn btn-primary text-sm py-1.5 px-4 w-full"
                        onClick={onGenerate}
                        disabled={isGenerating}
                        data-help-id="inventory-ai-generate-marketing"
                    >
                        {isGenerating ? 'Generating...' : 'Generate'}
                    </button>
                </div>
            )}

            {hasData ? (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div className="border border-gray-200 rounded bg-white overflow-hidden">
                        <div className="px-3 py-2 space-y-1">
                            {leftMetrics}
                        </div>
                    </div>
                    <div className="border border-gray-200 rounded bg-white overflow-hidden">
                        <div className="px-3 py-2 space-y-1">
                            {rightMetrics}
                        </div>
                    </div>
                </div>
            ) : (
                <div className="flex items-center justify-center py-6 text-gray-400">
                    <p className="text-xs italic text-center">No marketing data available.<br />Click Generate All to populate.</p>
                </div>
            )}
        </div>
    );
};

export default AIMarketingPanel;
