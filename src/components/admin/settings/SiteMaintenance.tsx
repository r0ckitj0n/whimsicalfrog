import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { useModalContext } from '../../../context/ModalContext.js';
import { useSiteMaintenance, IBackupDetails, IScanResult } from '../../../hooks/admin/useSiteMaintenance.js';

import { StatusTab } from './site-maintenance/StatusTab.js';
import { DatabaseTab } from './site-maintenance/DatabaseTab.js';
import { BackupTab } from './site-maintenance/BackupTab.js';

export type TabType = 'status' | 'database' | 'backup';

interface SiteMaintenanceProps {
    onClose?: () => void;
    title?: string;
    initialTab?: TabType;
}

export const SiteMaintenance: React.FC<SiteMaintenanceProps> = ({ onClose, initialTab = 'status', title }) => {
    const {
        systemConfig,
        dbInfo,
        isLoading,
        error,
        fetchSystemConfig,
        fetchDatabaseInfo,
        executeBackup,
        compactRepairDatabase
    } = useSiteMaintenance();

    const [activeTab, setActiveTab] = useState<TabType>(initialTab);
    const [backupResult, setBackupResult] = useState<IBackupDetails | null>(null);

    useEffect(() => {
        // Only fetch if data is missing
        if (!systemConfig) fetchSystemConfig();
        if (!dbInfo) fetchDatabaseInfo();
    }, [fetchSystemConfig, fetchDatabaseInfo, systemConfig, dbInfo]);




    const tabs: { id: TabType; label: string }[] = [
        { id: 'status', label: 'System Status' },
        { id: 'database', label: 'Database' },
        { id: 'backup', label: 'Backups' }
    ];

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
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1200px] max-w-[95vw] h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header border-b border-gray-100 bg-white sticky top-0 z-20 px-6 py-4 flex items-start justify-between">
                    <div className="flex flex-col gap-4">
                        <div className="flex items-center gap-4">
                            <div className="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-xl">
                                🛡️
                            </div>
                            <div>
                                <h2 className="text-xl font-black text-slate-800 tracking-tight">{title || 'Site Maintenance'}</h2>
                                <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">System Health & Data Management</p>
                            </div>
                        </div>

                        <div className="wf-tabs bg-slate-50/50 rounded-xl p-1 border border-slate-100 flex items-center gap-2 self-start">
                            {tabs.map(tab => (
                                <button
                                    key={tab.id}
                                    onClick={() => setActiveTab(tab.id)}
                                    className={`wf-tab ${activeTab === tab.id ? 'is-active' : ''}`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="common-close"
                        />
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto">
                    <div className="p-10">
                        {error && (
                            <div className="p-4 mb-6 bg-red-50 border border-red-100 text-red-600 text-xs font-bold rounded-2xl flex items-center gap-3 animate-in fade-in">
                                <span className="text-xl">⚠️</span>
                                <div>
                                    <p className="font-black uppercase tracking-tight">Maintenance Error</p>
                                    <p className="opacity-90">{error}</p>
                                </div>
                            </div>
                        )}

                        <div className="animate-in fade-in slide-in-from-bottom-2 duration-300">
                            {activeTab === 'status' && <StatusTab systemConfig={systemConfig} />}

                            {activeTab === 'database' && (
                                <DatabaseTab
                                    isLoading={isLoading}
                                    dbInfo={dbInfo}
                                    fetchDatabaseInfo={fetchDatabaseInfo}
                                    compactRepairDatabase={compactRepairDatabase}
                                />
                            )}

                            {activeTab === 'backup' && (
                                <BackupTab
                                    isLoading={isLoading}
                                    executeBackup={executeBackup}
                                    backupResult={backupResult}
                                    setBackupResult={setBackupResult}
                                />
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
