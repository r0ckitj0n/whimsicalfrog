import React from 'react';
import { IThemeWord, IThemeWordCategory } from '../../../../../types/theming.js';

interface WordEditorModalProps {
    editingWord: Partial<IThemeWord>;
    setEditingWord: (word: Partial<IThemeWord> | null) => void;
    categories: IThemeWordCategory[];
    isSaving: boolean;
    handleSaveWord: (e: React.FormEvent) => void;
}

export const WordEditorModal: React.FC<WordEditorModalProps> = ({
    editingWord,
    setEditingWord,
    categories,
    isSaving,
    handleSaveWord
}) => {
    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-8 bg-brand-primary/10 backdrop-blur-sm animate-in fade-in duration-200">
            <div
                className="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in-95 duration-300 border border-white"
                onClick={e => e.stopPropagation()}
            >
                <div className="px-10 py-8 border-b border-slate-50 flex items-center justify-between bg-white">
                    <h3 className="text-lg font-black text-slate-800 uppercase tracking-tight">{editingWord.id ? 'Word Details' : 'New Theme Word'}</h3>
                    <button
                        type="button"
                        onClick={() => setEditingWord(null)}
                        className="admin-action-btn btn-icon--close"
                        data-help-id="close-modal"
                    />
                </div>
                <form onSubmit={handleSaveWord} className="p-10 space-y-8 bg-slate-50/50">
                    <div className="space-y-3">
                        <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">The Word</label>
                        <input
                            type="text" required
                            value={editingWord.word}
                            onChange={e => setEditingWord({ ...editingWord, word: e.target.value })}
                            className="w-full px-5 py-4 bg-white border border-slate-100 rounded-2xl outline-none focus:ring-4 focus:ring-brand-primary/5 shadow-sm font-black text-slate-800 uppercase tracking-tight transition-all"
                            placeholder="e.g., Croak"
                            data-help-id="word-input"
                        />
                    </div>
                    <div className="space-y-3">
                        <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Category</label>
                        <div className="relative">
                            <select
                                value={editingWord.category_id}
                                onChange={e => {
                                    const id = parseInt(e.target.value);
                                    const cat = categories.find(c => c.id === id);
                                    setEditingWord({ ...editingWord, category_id: id, category: cat?.name || '' });
                                }}
                                className="w-full px-5 py-4 bg-white border border-slate-100 rounded-2xl outline-none focus:ring-4 focus:ring-brand-primary/5 shadow-sm font-bold text-slate-700 appearance-none transition-all cursor-pointer"
                                data-help-id="category-select"
                            >
                                {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                            <div className="absolute right-5 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">▼</div>
                        </div>
                    </div>

                    <label className="flex items-center gap-4 cursor-pointer p-4 bg-white border border-slate-100 rounded-2xl shadow-sm hover:border-brand-primary/20 transition-all select-none group">
                        <div className={`w-10 h-6 rounded-full relative transition-all ${editingWord.is_active ? 'bg-brand-primary' : 'bg-slate-200'}`}>
                            <div className={`absolute top-1 w-4 h-4 bg-white rounded-full transition-all ${editingWord.is_active ? 'left-5' : 'left-1'}`} />
                        </div>
                        <input
                            type="checkbox"
                            checked={editingWord.is_active}
                            onChange={e => setEditingWord({ ...editingWord, is_active: e.target.checked })}
                            className="hidden"
                        />
                        <span className="text-[11px] font-black text-slate-800 uppercase tracking-widest">Active in generator</span>
                    </label>

                    <div className="space-y-3">
                        <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Variants & Aliases</label>
                        <div className="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm space-y-4">
                            <div className="flex flex-wrap gap-2">
                                {(editingWord.variants || []).map((v, idx) => (
                                    <div key={idx} className="flex items-center gap-2 bg-slate-50 border border-slate-100 px-3 py-1.5 rounded-xl group/variant">
                                        <span className="text-xs font-bold text-slate-600">{typeof v === 'string' ? v : v.variant_text}</span>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                const newVariants = [...(editingWord.variants || [])];
                                                newVariants.splice(idx, 1);
                                                setEditingWord({ ...editingWord, variants: newVariants });
                                            }}
                                            className="text-slate-300 hover:text-red-500 transition-colors text-[10px]"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                ))}
                                {!(editingWord.variants || []).length && (
                                    <span className="text-[10px] text-slate-400 italic">No variants added yet</span>
                                )}
                            </div>
                            <div className="flex gap-2">
                                <input
                                    type="text"
                                    id="new-variant-input"
                                    className="flex-1 bg-slate-50 border border-slate-100 px-4 py-2 rounded-xl text-xs outline-none focus:ring-2 focus:ring-blue-500/10 transition-all"
                                    placeholder="Add variant..."
                                    onKeyDown={e => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            const val = (e.target as HTMLInputElement).value.trim();
                                            if (val) {
                                                const newVariants = [...(editingWord.variants || [])];
                                                if (!newVariants.some(v => (typeof v === 'string' ? v : v.variant_text).toLowerCase() === val.toLowerCase())) {
                                                    newVariants.push(val);
                                                    setEditingWord({ ...editingWord, variants: newVariants });
                                                    (e.target as HTMLInputElement).value = '';
                                                }
                                            }
                                        }
                                    }}
                                />
                                <button
                                    type="button"
                                    onClick={() => {
                                        const input = document.getElementById('new-variant-input') as HTMLInputElement;
                                        const val = input.value.trim();
                                        if (val) {
                                            const newVariants = [...(editingWord.variants || [])];
                                            if (!newVariants.some(v => (typeof v === 'string' ? v : v.variant_text).toLowerCase() === val.toLowerCase())) {
                                                newVariants.push(val);
                                                setEditingWord({ ...editingWord, variants: newVariants });
                                                input.value = '';
                                            }
                                        }
                                    }}
                                    className="bg-slate-800 text-white w-8 h-8 rounded-xl flex items-center justify-center hover:bg-brand-primary transition-all text-lg font-bold"
                                >
                                    +
                                </button>
                            </div>
                            <p className="text-[10px] text-slate-400 px-1">Aliases help the AI generate diverse content.</p>
                        </div>
                    </div>

                    <div className="flex gap-4 pt-4">
                        <button
                            type="button"
                            onClick={() => setEditingWord(null)}
                            className="btn-text-secondary flex-1 !py-4"
                            data-help-id="cancel-word-edit"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={isSaving || !editingWord.word}
                            className="btn-text-primary flex-[2] !py-4 disabled:opacity-50"
                            data-help-id="save-word-btn"
                        >
                            {isSaving ? 'Saving...' : 'Save Word'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};
