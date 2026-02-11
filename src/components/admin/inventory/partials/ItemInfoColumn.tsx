import React, { useState } from 'react';
import { ItemSelector } from '../../settings/ai-suggestions/ItemSelector.js';
import { useAIImageEdit } from '../../../../hooks/admin/useAIImageEdit.js';

interface ItemInfoColumnProps {
    sku: string;
    localSku: string;
    mode: 'edit' | 'view' | 'add' | '';
    isReadOnly: boolean;
    isAdding: boolean;
    formData: {
        name: string;
        category: string;
        status: string;
        stock_level: number;
        reorder_point: number;
        cost_price: number;
        retail_price: number;
        description: string;
        weight_oz: number;
        package_length_in: number;
        package_width_in: number;
        package_height_in: number;
    };
    categories: string[];
    onLocalSkuChange: (val: string) => void;
    onFieldChange: (field: string, value: string | number) => void;
    onGenerateSku: () => Promise<void>;
    onRegenerateSku?: () => Promise<void>;
    onGenerateInfoAndMarketing: () => Promise<void>;
    isBusy: boolean;
    primaryImage: string;
    /** Fields that are locked from AI overwrites */
    lockedFields?: Record<string, boolean>;
    /** Locked words for AI generation */
    lockedWords?: Record<string, string>;
    /** Toggle lock status for a field */
    onToggleFieldLock?: (field: string) => void;
    /** Update locked words for a field */
    onLockedWordsChange?: (field: string, value: string) => void;
    onImageTweakSaved?: () => Promise<void> | void;
}

