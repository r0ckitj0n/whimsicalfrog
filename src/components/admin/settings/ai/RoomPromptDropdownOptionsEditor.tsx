import React, { useEffect, useMemo, useState } from 'react';
import type { IAIPromptDropdownOptionsByVariable } from '../../../../types/ai-prompts.js';
import {
    AUTOGENERATE_LABEL,
    ROOM_PROMPT_DROPDOWN_DEFAULTS,
    ROOM_PROMPT_DROPDOWN_DEFINITIONS
} from './roomPromptDropdownDefaults.js';

interface RoomPromptDropdownOptionsEditorProps {
    optionsByVariable: IAIPromptDropdownOptionsByVariable;
    isLoading: boolean;
    onSave: (next: IAIPromptDropdownOptionsByVariable) => Promise<{ success: boolean; error?: string }>;
}

const buildDraftText = (options: string[]): string => options.join('\n');

const parseDraftText = (raw: string): string[] => {
    const seen = new Set<string>();
    const out: string[] = [AUTOGENERATE_LABEL];
    const lines = raw.split('\n').map((line) => line.trim()).filter(Boolean);
    for (const line of lines) {
        const key = line.toLowerCase();
        if (key === AUTOGENERATE_LABEL.toLowerCase()) continue;
        if (seen.has(key)) continue;
        seen.add(key);
        out.push(line);
    }
    return out;
};

export const RoomPromptDropdownOptionsEditor: React.FC<RoomPromptDropdownOptionsEditorProps> = ({
    optionsByVariable,
    isLoading,
    onSave
}) => {
    const [drafts, setDrafts] = useState<Record<string, string>>({});

    const mergedOptions = useMemo(() => {
        const map: IAIPromptDropdownOptionsByVariable = {};
        for (const def of ROOM_PROMPT_DROPDOWN_DEFINITIONS) {
            map[def.variable_key] = optionsByVariable[def.variable_key] || ROOM_PROMPT_DROPDOWN_DEFAULTS[def.variable_key] || [AUTOGENERATE_LABEL];
        }
        return map;
    }, [optionsByVariable]);

    useEffect(() => {
        const nextDrafts: Record<string, string> = {};
        for (const def of ROOM_PROMPT_DROPDOWN_DEFINITIONS) {
            nextDrafts[def.variable_key] = buildDraftText(mergedOptions[def.variable_key] || [AUTOGENERATE_LABEL]);
        }
        setDrafts(nextDrafts);
    }, [mergedOptions]);

    const handleSave = async () => {
        const nextPayload: IAIPromptDropdownOptionsByVariable = {};
        for (const def of ROOM_PROMPT_DROPDOWN_DEFINITIONS) {
            const raw = drafts[def.variable_key] || AUTOGENERATE_LABEL;
            nextPayload[def.variable_key] = parseDraftText(raw);
        }
        const res = await onSave(nextPayload);
        if (res.success) {
            window.WFToast?.success?.('Room dropdown presets saved');
        } else {
            window.WFToast?.error?.(res.error || 'Failed to save room dropdown presets');
        }
    };

    return (
        <div className="space-y-4 border border-slate-200 rounded-xl p-4 bg-slate-50/60">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <label className="text-[10px] font-black uppercase tracking-widest text-slate-500">Room Dropdown Presets</label>
                    <p className="text-[11px] text-slate-500 mt-1">Edit one option per line. The first option is always `{AUTOGENERATE_LABEL}`.</p>
                </div>
                <button
                    type="button"
                    onClick={() => void handleSave()}
                    disabled={isLoading}
                    className="btn btn-primary px-4 py-2 text-xs font-black uppercase tracking-widest"
                >
                    Save Presets
                </button>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-3">
                {ROOM_PROMPT_DROPDOWN_DEFINITIONS.map((def) => (
                    <div key={def.variable_key} className="space-y-1">
                        <label className="text-[10px] font-black uppercase tracking-widest text-slate-500">{def.label}</label>
                        <textarea
                            value={drafts[def.variable_key] || AUTOGENERATE_LABEL}
                            onChange={(e) => setDrafts((prev) => ({ ...prev, [def.variable_key]: e.target.value }))}
                            rows={5}
                            className="w-full text-xs p-2.5 border border-slate-200 rounded-lg bg-white font-medium"
                        />
                    </div>
                ))}
            </div>
        </div>
    );
};
