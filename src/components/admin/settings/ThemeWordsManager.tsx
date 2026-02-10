import React, { useEffect, useState, useMemo } from 'react';
import { createPortal } from 'react-dom';
import { useModalContext } from '../../../context/ModalContext.js';
import { useThemeWords } from '../../../hooks/admin/useThemeWords.js';
import { IThemeWord, IThemeWordCategory as ICategory } from '../../../types/theming.js';
import { WordGrid } from './theme-words/WordGrid.js';
import { CategoryTable } from './theme-words/CategoryTable.js';
import { WordEditorModal } from '../../modals/admin/settings/theme-words/WordEditorModal.js';
import { CategoryEditorModal } from '../../modals/admin/settings/theme-words/CategoryEditorModal.js';

interface ThemeWordsManagerProps {
    onClose?: () => void;
    title?: string;
}

export const ThemeWordsManager: React.FC<ThemeWordsManagerProps> = ({ onClose, title }) => {
    const {
        words, categories, isLoading, error,
        fetchWords, fetchCategories,
        saveWord, deleteWord,
        saveCategory, deleteCategory
    } = useThemeWords();

    const [isManagingCategories, setIsManagingCategories] = useState(false);
    const [editingWord, setEditingWord] = useState<Partial<IThemeWord> | null>(null);
    const [editingCategory, setEditingCategory] = useState<Partial<ICategory> | null>(null);

    // Inline editing states
    const [inlineEditingWordId, setInlineEditingWordId] = useState<number | null>(null);
    const [inlineEditingCategoryId, setInlineEditingCategoryId] = useState<number | null>(null);
    const [tempValue, setTempValue] = useState('');
    const [isSaving, setIsSaving] = useState(false);

    useEffect(() => {
        fetchWords();
        fetchCategories();
    }, [fetchWords, fetchCategories]);

    // Group and Sort Data
    const sortedCategories = useMemo(() => {
        return [...categories].sort((a, b) => a.name.localeCompare(b.name));
    }, [categories]);

    const groupedWords = useMemo(() => {
        const groups: Record<number, IThemeWord[]> = {};
        categories.forEach(cat => { groups[cat.id] = []; });
        words.forEach((word: IThemeWord) => {
            const catId = word.category_id;
            if (catId) {
                if (!groups[catId]) groups[catId] = [];
                groups[catId].push(word);
            }
        });
        Object.keys(groups).forEach(id => {
            groups[parseInt(id)].sort((a, b) => a.word.localeCompare(b.word));
        });
        return groups;
    }, [words, categories]);

    const handleCreateWord = (categoryId?: number) => {
        const cat = categoryId ? categories.find(c => c.id === categoryId) : categories[0];
        setEditingWord({
            word: '',
            category: cat?.name || 'General',
            category_id: cat?.id || categories[0]?.id || 0,
            is_active: true,
            variants: []
        });
    };

    const handleSaveWord = async (e?: React.FormEvent, wordData?: Partial<IThemeWord>) => {
        if (e) e.preventDefault();
        const dataToSave = wordData || editingWord;
        if (dataToSave) {
            setIsSaving(true);
            try {
                const payload = {
                    ...dataToSave,
                    variants: dataToSave.variants?.map(v => typeof v === 'string' ? v : v.variant_text) || []
                };
                const res = await saveWord(payload);
                if (res?.success) {
                    setEditingWord(null);
                    setInlineEditingWordId(null);
                    if (window.WFToast) window.WFToast.success('Theme word saved');
                } else {
                    if (window.WFToast) window.WFToast.error(res?.error || 'Failed to save word');
                }
            } finally {
                setIsSaving(false);
            }
        }
    };

    const handleCreateCategory = () => {
        setEditingCategory({
            name: '',
            slug: '',
            sort_order: categories.length,
            is_active: true
        });
    };

    const handleSaveCategory = async (e?: React.FormEvent, catData?: Partial<ICategory>) => {
        if (e) e.preventDefault();
        const dataToSave = catData || editingCategory;
        if (dataToSave) {
            setIsSaving(true);
            try {
                const res = await saveCategory(dataToSave);
                if (res?.success) {
                    setEditingCategory(null);
                    setInlineEditingCategoryId(null);
                    if (window.WFToast) window.WFToast.success('Category saved');
                } else {
                    if (window.WFToast) window.WFToast.error(res?.error || 'Failed to save category');
                }
            } finally {
                setIsSaving(false);
            }
        }
    };

    const startInlineWordEdit = (word: IThemeWord) => {
        setInlineEditingWordId(word.id);
        setTempValue(word.word);
    };

    const startInlineCategoryEdit = (cat: ICategory) => {
        setInlineEditingCategoryId(cat.id);
        setTempValue(cat.name);
    };

    const handleInlineWordBlur = (word: IThemeWord) => {
        if (tempValue.trim() && tempValue !== word.word) {
            handleSaveWord(undefined, { ...word, word: tempValue.trim() });
        } else {
            setInlineEditingWordId(null);
        }
    };

    const handleInlineCategoryBlur = (cat: ICategory) => {
        if (tempValue.trim() && tempValue !== cat.name) {
            handleSaveCategory(undefined, { ...cat, name: tempValue.trim() });
        } else {
            setInlineEditingCategoryId(null);
        }
    };

    const handleToggleCategoryActive = async (cat: ICategory) => {
        setIsSaving(true);
        try {
            const nextActive = !Boolean(cat.is_active);
            const res = await saveCategory({ ...cat, is_active: nextActive });
            if (res?.success) {
                if (window.WFToast) window.WFToast.success(nextActive ? 'Category activated' : 'Category deactivated');
            } else {
                if (window.WFToast) window.WFToast.error(res?.error || 'Failed to update category');
            }
        } finally {
            setIsSaving(false);
        }
    };

    const modalContent = (
        <div
            className="admin-modal-overlay over-header show topmost"
            role="dialog"
            aria-modal="true"
            onClick={(e) => { if (e.target === e.currentTarget) onClose?.(); }}
        >
            <div
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl !w-[95vw] max-w-[95vw] h-[85vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-8 py-5 sticky top-0 bg-white z-20">
                    <h2 className="text-2xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-3xl">✨</span> {title || 'Theme Words'}
                    </h2>
                    <div className="flex-1" />
                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setIsManagingCategories(!isManagingCategories)}
                                className="btn-text-secondary"
                                data-help-id="toggle-category-mode"
                            >
                                {isManagingCategories ? '← Back to Words' : 'Manage Categories'}
                            </button>
                            <button
                                type="button"
                                onClick={() => { fetchWords(); fetchCategories(); }}
                                className="admin-action-btn btn-icon--refresh"
                                data-help-id="reload-theme-data"
                            />
                            {!isManagingCategories && (
                                <button
                                    type="button"
                                    onClick={() => handleCreateWord()}
                                    className="admin-action-btn btn-icon--add"
                                    data-help-id="add-word"
                                />
                            )}
                            {isManagingCategories && (
                                <button
                                    type="button"
                                    onClick={handleCreateCategory}
                                    className="admin-action-btn btn-icon--add"
                                    data-help-id="add-category"
                                />
                            )}
                            <button
                                type="button"
                                onClick={onClose}
                                className="admin-action-btn btn-icon--close"
                                data-help-id="close-manager"
                            />
                        </div>
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto bg-slate-50/30">
                    <div className="p-8">
                        {error && (
                            <div className="p-4 mb-6 bg-red-50 border border-red-100 text-red-600 text-xs font-bold rounded-xl flex items-center gap-3">
                                <span className="text-lg">⚠️</span> {error}
                            </div>
                        )}

                        {isLoading && !words.length && !categories.length ? (
                            <div className="py-20 text-center flex flex-col items-center gap-4 uppercase tracking-widest font-black text-[10px] text-slate-400">
                                <div className="animate-spin text-4xl">✨</div>
                                <p>Tuning the vibes...</p>
                            </div>
                        ) : isManagingCategories ? (
                            <CategoryTable
                                sortedCategories={sortedCategories}
                                inlineEditingCategoryId={inlineEditingCategoryId}
                                tempValue={tempValue}
                                setTempValue={setTempValue}
                                handleInlineCategoryBlur={handleInlineCategoryBlur}
                                startInlineCategoryEdit={startInlineCategoryEdit}
                                setEditingCategory={setEditingCategory}
                                deleteCategory={deleteCategory}
                                toggleCategoryActive={handleToggleCategoryActive}
                            />
                        ) : (
                            <WordGrid
                                categories={sortedCategories}
                                groupedWords={groupedWords}
                                inlineEditingWordId={inlineEditingWordId}
                                tempValue={tempValue}
                                setTempValue={setTempValue}
                                startInlineWordEdit={startInlineWordEdit}
                                startInlineCategoryEdit={startInlineCategoryEdit}
                                handleInlineWordBlur={handleInlineWordBlur}
                                handleInlineCategoryBlur={handleInlineCategoryBlur}
                                handleCreateWord={handleCreateWord}
                                setEditingWord={(w) => setEditingWord(w)}
                                deleteWord={deleteWord}
                                inlineEditingCategoryId={inlineEditingCategoryId}
                            />
                        )}
                    </div>
                </div>
            </div>

            {editingWord && (
                <WordEditorModal
                    editingWord={editingWord}
                    setEditingWord={setEditingWord}
                    categories={categories}
                    isSaving={isSaving}
                    handleSaveWord={handleSaveWord}
                />
            )}

            {editingCategory && (
                <CategoryEditorModal
                    editingCategory={editingCategory}
                    setEditingCategory={setEditingCategory}
                    isSaving={isSaving}
                    handleSaveCategory={handleSaveCategory}
                />
            )}
        </div>
    );

    return createPortal(modalContent, document.body);
};
