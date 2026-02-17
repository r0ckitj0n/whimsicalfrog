import React, { useMemo, useState } from 'react';
import { IBackupDetails } from '../../../../hooks/admin/useSiteMaintenance.js';
import type { IDatabaseBackupScope, IWebsiteBackupScope } from '../../../../types/maintenance.js';

interface BackupTabProps {
    isLoading: boolean;
    executeBackup: (type: 'full' | 'database', scope?: IWebsiteBackupScope | IDatabaseBackupScope) => Promise<IBackupDetails | null>;
    backupResult: IBackupDetails | null;
    setBackupResult: (res: IBackupDetails | null) => void;
}

const IMAGE_GROUPS = [
    { id: 'items', label: 'Item Images' },
    { id: 'backgrounds', label: 'Background Images' },
    { id: 'signs', label: 'Sign Images' }
] as const;

const DATA_GROUPS = [
    { id: 'room_maps', label: 'Room Maps' },
    { id: 'customers', label: 'Customers' },
    { id: 'inventory', label: 'Inventory' },
    { id: 'orders', label: 'Orders' }
] as const;

export const BackupTab: React.FC<BackupTabProps> = ({
    isLoading,
    executeBackup,
    backupResult,
    setBackupResult
}) => {
    const [websiteMode, setWebsiteMode] = useState<'full' | 'images'>('full');
    const [imageGroups, setImageGroups] = useState<Array<'items' | 'backgrounds' | 'signs'>>(['items', 'backgrounds', 'signs']);
    const [databaseMode, setDatabaseMode] = useState<'full' | 'tables'>('full');
    const [dataGroups, setDataGroups] = useState<Array<'room_maps' | 'customers' | 'inventory' | 'orders'>>(['room_maps', 'customers', 'inventory', 'orders']);

    const imageGroupLabel = useMemo(() => {
        if (imageGroups.length === 0) return 'No image groups selected';
        return imageGroups.join(', ');
    }, [imageGroups]);

    const dataGroupLabel = useMemo(() => {
        if (dataGroups.length === 0) return 'No data groups selected';
        return dataGroups.join(', ');
    }, [dataGroups]);

    const toggleImageGroup = (group: 'items' | 'backgrounds' | 'signs') => {
        setImageGroups((prev) => (
            prev.includes(group) ? prev.filter((v) => v !== group) : [...prev, group]
        ));
    };

    const toggleDataGroup = (group: 'room_maps' | 'customers' | 'inventory' | 'orders') => {
        setDataGroups((prev) => (
            prev.includes(group) ? prev.filter((v) => v !== group) : [...prev, group]
        ));
    };

    const handleWebsiteBackup = async () => {
        if (websiteMode === 'images' && imageGroups.length === 0) {
            window.WFToast?.error('Select at least one image group for image-only backup.');
            return;
        }

        const scope: IWebsiteBackupScope | undefined = websiteMode === 'images'
            ? { mode: 'images', image_groups: imageGroups }
            : { mode: 'full' };

        window.WFToast?.info(websiteMode === 'images' ? 'Starting scoped image backup...' : 'Starting full website backup...');
        const res = await executeBackup('full', scope);
        if (res?.success) {
            setBackupResult(res);
            window.WFToast?.success('Website backup completed successfully.');
        } else {
            window.WFToast?.error(res?.error || 'Website backup failed.');
        }
    };

    const handleDatabaseBackup = async () => {
        if (databaseMode === 'tables' && dataGroups.length === 0) {
            window.WFToast?.error('Select at least one data group for scoped database backup.');
            return;
        }

        const scope: IDatabaseBackupScope | undefined = databaseMode === 'tables'
            ? { mode: 'tables', data_groups: dataGroups }
            : { mode: 'full' };

        window.WFToast?.info(databaseMode === 'tables' ? 'Starting scoped database backup...' : 'Starting full database backup...');
        const res = await executeBackup('database', scope);
        if (res?.success) {
            setBackupResult(res);
            window.WFToast?.success('Database backup completed successfully.');
        } else {
            window.WFToast?.error(res?.error || 'Database backup failed.');
        }
    };

    return (
        <div className="space-y-6 animate-in fade-in slide-in-from-bottom-2">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="p-6 border-2 border-dashed rounded-xl text-left space-y-4">
                    <h4 className="font-bold text-gray-800">Website Files Backup</h4>
                    <p className="text-sm text-gray-500">Create a full site archive or save only selected image folders.</p>

                    <div className="space-y-2 text-sm">
                        <label className="flex items-center gap-2">
                            <input type="radio" checked={websiteMode === 'full'} onChange={() => setWebsiteMode('full')} disabled={isLoading} />
                            <span>Full site files</span>
                        </label>
                        <label className="flex items-center gap-2">
                            <input type="radio" checked={websiteMode === 'images'} onChange={() => setWebsiteMode('images')} disabled={isLoading} />
                            <span>Images only</span>
                        </label>
                    </div>

                    {websiteMode === 'images' && (
                        <div className="grid grid-cols-1 gap-2 p-3 rounded-lg border border-slate-200 bg-slate-50">
                            {IMAGE_GROUPS.map((group) => (
                                <label key={group.id} className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={imageGroups.includes(group.id)}
                                        onChange={() => toggleImageGroup(group.id)}
                                        disabled={isLoading}
                                    />
                                    <span>{group.label}</span>
                                </label>
                            ))}
                            <p className="text-xs text-slate-500 pt-1">Selected: {imageGroupLabel}</p>
                        </div>
                    )}

                    <button
                        type="button"
                        onClick={() => void handleWebsiteBackup()}
                        disabled={isLoading || (websiteMode === 'images' && imageGroups.length === 0)}
                        className="w-full btn-text-primary py-3"
                        data-help-id="maintenance-backup-full"
                    >
                        {isLoading ? 'Creating Website Backup...' : 'Create Website Backup'}
                    </button>
                </div>

                <div className="p-6 border-2 border-dashed rounded-xl text-left space-y-4">
                    <h4 className="font-bold text-gray-800">Database Backup</h4>
                    <p className="text-sm text-gray-500">Create a full SQL dump or save only selected business data groups.</p>

                    <div className="space-y-2 text-sm">
                        <label className="flex items-center gap-2">
                            <input type="radio" checked={databaseMode === 'full'} onChange={() => setDatabaseMode('full')} disabled={isLoading} />
                            <span>Full database</span>
                        </label>
                        <label className="flex items-center gap-2">
                            <input type="radio" checked={databaseMode === 'tables'} onChange={() => setDatabaseMode('tables')} disabled={isLoading} />
                            <span>Selected data groups</span>
                        </label>
                    </div>

                    {databaseMode === 'tables' && (
                        <div className="grid grid-cols-1 gap-2 p-3 rounded-lg border border-slate-200 bg-slate-50">
                            {DATA_GROUPS.map((group) => (
                                <label key={group.id} className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={dataGroups.includes(group.id)}
                                        onChange={() => toggleDataGroup(group.id)}
                                        disabled={isLoading}
                                    />
                                    <span>{group.label}</span>
                                </label>
                            ))}
                            <p className="text-xs text-slate-500 pt-1">Selected: {dataGroupLabel}</p>
                        </div>
                    )}

                    <button
                        type="button"
                        onClick={() => void handleDatabaseBackup()}
                        disabled={isLoading || (databaseMode === 'tables' && dataGroups.length === 0)}
                        className="w-full btn-text-secondary py-3"
                        data-help-id="maintenance-backup-db"
                    >
                        {isLoading ? 'Creating Database Backup...' : 'Create Database Backup'}
                    </button>
                </div>
            </div>

            {backupResult && (
                <div className="p-4 bg-[var(--brand-accent)]/5 border border-[var(--brand-accent)]/20 rounded-lg animate-in zoom-in-95 duration-300">
                    <div className="flex items-center gap-3 mb-2">
                        <h5 className="font-bold text-[var(--brand-accent)]">Backup Succeeded</h5>
                    </div>
                    <div className="text-sm text-[var(--brand-accent)]/80 font-mono space-y-1 break-all">
                        <p>
                            <strong>File:</strong>{' '}
                            <a
                                href={`/api/download_backup.php?file=${encodeURIComponent(backupResult.filename || '')}`}
                                download={backupResult.filename}
                                className="underline hover:opacity-70 transition-opacity"
                                data-help-id="maintenance-download-backup"
                            >
                                {backupResult.filename || 'N/A'}
                            </a>
                        </p>
                        <p><strong>Size:</strong> {((backupResult.size || 0) / 1024 / 1024).toFixed(2)} MB</p>
                        <p><strong>Path:</strong> {backupResult.filepath || 'N/A'}</p>
                    </div>
                </div>
            )}
        </div>
    );
};
