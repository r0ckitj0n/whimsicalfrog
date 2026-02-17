import React, { useEffect, useMemo, useState } from 'react';
import { useModalContext } from '../../../../context/ModalContext.js';
import type { IMaintenanceBackupFile, IRestoreResult, IWebsiteBackupScope } from '../../../../types/maintenance.js';

interface RestoreTabProps {
    isLoading: boolean;
    listBackups: () => Promise<IMaintenanceBackupFile[]>;
    backupFiles: IMaintenanceBackupFile[];
    setBackupFiles: (files: IMaintenanceBackupFile[]) => void;
    restoreDatabaseBackup: (serverBackupPath: string, options?: { ignore_errors?: boolean; table_whitelist?: string[]; data_groups?: Array<'room_maps' | 'customers' | 'inventory' | 'orders'> }) => Promise<IRestoreResult>;
    restoreDatabaseBackupUpload: (backupFile: File, options?: { ignore_errors?: boolean; table_whitelist?: string[]; data_groups?: Array<'room_maps' | 'customers' | 'inventory' | 'orders'> }) => Promise<IRestoreResult>;
    restoreWebsiteBackup: (backupFile: string, scope?: IWebsiteBackupScope) => Promise<IRestoreResult>;
    restoreWebsiteBackupUpload: (backupFile: File, scope?: IWebsiteBackupScope) => Promise<IRestoreResult>;
    restoreResult: IRestoreResult | null;
    setRestoreResult: (res: IRestoreResult | null) => void;
}

const formatBytes = (bytes: number): string => {
    const value = Number(bytes || 0);
    if (value < 1024) return `${value} B`;
    if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
    if (value < 1024 * 1024 * 1024) return `${(value / (1024 * 1024)).toFixed(1)} MB`;
    return `${(value / (1024 * 1024 * 1024)).toFixed(2)} GB`;
};

const formatDate = (unixSeconds: number): string => {
    if (!unixSeconds) return 'Unknown';
    return new Date(unixSeconds * 1000).toLocaleString();
};

