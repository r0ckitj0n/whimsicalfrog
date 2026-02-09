import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useAIPromptTemplates } from '../../../../hooks/admin/useAIPromptTemplates.js';
import type { IAIPromptTemplate } from '../../../../types/ai-prompts.js';
import { RoomPromptDropdownOptionsEditor } from './RoomPromptDropdownOptionsEditor.js';

const slugifyKey = (value: string) =>
    value
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 100);

export const SystemPromptsTab: React.FC = () => {
    const {
        templates,
        variables,
        dropdownOptionsByVariable,
        isLoading,
        error,
        fetchTemplates,
        fetchVariables,
        fetchDropdownOptions,
        saveTemplate,
        deleteTemplate,
        saveDropdownOptions
    } = useAIPromptTemplates();

    const [selectedTemplateId, setSelectedTemplateId] = useState<number | null>(null);
    const [draft, setDraft] = useState<Partial<IAIPromptTemplate> | null>(null);
    const [isPickerOpen, setIsPickerOpen] = useState(false);
    const textareaRef = useRef<HTMLTextAreaElement | null>(null);

    useEffect(() => {
        void fetchTemplates();
        void fetchVariables();
        void fetchDropdownOptions();
    }, [fetchTemplates, fetchVariables, fetchDropdownOptions]);

    useEffect(() => {
        if (templates.length === 0) {
            setSelectedTemplateId(null);
            setDraft(null);
            return;
        }

        const existingSelected = selectedTemplateId && templates.some(t => t.id === selectedTemplateId);
        const nextTemplate = existingSelected
            ? templates.find(t => t.id === selectedTemplateId)
            : templates[0];

        if (nextTemplate) {
            setSelectedTemplateId(nextTemplate.id);
            setDraft({ ...nextTemplate });
        }
    }, [templates, selectedTemplateId]);

    const selectedTemplate = useMemo(
        () => templates.find(t => t.id === selectedTemplateId) || null,
        [templates, selectedTemplateId]
    );

    const isDirty = useMemo(() => {
        if (!selectedTemplate || !draft) return false;
        return (
            (draft.template_name || '') !== selectedTemplate.template_name ||
            (draft.context_type || '') !== selectedTemplate.context_type ||
            (draft.prompt_text || '') !== selectedTemplate.prompt_text
        );
    }, [selectedTemplate, draft]);

    const handleNewTemplate = () => {
        const now = Date.now();
        const name = `New Template ${now}`;
        setSelectedTemplateId(null);
        setDraft({
            id: 0,
            template_key: `template_${now}`,
            template_name: name,
            description: '',
            context_type: 'room_generation',
            prompt_text: ''
        });
    };

    const handleSave = async () => {
        if (!draft?.template_name || !draft?.prompt_text) {
            window.WFToast?.error?.('Template name and prompt text are required');
            return;
        }

        const payload: Partial<IAIPromptTemplate> = {
            id: draft.id && draft.id > 0 ? draft.id : undefined,
            template_key: slugifyKey(draft.template_name || ''),
            template_name: draft.template_name,
            description: '',
            context_type: draft.context_type || 'room_generation',
            prompt_text: draft.prompt_text,
            is_active: 1
        };

        const res = await saveTemplate(payload);
        if (res.success) {
            window.WFToast?.success?.('Prompt template saved');
        } else {
            window.WFToast?.error?.(res.error || 'Failed to save template');
        }
    };

    const handleDelete = async () => {
        if (!draft?.id || draft.id <= 0) return;
        const res = await deleteTemplate(draft.id);
        if (res.success) {
            window.WFToast?.success?.('Template deleted');
        } else {
            window.WFToast?.error?.(res.error || 'Failed to delete template');
        }
    };

    const handleVariableInsert = (variableKey: string) => {
        const token = `{{${variableKey}}}`;
        setDraft(prev => {
            if (!prev) return prev;
            const current = prev.prompt_text || '';
            const textarea = textareaRef.current;
            if (!textarea) {
                return { ...prev, prompt_text: `${current}${token}` };
            }

            const start = textarea.selectionStart ?? current.length;
            const end = textarea.selectionEnd ?? current.length;
            const nextText = `${current.slice(0, start)}${token}${current.slice(end)}`;

            setTimeout(() => {
                textarea.focus();
                const caret = start + token.length;
                textarea.setSelectionRange(caret, caret);
            }, 0);

            return { ...prev, prompt_text: nextText };
        });
        setIsPickerOpen(false);
    };

    return (
        <div className="space-y-6">
            {error && (
                <div className="p-4 rounded-xl border border-red-100 bg-red-50 text-xs font-bold text-red-600">
                    {error}
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_auto] gap-4 items-end">
                <div className="space-y-2">
                    <label className="text-[10px] font-black uppercase tracking-widest text-slate-500">Prompt Template</label>
                    <select
                        value={selectedTemplateId ?? ''}
                        onChange={(e) => {
                            const id = Number(e.target.value || 0);
                            setSelectedTemplateId(id || null);
                            const match = templates.find(t => t.id === id);
                            if (match) setDraft({ ...match });
                        }}
                        className="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-white"
                    >
                        {templates.map(t => (
                            <option key={t.id} value={t.id}>{t.template_name}</option>
                        ))}
                    </select>
                </div>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={handleNewTemplate}
                        className="admin-action-btn btn-icon--add"
                        data-help-id="common-add"
                    />
                    <button
                        type="button"
                        onClick={handleSave}
                        disabled={isLoading || !draft || (!isDirty && !!draft.id)}
                        className={`admin-action-btn btn-icon--save dirty-only ${(isDirty || !draft?.id) ? 'is-dirty' : ''}`}
                        data-help-id="common-save"
                    />
                    {!!draft?.id && (
                        <button
                            type="button"
                            onClick={handleDelete}
                            className="admin-action-btn btn-icon--delete"
                            data-help-id="common-delete"
                        />
                    )}
                </div>
            </div>

            {draft && (
                <div className="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
                    <div className="xl:col-span-7 space-y-4">
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase tracking-widest text-slate-500">Template Name</label>
                            <input
                                type="text"
                                value={draft.template_name || ''}
                                onChange={(e) => setDraft(prev => prev ? { ...prev, template_name: e.target.value } : prev)}
                                className="w-full text-xs font-bold p-3 border border-slate-200 rounded-xl bg-white"
                            />
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <label className="text-[10px] font-black uppercase tracking-widest text-slate-500">Prompt Template</label>
                                <div className="relative">
                                    <button
                                        type="button"
                                        onClick={() => setIsPickerOpen(prev => !prev)}
                                        className="admin-action-btn btn-icon--add"
                                        data-help-id="common-add"
                                    />
                                    {isPickerOpen && (
                                        <div className="absolute right-0 mt-2 w-96 max-w-[90vw] rounded-xl border border-slate-200 bg-white shadow-xl p-2 z-20">
                                            <div className="text-[10px] font-black text-slate-500 uppercase tracking-widest px-2 py-1">Insert Variable</div>
                                            <div className="max-h-64 overflow-auto space-y-1">
                                                {variables.map(v => (
                                                    <button
                                                        key={v.id}
                                                        type="button"
                                                        onClick={() => handleVariableInsert(v.variable_key)}
                                                        className="group relative w-full text-left px-3 py-2 rounded-lg hover:bg-slate-50"
                                                    >
                                                        <div className="text-xs font-black text-slate-700">{v.display_name}</div>
                                                        <div className="text-[10px] font-mono text-slate-500">{`{{${v.variable_key}}}`}</div>
                                                        <div className="absolute left-3 top-full mt-1 hidden group-hover:block w-72 rounded-md bg-slate-900 text-white text-[10px] p-2 shadow-lg">
                                                            {v.description || 'No description'}
                                                        </div>
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                            <textarea
                                ref={textareaRef}
                                value={draft.prompt_text || ''}
                                onChange={(e) => setDraft(prev => prev ? { ...prev, prompt_text: e.target.value } : prev)}
                                rows={16}
                                className="w-full text-xs font-mono p-4 border border-slate-200 rounded-xl bg-white resize-y"
                            />
                        </div>
                    </div>

                    <div className="xl:col-span-5 space-y-4">
                        <RoomPromptDropdownOptionsEditor
                            variables={variables}
                            optionsByVariable={dropdownOptionsByVariable}
                            isLoading={isLoading}
                            onSave={saveDropdownOptions}
                        />
                    </div>
                </div>
            )}
        </div>
    );
};
