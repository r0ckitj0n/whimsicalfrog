import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { API_ACTION } from '../../core/constants.js';
import type {
    IThemeWordVariant,
    IThemeWord,
    IThemeWordCategory,
    IThemeWordsResponse,
    IThemeWordsHook
} from '../../types/theming.js';

// Re-export for backward compatibility (with legacy names)
export type {
    IThemeWordVariant as IVariant,
    IThemeWord,
    IThemeWordCategory as ICategory,
    IThemeWordsHook
} from '../../types/theming.js';

export const useThemeWords = (): IThemeWordsHook => {
    const [words, setWords] = useState<IThemeWord[]>([]);
    const [categories, setCategories] = useState<IThemeWordCategory[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchWords = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IThemeWordsResponse>(`/api/theme_words.php?action=${API_ACTION.LIST}`);
            if (res?.success) {
                const words = (res.words || []).map((w: IThemeWord & { base_word?: string }) => ({
                    ...w,
                    word: w.base_word || w.word
                }));
                setWords(words);
            } else {
                setError(res?.error || 'Failed to load theme words');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchThemeWords failed', err);
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const fetchCategories = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IThemeWordsResponse>('/api/theme_words.php?action=list_categories');
            if (res?.success) {
                setCategories(res.categories || []);
            } else {
                setError(res?.error || 'Failed to load categories');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchCategories failed', err);
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const saveWord = useCallback(async (wordData: Partial<IThemeWord>) => {
        setIsLoading(true);
        try {
            const isEdit = !!wordData.id;
            const action = isEdit ? API_ACTION.UPDATE : API_ACTION.CREATE;
            const res = await ApiClient.post<IThemeWordsResponse>(`/api/theme_words.php?action=${action}`, wordData);
            if (res) {
                await fetchWords();
                return res;
            } else {
                throw new Error('Failed to save theme word');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveThemeWord failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [fetchWords]);

    const deleteWord = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<IThemeWordsResponse>(`/api/theme_words.php?action=${API_ACTION.DELETE}`, { id });
            if (res) {
                await fetchWords();
                return true;
            } else {
                throw new Error('Failed to delete theme word');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('deleteThemeWord failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchWords]);

    const saveCategory = useCallback(async (catData: Partial<IThemeWordCategory>) => {
        setIsLoading(true);
        try {
            const isEdit = !!catData.id;
            const action = isEdit ? 'update_category' : 'add_category';
            const res = await ApiClient.post<IThemeWordsResponse>(`/api/theme_words.php?action=${action}`, catData);
            if (res) {
                await fetchCategories();
                return res;
            } else {
                throw new Error('Failed to save category');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveCategory failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [fetchCategories]);

    const deleteCategory = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<IThemeWordsResponse>('/api/theme_words.php?action=delete_category', { id });
            if (res) {
                await fetchCategories();
                return true;
            } else {
                throw new Error('Failed to delete category');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('deleteCategory failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchCategories]);

    return {
        words,
        categories,
        isLoading,
        error,
        fetchWords,
        fetchCategories,
        saveWord,
        deleteWord,
        saveCategory,
        deleteCategory
    };
};