export const RestoreTab: React.FC<RestoreTabProps> = ({
    isLoading,
    listBackups,
    backupFiles,
    setBackupFiles,
    restoreDatabaseBackup,
    restoreDatabaseBackupUpload,
    restoreWebsiteBackup,
    restoreWebsiteBackupUpload,
    restoreResult,
    setRestoreResult
}) => {
    const { confirm } = useModalContext();
    const [selectedWebsiteBackup, setSelectedWebsiteBackup] = useState<string>('');
    const [selectedDatabaseBackup, setSelectedDatabaseBackup] = useState<string>('');
    const [localWebsiteBackup, setLocalWebsiteBackup] = useState<File | null>(null);
    const [localDatabaseBackup, setLocalDatabaseBackup] = useState<File | null>(null);
    const [websiteMode, setWebsiteMode] = useState<'full' | 'images'>('full');
    const [imageGroups, setImageGroups] = useState<Array<'items' | 'backgrounds' | 'signs'>>(['items', 'backgrounds', 'signs']);
    const [databaseMode, setDatabaseMode] = useState<'full' | 'groups'>('full');
    const [dataGroups, setDataGroups] = useState<Array<'room_maps' | 'customers' | 'inventory' | 'orders'>>(['room_maps', 'customers', 'inventory', 'orders']);

    const websiteBackups = useMemo(
        () => backupFiles.filter((f) => f.type === 'website'),
        [backupFiles]
    );
    const databaseBackups = useMemo(
        () => backupFiles.filter((f) => f.type === 'database'),
        [backupFiles]
    );

    const refreshBackups = async () => {
        const files = await listBackups();
        setBackupFiles(files);
    };

    const toggleImageGroup = (group: 'items' | 'backgrounds' | 'signs') => {
        setImageGroups((prev) => (prev.includes(group) ? prev.filter((v) => v !== group) : [...prev, group]));
    };

    const toggleDataGroup = (group: 'room_maps' | 'customers' | 'inventory' | 'orders') => {
        setDataGroups((prev) => (prev.includes(group) ? prev.filter((v) => v !== group) : [...prev, group]));
    };

    const selectedDataGroups = useMemo(
        () => (databaseMode === 'groups' ? dataGroups : []),
        [databaseMode, dataGroups]
    );

    useEffect(() => {
        if (!backupFiles.length) {
            void refreshBackups();
        }
    }, []);

    const handleRestoreWebsite = async () => {
        if (!selectedWebsiteBackup && !localWebsiteBackup) {
            window.WFToast?.error('Select a server backup or choose a local website backup file first.');
            return;
        }
        if (websiteMode === 'images' && imageGroups.length === 0) {
            window.WFToast?.error('Select at least one image group to restore.');
            return;
        }

        const scope: IWebsiteBackupScope = websiteMode === 'images'
            ? { mode: 'images', image_groups: imageGroups }
            : { mode: 'full' };

        const confirmed = await confirm({
            title: 'Restore Website Files',
            message: localWebsiteBackup
                ? `This uploads and restores ${localWebsiteBackup.name}. Current website files will be overwritten after a safety backup is created. Continue?`
                : 'This overwrites files in the current site with the selected backup. Continue only if you intend to roll back the code and assets.',
            confirmText: 'Restore Website',
            cancelText: 'Cancel',
            confirmStyle: 'danger',
            iconKey: 'warning'
        });
        if (!confirmed) return;

        const result = localWebsiteBackup
            ? await restoreWebsiteBackupUpload(localWebsiteBackup, scope)
            : await restoreWebsiteBackup(selectedWebsiteBackup, scope);
        setRestoreResult(result);

        if (result.success) {
            window.WFToast?.success('Website backup restored successfully.');
            setLocalWebsiteBackup(null);
        } else {
            window.WFToast?.error(result.error || 'Website restore failed.');
        }
    };

    const handleRestoreDatabase = async () => {
        if (!selectedDatabaseBackup && !localDatabaseBackup) {
            window.WFToast?.error('Select a server backup or choose a local backup file first.');
            return;
        }
        if (databaseMode === 'groups' && selectedDataGroups.length === 0) {
            window.WFToast?.error('Select at least one data group to restore.');
            return;
        }

        const confirmed = await confirm({
            title: 'Restore Database',
            message: localDatabaseBackup
                ? `This uploads and restores ${localDatabaseBackup.name}. Existing database data may be replaced. Continue?`
                : 'This can replace table data in your current database. Continue only if you have a current backup and want to roll back data.',
            confirmText: 'Restore Database',
            cancelText: 'Cancel',
            confirmStyle: 'danger',
            iconKey: 'warning'
        });
        if (!confirmed) return;

        const result = localDatabaseBackup
            ? await restoreDatabaseBackupUpload(localDatabaseBackup, { data_groups: selectedDataGroups })
            : await restoreDatabaseBackup(selectedDatabaseBackup, { data_groups: selectedDataGroups });
        setRestoreResult(result);

        if (result.success) {
            window.WFToast?.success('Database backup restored successfully.');
            setLocalDatabaseBackup(null);
        } else {
            window.WFToast?.error(result.error || 'Database restore failed.');
        }
    };

    return (
        <div className="space-y-6 animate-in fade-in slide-in-from-bottom-2">
            <div className="flex items-center justify-between p-4 rounded-xl border border-slate-200 bg-slate-50">
                <div>
                    <h4 className="font-bold text-slate-800">Available Backup Files</h4>
                    <p className="text-xs text-slate-500">{backupFiles.length} file(s) detected in /backups</p>
                </div>
                <button
                    type="button"
                    className="btn btn-secondary px-4 py-2 text-xs font-black uppercase tracking-widest"
                    onClick={() => void refreshBackups()}
                    disabled={isLoading}
                    data-help-id="maintenance-refresh-backups"
                >
                    Refresh List
                </button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="p-6 border-2 border-dashed rounded-xl">
                    <h4 className="font-bold text-gray-800">Website Backup Restore</h4>
                    <p className="text-sm text-gray-500 mt-1 mb-4">Restore site files from a server backup list or upload a `.tar.gz` backup from your computer.</p>
                    <div className="mb-4 p-3 rounded-lg border border-slate-200 bg-slate-50 space-y-2 text-sm">
                        <label className="flex items-center gap-2">
                            <input type="radio" checked={websiteMode === 'full'} onChange={() => setWebsiteMode('full')} disabled={isLoading} />
                            <span>Restore all website files from archive</span>
                        </label>
                        <label className="flex items-center gap-2">
                            <input type="radio" checked={websiteMode === 'images'} onChange={() => setWebsiteMode('images')} disabled={isLoading} />
                            <span>Restore only selected image groups</span>
                        </label>
                        {websiteMode === 'images' && (
                            <div className="grid grid-cols-1 gap-1 pt-1">
                                <label className="flex items-center gap-2"><input type="checkbox" checked={imageGroups.includes('items')} onChange={() => toggleImageGroup('items')} disabled={isLoading} />Item images</label>
                                <label className="flex items-center gap-2"><input type="checkbox" checked={imageGroups.includes('backgrounds')} onChange={() => toggleImageGroup('backgrounds')} disabled={isLoading} />Background images</label>
                                <label className="flex items-center gap-2"><input type="checkbox" checked={imageGroups.includes('signs')} onChange={() => toggleImageGroup('signs')} disabled={isLoading} />Sign images</label>
                            </div>
                        )}
                    </div>
                    <select
                        value={selectedWebsiteBackup}
                        onChange={(e) => setSelectedWebsiteBackup(e.target.value)}
                        className="w-full border border-slate-300 rounded-lg p-2 text-sm"
                        disabled={isLoading}
                    >
                        <option value="">Select website backup...</option>
                        {websiteBackups.map((file) => (
                            <option key={file.rel} value={file.rel}>
                                {file.name} • {formatBytes(file.size)} • {formatDate(file.mtime)}
                            </option>
                        ))}
                    </select>
                    <div className="mt-4">
                        <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                            Or choose local website backup
                        </label>
                        <input
                            type="file"
                            accept=".tar.gz,application/gzip,application/x-gzip"
                            disabled={isLoading}
                            onChange={(e) => {
                                const file = e.target.files?.[0] ?? null;
                                setLocalWebsiteBackup(file);
                            }}
                            className="block w-full text-xs text-slate-700 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border file:border-slate-300 file:bg-white file:text-slate-700 file:font-semibold hover:file:bg-slate-50"
                        />
                        {localWebsiteBackup && (
                            <p className="mt-2 text-xs text-slate-500">
                                Selected local file: <span className="font-semibold text-slate-700">{localWebsiteBackup.name}</span>
                            </p>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={() => void handleRestoreWebsite()}
                        disabled={isLoading || (!selectedWebsiteBackup && !localWebsiteBackup) || (websiteMode === 'images' && imageGroups.length === 0)}
                        className="mt-4 w-full btn-text-secondary py-3 disabled:opacity-60"
                        data-help-id="maintenance-restore-website"
                    >
                        {isLoading ? 'Restoring Website...' : localWebsiteBackup ? 'Upload & Restore Local Website Backup' : 'Restore Website Backup'}
                    </button>
                </div>

                <div className="p-6 border-2 border-dashed rounded-xl">
                    <h4 className="font-bold text-gray-800">Database Backup Restore</h4>
                    <p className="text-sm text-gray-500 mt-1 mb-4">Restore SQL data from a server backup list or upload a `.sql` / `.sql.gz` file from your computer.</p>
                    <div className="mb-4 p-3 rounded-lg border border-slate-200 bg-slate-50 space-y-2 text-sm">
                        <label className="flex items-center gap-2">
                            <input type="radio" checked={databaseMode === 'full'} onChange={() => setDatabaseMode('full')} disabled={isLoading} />
                            <span>Restore full SQL backup</span>
                        </label>
                        <label className="flex items-center gap-2">
                            <input type="radio" checked={databaseMode === 'groups'} onChange={() => setDatabaseMode('groups')} disabled={isLoading} />
                            <span>Restore selected data groups only</span>
                        </label>
                        {databaseMode === 'groups' && (
                            <div className="grid grid-cols-1 gap-1 pt-1">
                                <label className="flex items-center gap-2"><input type="checkbox" checked={dataGroups.includes('room_maps')} onChange={() => toggleDataGroup('room_maps')} disabled={isLoading} />Room maps</label>
                                <label className="flex items-center gap-2"><input type="checkbox" checked={dataGroups.includes('customers')} onChange={() => toggleDataGroup('customers')} disabled={isLoading} />Customers</label>
                                <label className="flex items-center gap-2"><input type="checkbox" checked={dataGroups.includes('inventory')} onChange={() => toggleDataGroup('inventory')} disabled={isLoading} />Inventory</label>
                                <label className="flex items-center gap-2"><input type="checkbox" checked={dataGroups.includes('orders')} onChange={() => toggleDataGroup('orders')} disabled={isLoading} />Orders</label>
                            </div>
                        )}
                    </div>
                    <select
                        value={selectedDatabaseBackup}
                        onChange={(e) => setSelectedDatabaseBackup(e.target.value)}
                        className="w-full border border-slate-300 rounded-lg p-2 text-sm"
                        disabled={isLoading}
                    >
                        <option value="">Select database backup...</option>
                        {databaseBackups.map((file) => (
                            <option key={file.rel} value={file.rel}>
                                {file.name} • {formatBytes(file.size)} • {formatDate(file.mtime)}
                            </option>
                        ))}
                    </select>
                    <div className="mt-4">
                        <label className="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                            Or choose local backup file
                        </label>
                        <input
                            type="file"
                            accept=".sql,.gz,.sql.gz,application/sql,application/gzip"
                            disabled={isLoading}
                            onChange={(e) => {
                                const file = e.target.files?.[0] ?? null;
                                setLocalDatabaseBackup(file);
                            }}
                            className="block w-full text-xs text-slate-700 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border file:border-slate-300 file:bg-white file:text-slate-700 file:font-semibold hover:file:bg-slate-50"
                        />
                        {localDatabaseBackup && (
                            <p className="mt-2 text-xs text-slate-500">
                                Selected local file: <span className="font-semibold text-slate-700">{localDatabaseBackup.name}</span>
                            </p>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={() => void handleRestoreDatabase()}
                        disabled={isLoading || (!selectedDatabaseBackup && !localDatabaseBackup) || (databaseMode === 'groups' && selectedDataGroups.length === 0)}
                        className="mt-4 w-full btn-text-primary py-3 disabled:opacity-60"
                        data-help-id="maintenance-restore-database"
                    >
                        {isLoading ? 'Restoring Database...' : localDatabaseBackup ? 'Upload & Restore Local Backup' : 'Restore Database Backup'}
                    </button>
                </div>
            </div>

            {restoreResult && (
                <div className={`p-4 rounded-xl border ${restoreResult.success ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50'}`}>
                    <h5 className={`font-bold ${restoreResult.success ? 'text-emerald-700' : 'text-rose-700'}`}>
                        {restoreResult.success ? 'Restore Complete' : 'Restore Failed'}
                    </h5>
                    <div className={`mt-2 text-xs font-mono space-y-1 ${restoreResult.success ? 'text-emerald-700/90' : 'text-rose-700/90'}`}>
                        {restoreResult.message && <p>{restoreResult.message}</p>}
                        {restoreResult.error && <p>{restoreResult.error}</p>}
                        {restoreResult.pre_restore_backup && <p><strong>Safety Backup:</strong> {restoreResult.pre_restore_backup}</p>}
                        {restoreResult.restored_file && <p><strong>File:</strong> {restoreResult.restored_file}</p>}
                        {restoreResult.preflight && (
                            <p>
                                <strong>Preflight:</strong> {restoreResult.preflight.statements} statements, {restoreResult.preflight.tables_touched} table(s) touched
                            </p>
                        )}
                        {typeof restoreResult.extracted_files === 'number' && <p><strong>Extracted Files:</strong> {restoreResult.extracted_files}</p>}
                        {typeof restoreResult.tables_restored === 'number' && <p><strong>Tables Restored:</strong> {restoreResult.tables_restored}</p>}
                        {typeof restoreResult.records_restored === 'number' && <p><strong>Records Restored:</strong> {restoreResult.records_restored}</p>}
                        {typeof restoreResult.statements_executed === 'number' && <p><strong>Statements:</strong> {restoreResult.statements_executed}</p>}
                    </div>
                </div>
            )}
        </div>
    );
};
