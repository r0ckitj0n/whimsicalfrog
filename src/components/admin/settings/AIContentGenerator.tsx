import React, { useState, useEffect } from 'react';
import { useAIContentGenerator, IInventoryItemMinimal } from '../../../hooks/admin/useAIContentGenerator.js';

export const AIContentGenerator: React.FC = () => {
    const {
        items,
        isLoadingItems,
        isGenerating,
        generatedContent,
        generate,
        applyField,
    } = useAIContentGenerator();

    const [selectedSku, setSelectedSku] = useState('');
    const [currentItem, setCurrentItem] = useState<IInventoryItemMinimal | null>(null);
    const [voice, setVoice] = useState('');
    const [tone, setTone] = useState('');
    const [customDesc, setCustomDesc] = useState('');

    useEffect(() => {
        const item = items.find(it => it.sku === selectedSku) || null;
        setCurrentItem(item);
        if (item && !customDesc) {
            setCustomDesc(item.description);
        }
    }, [selectedSku, items]);

    const handleGenerate = () => {
        if (!currentItem) return;
        generate({
            sku: currentItem.sku,
            name: currentItem.name,
            description: customDesc || currentItem.description,
            category: currentItem.category,
            brandVoice: voice,
            contentTone: tone
        });
    };

    const handleApply = async (field: 'name' | 'description') => {
        if (!currentItem || !generatedContent) return;
        const value = field === 'name' ? generatedContent.title : generatedContent.description;
        const success = await applyField(currentItem.sku, field, value);
        if (success && window.WFToast) {
            window.WFToast.success(`${field.charAt(0).toUpperCase() + field.slice(1)} applied successfully`);
        } else if (!success && window.WFToast) {
            window.WFToast.error(`Failed to apply ${field}`);
        }
    };

    if (isLoadingItems && items.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center p-12 text-gray-400 gap-4">
                <span className="wf-emoji-loader text-4xl">🤖</span>
                <p className="italic">Awakening AI generation engine...</p>
            </div>
        );
    }
    return (
        <div className="bg-white border rounded-xl shadow-sm overflow-hidden flex flex-col min-h-[600px]">
            <div className="px-6 py-4 border-b bg-gray-50 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <h2 className="text-lg font-bold text-gray-800">AI Content Generator</h2>
                </div>
            </div>

            <div className="p-6 space-y-6 flex-1 overflow-y-auto">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-4">
                        <div>
                            <label className="text-xs font-black text-gray-400 uppercase tracking-widest mb-2 block">1. Select Item</label>
                            <select
                                value={selectedSku}
                                onChange={(e) => setSelectedSku(e.target.value)}
                                className="form-input w-full"
                                disabled={isLoadingItems || isGenerating}
                            >
                                <option value="">Choose an item to enhance...</option>
                                {items.map(it => (
                                    <option key={it.sku} value={it.sku}>{it.sku} — {it.name}</option>
                                ))}
                            </select>
                        </div>

                        {currentItem && (
                            <div className="space-y-4 animate-in fade-in slide-in-from-top-2">
                                <div>
                                    <label className="text-xs font-bold text-gray-500 uppercase block mb-1">Context / Description (Optional)</label>
                                    <textarea
                                        value={customDesc}
                                        onChange={(e) => setCustomDesc(e.target.value)}
                                        placeholder="Add any extra details or override the current description..."
                                        className="form-input w-full h-32 text-sm"
                                        disabled={isGenerating}
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="text-xs font-bold text-gray-500 uppercase block mb-1">Brand Voice</label>
                                        <select
                                            value={voice}
                                            onChange={(e) => setVoice(e.target.value)}
                                            className="form-input w-full text-sm"
                                            disabled={isGenerating}
                                        >
                                            <option value="">Auto-detect</option>
                                            <option value="friendly">Friendly</option>
                                            <option value="professional">Professional</option>
                                            <option value="playful">Playful</option>
                                            <option value="luxurious">Luxurious</option>
                                            <option value="casual">Casual</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="text-xs font-bold text-gray-500 uppercase block mb-1">Content Tone</label>
                                        <select
                                            value={tone}
                                            onChange={(e) => setTone(e.target.value)}
                                            className="form-input w-full text-sm"
                                            disabled={isGenerating}
                                        >
                                            <option value="">Auto-detect</option>
                                            <option value="energetic">Energetic</option>
                                            <option value="informative">Informative</option>
                                            <option value="urgent">Urgent</option>
                                            <option value="funny">Funny</option>
                                        </select>
                                    </div>
                                </div>

                                <button
                                    onClick={handleGenerate}
                                    disabled={isGenerating}
                                    className="btn btn-primary w-full py-3 bg-transparent border-0 text-[var(--brand-primary)] hover:bg-[var(--brand-primary)]/5 transition-all font-bold uppercase tracking-widest text-sm"
                                >
                                    {isGenerating ? 'Drafting Excellence...' : 'Generate AI Content'}
                                </button>
                            </div>
                        )}
                    </div>

                    <div className="space-y-4">
                        <label className="text-xs font-black text-gray-400 uppercase tracking-widest mb-2 block">2. Review & Apply</label>
                        {generatedContent ? (
                            <div className="bg-[var(--brand-primary)]/5 border border-[var(--brand-primary)]/10 rounded-xl p-5 space-y-6 animate-in fade-in zoom-in-95">
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2 text-[var(--brand-primary)] font-bold text-sm">
                                            Suggested Title
                                        </div>
                                        <button
                                            onClick={() => handleApply('name')}
                                            className="btn btn-secondary px-3 py-1 bg-transparent border-0 text-[var(--brand-primary)] hover:bg-[var(--brand-primary)]/5 transition-all text-xs font-bold"
                                            data-help-id="settings-ai-apply-title"
                                        >
                                            Apply Title
                                        </button>
                                    </div>
                                    <div className="bg-white p-3 border border-[var(--brand-primary)]/10 rounded-lg text-sm font-medium">
                                        {generatedContent.title}
                                    </div>
                                </div>

                                <div className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2 text-[var(--brand-primary)] font-bold text-sm">
                                            Enhanced Description
                                        </div>
                                        <button
                                            onClick={() => handleApply('description')}
                                            className="btn btn-secondary px-3 py-1 bg-transparent border-0 text-[var(--brand-primary)] hover:bg-[var(--brand-primary)]/5 transition-all text-xs font-bold"
                                            data-help-id="settings-ai-apply-description"
                                        >
                                            Apply Description
                                        </button>
                                    </div>
                                    <div className="bg-white p-3 border border-[var(--brand-primary)]/10 rounded-lg text-sm leading-relaxed whitespace-pre-wrap min-h-[120px]">
                                        {generatedContent.description}
                                    </div>
                                </div>

                                {generatedContent.keywords.length > 0 && (
                                    <div className="space-y-2">
                                        <div className="text-[10px] font-black text-[var(--brand-primary)]/60 uppercase tracking-widest">Target Keywords</div>
                                        <div className="flex flex-wrap gap-1.5">
                                            {generatedContent.keywords.map((k, i) => (
                                                <span key={i} className="px-2 py-0.5 bg-[var(--brand-primary)]/10 text-[var(--brand-primary)] text-[10px] font-bold rounded-full border border-[var(--brand-primary)]/20 uppercase">
                                                    {k}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <button
                                    onClick={async () => {
                                        await handleApply('name');
                                        await handleApply('description');
                                    }}
                                    className="btn btn-primary w-full py-2 bg-transparent border-0 text-[var(--brand-primary)] hover:bg-[var(--brand-primary)]/5 transition-all flex items-center justify-center gap-2 font-bold uppercase tracking-widest text-sm"
                                >
                                    Apply All Changes
                                </button>
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center py-24 border-2 border-dashed border-gray-100 rounded-xl text-gray-400 space-y-3">
                                <p className="text-sm italic">Generated content will appear here</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div >
    );
};
