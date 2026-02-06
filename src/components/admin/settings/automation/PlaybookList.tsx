import React from 'react';
import { IAutomationPlaybook } from '../../../../hooks/admin/useAutomation.js';

interface PlaybookListProps {
    playbooks: IAutomationPlaybook[];
    onToggleActive: (index: number) => void;
    onEdit: (index: number) => void;
    onDelete: (index: number) => void;
}

export const PlaybookList: React.FC<PlaybookListProps> = ({
    playbooks,
    onToggleActive,
    onEdit,
    onDelete
}) => {
    return (
        <div className="grid grid-cols-1 gap-4">
            {playbooks.map((p, i) => (
                <div key={i} className={`border rounded-xl p-5 transition-all ${p.active ? 'bg-[var(--brand-secondary)]/5 border-[var(--brand-secondary)]/10' : 'bg-gray-50 border-gray-200 opacity-75'}`}>
                    <div className="flex items-start justify-between mb-4">
                        <div className="flex items-start gap-4">
                            <div className={`mt-1 p-2 rounded-lg ${p.active ? 'bg-[var(--brand-secondary)] text-white' : 'bg-gray-300 text-gray-100'}`}>
                                <div className="admin-action-btn btn-icon--power-on" />
                            </div>
                            <div>
                                <h3 className="font-bold text-gray-900">{p.name}</h3>
                                <div className="flex items-center gap-3 mt-1">
                                    <span className="text-[10px] font-black uppercase tracking-widest text-[var(--brand-secondary)] bg-[var(--brand-secondary)]/10 px-2 py-0.5 rounded">
                                        {p.cadence}
                                    </span>
                                    <span className={`text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded ${p.active ? 'bg-[var(--brand-accent)]/10 text-[var(--brand-accent)]' : 'bg-gray-200 text-gray-600'}`}>
                                        {p.active ? 'Active' : 'Disabled'}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <button
                                onClick={() => onToggleActive(i)}
                                className={`admin-action-btn ${p.active ? 'btn-icon--power-on' : 'btn-icon--power-off'}`}
                                data-help-id={p.active ? 'common-disable' : 'common-enable'}
                            />
                            <button
                                onClick={() => onEdit(i)}
                                className="admin-action-btn btn-icon--edit"
                                data-help-id="common-edit"
                            />
                            <button
                                onClick={() => onDelete(i)}
                                className="admin-action-btn btn-icon--delete"
                                data-help-id="common-delete"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                        <div className="space-y-1">
                            <div className="text-[10px] font-bold text-gray-400 uppercase">Trigger Event</div>
                            <p className="text-gray-700 leading-relaxed">{p.trigger}</p>
                        </div>
                        <div className="space-y-1">
                            <div className="text-[10px] font-bold text-gray-400 uppercase">System Action</div>
                            <p className="text-gray-700 leading-relaxed font-mono text-xs bg-black/5 p-2 rounded">{p.action}</p>
                        </div>
                    </div>
                    {p.status && (
                        <div className="mt-4 pt-4 border-t border-[var(--brand-secondary)]/10 flex items-center gap-2 text-xs italic text-[var(--brand-secondary)]">
                            {p.status}
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
};
