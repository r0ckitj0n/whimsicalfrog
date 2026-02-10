import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type {
    IRoomCategory as ICategory,
    IRoomAssignment,
    IRoomOverview,
    ICategoryResponse,
    IAssignmentsResponse,
    ICategoryOverviewResponse
} from '../../types/room.js';
import type { IActionResponse } from '../../types/api.js';

// Re-export for backward compatibility
export type {
    IRoomCategory as ICategory,
    IRoomAssignment,
    IRoomOverview,
    ICategoryResponse,
    IAssignmentsResponse,
    ICategoryOverviewResponse
} from '../../types/room.js';
export type { IActionResponse } from '../../types/api.js';

type ICategoryActionResponse = IActionResponse & { message?: string };

export const useCategories = () => {
    const [categories, setCategories] = useState<ICategory[]>([]);
    const [assignments, setAssignments] = useState<IRoomAssignment[]>([]);
    const [overview, setOverview] = useState<IRoomOverview[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchCategories = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<ICategoryResponse | { data?: { categories?: ICategory[] }; categories?: ICategory[] }>('/api/categories.php', { action: 'list' });
            if (res) {
                // Handle various potential structures from ApiClient/Backend
                const root = 'data' in res ? res.data : res;
                const raw = Array.isArray(root) ? root : (root?.categories || []);
                const normalized = raw.map((c: ICategory & { name?: string }) => ({
                    ...c,
                    category: c.category || c.name || `Category #${c.id || '?'}`
                }));
                setCategories(normalized);
            } else {
                setError('Failed to load categories');
            }
        } catch (err) {
            logger.error('[useCategories] fetchCategories failed', err);
            setError('Unable to load categories');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const fetchAssignments = useCallback(async () => {
        setIsLoading(true);
        try {
            const res = await ApiClient.get<IAssignmentsResponse | IRoomAssignment[]>('/api/room_category_assignments.php', { action: 'get_all' });
            if (res) {
                const payload = Array.isArray(res) ? res : res.assignments;
                setAssignments(payload || []);
            }
        } catch (err) {
            logger.error('[useCategories] fetchAssignments failed', err);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const fetchOverview = useCallback(async () => {
        setIsLoading(true);
        try {
            const res = await ApiClient.get<ICategoryOverviewResponse | any[]>('/api/room_category_assignments.php', { action: 'get_summary' });
            if (res) {
                const raw = Array.isArray(res) ? res : (res.summary || []);
                const transformed: IRoomOverview[] = raw.map((item: IRoomOverview & { categories?: string }) => {
                    if (!item) return null;
                    let assigned: string[] = [];
                    if (Array.isArray(item.assigned_categories)) {
                        assigned = item.assigned_categories;
                    } else if (typeof item.categories === 'string') {
                        assigned = item.categories.split(',').map((s: string) => s.trim()).filter(Boolean);
                    }

                    return {
                        room_number: Number.isNaN(Number(item.room_number)) ? 0 : Number(item.room_number),
                        room_name: item.room_name || `Room ${item.room_number}`,
                        assigned_categories: assigned,
                        primary_category: item.primary_category || undefined
                    };
                }).filter(Boolean) as IRoomOverview[];
                setOverview(transformed);
            }
        } catch (err) {
            logger.error('[useCategories] fetchOverview failed', err);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const createCategory = async (name: string) => {
        try {
            const res = await ApiClient.post<ICategoryActionResponse>('/api/categories.php', {
                action: 'add',
                name: name
            });
            if (res?.success) {
                await fetchCategories();
                return { success: true, message: res.message || 'Category created successfully.' };
            }
            return { success: false, error: res?.error || res?.message || 'Failed to create category' };
        } catch (err) {
            logger.error('[useCategories] createCategory failed', err);
            const errorMessage = err instanceof Error ? err.message : 'Network error';
            return { success: false, error: errorMessage };
        }
    };

    const renameCategory = async (oldName: string, newName: string) => {
        try {
            const res = await ApiClient.post<IActionResponse>('/api/categories.php', {
                action: 'rename',
                old_name: oldName,
                new_name: newName
            });
            if (res?.success) {
                await fetchCategories();
                return { success: true };
            }
            return { success: false, error: 'Failed to rename category' };
        } catch (err) {
            logger.error('[useCategories] renameCategory failed', err);
            return { success: false, error: 'Network error' };
        }
    };

    const deleteCategory = async (name: string) => {
        try {
            const res = await ApiClient.post<IActionResponse>('/api/categories.php', {
                action: 'delete',
                name: name
            });
            if (res?.success) {
                setCategories(prev => prev.filter(c => c.category !== name));
                return { success: true };
            }
            return { success: false, error: 'Failed to delete category' };
        } catch (err) {
            logger.error('[useCategories] deleteCategory failed', err);
            return { success: false, error: 'Network error' };
        }
    };

    const addAssignment = async (roomNumber: number, categoryId: number) => {
        try {
            const res = await ApiClient.post<IActionResponse>('/api/room_category_assignments.php', {
                action: 'add',
                room_number: roomNumber,
                category_id: categoryId
            });
            if (res?.success) {
                await Promise.all([fetchAssignments(), fetchOverview()]);
                return { success: true };
            }
            return { success: false, error: 'Failed to add assignment' };
        } catch (err) {
            logger.error('[useCategories] addAssignment failed', err);
            return { success: false, error: 'Network error' };
        }
    };

    const deleteAssignment = async (id: number) => {
        try {
            const res = await ApiClient.delete<IActionResponse>('/api/room_category_assignments.php', {
                body: JSON.stringify({ assignment_id: id })
            });
            if (res?.success) {
                await Promise.all([fetchAssignments(), fetchOverview()]);
                return { success: true };
            }
            return { success: false, error: 'Failed to delete assignment' };
        } catch (err) {
            logger.error('[useCategories] deleteAssignment failed', err);
            return { success: false, error: 'Network error' };
        }
    };

    const updateAssignment = async (id: number, data: { room_number?: number; category_id?: number; is_primary?: number }) => {
        try {
            const res = await ApiClient.post<IActionResponse>('/api/room_category_assignments.php', {
                action: 'update_assignment',
                id,
                ...data
            });
            if (res?.success) {
                await Promise.all([fetchAssignments(), fetchOverview()]);
                return { success: true };
            }
            return { success: false, error: 'Failed to update assignment' };
        } catch (err) {
            logger.error('[useCategories] updateAssignment failed', err);
            return { success: false, error: 'Network error' };
        }
    };

    useEffect(() => {
        fetchCategories();
        fetchAssignments();
        fetchOverview();
    }, [fetchCategories, fetchAssignments, fetchOverview]);

    return {
        categories,
        assignments,
        overview,
        isLoading,
        error,
        createCategory,
        renameCategory,
        deleteCategory,
        addAssignment,
        deleteAssignment,
        updateAssignment,
        refresh: () => Promise.all([fetchCategories(), fetchAssignments(), fetchOverview()])
    };
};
