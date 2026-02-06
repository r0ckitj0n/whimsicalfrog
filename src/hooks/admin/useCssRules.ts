import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { ICssRule } from '../../types/theming.js';
import type { ICssRulesResponse } from '../../types/admin.js';

// Re-export for backward compatibility
export type { ICssRule } from '../../types/theming.js';
export type { ICssRulesResponse } from '../../types/admin.js';


export const useCssRules = () => {
    const [rules, setRules] = useState<ICssRule[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchRules = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<ICssRulesResponse>('/api/css_rules.php');
            if (res && res.success) {
                setRules(res.rules || []);
            } else {
                setError(res?.error || 'Failed to load rules');
            }
        } catch (err) {
            logger.error('[useCssRules] fetch failed', err);
            setError('Unable to load CSS rules');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const addRule = async (rule: ICssRule) => {
        setIsLoading(true);
        // Map hook fields to API expected fields
        const apiPayload = {
            rule_name: rule.selector,
            css_property: rule.property,
            css_value: rule.value + (rule.important ? ' !important' : ''),
            category: 'general',
            is_active: 1
        };

        try {
            const res = await ApiClient.post<{ success: boolean; error?: string }>('/api/css_rules.php', apiPayload);
            if (res && res.success) {
                await fetchRules();
                return { success: true };
            }
            return { success: false, error: res?.error || 'Failed to add rule' };
        } catch (err) {
            logger.error('[useCssRules] add failed', err);
            return { success: false, error: 'Network error' };
        } finally {
            setIsLoading(false);
        }
    };

    const deleteRule = async (id: number) => {
        setIsLoading(true);
        try {
            // The API supports hard delete via a JSON payload in DELETE or POST
            const res = await ApiClient.post<{ success: boolean; error?: string }>('/api/css_rules.php', {
                id,
                hard: true
            }, {
                headers: { 'X-HTTP-Method-Override': 'DELETE' } // Or just use POST if the backend handles it as DELETE logic
            });

            // Wait, looking at the PHP, handleDelete expects DELETE method.
            // But ApiClient.post with override or ApiClient.delete should work.
            // Actually the PHP handles DELETE method case 'DELETE': handleDelete($pdo);

            const deleteRes = await ApiClient.delete<{ success: boolean; error?: string }>('/api/css_rules.php', {
                body: JSON.stringify({ id, hard: true })
            });

            if (deleteRes && deleteRes.success) {
                setRules(prev => prev.filter(r => r.id !== id));
                return { success: true };
            }
            return { success: false, error: deleteRes?.error || 'Delete failed' };
        } catch (err) {
            logger.error('[useCssRules] delete failed', err);
            return { success: false, error: 'Network error' };
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        fetchRules();
    }, [fetchRules]);

    return {
        rules,
        isLoading,
        error,
        addRule,
        deleteRule,
        refresh: fetchRules
    };
};
