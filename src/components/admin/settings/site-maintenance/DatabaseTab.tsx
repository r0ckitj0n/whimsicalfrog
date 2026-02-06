import React from 'react';
import { useModalContext } from '../../../../context/ModalContext.js';
import { IDatabaseInfo } from '../../../../hooks/admin/useSiteMaintenance.js';


interface DatabaseTabProps {
    isLoading: boolean;
    dbInfo: IDatabaseInfo | null;
    fetchDatabaseInfo: () => Promise<IDatabaseInfo | null>;
    compactRepairDatabase: () => Promise<{ success: boolean; message?: string } | null>;
}

export const DatabaseTab: React.FC<DatabaseTabProps> = ({
    isLoading,
    dbInfo,
    fetchDatabaseInfo,
    compactRepairDatabase
}) => {
    if (!dbInfo) return null;

    const { confirm: themedConfirm } = useModalContext();

    const handleCompactRepair = async () => {
        const confirmed = await themedConfirm({
            title: 'Database Optimization',
            message: 'Create a safety backup and optimize the database? This may take a few minutes.',
            confirmText: 'Optimize Now',
            iconKey: 'warning'
        });

        if (confirmed) {
            const res = await compactRepairDatabase();
            if (res?.success) {
                if (window.WFToast) window.WFToast.success('Database optimization complete!');
                fetchDatabaseInfo();
            }
        }
    };


    return (
        <div className="space-y-6 animate-in fade-in slide-in-from-bottom-2">
            <div className="flex justify-between items-center bg-[var(--brand-primary)]/5 p-4 border border-[var(--brand-primary)]/20 rounded-lg">
                <div>
                    <h4 className="font-bold text-[var(--brand-primary)]">Database Structure</h4>
                    <p className="text-xs text-[var(--brand-primary)]/70">{dbInfo.total_active} active tables, {dbInfo.total_backup} backup tables</p>
                </div>
                <button
                    type="button"
                    onClick={handleCompactRepair}
                    disabled={isLoading}
                    className="btn btn-primary px-4 py-2 bg-transparent border-0 text-[var(--brand-primary)] hover:bg-[var(--brand-primary)]/5 transition-all"
                    data-help-id="maintenance-db-compact"
                >
                    Compact & Repair
                </button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {Object.entries(dbInfo.organized).map(([cat, tables]) => (
                    <div key={cat} className="p-3 border rounded-lg bg-white shadow-sm">
                        <h5 className="text-xs font-black text-gray-400 uppercase tracking-widest mb-2 border-b pb-1">
                            {cat.replace(/_/g, ' ')}
                        </h5>
                        <div className="space-y-1">
                            {Array.isArray(tables) && tables.map(t => (
                                <div key={t.name} className="flex justify-between items-center text-sm">
                                    <span className="text-gray-700 font-medium">{t.name}</span>
                                    <span className="text-xs font-mono text-gray-400">{t.rows || t.row_count || 0} rows</span>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};
