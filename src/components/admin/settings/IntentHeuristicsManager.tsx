import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useModalContext } from '../../../context/ModalContext.js';
import { useIntentHeuristics, getDefaultHeuristics, IIntentHeuristics } from '../../../hooks/admin/useIntentHeuristics.js';

import { isDraftDirty } from '../../../core/utils.js';
import { useUnsavedChangesCloseGuard } from '../../../hooks/useUnsavedChangesCloseGuard.js';

import { WeightTab } from './heuristics/WeightTab.js';
import { BudgetTab } from './heuristics/BudgetTab.js';
import { KeywordTab } from './heuristics/KeywordTab.js';

interface IntentHeuristicsManagerProps {
    onClose?: () => void;
    title?: string;
}

export const IntentHeuristicsManager: React.FC<IntentHeuristicsManagerProps> = ({ onClose, title }) => {
    const {
        config,
        isLoading,
        error,
        fetchConfig,
        saveConfig
    } = useIntentHeuristics();

    const [editConfig, setEditBuffer] = useState<IIntentHeuristics | null>(null);
    const [activeTab, setActiveTab] = useState<'weights' | 'budget' | 'keywords'>('weights');

    useEffect(() => {
        if (isLoading) return;

        if (config && config.weights) {
            setEditBuffer(config);
        } else if (!config && !editConfig) {
            setEditBuffer(getDefaultHeuristics());
        }
    }, [config, isLoading]);

    const handleSave = async (): Promise<boolean> => {
        if (!editConfig) return false;
        const success = await saveConfig(editConfig);
        if (success) {
            if (window.WFToast) window.WFToast.success('Intelligence heuristics updated!');
            return true;
        } else {
            if (window.WFToast) window.WFToast.error('Update failed');
            return false;
        }
    };

    const { confirm: themedConfirm } = useModalContext();

    const handleLoadDefaults = async () => {
        const confirmed = await themedConfirm({
            title: 'Load Defaults',
            message: 'Replace current config with defaults? You must click Save to persist.',
            confirmText: 'Load Now',
            iconKey: 'warning'
        });
        if (confirmed) {
            setEditBuffer(getDefaultHeuristics());
        }
    };


    const updateWeight = (key: keyof IIntentHeuristics['weights'], value: string) => {
        if (!editConfig) return;
        setEditBuffer({
            ...editConfig,
            weights: {
                ...editConfig.weights,
                [key]: parseFloat(value) || 0
            }
        });
    };

    const updateBudget = (tier: 'low' | 'mid' | 'high', index: 0 | 1, value: string) => {
        if (!editConfig) return;
        const next = { ...editConfig.budget_ranges };
        next[tier][index] = parseFloat(value) || 0;
        setEditBuffer({ ...editConfig, budget_ranges: next });
    };

    const updateKeywords = (type: 'positive' | 'categories', value: string) => {
        if (!editConfig) return;
        try {
            const val = JSON.parse(value);
            setEditBuffer({
                ...editConfig,
                keywords: {
                    ...editConfig.keywords,
                    [type]: val
                }
            });
        } catch { /* JSON parse failed - ignore invalid input */ }
    };

    const isDirty = isDraftDirty(editConfig, config);
    const attemptClose = useUnsavedChangesCloseGuard({
        isDirty,
        isBlocked: isLoading,
        onClose,
        onSave: handleSave,
        closeAfterSave: true
    });

    const modalContent = (
        <div
            className="admin-modal-overlay over-header show topmost"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) void attemptClose();
            }}
        >
            <div
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1000px] max-w-[95vw] h-[80vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header border-b border-gray-100 px-6 py-4 sticky top-0 bg-white z-20 flex items-start justify-between">
                    <div className="flex flex-col gap-4">
                        <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                            <span className="text-2xl">🧠</span> {title || 'Intent Heuristics'}
                        </h2>

                        <div className="wf-tabs bg-slate-50/50 rounded-xl p-1 border border-slate-100 flex items-center gap-2 self-start">
                            {[
                                { id: 'weights' as const, label: 'Scoring Weights' },
                                { id: 'budget' as const, label: 'Budget Ranges' },
                                { id: 'keywords' as const, label: 'Advanced JSON' }
                            ].map((tab) => (
                                <button
                                    key={tab.id}
                                    onClick={() => setActiveTab(tab.id)}
                                    className={`wf-tab ${activeTab === tab.id ? 'is-active' : ''}`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => fetchConfig()}
                            className="admin-action-btn btn-icon--refresh"
                            data-help-id="common-refresh"
                            type="button"
                        />
                        <button
                            onClick={handleLoadDefaults}
                            className="admin-action-btn btn-icon--reset"
                            data-help-id="common-reset"
                            type="button"
                        />
                        <button
                            onClick={handleSave}
                            disabled={isLoading}
                            className={`admin-action-btn btn-icon--save ${isDirty ? 'is-dirty' : ''}`}
                            data-help-id="common-save"
                            type="button"
                        />
                        <button
                            onClick={() => { void attemptClose(); }}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="common-close"
                        />
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-hidden p-0 flex flex-col">

                    <div className="flex-1 overflow-y-auto p-10">
                        {isLoading && !config ? (
                            <div className="flex flex-col items-center justify-center p-12 text-gray-500 gap-4 uppercase tracking-widest font-black text-[10px]">
                                <span className="text-4xl animate-bounce">🧠</span>
                                <p>Loading brain heuristics...</p>
                            </div>
                        ) : !editConfig ? null : (
                            <div className="animate-in fade-in slide-in-from-bottom-2 duration-300">
                                {error && (
                                    <div className="mb-6 p-4 bg-red-50 border border-red-100 text-red-600 text-xs font-bold rounded-xl flex items-center gap-3">
                                        <span className="text-lg">⚠️</span>
                                        {error}
                                    </div>
                                )}

                                {activeTab === 'weights' && (
                                    <WeightTab editConfig={editConfig} onUpdateWeight={updateWeight} />
                                )}

                                {activeTab === 'budget' && (
                                    <BudgetTab editConfig={editConfig} onUpdateBudget={updateBudget} />
                                )}

                                {activeTab === 'keywords' && (
                                    <KeywordTab editConfig={editConfig} onUpdateKeywords={updateKeywords} />
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
