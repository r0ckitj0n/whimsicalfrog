import React from 'react';
import { IThemeWord, IThemeWordCategory, IThemeWordVariant } from '../../../../types/theming.js';
import { useModalContext } from '../../../../context/ModalContext.js';

interface WordGridProps {
    categories: IThemeWordCategory[];
    groupedWords: Record<number, IThemeWord[]>;
    inlineEditingWordId: number | null;
    tempValue: string;
    setTempValue: (val: string) => void;
    startInlineWordEdit: (word: IThemeWord) => void;
    startInlineCategoryEdit: (cat: IThemeWordCategory) => void;
    handleInlineWordBlur: (word: IThemeWord) => void;
    handleInlineCategoryBlur: (cat: IThemeWordCategory) => void;
    handleCreateWord: (categoryId?: number) => void;
    setEditingWord: (word: IThemeWord) => void;
    deleteWord: (id: number) => Promise<boolean>;
    inlineEditingCategoryId: number | null;
}

export const WordGrid: React.FC<WordGridProps> = ({
    categories,
    groupedWords,
    inlineEditingWordId,
    tempValue,
    setTempValue,
    startInlineWordEdit,
    startInlineCategoryEdit,
    handleInlineWordBlur,
    handleInlineCategoryBlur,
    handleCreateWord,
    setEditingWord,
    deleteWord,
    inlineEditingCategoryId
}) => {
    const { confirm: themedConfirm } = useModalContext();

    return (
        <div className="flex flex-nowrap gap-8 animate-in fade-in slide-in-from-bottom-2 duration-300 overflow-x-auto pb-6 custom-scrollbar">
            {categories.map(cat => (
                <div key={cat.id} className="flex flex-col gap-4 min-w-[220px] max-w-[350px] flex-1">
                    <div className="flex items-center justify-between px-2">
                        <h3
                            className="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] flex items-center gap-2 group cursor-pointer hover:text-blue-600 transition-all"
                            onClick={() => startInlineCategoryEdit(cat)}
                        >
                            {inlineEditingCategoryId === cat.id ? (
                                <input
                                    autoFocus
                                    className="bg-white border border-brand-primary/20 px-2 py-1 rounded-md outline-none ring-2 ring-brand-primary/5 normal-case tracking-normal w-32"
                                    value={tempValue}
                                    onChange={e => setTempValue(e.target.value)}
                                    onBlur={() => handleInlineCategoryBlur(cat)}
                                    onKeyDown={e => e.key === 'Enter' && handleInlineCategoryBlur(cat)}
                                />
                            ) : (
                                <>
                                    {cat.name}
                                    <span className="opacity-0 group-hover:opacity-40 text-[9px]">✎</span>
                                </>
                            )}
                            <span className="bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full text-[9px] tracking-normal font-bold">
                                {(groupedWords[cat.id] || []).length}
                            </span>
                        </h3>
                        <button
                            type="button"
                            onClick={() => handleCreateWord(cat.id)}
                            className="w-6 h-6 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center hover:bg-brand-primary hover:text-white transition-all text-xs font-bold"
                            data-help-id={`add-word-${cat.slug}`}
                        >
                            +
                        </button>
                    </div>

                    <div className="bg-white rounded-[1.5rem] border border-slate-100 p-1.5 min-h-[100px] shadow-sm flex flex-col gap-1">
                        {(groupedWords[cat.id] || []).map((word: IThemeWord) => (
                            <div
                                key={word.id}
                                className={`group flex items-center justify-between px-3 py-1.5 rounded-[1rem] transition-all ${word.is_active ? 'hover:bg-brand-primary/5' : 'opacity-40 grayscale'}`}
                            >
                                <div className="flex-1 flex flex-col justify-center min-w-0 pr-2">
                                    {inlineEditingWordId === word.id ? (
                                        <input
                                            autoFocus
                                            className="w-full bg-slate-50 border border-brand-primary/20 px-2 py-1 rounded-lg outline-none text-sm font-bold text-slate-800"
                                            value={tempValue}
                                            onChange={e => setTempValue(e.target.value)}
                                            onBlur={() => handleInlineWordBlur(word)}
                                            onKeyDown={e => e.key === 'Enter' && handleInlineWordBlur(word)}
                                        />
                                    ) : (
                                        <span
                                            className="text-sm font-bold text-slate-700 cursor-pointer hover:text-blue-600 truncate w-full"
                                            onClick={() => startInlineWordEdit(word)}
                                            data-help-id="edit-word-inline"
                                        >
                                            {word.word}
                                        </span>
                                    )}
                                    {word.variants && word.variants.length > 0 && (
                                        <div className="flex flex-wrap gap-1 mt-1">
                                            {word.variants.slice(0, 5).map((v: IThemeWordVariant | string, i: number) => (
                                                <span key={typeof v === 'string' ? i : v.id} className="text-[9px] text-slate-400 bg-slate-50 px-1.5 py-0.5 rounded-md border border-slate-100">
                                                    {typeof v === 'string' ? v : v.variant_text}
                                                </span>
                                            ))}
                                            {word.variants.length > 5 && (
                                                <span className="text-[9px] text-slate-300 px-1">+{word.variants.length - 5}</span>
                                            )}
                                        </div>
                                    )}
                                </div>

                                <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-all">
                                    <button
                                        type="button"
                                        onClick={() => setEditingWord(word)}
                                        className="w-7 h-7 flex items-center justify-center rounded-full bg-white border border-slate-100 text-slate-400 hover:text-brand-primary hover:border-brand-primary/20 transition-all shadow-sm"
                                        data-help-id="edit-word-detail"
                                    >
                                        <span className="text-[10px]">✎</span>
                                    </button>
                                    <button
                                        type="button"
                                        onClick={async () => {
                                            const confirmed = await themedConfirm({
                                                title: 'Delete Word',
                                                message: `Delete "${word.word}"?`,
                                                confirmText: 'Delete Now',
                                                confirmStyle: 'danger',
                                                iconKey: 'delete'
                                            });

                                            if (confirmed) {
                                                const success = await deleteWord(word.id);
                                                if (success && window.WFToast) window.WFToast.success('Word deleted');
                                            }
                                        }}
                                        className="w-7 h-7 flex items-center justify-center rounded-full bg-white border border-slate-100 text-slate-400 hover:text-red-500 hover:border-red-100 transition-all shadow-sm"
                                        data-help-id="delete-word"
                                    >
                                        <span className="text-[10px]">✕</span>
                                    </button>
                                </div>
                            </div>
                        ))}
                        {(groupedWords[cat.id] || []).length === 0 && (
                            <div className="py-6 text-center bg-slate-50/50 rounded-xl border border-dashed border-slate-100 m-1">
                                <span className="text-[9px] font-black uppercase tracking-widest text-slate-300">Empty</span>
                            </div>
                        )}
                    </div>
                </div>
            ))}
        </div>
    );
};
