import React from 'react';
import { createPortal } from 'react-dom';
import { useAdminTools, IAdminTool } from '../../../hooks/admin/useAdminTools.js';

interface SystemToolsProps {
    onClose?: () => void;
    title?: string;
}

export const SystemTools: React.FC<SystemToolsProps> = ({ onClose, title }) => {
    const { categories, isLoading, error } = useAdminTools();

    const handleToolClick = (tool: IAdminTool) => {
        if (tool.external && tool.url) {
            window.open(tool.url, '_blank');
            return;
        }

        if (tool.inline_modal) {
            // These should match the strings in useAdminTools.ts which now match ADMIN_SECTION constants
            const url = new URL(window.location.href);
            url.searchParams.set('section', tool.inline_modal);
            url.searchParams.delete('tab');
            window.history.pushState({}, '', url.toString());
            window.dispatchEvent(new Event('popstate'));
            // Remove onClose() to let AdminConductor handle the transition to the new modal/section
            return;
        }

        if (tool.file) {
            window.open(`/api/${tool.file}`, '_blank');
        } else if (tool.url) {
            window.open(tool.url, '_blank');
        }
    };

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
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1300px] max-w-[95vw] h-auto max-h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">🧪</span> {title || 'Advanced Tools'}
                    </h2>

                    <div className="flex-1" />

                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                            <button
                                onClick={onClose}
                                className="admin-action-btn btn-icon--close"
                                data-help-id="system-tools-close"
                                type="button"
                            />
                        </div>
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto">
                    <div className="p-10">
                        {isLoading && !categories.length && (
                            <div className="flex flex-col items-center justify-center p-12 text-gray-500 animate-pulse">
                                <span className="text-4xl mb-4">🛠️</span>
                                <p className="font-black uppercase tracking-widest text-[10px]">Synchronizing System Tools...</p>
                            </div>
                        )}

                        {error && (
                            <div className="p-4 mb-8 bg-red-50 border border-red-100 text-red-600 text-xs font-bold rounded-2xl animate-in shake">
                                Error: {error}
                            </div>
                        )}

                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-10 animate-in fade-in slide-in-from-bottom-2 duration-300 items-start">
                            {categories.map((cat, idx) => (
                                <section key={idx} className="space-y-6">
                                    <div className="flex items-center gap-4">
                                        <h3 className="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] whitespace-nowrap bg-slate-50 px-3 py-1 rounded-full">{cat.title}</h3>
                                        <div className="h-px bg-slate-100 flex-1"></div>
                                    </div>

                                    <div className="flex flex-col gap-4">
                                        {cat.tools.map((tool, tIdx) => (
                                            <button
                                                key={tIdx}
                                                onClick={() => handleToolClick(tool)}
                                                className="group flex items-center gap-4 text-left bg-white border-2 border-slate-50 rounded-2xl p-4 transition-all hover:border-blue-100 hover:shadow-lg hover:shadow-blue-500/5 hover:-translate-y-0.5 relative"
                                                data-help-id={`tool-${tool.name.toLowerCase().replace(/\s+/g, '-')}`}
                                            >
                                                <div className="flex-shrink-0 w-12 h-12 rounded-xl bg-slate-50 text-slate-400 flex items-center justify-center group-hover:bg-blue-50 group-hover:text-blue-500 transition-colors">
                                                    <span className="text-2xl">
                                                        {tool.icon}
                                                    </span>
                                                </div>

                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center justify-between mb-0.5">
                                                        <div className="font-black text-slate-800 text-sm group-hover:text-blue-600 transition-colors truncate">{tool.name}</div>
                                                        {tool.external && (
                                                            <div className="admin-action-btn btn-icon--external opacity-20 group-hover:opacity-100 scale-50 -mr-2" data-help-id="common-external" />
                                                        )}
                                                    </div>
                                                    <p className="text-[10px] text-slate-400 leading-tight line-clamp-1">{tool.desc}</p>
                                                </div>

                                                <div className="opacity-0 group-hover:opacity-100 transition-all -translate-x-2 group-hover:translate-x-0 pr-1">
                                                    <span className="text-blue-400 text-lg">→</span>
                                                </div>
                                            </button>
                                        ))}
                                    </div>
                                </section>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
