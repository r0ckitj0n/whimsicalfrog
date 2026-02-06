import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useActionIcons } from '../../../hooks/admin/useActionIcons.js';
import { useModalContext } from '../../../context/ModalContext.js';
import { RegistryHeader } from './action-icons/RegistryHeader.js';
import { AddIconForm } from './action-icons/AddIconForm.js';
import { IconTable } from './action-icons/IconTable.js';

const DEFAULT_MAPPINGS: Record<string, string> = {
    add: '➕', edit: '✏️', duplicate: '📄', delete: '🗑️', view: '👁️', preview: '👁️', 'preview-inline': '🪟',
    refresh: '🔄', send: '📤', save: '💾', archive: '🗄️', settings: '⚙️', download: '⬇️', upload: '⬆️',
    external: '↗️', link: '🔗', info: 'ℹ️', help: '❓', print: '🖨️', up: '▲', down: '▼'
};

interface ActionIconsManagerProps {
    onClose?: () => void;
    title?: string;
}

export const ActionIconsManager: React.FC<ActionIconsManagerProps> = ({ onClose, title }) => {
    const {
        iconMap,
        isLoading,
        error,
        saveIconMap,
        refresh
    } = useActionIcons();

    const { confirm: confirmModal } = useModalContext();

    const [localMap, setLocalMap] = useState<Record<string, string>>({});
    const [newKey, setNewKey] = useState('');
    const [newEmoji, setNewEmoji] = useState('');
    const [searchTerm, setSearchTerm] = useState('');

    useEffect(() => {
        if (isLoading) return;
        if (iconMap && Object.keys(iconMap).length > 0) {
            setLocalMap(iconMap);
        } else {
            setLocalMap(DEFAULT_MAPPINGS);
        }
    }, [iconMap, isLoading]);

    const handleSave = async () => {
        const res = await saveIconMap(localMap);
        if (res.success) {
            if (window.WFToast) window.WFToast.success('Mappings saved');
        } else {
            if (window.WFToast) window.WFToast.error(res.error || 'Failed to save');
        }
    };

    const handleUpdateMapping = (key: string, emoji: string) => {
        setLocalMap(prev => ({ ...prev, [key]: emoji }));
    };

    const handleRemoveMapping = (key: string) => {
        const next = { ...localMap };
        delete next[key];
        setLocalMap(next);
    };

    const handleAdd = (e: React.FormEvent) => {
        e.preventDefault();
        const key = newKey.trim().toLowerCase().replace(/\s+|_/g, '-');
        const emoji = newEmoji.trim();
        if (key && emoji) {
            setLocalMap(prev => ({ ...prev, [key]: emoji }));
            setNewKey('');
            setNewEmoji('');
        }
    };

    const handleReset = async () => {
        const confirmed = await confirmModal({
            title: 'Reset Icons',
            message: 'Reset all icons to system defaults? This will not save until you click Save.',
            confirmText: 'Reset Now',
            confirmStyle: 'danger',
            iconKey: 'rotate'
        });

        if (confirmed) {
            setLocalMap(DEFAULT_MAPPINGS);
        }
    };

    const filteredKeys = Object.keys(localMap).filter(k =>
        k.toLowerCase().includes(searchTerm.toLowerCase())
    ).sort();

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
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1000px] max-w-[95vw] h-auto max-h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">🔘</span> {title || 'Icon Buttons'}
                    </h2>

                    <div className="flex-1" />

                    <div className="flex items-center gap-4">
                        <button
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="action-icons-close"
                            type="button"
                        />
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto p-8">
                    {isLoading && Object.keys(localMap).length === 0 ? (
                        <div className="flex flex-col items-center justify-center p-12 text-gray-500">
                            <span className="wf-emoji-loader">🖼️</span>
                            <p>Loading icon registry...</p>
                        </div>
                    ) : (
                        <div className="space-y-10">
                            {/* Add New Section Card */}
                            <div className="bg-blue-50/30 border border-blue-100/50 rounded-[2.5rem] p-10 shadow-sm">
                                <AddIconForm
                                    newKey={newKey}
                                    onNewKeyChange={setNewKey}
                                    newEmoji={newEmoji}
                                    onNewEmojiChange={setNewEmoji}
                                    onAdd={handleAdd}
                                />
                            </div>

                            {/* Registry Section Card */}
                            <div className="bg-white border border-gray-100 rounded-[2.5rem] shadow-xl overflow-hidden flex flex-col min-h-[600px]">
                                <RegistryHeader
                                    isLoading={isLoading}
                                    onRefresh={refresh}
                                    onReset={handleReset}
                                    onSave={handleSave}
                                />

                                <div className="p-8 border-b bg-gray-50/30">
                                    <div className="flex flex-col md:flex-row gap-6 items-center">
                                        <div className="flex-1 w-full space-y-4">
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm font-black text-gray-400 uppercase tracking-widest px-1">Registry Filter</span>
                                                <div className="h-px flex-1 bg-gray-100" />
                                            </div>
                                            <div className="flex items-center gap-2 bg-white p-2 rounded-xl border border-gray-100 shadow-inner">
                                                <span className="text-sm opacity-30 pl-1">🔍</span>
                                                <input
                                                    type="text"
                                                    value={searchTerm}
                                                    onChange={e => setSearchTerm(e.target.value)}
                                                    placeholder="Find keyword..."
                                                    className="w-full bg-transparent border-none p-0 text-sm focus:ring-0"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <IconTable
                                    filteredKeys={filteredKeys}
                                    localMap={localMap}
                                    onUpdateMapping={handleUpdateMapping}
                                    onRemoveMapping={handleRemoveMapping}
                                />
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};

