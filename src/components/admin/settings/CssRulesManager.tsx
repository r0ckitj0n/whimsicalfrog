import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useCssRules, ICssRule } from '../../../hooks/admin/useCssRules.js';
import { useModalContext } from '../../../context/ModalContext.js';
import { RuleForm } from './css/RuleForm.js';
import { RuleList } from './css/RuleList.js';

interface CssRulesManagerProps {
    onClose?: () => void;
    title?: string;
}

export const CssRulesManager: React.FC<CssRulesManagerProps> = ({ onClose, title }) => {
    const {
        rules,
        isLoading,
        error,
        addRule,
        deleteRule,
        refresh
    } = useCssRules();

    const { confirm: confirmModal } = useModalContext();

    const [newRule, setNewRule] = useState<ICssRule>({
        selector: '',
        property: '',
        value: '',
        important: false,
        note: ''
    });

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        const { name, value, type } = e.target as HTMLInputElement;
        const val = type === 'checkbox' ? (e.target as HTMLInputElement).checked : value;
        setNewRule(prev => ({ ...prev, [name]: val }));
    };

    const handleAdd = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!newRule.selector.trim() || !newRule.property.trim()) return;

        const res = await addRule(newRule);
        if (res.success) {
            setNewRule({
                selector: '',
                property: '',
                value: '',
                important: false,
                note: ''
            });
            if (window.WFToast) window.WFToast.success('Rule added successfully');
        } else {
            if (window.WFToast) window.WFToast.error(res.error || 'Failed to add rule');
        }
    };

    const handleDelete = async (id: number) => {
        const confirmed = await confirmModal({
            title: 'Delete CSS Rule',
            message: 'Delete this CSS rule? This may affect site appearance immediately.',
            confirmText: 'Delete',
            confirmStyle: 'danger',
            icon: '⚠️',
            iconType: 'danger'
        });

        if (confirmed) {
            const res = await deleteRule(id);
            if (res.success) {
                if (window.WFToast) window.WFToast.success('Rule deleted');
            }
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
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1200px] max-w-[95vw] h-auto max-h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">🎨</span> {title || 'CSS Override Rules'}
                    </h2>

                    <div className="flex-1" />

                    <div className="flex items-center gap-4">
                        <button
                            onClick={refresh}
                            disabled={isLoading}
                            className="admin-action-btn btn-icon--refresh"
                            data-help-id="common-refresh"
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

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto p-8">
                    {error && (
                        <div className="mx-2 p-4 mb-6 bg-[var(--brand-error-bg)] border border-[var(--brand-error-border)] text-[var(--brand-error)] text-sm rounded-2xl flex items-center gap-3 animate-in slide-in-from-top-2">
                            <span className="text-xl">⚠️</span>
                            {error}
                        </div>
                    )}

                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 mx-2">
                        <div className="lg:col-span-4">
                            <RuleForm
                                newRule={newRule}
                                onInputChange={handleInputChange}
                                onSubmit={handleAdd}
                                isLoading={isLoading}
                            />
                        </div>

                        <div className="lg:col-span-8 space-y-4">
                            <RuleList
                                rules={rules}
                                onDelete={handleDelete}
                                isLoading={isLoading}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};

