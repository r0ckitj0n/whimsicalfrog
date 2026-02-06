import React, { useState } from 'react';
import { IRoomOverview, ICategory, IRoomAssignment } from '../../../../hooks/admin/useCategories.js';

interface OverviewCategoryEditorProps {
    roomNumber: string | number;
    overview: IRoomOverview | undefined;
    categories: ICategory[];
    assignments: IRoomAssignment[];
    onAdd: (roomNumber: number, categoryId: number) => Promise<unknown>;
    onDelete: (id: number) => Promise<unknown>;
    onUpdate: (id: number, data: { is_primary?: number }) => Promise<unknown>;
}

export const OverviewCategoryEditor: React.FC<OverviewCategoryEditorProps> = ({
    roomNumber,
    overview,
    categories,
    assignments,
    onAdd,
    onDelete,
    onUpdate
}) => {
    const [showDropdown, setShowDropdown] = useState(false);
    const [isLoading, setIsLoading] = useState(false);

    // Get assignments for this room
    const roomAssignments = assignments.filter(
        a => String(a.room_number) === String(roomNumber)
    );

    // Get category IDs already assigned
    const assignedCategoryIds = new Set(roomAssignments.map(a => a.category_id));

    // Available categories (not yet assigned)
    const availableCategories = categories.filter(
        c => c.id && !assignedCategoryIds.has(c.id)
    );

    const handleAdd = async (categoryId: number) => {
        setIsLoading(true);
        await onAdd(parseInt(String(roomNumber)), categoryId);
        setIsLoading(false);
        setShowDropdown(false);
    };

    const handleRemove = async (assignmentId: number) => {
        setIsLoading(true);
        await onDelete(assignmentId);
        setIsLoading(false);
    };

    const handleTogglePrimary = async (assignmentId: number, currentlyPrimary: boolean) => {
        if (currentlyPrimary) return; // Can't un-primary directly, must set another as primary
        setIsLoading(true);
        await onUpdate(assignmentId, { is_primary: 1 });
        setIsLoading(false);
    };

    if (roomAssignments.length === 0) {
        // No categories assigned - show just the add button
        return (
            <div className="relative">
                {showDropdown ? (
                    <div className="flex flex-col gap-1">
                        <select
                            autoFocus
                            className="text-xs px-2 py-1 rounded-lg border border-slate-200 bg-white"
                            onChange={(e) => e.target.value && handleAdd(parseInt(e.target.value))}
                            onBlur={() => setShowDropdown(false)}
                            disabled={isLoading}
                        >
                            <option value="">Select category...</option>
                            {availableCategories.map(c => (
                                <option key={c.id} value={c.id}>{c.category}</option>
                            ))}
                        </select>
                    </div>
                ) : (
                    <button
                        onClick={() => setShowDropdown(true)}
                        className="text-[10px] text-slate-400 hover:text-blue-500 transition-colors italic flex items-center gap-1"
                        disabled={isLoading || availableCategories.length === 0}
                        data-help-id={availableCategories.length === 0 ? 'categories-none-available' : 'categories-action-add'}
                    >
                        + Add
                    </button>
                )}
            </div>
        );
    }

    return (
        <div className="flex flex-wrap gap-1 items-center">
            {roomAssignments.map((asgn) => {
                const isPrimary = asgn.is_primary;
                return (
                    <span
                        key={asgn.id}
                        className={`group inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-[9px] font-bold transition-all ${isPrimary
                            ? 'bg-amber-100 text-amber-700 border border-amber-200'
                            : 'bg-slate-100 text-slate-500 border border-slate-200 hover:bg-slate-200'
                            }`}
                    >
                        {/* Primary star toggle */}
                        <button
                            onClick={() => handleTogglePrimary(asgn.id, !!isPrimary)}
                            className={`${isPrimary ? 'text-amber-500' : 'text-slate-300 hover:text-amber-400'} transition-colors`}
                            data-help-id={isPrimary ? 'categories-status-primary' : 'categories-action-make-primary'}
                            disabled={isLoading || isPrimary}
                        >
                            {isPrimary ? '⭐' : '☆'}
                        </button>

                        <span>{asgn.category_name}</span>

                        {/* Remove button */}
                        <button
                            onClick={() => handleRemove(asgn.id)}
                            className="text-slate-400 hover:text-red-500 transition-colors ml-0.5 opacity-0 group-hover:opacity-100"
                            data-help-id="categories-action-remove"
                            disabled={isLoading}
                        >
                            ×
                        </button>
                    </span>
                );
            })}

            {/* Add more button */}
            {availableCategories.length > 0 && (
                showDropdown ? (
                    <select
                        autoFocus
                        className="text-[9px] px-1 py-0.5 rounded border border-slate-200 bg-white"
                        onChange={(e) => e.target.value && handleAdd(parseInt(e.target.value))}
                        onBlur={() => setShowDropdown(false)}
                        disabled={isLoading}
                    >
                        <option value="">+</option>
                        {availableCategories.map(c => (
                            <option key={c.id} value={c.id}>{c.category}</option>
                        ))}
                    </select>
                ) : (
                    <button
                        onClick={() => setShowDropdown(true)}
                        className="text-[9px] text-slate-400 hover:text-blue-500 transition-colors"
                        disabled={isLoading}
                        data-help-id="categories-action-add"
                    >
                        +
                    </button>
                )
            )}
        </div>
    );
};
