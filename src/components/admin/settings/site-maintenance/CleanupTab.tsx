import React, { useEffect, useMemo, useRef, useState } from 'react';
import { ApiClient } from '../../../../core/ApiClient.js';
import type { IImageCleanupReport, IImageCleanupStartResponse, IImageCleanupStepResponse } from '../../../../types/maintenance.js';
import { useModalContext } from '../../../../context/ModalContext.js';

interface CleanupTabProps {
    isLoading: boolean;
}

const formatBytes = (bytes: number): string => {
    const b = Number(bytes || 0);
    if (b < 1024) return `${b} B`;
    if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`;
    if (b < 1024 * 1024 * 1024) return `${(b / (1024 * 1024)).toFixed(1)} MB`;
    return `${(b / (1024 * 1024 * 1024)).toFixed(2)} GB`;
};

export const CleanupTab: React.FC<CleanupTabProps> = ({ isLoading }) => {
    const { confirm } = useModalContext();
    const [dryRun, setDryRun] = useState(true);
    const [jobId, setJobId] = useState<string>('');
    const [phase, setPhase] = useState<IImageCleanupStepResponse['phase']>('init');
    const [status, setStatus] = useState<string>('');
    const [progress, setProgress] = useState<IImageCleanupStepResponse['progress'] | null>(null);
    const [report, setReport] = useState<IImageCleanupReport | null>(null);
    const [error, setError] = useState<string>('');
    const pollTimer = useRef<number | null>(null);

    const percent = useMemo(() => {
        const processed = Number(progress?.processed || 0);
        const total = Number(progress?.total || 0);
        if (total <= 0) return 0;
        return Math.min(100, Math.max(0, Math.round((processed / total) * 100)));
    }, [progress?.processed, progress?.total]);

    const stopPolling = () => {
        if (pollTimer.current) {
            window.clearTimeout(pollTimer.current);
            pollTimer.current = null;
        }
    };

    const step = async (id: string) => {
        try {
            const res = await ApiClient.post<IImageCleanupStepResponse>('/api/image_cleanup.php', {
                action: 'step',
                job_id: id
            });
            if (!res?.success) throw new Error(res?.error || 'Cleanup step failed');

            setPhase(res.phase || 'init');
            setStatus(String(res.status || ''));
            setProgress(res.progress || null);
            if (res.report) setReport(res.report);

            if (res.phase === 'complete') {
                stopPolling();
                return;
            }

            pollTimer.current = window.setTimeout(() => {
                void step(id);
            }, 700);
        } catch (err: unknown) {
            stopPolling();
            const msg = err instanceof Error ? err.message : 'Cleanup failed';
            setError(msg);
        }
    };

    const start = async () => {
        setError('');
        setReport(null);
        setProgress(null);
        setStatus('');
        setPhase('init');

        if (!dryRun) {
            const confirmed = await confirm({
                title: 'Archive Unreferenced Images?',
                message: 'This will move images under /images/ that are not referenced in the database into /backups/. This is reversible, but it can break any hardcoded assets not stored in the DB. Continue?',
                confirmText: 'Yes, Archive',
                cancelText: 'Cancel',
                confirmStyle: 'danger',
                iconKey: 'warning'
            });
            if (!confirmed) return;
        }

        try {
            const res = await ApiClient.post<IImageCleanupStartResponse>('/api/image_cleanup.php', {
                action: 'start',
                dry_run: dryRun ? 1 : 0
            });
            if (!res?.success || !res.job_id) {
                throw new Error(res?.error || 'Failed to start cleanup job');
            }

            setJobId(res.job_id);
            setPhase('building_references');
            setStatus('Starting cleanup job...');
            stopPolling();
            pollTimer.current = window.setTimeout(() => void step(res.job_id!), 200);
        } catch (err: unknown) {
            const msg = err instanceof Error ? err.message : 'Failed to start cleanup job';
            setError(msg);
        }
    };

    useEffect(() => () => stopPolling(), []);

    return (
        <div className="space-y-6">
            <div className="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 className="text-sm font-black text-slate-800 uppercase tracking-widest">Image Cleanup</h3>
                <p className="text-xs text-slate-600 mt-2">
                    Scans the <code className="font-mono">/images</code> folder, checks which files are referenced in MySQL, and archives unreferenced files into <code className="font-mono">/backups</code>.
                </p>

                <div className="mt-4 flex items-center justify-between gap-4">
                    <label className="flex items-center gap-3 text-xs font-bold text-slate-700">
                        <input
                            type="checkbox"
                            className="accent-emerald-600"
                            checked={dryRun}
                            onChange={(e) => setDryRun(e.target.checked)}
                            disabled={phase !== 'init' && phase !== 'complete' && phase !== 'error'}
                        />
                        Dry run (Recommended)
                        <span className="text-[10px] font-black uppercase tracking-widest text-slate-400">
                            no files moved
                        </span>
                    </label>

                    <button
                        type="button"
                        onClick={() => void start()}
                        disabled={isLoading || (phase !== 'init' && phase !== 'complete' && phase !== 'error')}
                        className="btn btn-primary px-4 py-2 text-[10px] font-black uppercase tracking-widest disabled:opacity-60"
                    >
                        Run Cleanup
                    </button>
                </div>

                {error && (
                    <div className="mt-4 p-3 rounded-xl border border-rose-200 bg-rose-50 text-rose-700 text-xs font-bold">
                        {error}
                    </div>
                )}

                {(phase !== 'init' && !error) && (
                    <div className="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                        <div className="flex items-center justify-between gap-3">
                            <div className="text-xs font-black text-slate-700 uppercase tracking-widest">
                                Status
                            </div>
                            {jobId && (
                                <div className="text-[10px] font-mono text-slate-500">
                                    job: {jobId}
                                </div>
                            )}
                        </div>
                        <div className="text-xs text-slate-700">{status || 'Working...'}</div>
                        {progress && (
                            <div className="space-y-2">
                                <div className="w-full h-2 rounded-full bg-slate-200 overflow-hidden">
                                    <div className="h-2 bg-emerald-500" style={{ width: `${percent}%` }} />
                                </div>
                                <div className="text-[11px] text-slate-600 font-mono">
                                    processed {progress.processed}/{progress.total} | referenced {progress.referenced} | archived {progress.archived} | whitelisted {progress.whitelisted}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>

            {report && (
                <div className="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
                    <div className="flex items-center justify-between">
                        <h4 className="text-xs font-black text-slate-800 uppercase tracking-widest">Cleanup Report</h4>
                        <div className="text-[10px] font-mono text-slate-500">
                            {report.dry_run ? 'DRY RUN' : 'ARCHIVED'} • {report.archived_files.length} file(s)
                        </div>
                    </div>

                    <div className="text-xs text-slate-700">
                        Archive folder: <code className="font-mono">{`/${report.archive_root_rel}`}</code>
                    </div>

                    <div className="max-h-[46vh] overflow-y-auto border border-slate-200 rounded-xl">
                        <table className="w-full text-[11px]">
                            <thead className="sticky top-0 bg-slate-50 border-b border-slate-200">
                                <tr className="text-left">
                                    <th className="p-2 font-black uppercase tracking-widest text-slate-600">File</th>
                                    <th className="p-2 font-black uppercase tracking-widest text-slate-600 w-[120px]">Size</th>
                                </tr>
                            </thead>
                            <tbody>
                                {report.archived_files.length === 0 ? (
                                    <tr>
                                        <td className="p-3 text-slate-500 italic" colSpan={2}>No unreferenced images found.</td>
                                    </tr>
                                ) : (
                                    report.archived_files.map((f) => (
                                        <tr key={f.archived_rel_path} className="border-b border-slate-100">
                                            <td className="p-2 font-mono text-slate-700">{f.rel_path}</td>
                                            <td className="p-2 font-mono text-slate-500">{formatBytes(f.bytes)}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {report.errors.length > 0 && (
                        <div className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                            <p className="font-black uppercase tracking-widest text-[10px] mb-2">Errors</p>
                            <ul className="list-disc pl-5">
                                {report.errors.map((e) => <li key={e} className="font-mono text-[11px]">{e}</li>)}
                            </ul>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

