import React, { useState } from 'react';
import { ICostItem } from '../../../../types/index.js';
import { useAuthContext } from '../../../../context/AuthContext.js';

interface FactorListProps {
    category: string;
    factors: ICostItem[];
    onDelete: (category: string, id: number | string) => void;
    onUpdate: (category: string, id: number | string, cost: number, newLabel: string) => Promise<boolean>;
    onAdd: (category: string) => void;
    onRefresh: () => void;
}

interface EditingState {
    id: number | string;
    cost: string;
    label: string;
}

// Generate auto-label with user name and timestamp
const generateAutoLabel = (username: string): string => {
    const now = new Date();
    const timestamp = now.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
    return `${username} - ${timestamp}`;
};

export const FactorList: React.FC<FactorListProps> = ({
    category,
    factors,
    onDelete,
    onUpdate,
    onAdd,
    onRefresh
}) => {
    const { user } = useAuthContext();
    const [editing, setEditing] = useState<EditingState | null>(null);
    const [isSaving, setIsSaving] = useState(false);

    const startEditing = (factor: ICostItem) => {
        setEditing({
            id: factor.id,
            cost: Number(factor.cost).toFixed(2),
            label: factor.label || ''
        });
    };

    const cancelEditing = () => {
        setEditing(null);
    };

    const saveEditing = async () => {
        if (!editing) return;

        const cost = parseFloat(editing.cost);
        if (isNaN(cost)) {
            if (window.WFToast) window.WFToast.error('Invalid cost amount');
            return;
        }

        // Auto-generate label with username and timestamp
        const username = user?.username || user?.email || 'Admin';
        const newLabel = generateAutoLabel(username);

        setIsSaving(true);
        const success = await onUpdate(category, editing.id, cost, newLabel);
        setIsSaving(false);

        if (success) {
            setEditing(null);
            if (window.WFToast) window.WFToast.success('Factor updated');
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveEditing();
        } else if (e.key === 'Escape') {
            cancelEditing();
        }
    };

    return (
        <div className="space-y-3">
            {factors.map((factor) => {
                const isEditing = editing !== null && editing.id === factor.id;
                const displayLabel = factor.label || `${category} Cost`;

                return (
                    <div
                        key={factor.id}
                        className={`flex items-center justify-between p-4 border rounded-2xl bg-white hover:border-[var(--brand-primary)]/20 group transition-all shadow-sm ${isEditing ? 'ring-2 ring-blue-200 border-blue-300' : ''}`}
                    >
                        <div className="flex items-center gap-4 flex-1">
                            <div className="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center text-gray-400 group-hover:bg-[var(--brand-primary)]/5 group-hover:text-[var(--brand-primary)] transition-colors border border-black/5">
                                <span className="text-xl">ℹ️</span>
                            </div>
                            <div className="flex-1">
                                <div className={`text-base font-bold ${factor.label ? 'text-gray-900' : 'text-gray-400 italic'}`}>
                                    {displayLabel}
                                </div>
                                <div className="text-[11px] font-black text-gray-400 uppercase tracking-widest">{category} Factor</div>
                            </div>
                        </div>
                        <div className="flex items-center gap-4">
                            <div className="text-right">
                                {isEditing ? (
                                    <div className="flex items-center gap-1">
                                        <span className="text-xl font-black text-gray-500">$</span>
                                        <input
                                            type="text"
                                            value={editing.cost}
                                            onChange={(e) => setEditing({ ...editing, cost: e.target.value })}
                                            onKeyDown={handleKeyDown}
                                            className="text-xl font-black text-gray-900 font-mono w-24 px-2 py-1 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200 text-right"
                                            placeholder="0.00"
                                            autoFocus
                                        />
                                    </div>
                                ) : (
                                    <button
                                        onClick={() => startEditing(factor)}
                                        className="text-xl font-black text-gray-900 font-mono hover:text-blue-600 hover:bg-blue-50 px-2 py-1 rounded-lg transition-colors cursor-pointer"
                                        data-help-id="factor-edit"
                                    >
                                        ${Number(factor.cost).toFixed(2)}
                                    </button>
                                )}
                            </div>
                            {isEditing ? (
                                <div className="flex items-center gap-1">
                                    <button
                                        onClick={saveEditing}
                                        disabled={isSaving}
                                        className="admin-action-btn btn-icon--save"
                                        type="button"
                                        data-help-id="modal-save"
                                    />
                                    <button
                                        onClick={cancelEditing}
                                        className="admin-action-btn btn-icon--close"
                                        type="button"
                                        data-help-id="modal-cancel"
                                    />
                                </div>
                            ) : (
                                <button
                                    onClick={() => onDelete(category, factor.id)}
                                    className="admin-action-btn btn-icon--delete"
                                    type="button"
                                    data-help-id="factor-delete"
                                />
                            )}
                        </div>
                    </div>
                );
            })}
            {factors.length === 0 && (
                <div className="py-24 text-center space-y-4 border-2 border-dashed border-gray-100 rounded-[2rem]">
                    <div className="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto">
                        <span className="text-3xl opacity-20">🧮</span>
                    </div>
                    <p className="text-sm text-gray-400 font-medium italic">No {category} factors defined yet.</p>
                </div>
            )}
        </div>
    );
};
