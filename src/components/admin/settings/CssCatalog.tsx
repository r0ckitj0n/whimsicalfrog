import React, { useState, useEffect } from 'react';
import { useCssCatalog } from '../../../hooks/admin/useCssCatalog.js';
import { CssEditorModal } from '../../modals/admin/settings/CssEditorModal.js';

interface CssCatalogProps {
    onClose?: () => void;
}

export const CssCatalog: React.FC<CssCatalogProps> = ({ onClose }) => {
    const {
        data,
        isLoading,
        error,
        fetchCatalog
    } = useCssCatalog();

    const [searchTerm, setSearchSearchTerm] = useState('');
    const [groupMode, setGroupMode] = useState<'prefix' | 'file'>('prefix');
    const [expandedGroups, setExpandedGroups] = useState<Set<string>>(new Set());
    const [copiedClass, setCopiedClass] = useState<string | null>(null);
    const [editorModal, setEditorModal] = useState<{ filePath: string; targetClass?: string } | null>(null);

    useEffect(() => {
        if (data && expandedGroups.size === 0) {
            // Initially expand first 3 groups
            const initial = Array.from(Object.keys(groupMode === 'prefix' ? groupedByPrefix : groupedByFile)).slice(0, 3);
            setExpandedGroups(new Set(initial));
        }
    }, [data, groupMode]);

    const toggleGroup = (group: string) => {
        const next = new Set(expandedGroups);
        if (next.has(group)) next.delete(group);
        else next.add(group);
        setExpandedGroups(next);
    };

    const expandAll = () => {
        const groups = Object.keys(groupMode === 'prefix' ? groupedByPrefix : groupedByFile);
        setExpandedGroups(new Set(groups));
    };

    const collapseAll = () => {
        setExpandedGroups(new Set());
    };

    const handleCopy = (cls: string) => {
        navigator.clipboard.writeText(cls.startsWith('.') ? cls : `.${cls}`);
        setCopiedClass(cls);
        setTimeout(() => setCopiedClass(null), 2000);
    };

    const handleOpenFile = (filePath: string, targetClass?: string) => {
        setEditorModal({ filePath, targetClass });
    };

    const groupedByPrefix = React.useMemo(() => {
        if (!data || !data.allClasses) return {};
        const groups: Record<string, string[]> = {};
        data.allClasses.forEach(cls => {
            const name = cls.startsWith('.') ? cls.slice(1) : cls;
            let prefix = name.split(/[-_]/)[0] || name;
            if (!groups[prefix]) groups[prefix] = [];
            groups[prefix].push(cls);
        });
        return groups;
    }, [data]);

    const groupedByFile = React.useMemo(() => {
        if (!data || !data.sources) return {};
        const groups: Record<string, string[]> = {};
        data.sources.forEach(src => {
            groups[src.file] = src.classes;
        });
        return groups;
    }, [data]);

    if (isLoading && !data) {
        return (
            <div className="flex flex-col items-center justify-center p-12 text-gray-500">
                <span className="wf-emoji-loader">🎨</span>
                <p>Indexing styles and tokens...</p>
            </div>
        );
    }

    const currentGroups = groupMode === 'prefix' ? groupedByPrefix : groupedByFile;
    const filteredGroups = Object.entries(currentGroups).reduce((acc, [group, classes]) => {
        const matches = classes.filter(cls => cls.toLowerCase().includes(searchTerm.toLowerCase()));
        if (matches.length > 0) acc[group] = matches;
        return acc;
    }, {} as Record<string, string[]>);

    return (
        <div
            className="admin-modal-overlay over-header show topmost"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose?.();
            }}
        >
            <div
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1200px] max-w-[95vw] h-auto max-h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">📚</span> CSS Catalog
                    </h2>
                    <div className="flex-1" />
                    <button
                        onClick={onClose}
                        className="admin-action-btn btn-icon--close"
                        data-help-id="modal-close"
                        type="button"
                    />
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto p-6">
                    <div className="bg-white border rounded-xl shadow-sm overflow-hidden flex flex-col min-h-[600px]">
                        <div className="px-6 py-4 border-b bg-gray-50 flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <div>
                                    <h2 className="text-lg font-bold text-gray-800">CSS Class Catalog</h2>
                                    {data && <p className="text-[10px] text-gray-400 uppercase font-black">Last Generated: {data.generatedAt}</p>}
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                <div className="flex-1 min-w-[200px]">
                                    <input
                                        type="text"
                                        value={searchTerm}
                                        onChange={e => setSearchSearchTerm(e.target.value)}
                                        placeholder="Search classes..."
                                        className="form-input form-input-search text-xs"
                                    />
                                </div>
                                <div className="flex bg-gray-100 p-1 rounded-lg">
                                    <button
                                        onClick={() => setGroupMode('prefix')}
                                        className={`px-3 py-1 text-[10px] font-bold uppercase rounded-md transition-all ${groupMode === 'prefix' ? 'bg-white text-[var(--brand-accent)] shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
                                    >
                                        By Prefix
                                    </button>
                                    <button
                                        onClick={() => setGroupMode('file')}
                                        className={`px-3 py-1 text-[10px] font-bold uppercase rounded-md transition-all ${groupMode === 'file' ? 'bg-white text-[var(--brand-accent)] shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
                                    >
                                        By File
                                    </button>
                                </div>
                                <div className="h-6 w-px bg-gray-200 mx-1"></div>
                                <button onClick={expandAll} className="admin-action-btn btn-icon--maximize" data-help-id="catalog-expand-all" />
                                <button onClick={collapseAll} className="admin-action-btn btn-icon--minimize" data-help-id="catalog-collapse-all" />
                            </div>
                        </div>

                        <div className="p-6 flex-1 overflow-y-auto">
                            {error && (
                                <div className="mx-4 p-4 bg-[var(--brand-error-bg)] border border-[var(--brand-error-border)] text-[var(--brand-error)] text-sm rounded-xl flex items-center gap-3">
                                    <span className="text-xl">⚠️</span> {error}
                                </div>
                            )}

                            <div className="space-y-4">
                                {Object.entries(filteredGroups).map(([group, classes]) => (
                                    <div key={group} className="group border rounded-xl overflow-hidden bg-white shadow-sm transition-all hover:border-[var(--brand-accent)]/30">
                                        <div
                                            onClick={() => toggleGroup(group)}
                                            className="w-full px-4 py-3 flex items-center justify-between bg-gray-50/50 hover:bg-[var(--brand-accent)]/5 transition-colors cursor-pointer"
                                            role="button"
                                            tabIndex={0}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter' || e.key === ' ') {
                                                    e.preventDefault();
                                                    toggleGroup(group);
                                                }
                                            }}
                                        >
                                            <div className="flex items-center gap-3">
                                                <div className={`p-1.5 rounded-lg ${expandedGroups.has(group) ? 'bg-[var(--brand-accent)] text-white' : 'bg-gray-200 text-gray-500'}`}>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-sm font-bold text-gray-700 truncate max-w-md">
                                                        {groupMode === 'file' ? group.split('/').pop() : group}
                                                        <span className="ml-2 text-xs font-normal text-gray-400">{classes.length} classes</span>
                                                    </span>
                                                    {groupMode === 'file' && (
                                                        <button
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                handleOpenFile(group);
                                                            }}
                                                            className="admin-action-btn btn-icon--edit opacity-0 group-hover:opacity-100 transition-opacity"
                                                            data-help-id="file-edit"
                                                            type="button"
                                                        />
                                                    )}
                                                </div>
                                            </div>
                                            <span className={`text-xs text-gray-400 transition-transform ${expandedGroups.has(group) ? 'rotate-90' : ''}`}>▶</span>
                                        </div>

                                        {expandedGroups.has(group) && (
                                            <div className="p-4 bg-white animate-in fade-in slide-in-from-top-1">
                                                <div className="flex flex-wrap gap-2">
                                                    {classes.map((cls, i) => (
                                                        <div key={i} className="group relative">
                                                            <button
                                                                onClick={() => {
                                                                    if (groupMode === 'file') {
                                                                        // In file mode, open the file
                                                                        handleOpenFile(group, cls);
                                                                    } else {
                                                                        // In prefix mode, find the file containing this class
                                                                        const sourceFile = data?.sources.find(src =>
                                                                            src.classes.includes(cls)
                                                                        );
                                                                        if (sourceFile) {
                                                                            handleOpenFile(sourceFile.file, cls);
                                                                        }
                                                                    }
                                                                }}
                                                                className={`px-3 py-1.5 rounded-lg border text-xs font-mono transition-all flex items-center gap-2 ${copiedClass === group || copiedClass === cls
                                                                    ? 'bg-[var(--brand-accent)]/10 border-[var(--brand-accent)] text-[var(--brand-accent)] ring-1 ring-[var(--brand-accent)]'
                                                                    : 'bg-white border-gray-200 text-gray-600 hover:border-[var(--brand-accent)]/40 hover:text-[var(--brand-accent)]'
                                                                    }`}
                                                                data-help-id="class-copy-path"
                                                            >
                                                                {copiedClass === group || copiedClass === cls ? '✅' : (
                                                                    <div className="admin-action-btn btn-icon--copy w-3 h-3" />
                                                                )}
                                                                {cls}
                                                            </button>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ))}
                                {Object.keys(filteredGroups).length === 0 && (
                                    <div className="py-24 text-center text-gray-400 italic">
                                        No matching CSS classes found.
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* CSS Editor Modal */}
            {editorModal && (
                <CssEditorModal
                    filePath={editorModal.filePath}
                    targetClass={editorModal.targetClass}
                    onClose={() => setEditorModal(null)}
                />
            )}
        </div>
    );
};