export const ItemInfoColumn: React.FC<ItemInfoColumnProps> = ({
    sku,
    localSku,
    mode,
    isReadOnly,
    isAdding,
    formData,
    categories,
    onLocalSkuChange,
    onFieldChange,
    onGenerateSku,
    onRegenerateSku,
    onGenerateInfoAndMarketing,
    isBusy,
    primaryImage,
    lockedFields = {},
    lockedWords = {},
    onToggleFieldLock,
    onLockedWordsChange,
    onImageTweakSaved
}) => {
    const [isPreviewOpen, setIsPreviewOpen] = useState(false);
    const [imageTweakPrompt, setImageTweakPrompt] = useState('');
    const { isSubmitting: isSubmittingImageTweak, submitImageEdit } = useAIImageEdit();

    const handleSubmitImageTweak = async () => {
        const instructions = imageTweakPrompt.trim();
        if (!instructions) {
            window.WFToast?.error?.('Enter tweak instructions first');
            return;
        }
        if (!sku) {
            window.WFToast?.error?.('A valid SKU is required to save edited item images');
            return;
        }

        try {
            await submitImageEdit({
                target_type: 'item',
                source_image_url: primaryImage,
                instructions,
                item_sku: sku
            });
            window.WFToast?.success?.('AI-edited image saved as an additional item image');
            setImageTweakPrompt('');
            if (onImageTweakSaved) {
                await onImageTweakSaved();
            }
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to submit image tweak';
            if (message === 'AI image edit canceled') return;
            window.WFToast?.error?.(message);
        }
    };

    return (
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div className="px-4 py-2.5 bg-slate-100/80 border-b border-slate-200">
                <h3 className="text-[10px] font-bold text-slate-700 uppercase tracking-widest flex items-center gap-2">
                    <span>📦</span> Item Information
                </h3>
            </div>

            <div className="px-4 py-3 border-b border-slate-200 bg-slate-50/50">
                <div className="relative group">
                    <img
                        src={primaryImage}
                        alt={formData.name || sku}
                        className="w-full h-40 object-cover rounded-xl border border-slate-200 shadow-sm"
                        onError={(e) => {
                            (e.target as HTMLImageElement).src = '/images/placeholder.webp';
                        }}
                    />
                    <div className="absolute inset-0 rounded-xl bg-black/35 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <button
                            type="button"
                            className="admin-action-btn btn-icon--view"
                            data-help-id="common-view"
                            aria-label="View larger image"
                            onClick={() => setIsPreviewOpen(true)}
                        />
                    </div>
                </div>
            </div>

            {!isReadOnly && (
                <div className="px-4 py-3 border-b border-slate-200 bg-white">
                    <button
                        onClick={onGenerateInfoAndMarketing}
                        disabled={isBusy}
                        className="btn btn-primary text-xs py-2 px-3 w-full flex items-center justify-center gap-2"
                        data-help-id="inventory-info-generate-below-image"
                    >
                        <span className={isBusy ? 'animate-spin' : ''}>
                            {isBusy ? '⌛' : '🪄'}
                        </span>
                        <span>{isBusy ? 'Generating...' : 'Generate'}</span>
                    </button>
                </div>
            )}

            <div className="p-3 bg-white space-y-4">
                <div>
                    <label className="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">SKU *</label>
                    <div className="flex gap-2">
                        <input
                            type="text"
                            value={localSku}
                            onChange={(e) => isAdding && onLocalSkuChange(e.target.value)}
                            disabled={!isAdding}
                            className={`form-input w-full py-2 ${!isAdding ? 'bg-slate-50 text-slate-600' : ''}`}
                            placeholder="e.g. WF-TN-001"
                        />
                        {isAdding && (
                            <button
                                onClick={onGenerateSku}
                                className="px-3 py-2 bg-slate-100 hover:bg-slate-200 rounded-lg text-sm font-medium transition-colors"
                                data-help-id="inventory-sku-generate"
                            >
                                ✨
                            </button>
                        )}
                        {!isAdding && !isReadOnly && onRegenerateSku && (
                            <button
                                onClick={onRegenerateSku}
                                className="px-3 py-2 bg-amber-100 hover:bg-amber-200 rounded-lg text-sm font-medium transition-colors"
                                data-help-id="inventory-sku-regenerate"
                                title="Regenerate SKU and update related records"
                            >
                                ♻
                            </button>
                        )}
                    </div>
                </div>

                {!isAdding ? (
                    <div className="border border-slate-200 rounded bg-white overflow-hidden">
                        <ItemSelector
                            items={[]}
                            selectedSku={localSku || sku}
                            onSelectedSkuChange={() => { /* no-op in item modal */ }}
                            isLoadingItems={false}
                            isLoadingSuggestions={isBusy}
                            currentItem={{
                                sku: localSku || sku,
                                name: formData.name,
                                description: formData.description,
                                category: formData.category,
                                cost_price: formData.cost_price,
                                retail_price: formData.retail_price
                            }}
                            nameValue={formData.name}
                            descriptionValue={formData.description}
                            categoryValue={formData.category}
                            categoryOptions={categories}
                            onNameChange={(value) => onFieldChange('name', value)}
                            onDescriptionChange={(value) => onFieldChange('description', value)}
                            onCategoryChange={(value) => onFieldChange('category', value)}
                            lockedFields={lockedFields}
                            onToggleFieldLock={onToggleFieldLock}
                            lockedWords={lockedWords}
                            onLockedWordsChange={onLockedWordsChange}
                            isReadOnly={isReadOnly}
                            onGenerate={onGenerateInfoAndMarketing}
                        />
                    </div>
                ) : (
                    <>
                        <div>
                            <label className="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1 flex items-center gap-2">
                                Name *
                            </label>
                            <input
                                type="text"
                                value={formData.name}
                                onChange={(e) => onFieldChange('name', e.target.value)}
                                disabled={isReadOnly}
                                className="form-input w-full py-2"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1 block">Category *</label>
                                <select
                                    value={formData.category}
                                    onChange={(e) => onFieldChange('category', e.target.value)}
                                    disabled={isReadOnly}
                                    className="form-select w-full py-2"
                                >
                                    <option value="">Select Category</option>
                                    {categories.map(cat => (
                                        <option key={cat} value={cat}>{cat}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">Status *</label>
                                <select
                                    value={formData.status}
                                    onChange={(e) => onFieldChange('status', e.target.value)}
                                    disabled={isReadOnly}
                                    className="form-select w-full py-2"
                                >
                                    <option value="live">Live (Public)</option>
                                    <option value="draft">Draft (Hidden)</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1 flex items-center gap-2">
                                Description
                            </label>
                            <textarea
                                value={formData.description}
                                onChange={(e) => onFieldChange('description', e.target.value)}
                                disabled={isReadOnly}
                                rows={4}
                                className="form-textarea w-full py-2 resize-none"
                                placeholder="Enter product description..."
                            />
                        </div>
                    </>
                )}

                {!isAdding && (
                    <div>
                        <label className="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">Status *</label>
                        <select
                            value={formData.status}
                            onChange={(e) => onFieldChange('status', e.target.value)}
                            disabled={isReadOnly}
                            className="form-select w-full py-2"
                        >
                            <option value="live">Live (Public)</option>
                            <option value="draft">Draft (Hidden)</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                )}

                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">Stock Level *</label>
                        <input
                            type="number"
                            value={formData.stock_level}
                            onChange={(e) => onFieldChange('stock_level', parseInt(e.target.value) || 0)}
                            disabled={isReadOnly}
                            className="form-input w-full py-2"
                        />
                    </div>
                    <div>
                        <label className="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">Reorder Point *</label>
                        <input
                            type="number"
                            value={formData.reorder_point}
                            onChange={(e) => onFieldChange('reorder_point', parseInt(e.target.value) || 0)}
                            disabled={isReadOnly}
                            className="form-input w-full py-2"
                        />
                    </div>
                </div>

                <p className="text-[10px] text-slate-400 mt-2">
                    Run AI processes to generate data for Item Information, Cost Analysis, Price Analysis, and Marketing fields.
                </p>
            </div>

            {isPreviewOpen && (
                <div
                    className="fixed inset-0 z-[var(--wf-z-topmost)] flex items-center justify-center p-4 bg-black/80"
                    onClick={() => setIsPreviewOpen(false)}
                    role="presentation"
                >
                    <div
                        className="viewer-content relative bg-black/90 p-4 rounded-xl shadow-2xl max-w-4xl w-full"
                        onClick={(e) => e.stopPropagation()}
                        role="presentation"
                    >
                        <div className="flex items-center gap-3 p-3 border-b">
                            <h3 className="text-sm font-medium text-white truncate shrink-0 max-w-[180px]">{formData.name || sku}</h3>
                            <input
                                type="text"
                                value={imageTweakPrompt}
                                onChange={(e) => setImageTweakPrompt(e.target.value)}
                                placeholder="Tweak your image"
                                className="flex-1 min-w-0 text-sm p-2 border border-slate-300 rounded-lg bg-white"
                                disabled={isSubmittingImageTweak}
                            />
                            <button
                                type="button"
                                className="btn btn-primary px-3 py-2 text-[10px] font-black uppercase tracking-widest disabled:opacity-60"
                                onClick={() => void handleSubmitImageTweak()}
                                disabled={isSubmittingImageTweak}
                            >
                                {isSubmittingImageTweak ? 'Submitting...' : 'Submit to AI'}
                            </button>
                            <button
                                onClick={() => setIsPreviewOpen(false)}
                                className="admin-action-btn btn-icon--close"
                                type="button"
                                data-help-id="common-close"
                            />
                        </div>
                        <div className="p-4 flex items-center justify-center bg-gray-100 min-h-[300px]">
                            <img
                                src={primaryImage}
                                alt={formData.name || sku}
                                className="max-h-[70vh] object-contain"
                                loading="lazy"
                                onError={(e) => {
                                    (e.target as HTMLImageElement).src = '/images/placeholder.webp';
                                }}
                            />
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};
