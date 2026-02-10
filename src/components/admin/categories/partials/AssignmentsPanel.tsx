import React, { useState } from 'react';
import { IRoomAssignment, ICategory } from '../../../../hooks/admin/useCategories.js';

interface AssignmentsPanelProps {
    assignments: IRoomAssignment[];
    categories: ICategory[];
    onAdd: (roomNumber: number, categoryId: number) => Promise<unknown>;
    onDelete: (id: number) => Promise<unknown>;
    onUpdate: (id: number, data: { category_id?: number; is_primary?: number }) => Promise<unknown>;
}

export const AssignmentsPanel: React.FC<AssignmentsPanelProps> = ({
    assignments,
    categories,
    onAdd,
    onDelete,
    onUpdate
}) => {
    const [roomNumber, setRoomNumber] = useState('');
    const [categoryId, setCategoryId] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [promotingId, setPromotingId] = useState<number | null>(null);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!roomNumber || !categoryId) return;

        setIsSubmitting(true);
        await onAdd(parseInt(roomNumber), parseInt(categoryId));
        setIsSubmitting(false);
        setRoomNumber('');
        setCategoryId('');
    };

    const handleCategoryChange = async (id: number, newCategoryId: string) => {
        if (!newCategoryId) {
            // "N/A" or clear selection
            await onDelete(id);
        } else {
            await onUpdate(id, { category_id: parseInt(newCategoryId) });
        }
    };

    const handleSetPrimary = async (assignment: IRoomAssignment) => {
        if (assignment.is_primary || promotingId !== null) return;

        setPromotingId(assignment.id);
        try {
            const result = await onUpdate(assignment.id, { is_primary: 1 });
            const failed = !!(result && typeof result === 'object' && 'success' in result && (result as { success?: boolean }).success === false);
            if (failed) {
                if (window.WFToast) window.WFToast.error('Failed to set primary category');
                return;
            }
            if (window.WFToast) window.WFToast.success(`Room ${assignment.room_number} primary updated`);
        } finally {
            setPromotingId(null);
        }
    };

    return (
        <div id="tabPanelAssignments" role="tabpanel" className="h-full flex flex-col min-h-0">
            <div className="bg-white border rounded-3xl p-6 shadow-sm flex-shrink-0 mb-6">
                <h3 className="text-sm font-black text-gray-900 uppercase tracking-wider mb-4">Add Assignment</h3>
                <form onSubmit={handleSubmit} className="flex flex-wrap items-end gap-4">
                    <div className="space-y-1 flex-1 min-w-[120px]">
                        <label className="text-[10px] font-bold text-gray-500 uppercase">Room #</label>
                        <input
                            type="number"
                            value={roomNumber}
                            onChange={(e) => setRoomNumber(e.target.value)}
                            placeholder="e.g. 1"
                            className="form-input w-full py-2 text-sm rounded-xl border-gray-100 bg-gray-50 focus:bg-white transition-all shadow-inner"
                            required
                        />
                    </div>
                    <div className="space-y-1 flex-[2] min-w-[200px]">
                        <label className="text-[10px] font-bold text-gray-500 uppercase">Category</label>
                        <select
                            value={categoryId}
                            onChange={(e) => setCategoryId(e.target.value)}
                            className="form-input w-full py-2 text-sm rounded-xl border-gray-100 bg-gray-50 focus:bg-white transition-all shadow-inner"
                            required
                        >
                            <option value="">Select Category...</option>
                            {categories?.filter(c => c.id).map((c: ICategory) => (
                                <option key={c.id} value={c.id}>{c.category}</option>
                            ))}
                        </select>
                    </div>
                    <button
                        type="submit"
                        disabled={isSubmitting || !roomNumber || !categoryId}
                        className="btn btn-primary px-6 py-2.5 bg-transparent border-0 text-[var(--brand-primary)] hover:bg-[var(--brand-primary)]/5 transition-all font-bold uppercase tracking-widest text-xs"
                    >
                        Add Assignment
                    </button>
                </form>
            </div>

            <div className="bg-white border rounded-3xl p-6 shadow-sm flex-1 min-h-0 flex flex-col">
                <h3 className="text-sm font-black text-gray-900 uppercase tracking-wider mb-4 flex-shrink-0">Room-Category Assignments</h3>
                <div className="overflow-y-auto overflow-x-auto flex-1 min-h-0">
                    <table className="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr className="bg-gray-50 border-b border-gray-100">
                                <th className="px-4 py-3 font-black text-gray-400 uppercase tracking-widest text-[9px]">Room</th>
                                <th className="px-4 py-3 font-black text-gray-400 uppercase tracking-widest text-[9px]">Category</th>
                                <th className="px-4 py-3 font-black text-gray-400 uppercase tracking-widest text-[9px] text-center">Primary</th>
                                <th className="px-4 py-3 font-black text-gray-400 uppercase tracking-widest text-[9px] text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {assignments?.map((asgn: IRoomAssignment) => (
                                <tr key={asgn.id} className={asgn.is_primary ? 'bg-blue-50/30' : ''}>
                                    <td className="px-4 py-3 font-bold text-gray-900">Room {asgn.room_number}</td>
                                    <td className="px-4 py-3 text-gray-700">
                                        <select
                                            value={asgn.category_id}
                                            onChange={(e) => handleCategoryChange(asgn.id, e.target.value)}
                                            className="bg-transparent border-0 font-medium text-gray-700 focus:ring-0 cursor-pointer p-0 m-0 hover:text-[var(--brand-primary)] transition-colors"
                                        >
                                            <option value="">N/A (Remove)</option>
                                            {categories?.filter(c => c.id).map((c: ICategory) => (
                                                <option key={c.id} value={c.id}>{c.category}</option>
                                            ))}
                                        </select>
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        <button
                                            type="button"
                                            onClick={() => handleSetPrimary(asgn)}
                                            disabled={Boolean(asgn.is_primary) || promotingId !== null}
                                            title={asgn.is_primary ? 'Primary category' : 'Set as primary category'}
                                            className={`inline-flex items-center justify-center w-6 h-6 rounded-full border transition-colors ${
                                                asgn.is_primary
                                                    ? 'bg-[var(--brand-primary)] border-[var(--brand-primary)] text-white cursor-default'
                                                    : 'bg-white border-gray-300 text-gray-400 hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)]'
                                            } disabled:opacity-60`}
                                            data-help-id="categories-assignment-set-primary"
                                        >
                                            <span className="inline-block w-2 h-2 rounded-full bg-current"></span>
                                        </button>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <button
                                            onClick={() => onDelete(asgn.id)}
                                            className="btn-icon--delete"
                                            data-help-id="categories-assignment-delete"
                                            type="button"
                                        ></button>
                                    </td>
                                </tr>
                            ))}
                            {(!assignments || assignments.length === 0) && (
                                <tr>
                                    <td colSpan={4} className="px-4 py-8 text-center text-gray-400 italic">No assignments defined</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
};
