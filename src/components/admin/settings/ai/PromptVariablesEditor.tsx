import React, { useMemo, useState } from 'react';
import type { IAIPromptDropdownOptionsByVariable, IAIPromptVariable } from '../../../../types/ai-prompts.js';
import { AUTOGENERATE_LABEL, ROOM_PROMPT_DROPDOWN_DEFAULTS, getVariableLabel } from './roomPromptDropdownDefaults.js';

interface PromptVariablesEditorProps {
    variables: IAIPromptVariable[];
    optionsByVariable: IAIPromptDropdownOptionsByVariable;
    isLoading: boolean;
    onSave: (variables: IAIPromptVariable[]) => Promise<{ success: boolean; error?: string }>;
}

export const PromptVariablesEditor: React.FC<PromptVariablesEditorProps> = ({
    variables,
    optionsByVariable,
    isLoading,
    onSave
}) => {
    const [draftVariables, setDraftVariables] = useState<IAIPromptVariable[]>(variables);

    const sortedVariables = useMemo(
        () => [...draftVariables].sort((a, b) => String(a.display_name || a.variable_key).localeCompare(String(b.display_name || b.variable_key))),
        [draftVariables]
    );

    React.useEffect(() => {
        setDraftVariables(variables);
    }, [variables]);

    const getOptions = (key: string): string[] => {
        const values = optionsByVariable[key] || ROOM_PROMPT_DROPDOWN_DEFAULTS[key] || [AUTOGENERATE_LABEL];
        return values;
    };

    const updateVariable = (variableKey: string, patch: Partial<IAIPromptVariable>) => {
        setDraftVariables((prev) => prev.map((entry) => (entry.variable_key === variableKey ? { ...entry, ...patch } : entry)));
    };

    const handleSave = async () => {
        const payload = draftVariables.map((entry) => ({
            ...entry,
            sample_value: String(entry.sample_value || '').trim()
        }));
        const res = await onSave(payload);
        if (res.success) {
            window.WFToast?.success?.('Prompt variable defaults saved');
        } else {
            window.WFToast?.error?.(res.error || 'Failed to save prompt variable defaults');
        }
    };

    return (
        <div className="space-y-4 border border-slate-200 rounded-xl p-4 bg-slate-50/60">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <label className="text-[10px] font-black uppercase tracking-widest text-slate-500">Prompt Variable Defaults</label>
                    <p className="text-[11px] text-slate-500 mt-1">Every variable has a default value you can edit and reuse in templates.</p>
                </div>
                <button
                    type="button"
                    onClick={() => void handleSave()}
                    disabled={isLoading}
                    className="btn btn-primary px-4 py-2 text-xs font-black uppercase tracking-widest"
                >
                    Save Variable Defaults
                </button>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-3">
                {sortedVariables.map((variable) => {
                    const options = getOptions(variable.variable_key);
                    const listId = `prompt-variable-${variable.variable_key}`;
                    return (
                        <div key={variable.variable_key} className="space-y-1">
                            <label className="text-[10px] font-black uppercase tracking-widest text-slate-500">
                                {getVariableLabel(variable.variable_key, variable.display_name)}
                            </label>
                            <input
                                list={listId}
                                type="text"
                                value={String(variable.sample_value || '')}
                                onChange={(e) => updateVariable(variable.variable_key, { sample_value: e.target.value })}
                                className="w-full text-xs p-2.5 border border-slate-200 rounded-lg bg-white"
                            />
                            <datalist id={listId}>
                                {options.map((opt) => (
                                    <option key={`${listId}-${opt}`} value={opt} />
                                ))}
                            </datalist>
                            <div className="text-[10px] text-slate-500 font-mono">{`{{${variable.variable_key}}}`}</div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
};
