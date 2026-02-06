import React from 'react';
import { IBackupDetails } from '../../../../hooks/admin/useSiteMaintenance.js';

interface BackupTabProps {
    isLoading: boolean;
    executeBackup: (type: 'full' | 'database') => Promise<IBackupDetails | null>;
    backupResult: IBackupDetails | null;
    setBackupResult: (res: IBackupDetails | null) => void;
}

export const BackupTab: React.FC<BackupTabProps> = ({
    isLoading,
    executeBackup,
    backupResult,
    setBackupResult
}) => {
    const handleBackup = async (type: 'full' | 'database') => {
        const typeLabel = type === 'full' ? 'Full Site Backup' : 'Database Backup';

        if (window.WFToast) window.WFToast.info(`Starting ${typeLabel.toLowerCase()}...`);

        try {
            const res = await executeBackup(type);
            if (res?.success) {
                setBackupResult(res);
                if (window.WFToast) window.WFToast.success(`${typeLabel} completed successfully!`);
            } else {
                const excuses = [
                    "The backup gremlins are on strike.",
                    "The database is feeling shy today.",
                    "The server had too much coffee and can't focus.",
                    "I lost the backup key under the digital rug.",
                    "The bits are refusing to be bottled.",
                    "My binary carrier pigeon got lost in the cloud."
                ];
                const excuse = excuses[Math.floor(Math.random() * excuses.length)];
                if (window.WFToast) window.WFToast.error(`${excuse} (${res?.error || 'Unknown server failure'})`);
            }
        } catch (err) {
            if (window.WFToast) window.WFToast.error(`Ugh. Something went wrong with the ${typeLabel.toLowerCase()}.`);
        }
    };

    return (
        <div className="space-y-6 animate-in fade-in slide-in-from-bottom-2">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="p-6 border-2 border-dashed rounded-xl flex flex-col items-center text-center">
                    <h4 className="font-bold text-gray-800">Full Site Backup</h4>
                    <p className="text-sm text-gray-500 mb-6">Archive all files, images, and the database into a single package.</p>
                    <button
                        type="button"
                        onClick={() => handleBackup('full')}
                        disabled={isLoading}
                        className="w-full btn-text-primary py-3 flex items-center justify-center gap-2"
                        data-help-id="maintenance-backup-full"
                    >
                        {isLoading ? 'Start Full Backup...' : 'Start Full Backup'}
                    </button>
                </div>
                <div className="p-6 border-2 border-dashed rounded-xl flex flex-col items-center text-center">
                    <h4 className="font-bold text-gray-800">Database Only</h4>
                    <p className="text-sm text-gray-500 mb-6">Quick SQL dump of all tables and structure. Best for routine safety.</p>
                    <button
                        type="button"
                        onClick={() => handleBackup('database')}
                        disabled={isLoading}
                        className="w-full btn-text-secondary py-3 flex items-center justify-center gap-2"
                        data-help-id="maintenance-backup-db"
                    >
                        {isLoading ? 'Backup Database...' : 'Backup Database'}
                    </button>
                </div>
            </div>

            {backupResult && (
                <div className="p-4 bg-[var(--brand-accent)]/5 border border-[var(--brand-accent)]/20 rounded-lg animate-in zoom-in-95 duration-300">
                    <div className="flex items-center gap-3 mb-2">
                        <h5 className="font-bold text-[var(--brand-accent)]">Backup Succeeded!</h5>
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
            )
            }
        </div >
    );
};
