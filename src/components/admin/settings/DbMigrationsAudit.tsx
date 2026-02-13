import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useDbMigrationsAudit } from '../../../hooks/admin/useDbMigrationsAudit.js';

interface DbMigrationsAuditProps {
    onClose?: () => void;
    title?: string;
}

export const DbMigrationsAudit: React.FC<DbMigrationsAuditProps> = ({ onClose, title }) => {
    const { data, isLoading, error, run } = useDbMigrationsAudit();

    useEffect(() => {
        void run();
    }, [run]);

    const missingTables = data?.missing_tables || [];
    const missingColumns = data?.missing_columns || [];
    const hasIssues = missingTables.length > 0 || missingColumns.length > 0;

    const modalContent = (
        <div
            className="admin-modal-overlay over-header show topmost"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose?.();
            }}
        >
            <div
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1100px] max-w-[95vw] h-[85vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">🧱</span> {title || 'DB Migrations Audit'}
                    </h2>
                    <div className="flex-1" />
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            className="admin-action-btn btn-icon--refresh"
                            onClick={run}
                            disabled={isLoading}
                            data-help-id="db-migrations-audit-refresh"
                        />
                        <button
                            type="button"
                            className="admin-action-btn btn-icon--close"
                            onClick={onClose}
                            data-help-id="common-close"
                        />
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto">
                    <div className="p-10 space-y-6">
                        {(error || (!data?.success && data)) && (
                            <div className="p-4 bg-red-50 border border-red-100 text-red-700 text-xs font-bold rounded-2xl">
                                {error || data?.error || data?.message || 'Audit failed'}
                            </div>
                        )}

                        {isLoading && !data && (
                            <div className="flex flex-col items-center justify-center p-12 text-gray-500 animate-pulse">
                                <span className="text-4xl mb-4">🔍</span>
                                <p className="font-black uppercase tracking-widest text-[10px]">Scanning schema drift...</p>
                            </div>
                        )}

                        {data?.success && (
                            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                                <section className="lg:col-span-1 bg-slate-50 border border-slate-100 rounded-2xl p-5 space-y-2">
                                    <div className="text-[10px] font-black uppercase tracking-widest text-slate-400">Environment</div>
                                    <div className="text-sm font-bold text-slate-800">DB: {data.db_name || 'Unknown'}</div>
                                    <div className="text-xs text-slate-600">Generated: {data.generated_at || 'Unknown'}</div>
                                    <div className="text-xs text-slate-600">Expected tables: {data.expected_table_count ?? 'Unknown'}</div>
                                </section>

                                <section className="lg:col-span-2 space-y-4">
                                    <div className={`p-5 rounded-2xl border ${hasIssues ? 'bg-amber-50 border-amber-100' : 'bg-emerald-50 border-emerald-100'}`}>
                                        <div className="text-[10px] font-black uppercase tracking-widest text-slate-500">Summary</div>
                                        <div className="text-sm font-black text-slate-800 mt-1">
                                            {hasIssues ? 'Schema drift detected' : 'No missing tables/columns detected'}
                                        </div>
                                        {hasIssues && (
                                            <div className="text-xs text-slate-600 mt-1">
                                                Missing tables: {missingTables.length}. Tables with missing columns: {missingColumns.length}.
                                            </div>
                                        )}
                                    </div>

                                    {missingTables.length > 0 && (
                                        <div className="bg-white border border-slate-100 rounded-2xl p-5">
                                            <div className="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3">Missing Tables</div>
                                            <ul className="text-sm font-mono text-slate-800 space-y-1">
                                                {missingTables.map((t) => (
                                                    <li key={t}>{t}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}

                                    {missingColumns.length > 0 && (
                                        <div className="bg-white border border-slate-100 rounded-2xl p-5">
                                            <div className="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3">Missing Columns</div>
                                            <div className="space-y-4">
                                                {missingColumns.map((entry) => (
                                                    <div key={entry.table} className="border border-slate-100 rounded-xl p-4">
                                                        <div className="text-sm font-black text-slate-800 mb-2">{entry.table}</div>
                                                        <ul className="text-xs font-mono text-slate-700 space-y-1">
                                                            {(entry.missing || []).map((c) => (
                                                                <li key={c}>{c}</li>
                                                            ))}
                                                        </ul>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </section>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};

