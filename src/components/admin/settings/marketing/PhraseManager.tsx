import React, { useMemo, useState } from 'react';

interface PhraseItem {
    id: number;
    text: string;
}

interface PhraseManagerProps {
    cartTexts: PhraseItem[];
    encouragements: PhraseItem[];
    isLoading: boolean;
    isMarketingLoading: boolean;
    addCartText: (text: string) => Promise<{ success: boolean; error?: string }>;
    updateCartText: (id: number, text: string) => Promise<{ success: boolean; error?: string }>;
    deleteCartText: (id: number) => Promise<{ success: boolean; error?: string }>;
    addEncouragement: (text: string) => Promise<{ success: boolean; error?: string }>;
    updateEncouragement: (id: number, text: string) => Promise<{ success: boolean; error?: string }>;
    deleteEncouragement: (id: number) => Promise<{ success: boolean; error?: string }>;
    confirmModal: (config: {
        title: string;
        message: string;
        confirmText?: string;
        confirmStyle?: 'confirm' | 'danger' | 'secondary';
        iconKey?: string;
    }) => Promise<boolean>;
}

export const PhraseManager: React.FC<PhraseManagerProps> = ({
    cartTexts,
    encouragements,
    isLoading,
    isMarketingLoading,
    addCartText,
    updateCartText,
    deleteCartText,
    addEncouragement,
    updateEncouragement,
    deleteEncouragement,
    confirmModal
}) => {
    const [newCartText, setNewCartText] = useState('');
    const [newEncouragement, setNewEncouragement] = useState('');
    const [inlineEditingId, setInlineEditingId] = useState<string | null>(null);
    const [tempValue, setTempValue] = useState('');
    const alphaCollator = useMemo(
        () => new Intl.Collator(undefined, { sensitivity: 'base', ignorePunctuation: true }),
        []
    );
    const normalizeForSort = (value: string) =>
        value
            .trim()
            .replace(/^[^a-z0-9]+/i, '');
    const sortPhraseItems = (a: PhraseItem, b: PhraseItem) => {
        const textCompare = alphaCollator.compare(
            normalizeForSort(a.text),
            normalizeForSort(b.text)
        );
        if (textCompare !== 0) return textCompare;
        return a.id - b.id;
    };

    const sortedCartTexts = useMemo(
        () => [...cartTexts].sort(sortPhraseItems),
        [cartTexts]
    );
    const sortedEncouragements = useMemo(
        () => [...encouragements].sort(sortPhraseItems),
        [encouragements]
    );

    const handleAddCartText = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!newCartText.trim()) return;
        const res = await addCartText(newCartText.trim());
        if (res?.success) {
            setNewCartText('');
            if (window.WFToast) window.WFToast.success('Cart text added');
        } else {
            if (window.WFToast) window.WFToast.error('Failed to add cart text');
        }
    };

    const handleAddEncouragement = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!newEncouragement.trim()) return;
        const res = await addEncouragement(newEncouragement.trim());
        if (res?.success) {
            setNewEncouragement('');
            if (window.WFToast) window.WFToast.success('Encouragement added');
        } else {
            if (window.WFToast) window.WFToast.error(res?.error || 'Failed to add encouragement');
        }
    };

    const startInlineEdit = (type: 'cart' | 'encouragement', id: number, text: string) => {
        setInlineEditingId(`${type}-${id}`);
        setTempValue(text);
    };

    const handleInlineBlur = async (type: 'cart' | 'encouragement', id: number, originalText: string) => {
        const val = tempValue.trim();
        if (val && val !== originalText) {
            const res = (type === 'cart')
                ? await updateCartText(id, val)
                : await updateEncouragement(id, val);

            if (res?.success) {
                if (window.WFToast) window.WFToast.success('Phrase updated');
            } else {
                if (window.WFToast) window.WFToast.error(res?.error || 'Failed to update');
            }
        }
        setInlineEditingId(null);
    };

    const handleDeletePhrase = async (type: 'cart' | 'encouragement', id: number, text: string) => {
        const confirmed = await confirmModal({
            title: 'Delete Phrase',
            message: `Delete phrase "${text}"?`,
            confirmText: 'Delete Now',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const res = (type === 'cart')
                ? await deleteCartText(id)
                : await deleteEncouragement(id);

            if (res?.success) {
                if (window.WFToast) window.WFToast.success('Phrase deleted');
            } else {
                if (window.WFToast) window.WFToast.error('Failed to delete');
            }
        }
    };

    const renderPhraseItem = (item: { id: number; text: string }, type: 'cart' | 'encouragement') => {
        const isEditing = inlineEditingId === `${type}-${item.id}`;

        return (
            <div
                key={`${type}-${item.id}`}
                className="group flex items-center justify-between px-3 py-1.5 rounded-[1rem] transition-all hover:bg-blue-50/50"
            >
                <div className="flex-1 flex flex-col justify-center min-w-0 pr-2">
                    {isEditing ? (
                        <input
                            autoFocus
                            className="w-full bg-slate-50 border border-blue-200 px-2 py-1 rounded-lg outline-none text-sm font-bold text-slate-800"
                            value={tempValue}
                            onChange={e => setTempValue(e.target.value)}
                            onBlur={() => handleInlineBlur(type, item.id, item.text)}
                            onKeyDown={e => {
                                if (e.key === 'Enter') handleInlineBlur(type, item.id, item.text);
                                if (e.key === 'Escape') setInlineEditingId(null);
                            }}
                        />
                    ) : (
                        <span
                            className="text-sm font-bold text-slate-700 cursor-pointer hover:text-blue-600 truncate w-full"
                            onClick={() => startInlineEdit(type, item.id, item.text)}
                            data-help-id="phrase-edit-hint"
                        >
                            {item.text}
                        </span>
                    )}
                </div>

                <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-all">
                    <button
                        type="button"
                        onClick={() => startInlineEdit(type, item.id, item.text)}
                        className="w-7 h-7 flex items-center justify-center rounded-full bg-white border border-slate-100 text-slate-400 hover:text-blue-600 hover:border-blue-200 transition-all shadow-sm"
                        data-help-id="phrase-edit-btn"
                    >
                        <span className="text-[10px]">✎</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => handleDeletePhrase(type, item.id, item.text)}
                        className="w-7 h-7 flex items-center justify-center rounded-full bg-white border border-slate-100 text-slate-400 hover:text-red-500 hover:border-red-100 transition-all shadow-sm"
                        data-help-id="phrase-delete-btn"
                    >
                        <span className="text-[10px]">✕</span>
                    </button>
                </div>
            </div>
        );
    };

    return (
        <div className="flex flex-nowrap gap-8 pb-6 w-full">
            {/* Column: Cart Phrases */}
            <div className="flex flex-col gap-4 min-w-[350px] flex-1">
                <div className="flex items-center justify-between px-2">
                    <h3 className="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] flex items-center gap-2 transition-all">
                        Cart Button Phrases
                        <span className="bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full text-[9px] tracking-normal font-bold">
                            {cartTexts.length}
                        </span>
                    </h3>
                </div>

                <div className="bg-white rounded-[1.5rem] border border-slate-100 p-1.5 min-h-[100px] shadow-sm flex flex-col gap-1">
                    <form onSubmit={handleAddCartText} className="p-2 mb-2">
                        <div className="flex gap-2">
                            <input
                                type="text"
                                value={newCartText}
                                onChange={e => setNewCartText(e.target.value)}
                                placeholder="Quick add phrase..."
                                className="flex-1 bg-slate-50 border border-slate-100 px-4 py-2 rounded-xl text-xs outline-none focus:ring-2 focus:ring-blue-500/10 transition-all font-medium"
                            />
                            <button
                                type="submit"
                                disabled={isLoading || !newCartText.trim()}
                                className="bg-slate-800 text-white w-8 h-8 rounded-xl flex items-center justify-center hover:bg-blue-600 transition-all text-lg font-bold disabled:opacity-50"
                                data-help-id="phrase-add-btn"
                            >
                                +
                            </button>
                        </div>
                    </form>

                    {sortedCartTexts.map(t => renderPhraseItem(t, 'cart'))}
                    {cartTexts.length === 0 && !isMarketingLoading && (
                        <div className="py-6 text-center bg-slate-50/50 rounded-xl border border-dashed border-slate-100 m-1">
                            <span className="text-[9px] font-black uppercase tracking-widest text-slate-300">Empty</span>
                        </div>
                    )}
                </div>
            </div>

            {/* Column: Shop Encouragements */}
            <div className="flex flex-col gap-4 min-w-[350px] flex-1">
                <div className="flex items-center justify-between px-2">
                    <h3 className="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] flex items-center gap-2 transition-all">
                        Shop Encouragements
                        <span className="bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full text-[9px] tracking-normal font-bold">
                            {encouragements.length}
                        </span>
                    </h3>
                </div>

                <div className="bg-white rounded-[1.5rem] border border-slate-100 p-1.5 min-h-[100px] shadow-sm flex flex-col gap-1">
                    <form onSubmit={handleAddEncouragement} className="p-2 mb-2">
                        <div className="flex gap-2">
                            <input
                                type="text"
                                value={newEncouragement}
                                onChange={e => setNewEncouragement(e.target.value)}
                                placeholder="Quick add phrase..."
                                className="flex-1 bg-slate-50 border border-slate-100 px-4 py-2 rounded-xl text-xs outline-none focus:ring-2 focus:ring-blue-500/10 transition-all font-medium"
                            />
                            <button
                                type="submit"
                                disabled={isLoading || !newEncouragement.trim()}
                                className="bg-slate-800 text-white w-8 h-8 rounded-xl flex items-center justify-center hover:bg-blue-600 transition-all text-lg font-bold disabled:opacity-50"
                                data-help-id="phrase-add-btn"
                            >
                                +
                            </button>
                        </div>
                    </form>

                    {sortedEncouragements.map(e => renderPhraseItem(e, 'encouragement'))}
                    {encouragements.length === 0 && !isMarketingLoading && (
                        <div className="py-6 text-center bg-slate-50/50 rounded-xl border border-dashed border-slate-100 m-1">
                            <span className="text-[9px] font-black uppercase tracking-widest text-slate-300">Empty</span>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};
