import React from 'react';
import { MarketingData } from '../../../../hooks/admin/inventory-ai/useMarketingManager.js';

interface IntelligenceSectionProps {
    marketingData: MarketingData;
    showIntelligence: boolean;
    setShowIntelligence: (show: boolean) => void;
}

export const IntelligenceSection: React.FC<IntelligenceSectionProps> = ({
    marketingData,
    showIntelligence,
    setShowIntelligence
}) => {
    const [openSections, setOpenSections] = React.useState<Record<string, boolean>>({
        overview: true,
        selling: false,
        advantages: false,
        benefits: false,
        channels: false,
        triggers: false,
        seo: false,
        social: false,
        objections: false,
        themes: false,
        pains: false,
        trends: false,
        value: false,
        reasoning: false,
        analysis: false
    });

    const renderListItems = (items: string[] | undefined, label: string) => {
        if (!items || items.length === 0) return null;
        return (
            <div className="space-y-2">
                <h4 className="text-xs font-bold text-gray-500 uppercase tracking-widest">{label}</h4>
                <ul className="space-y-1">
                    {items.map((item, idx) => (
                        <li key={idx} className="text-sm text-gray-700 flex items-start gap-2">
                            <span className="text-[var(--brand-primary)]">•</span>
                            {item}
                        </li>
                    ))}
                </ul>
            </div>
        );
    };

    const setAllSections = (open: boolean) => {
        setOpenSections(Object.keys(openSections).reduce((acc, key) => {
            acc[key] = open;
            return acc;
        }, {} as Record<string, boolean>));
    };

    return (
        <div className="border border-gray-200 rounded-xl overflow-hidden mt-6">
            <button
                onClick={() => setShowIntelligence(!showIntelligence)}
                className="w-full px-4 py-3 bg-gray-50 flex items-center justify-between hover:bg-gray-100 transition-colors"
                type="button"
            >
                <span className="text-sm font-bold text-gray-700 flex items-center gap-2">
                    <span>🧠</span> AI Marketing Intelligence
                    {marketingData.confidence_score && (
                        <span className="text-xs text-gray-500">
                            ({Math.round((marketingData.confidence_score || 0) * 100)}% confidence)
                        </span>
                    )}
                </span>
                <span className="text-gray-400">
                    {showIntelligence ? '▲' : '▼'}
                </span>
            </button>
            {showIntelligence && (
                <div className="p-4 space-y-3 bg-white">
                    <div className="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => setAllSections(true)}
                            className="px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-widest border bg-white text-gray-700 border-gray-200 hover:bg-gray-50"
                        >
                            Expand All
                        </button>
                        <button
                            type="button"
                            onClick={() => setAllSections(false)}
                            className="px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-widest border bg-white text-gray-700 border-gray-200 hover:bg-gray-50"
                        >
                            Collapse All
                        </button>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {Object.keys(openSections).map((key) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => setOpenSections(prev => ({ ...prev, [key]: !prev[key] }))}
                                className={`px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-widest border ${
                                    openSections[key] ? 'bg-[var(--brand-primary)]/10 text-[var(--brand-primary)] border-[var(--brand-primary)]/20' : 'bg-gray-50 text-gray-500 border-gray-200'
                                }`}
                            >
                                {key.replace(/_/g, ' ')}
                            </button>
                        ))}
                    </div>

                    {openSections.overview && (
                        <div className="border border-gray-200 rounded-xl p-4 space-y-3">
                            <h4 className="text-xs font-bold text-gray-500 uppercase tracking-widest">Overview</h4>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {marketingData.search_intent && (
                                    <div className="space-y-1">
                                        <div className="text-[10px] font-bold text-gray-400 uppercase">Search Intent</div>
                                        <div className="text-sm text-gray-700">{marketingData.search_intent}</div>
                                    </div>
                                )}
                                {marketingData.seasonal_relevance && (
                                    <div className="space-y-1">
                                        <div className="text-[10px] font-bold text-gray-400 uppercase">Seasonal Relevance</div>
                                        <div className="text-sm text-gray-700">{marketingData.seasonal_relevance}</div>
                                    </div>
                                )}
                                {marketingData.market_positioning && (
                                    <div className="space-y-1">
                                        <div className="text-[10px] font-bold text-gray-400 uppercase">Market Positioning</div>
                                        <div className="text-sm text-gray-700">{marketingData.market_positioning}</div>
                                    </div>
                                )}
                                {marketingData.pricing_psychology && (
                                    <div className="space-y-1">
                                        <div className="text-[10px] font-bold text-gray-400 uppercase">Pricing Psychology</div>
                                        <div className="text-sm text-gray-700">{marketingData.pricing_psychology}</div>
                                    </div>
                                )}
                                {marketingData.brand_voice && (
                                    <div className="space-y-1">
                                        <div className="text-[10px] font-bold text-gray-400 uppercase">Brand Voice</div>
                                        <div className="text-sm text-gray-700">{marketingData.brand_voice}</div>
                                    </div>
                                )}
                                {marketingData.content_tone && (
                                    <div className="space-y-1">
                                        <div className="text-[10px] font-bold text-gray-400 uppercase">Content Tone</div>
                                        <div className="text-sm text-gray-700">{marketingData.content_tone}</div>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {openSections.selling && (
                        <div className="border border-gray-200 rounded-xl p-4">
                            {renderListItems(marketingData.selling_points, 'Selling Points')}
                        </div>
                    )}
                    {openSections.advantages && (
                        <div className="border border-gray-200 rounded-xl p-4">
                            {renderListItems(marketingData.competitive_advantages, 'Competitive Advantages')}
                        </div>
                    )}
                    {openSections.benefits && (
                        <div className="border border-gray-200 rounded-xl p-4">
                            {renderListItems(marketingData.customer_benefits, 'Customer Benefits')}
                        </div>
                    )}
                    {openSections.channels && (
                        <div className="border border-gray-200 rounded-xl p-4">
                            {renderListItems(marketingData.marketing_channels, 'Marketing Channels')}
                            {renderListItems(marketingData.conversion_triggers, 'Conversion Triggers')}
                            {renderListItems(marketingData.call_to_action_suggestions, 'Calls To Action')}
                        </div>
                    )}
                    {openSections.triggers && (
                        <div className="border border-gray-200 rounded-xl p-4">
                            {renderListItems(marketingData.emotional_triggers, 'Emotional Triggers')}
                            {renderListItems(marketingData.urgency_factors, 'Urgency Factors')}
                        </div>
                    )}
                    {openSections.seo && (
                        <div className="border border-gray-200 rounded-xl p-4">
                            {renderListItems(marketingData.seo_keywords, 'SEO Keywords')}
                        </div>
                    )}
                    {openSections.social && (
                        <div className="border border-gray-200 rounded-xl p-4">
                            {renderListItems(marketingData.social_proof_elements, 'Social Proof')}
                        </div>
                    )}
                    {openSections.objections && (
                        <div className="border border-gray-200 rounded-xl p-4">
                            {renderListItems(marketingData.objection_handlers, 'Objection Handlers')}
                        </div>
                    )}
                    {openSections.themes && (
                        <div className="border border-gray-200 rounded-xl p-4">
                            {renderListItems(marketingData.content_themes, 'Content Themes')}
                        </div>
                    )}
                    {openSections.pains && (
                        <div className="border border-gray-200 rounded-xl p-4">
                            {renderListItems(marketingData.pain_points_addressed, 'Pain Points Addressed')}
                            {renderListItems(marketingData.lifestyle_alignment, 'Lifestyle Alignment')}
                        </div>
                    )}
                    {openSections.trends && (
                        <div className="border border-gray-200 rounded-xl p-4">
                            {renderListItems(marketingData.market_trends, 'Market Trends')}
                        </div>
                    )}
                    {openSections.analysis && marketingData.analysis_factors && (
                        <div className="border border-gray-200 rounded-xl p-4 space-y-2">
                            <h4 className="text-xs font-bold text-gray-500 uppercase tracking-widest">Analysis Factors</h4>
                            {Object.entries(marketingData.analysis_factors).map(([key, value]) => (
                                <div key={key} className="flex items-center justify-between gap-2 text-sm">
                                    <span className="text-gray-600">{key.replace(/_/g, ' ')}</span>
                                    <span className="text-gray-900 font-semibold">
                                        {Array.isArray(value) ? value.join(', ') : typeof value === 'object' ? JSON.stringify(value) : String(value)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                    {openSections.value && (
                        <div className="space-y-4">
                            {marketingData.unique_selling_points && (
                                <div className="intelligence-card p-4 bg-blue-50/50 border border-blue-100 rounded-xl">
                                    <h4 className="text-[10px] font-bold text-blue-600 uppercase tracking-widest mb-2 flex items-center gap-2">
                                        <span>💎</span> Unique Selling Points
                                    </h4>
                                    <p className="text-sm text-gray-700 leading-relaxed font-serif whitespace-pre-wrap italic">
                                        {marketingData.unique_selling_points}
                                    </p>
                                </div>
                            )}
                            {marketingData.value_propositions && (
                                <div className="intelligence-card p-4 bg-purple-50/50 border border-purple-100 rounded-xl">
                                    <h4 className="text-[10px] font-bold text-purple-600 uppercase tracking-widest mb-2 flex items-center gap-2">
                                        <span>🎯</span> Value Propositions
                                    </h4>
                                    <p className="text-sm text-gray-700 leading-relaxed font-serif whitespace-pre-wrap italic">
                                        {marketingData.value_propositions}
                                    </p>
                                </div>
                            )}
                        </div>
                    )}
                    {openSections.reasoning && marketingData.recommendation_reasoning && (
                        <div className="p-3 bg-gray-50 rounded-xl border border-gray-200">
                            <h4 className="text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">
                                AI Reasoning
                            </h4>
                            <p className="text-sm text-gray-600">
                                {marketingData.recommendation_reasoning}
                            </p>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};
