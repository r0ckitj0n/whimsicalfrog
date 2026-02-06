import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { useModalContext } from '../../../context/ModalContext.js';
import { useSecrets, ISecret } from '../../../hooks/admin/useSecrets.js';
import { formatDateTime } from '../../../core/date-utils.js';


interface SecretsManagerProps {
    onClose?: () => void;
    title?: string;
}

export const SecretsManager: React.FC<SecretsManagerProps> = ({ onClose, title }) => {
    const {
        secrets,
        isLoading,
        error,
        fetchSecrets,
        saveSecret,
        deleteSecret,
        rotateKeys
    } = useSecrets();

    const [editingSecret, setEditingSecret] = useState<{ key: string; value: string } | null>(null);
    const [csrfToken] = useState(() => (document.getElementById('secretsCsrf') as HTMLInputElement)?.value || '');

    useEffect(() => {
        if (isLoading) return;
        fetchSecrets();
    }, [fetchSecrets, isLoading]);

    const handleAdd = () => {
        setEditingSecret({ key: '', value: '' });
    };

    const handleEdit = (secret: ISecret) => {
        setEditingSecret({ key: secret.key, value: '' });
    };

    const { confirm: themedConfirm } = useModalContext();

    const handleDelete = async (key: string) => {
        const confirmed = await themedConfirm({
            title: 'Delete Secret',
            message: `Are you sure you want to delete the secret "${key}"?\nThis cannot be undone.`,
            confirmText: 'Delete Now',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const success = await deleteSecret(key, csrfToken);
            if (success && window.WFToast) window.WFToast.success(`Secret "${key}" deleted`);
        }
    };

    const handleRotate = async () => {
        const confirmed = await themedConfirm({
            title: 'Rotate Keys',
            message: 'Are you sure you want to rotate all encryption keys?\nThis will re-encrypt all secrets with a new key file.\nMake sure you have a backup of your old key file!',
            confirmText: 'Rotate Now',
            confirmStyle: 'danger',
            iconKey: 'rotate'
        });

        if (confirmed) {
            const success = await rotateKeys(csrfToken);
            if (success && window.WFToast) window.WFToast.success('Encryption keys rotated successfully');
        }
    };


    const handleSave = async () => {
        if (editingSecret && editingSecret.key) {
            const success = await saveSecret(editingSecret.key, editingSecret.value, csrfToken);
            if (success) {
                setEditingSecret(null);
                if (window.WFToast) window.WFToast.success(`Secret "${editingSecret.key}" saved`);
            }
        }
    };

    const isDirty = editingSecret !== null && editingSecret.value !== '';

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
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[900px] max-w-[95vw] h-[80vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">🔐</span> {title || 'System Secrets'}
                    </h2>

                    <div className="flex-1" />

                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                            {editingSecret && (
                                <button
                                    onClick={handleSave}
                                    disabled={isLoading || !isDirty}
                                    className={`admin-action-btn btn-icon--save ${isDirty ? 'is-dirty' : ''}`}
                                    data-help-id="secrets-save"
                                    type="button"
                                />
                            )}
                            <button
                                onClick={handleRotate}
                                className="admin-action-btn btn-icon--refresh"
                                data-help-id="secrets-rotate"
                                type="button"
                            />
                            <button
                                onClick={handleAdd}
                                className="admin-action-btn btn-icon--add"
                                data-help-id="secrets-add"
                                type="button"
                            />
                            <button
                                onClick={onClose}
                                className="admin-action-btn btn-icon--close"
                                data-help-id="common-close"
                                type="button"
                            />
                        </div>
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto">
                    <div className="p-10">
                        {error && (
                            <div className="p-4 mb-6 bg-red-50 border border-red-100 text-red-600 text-xs font-bold rounded-xl flex items-center gap-3 animate-in fade-in">
                                <span className="text-lg">⚠️</span>
                                {error}
                            </div>
                        )}

                        <div className="animate-in fade-in slide-in-from-bottom-2 duration-300">
                            <div className="overflow-hidden border border-slate-100 rounded-[2rem] shadow-sm">
                                <table className="min-w-full divide-y divide-slate-100">
                                    <thead className="bg-slate-50/50">
                                        <tr>
                                            <th className="px-8 py-5 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Environment Key</th>
                                            <th className="px-8 py-5 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Modified</th>
                                            <th className="px-8 py-5 text-right text-[10px] font-black text-slate-400 uppercase tracking-widest">Management</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-slate-50">
                                        {secrets.map(secret => (
                                            <tr key={secret.key} className="hover:bg-slate-50/50 group transition-colors">
                                                <td className="px-8 py-5">
                                                    <div className="font-mono font-bold text-slate-700 text-sm tracking-tight break-all">
                                                        {secret.key}
                                                    </div>
                                                </td>
                                                <td className="px-8 py-5 whitespace-nowrap">
                                                    <div className="text-[10px] font-black uppercase tracking-tight text-slate-400">
                                                        {secret.updated_at ? (() => { const dt = formatDateTime(secret.updated_at); return `${dt.date} ${dt.time}`; })() : (secret.created_at ? (() => { const dt = formatDateTime(secret.created_at); return `${dt.date} ${dt.time}`; })() : '-')}
                                                    </div>
                                                </td>
                                                <td className="px-8 py-5 whitespace-nowrap text-right">
                                                    <div className="flex justify-end gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                                        <button
                                                            type="button"
                                                            onClick={() => handleEdit(secret)}
                                                            className="admin-action-btn btn-icon--edit"
                                                            data-help-id="common-edit"
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() => handleDelete(secret.key)}
                                                            className="admin-action-btn btn-icon--delete"
                                                            data-help-id="common-delete"
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                        {secrets.length === 0 && !isLoading && (
                                            <tr>
                                                <td colSpan={3} className="px-8 py-20 text-center">
                                                    <div className="flex flex-col items-center gap-3">
                                                        <span className="text-3xl opacity-20">🕳️</span>
                                                        <p className="text-[10px] font-black uppercase tracking-widest text-slate-300 italic">No encrypted secrets found</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {isLoading && secrets.length === 0 && (
                            <div className="flex flex-col items-center justify-center p-24 text-slate-300 gap-6">
                                <span className="text-6xl animate-pulse">🔐</span>
                                <p className="text-[11px] font-black uppercase tracking-[0.2em] italic">Decrypting vault metadata...</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Internal Edit/Add View Context */}
                {editingSecret && (
                    <div
                        className="fixed inset-0 z-[100] flex items-center justify-center p-8 bg-slate-900/40 backdrop-blur-sm"
                        onClick={() => setEditingSecret(null)}
                    >
                        <div
                            className="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-xl overflow-hidden animate-in zoom-in-95 duration-200"
                            onClick={e => e.stopPropagation()}
                        >
                            <div className="px-10 py-8 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
                                <div className="space-y-1">
                                    <h3 className="font-black text-slate-800 text-lg uppercase tracking-tight">
                                        {editingSecret.key ? `Edit: ${editingSecret.key}` : 'Vault Entry'}
                                    </h3>
                                    <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Configure encrypted environment variable</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setEditingSecret(null)}
                                    className="admin-action-btn btn-icon--close"
                                    data-help-id="common-close"
                                />
                            </div>
                            <form onSubmit={(e) => { e.preventDefault(); handleSave(); }} className="p-10 space-y-8">
                                <div className="space-y-3">
                                    <label className="text-[10px] font-black text-slate-500 uppercase tracking-widest px-1">Vault Key</label>
                                    <input
                                        type="text"
                                        required
                                        disabled={!!secrets.find(s => s.key === editingSecret.key)}
                                        value={editingSecret.key}
                                        onChange={e => setEditingSecret({ ...editingSecret, key: e.target.value.toUpperCase() })}
                                        className="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-4 focus:ring-blue-600/5 shadow-inner font-mono font-bold text-slate-700 transition-all focus:bg-white disabled:opacity-50"
                                        placeholder="API_KEY_NAME"
                                    />
                                </div>
                                <div className="space-y-3">
                                    <label className="text-[10px] font-black text-slate-500 uppercase tracking-widest px-1">Value (Plaintext)</label>
                                    <textarea
                                        required
                                        value={editingSecret.value}
                                        onChange={e => setEditingSecret({ ...editingSecret, value: e.target.value })}
                                        className="w-full h-40 px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-4 focus:ring-blue-600/5 shadow-inner font-mono text-sm text-slate-700 transition-all focus:bg-white resize-none"
                                        placeholder="Paste unencrypted value..."
                                    />
                                    <div className="flex items-center gap-2 px-1">
                                        <span className="text-blue-500 text-sm">🛡️</span>
                                        <p className="text-[9px] font-bold text-slate-400 uppercase tracking-widest">AES-256 encryption applied on save</p>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
