import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useDashboardConfig, IDashboardSection } from '../../../hooks/admin/useDashboardConfig.js';
import { useDashboard } from '../../../hooks/admin/useDashboard.js';
import { useHealthChecks } from '../../../hooks/admin/useHealthChecks.js';
import { ApiClient } from '../../../core/ApiClient.js';

interface DashboardConfigProps {
    onClose?: () => void;
    title?: string;
}

export const DashboardConfig: React.FC<DashboardConfigProps> = ({ onClose, title }) => {
    const { sections, isLoading: isConfigLoading, error, fetchConfig, updateSection } = useDashboardConfig();
    const { refresh, isLoading: isDashboardLoading } = useDashboard();
    const { runChecks, isLoading: isHealthLoading } = useHealthChecks(true);

    const isSyncing = isDashboardLoading || isHealthLoading;

    const handleSync = async () => {
        const [, healthResults] = await Promise.all([refresh(), runChecks()]);

        if (window.WFToast) {
            if (healthResults) {
                const missingFiles = (healthResults.backgrounds.missingFiles.length || 0) + (healthResults.items.missingFiles || 0);
                const msg = missingFiles > 0
                    ? `Sync Complete: Metrics updated. ⚠️ ${missingFiles} missing files found.`
                    : `Sync Complete: Metrics updated. All assets verified.`;
                window.WFToast.success(msg);
            } else {
                window.WFToast.success('Sync Complete: Dashboard metrics refreshed.');
            }
        }
    };

    useEffect(() => {
        fetchConfig();
    }, [fetchConfig]);

    const handleWidthToggle = async (key: string, current: string) => {
        const next = current === 'full' ? 'half' : 'full';
        await updateSection(key, { width: next as IDashboardSection['width'] });
    };

    const handleVisibilityToggle = async (key: string, isVisible: boolean) => {
        await updateSection(key, { is_visible: isVisible });
    };

    const handleMove = async (index: number, direction: 'up' | 'down') => {
        const newSections = [...sections];
        const nextIndex = direction === 'up' ? index - 1 : index + 1;
        if (nextIndex < 0 || nextIndex >= sections.length) return;

        const [moved] = newSections.splice(index, 1);
        newSections.splice(nextIndex, 0, moved);

        // Update all orders
        const updated = newSections.map((s, i) => ({ ...s, order: i + 1, section_key: s.key }));
        await ApiClient.post('/api/dashboard_sections.php?action=update_sections', { sections: updated });
        fetchConfig();
    };

    const modalContent = (
        <div className="admin-modal-overlay over-header show topmost" onClick={(e) => e.target === e.currentTarget && onClose?.()}>
            <div className="admin-modal admin-modal-content show bg-white rounded-3xl shadow-2xl w-[600px] max-w-[95vw] h-auto max-h-[90vh] flex flex-col overflow-hidden" onClick={e => e.stopPropagation()}>
                <div className="modal-header flex items-center justify-between px-6 py-5 border-b border-gray-50 sticky top-0 bg-white z-20">
                    <h2 className="text-2xl font-bold text-gray-800 tracking-tight font-merienda">{title || 'Dashboard Layout'}</h2>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 transition-colors" data-help-id="common-close">
                        <span className="text-2xl font-bold">✕</span>
                    </button>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto p-6 pt-4">
                    <p className="text-gray-600 mb-6 font-medium text-sm">Toggle which sections are active on your Dashboard, then click Save.</p>

                    <div className="border rounded-xl overflow-hidden shadow-sm">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b border-gray-100 bg-gray-50/30">
                                    <th className="py-3 px-4 text-[10px] font-black uppercase tracking-widest text-gray-400 border-r border-gray-50">Order</th>
                                    <th className="py-3 px-4 text-[10px] font-black uppercase tracking-widest text-gray-400 border-r border-gray-50">Section</th>
                                    <th className="py-3 px-4 text-[10px] font-black uppercase tracking-widest text-gray-400 border-r border-gray-50">Width</th>
                                    <th className="py-3 px-4 text-[10px] font-black uppercase tracking-widest text-gray-400 text-center">Active</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {sections.map((section, idx) => (
                                    <tr key={section.key} className="group hover:bg-gray-50/30 transition-colors">
                                        <td className="py-3 px-4 border-r border-gray-50">
                                            <div className="flex items-center gap-4">
                                                <div className="flex flex-col gap-2 items-center text-gray-400">
                                                    <button onClick={() => handleMove(idx, 'up')} disabled={idx === 0} className="text-[10px] hover:text-gray-900 disabled:opacity-0 transition-all transform active:scale-125" data-help-id="dashboard-move-up">▲</button>
                                                    <button onClick={() => handleMove(idx, 'down')} disabled={idx === sections.length - 1} className="text-[10px] hover:text-gray-900 disabled:opacity-0 transition-all transform active:scale-125" data-help-id="dashboard-move-down">▼</button>
                                                </div>
                                                <span className="text-sm font-bold text-gray-700 w-4">{idx + 1}</span>
                                            </div>
                                        </td>
                                        <td className="py-3 px-4 border-r border-gray-50">
                                            <div className="flex items-center gap-3">
                                                <span className="text-xl drop-shadow-sm">{section.title.split(' ')[0]}</span>
                                                <span className="text-sm font-semibold text-gray-800">{section.title.split(' ').slice(1).join(' ')}</span>
                                            </div>
                                        </td>
                                        <td className="py-3 px-4 border-r border-gray-50">
                                            <button
                                                onClick={() => handleWidthToggle(section.key, section.width)}
                                                className="px-4 py-1.5 rounded-full bg-gray-50/80 text-[13px] italic font-medium text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-all"
                                                data-help-id="dashboard-toggle-width"
                                            >
                                                {section.width === 'full' ? 'Full Width' : 'Half Width'}
                                            </button>
                                        </td>
                                        <td className="py-3 px-4 text-center">
                                            <button
                                                onClick={() => handleVisibilityToggle(section.key, !section.is_visible)}
                                                className={`text-xl transition-all ${section.is_visible ? 'text-gray-900 scale-110' : 'text-gray-100'}`}
                                                data-help-id="dashboard-toggle-visibility"
                                            >
                                                ✓
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="modal-footer px-6 py-8 border-t border-gray-100 flex justify-start gap-6 bg-gray-50/30">
                    <button
                        onClick={() => ApiClient.post('/api/dashboard_sections.php?action=reset_defaults').then(fetchConfig)}
                        className="px-10 py-3.5 bg-[#87ac3a] text-white rounded-2xl text-[20px] font-normal italic shadow-xl hover:brightness-105 active:scale-95 transition-all !w-auto !h-auto admin-action-btn font-merienda inline-block min-w-[220px] border-none cursor-pointer"
                        data-help-id="dashboard-reset"
                    >
                        Reset to defaults
                    </button>
                    <button
                        onClick={handleSync}
                        disabled={isSyncing}
                        className="px-10 py-3.5 bg-[#87ac3a] text-white rounded-2xl text-[20px] font-normal italic shadow-xl hover:brightness-105 active:scale-95 transition-all !w-auto !h-auto flex items-center gap-2 admin-action-btn font-merienda inline-block min-w-[140px] border-none cursor-pointer"
                        data-help-id="dashboard-sync"
                    >
                        {isSyncing ? 'Syncing...' : 'Sync Site Data'}
                    </button>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
