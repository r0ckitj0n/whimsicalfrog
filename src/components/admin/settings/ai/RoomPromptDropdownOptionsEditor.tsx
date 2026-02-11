import React, { useEffect, useMemo, useState } from 'react';
import type { IAIPromptDropdownOptionsByVariable, IAIPromptVariable } from '../../../../types/ai-prompts.js';
import {
    AUTOGENERATE_LABEL,
    ROOM_PROMPT_DROPDOWN_DEFAULTS,
    getVariableLabel
} from './roomPromptDropdownDefaults.js';

interface RoomPromptDropdownOptionsEditorProps {
    variables: IAIPromptVariable[];
    optionsByVariable: IAIPromptDropdownOptionsByVariable;
    isLoading: boolean;
    onSave: (next: IAIPromptDropdownOptionsByVariable) => Promise<{ success: boolean; error?: string }>;
}

const buildDraftText = (options: string[]): string => options.join('\n');
const EXCLUDED_VARIABLE_KEYS = new Set(['room_number', 'display_order']);
const LEGACY_AUTOGENERATE_LABEL = '(autogenerate)';
const ROOM_PROMPT_VARIABLE_GROUPS: Array<{ title: string; keys: string[] }> = [
    { title: 'Scene Setup', keys: ['scene_type', 'room_theme', 'location_phrase', 'room_name', 'door_label', 'room_description'] },
    { title: 'Subject', keys: ['subject_species', 'subject_headwear', 'frog_action', 'character_statement'] },
    { title: 'Environment', keys: ['display_furniture_style', 'thematic_accent_decorations', 'background_thematic_elements', 'aesthetic_statement'] },
    { title: 'Style & Rendering', keys: ['image_style_declaration', 'vibe_adjectives', 'color_scheme', 'art_style_line', 'surfaces_line', 'lighting_line'] },
    { title: 'Constraints', keys: ['critical_constraint_line', 'no_props_line', 'decorative_elements_line', 'open_display_zones_line', 'text_constraint_line'] }
];

const parseDraftText = (raw: string): string[] => {
    const seen = new Set<string>();
    const out: string[] = [AUTOGENERATE_LABEL];
    const lines = raw.split('\n').map((line) => line.trim()).filter(Boolean);
    for (const line of lines) {
        const key = line.toLowerCase();
        if (key === AUTOGENERATE_LABEL.toLowerCase() || key === LEGACY_AUTOGENERATE_LABEL) continue;
        if (seen.has(key)) continue;
        seen.add(key);
        out.push(line);
    }
    return out;
};

export const RoomPromptDropdownOptionsEditor: React.FC<RoomPromptDropdownOptionsEditorProps> = ({
    variables,
    optionsByVariable,
    isLoading,
    onSave
}) => {
    const [drafts, setDrafts] = useState<Record<string, string>>({});

    const variableOrder = useMemo(
        () => [...variables]
            .filter((variable) => !EXCLUDED_VARIABLE_KEYS.has(variable.variable_key))
            .sort((a, b) => String(a.display_name || a.variable_key).localeCompare(String(b.display_name || b.variable_key))),
        [variables]
    );

    const mergedOptions: IAIPromptDropdownOptionsByVariable = useMemo(() => {
        const map: IAIPromptDropdownOptionsByVariable = {};
        for (const variable of variableOrder) {
            map[variable.variable_key] = optionsByVariable[variable.variable_key] || ROOM_PROMPT_DROPDOWN_DEFAULTS[variable.variable_key] || [AUTOGENERATE_LABEL];
        }
        return map;
    }, [optionsByVariable, variableOrder]);

    const groupedVariables = useMemo(() => {
        const variableByKey = new Map(variableOrder.map((variable) => [variable.variable_key, variable] as const));
        const assigned = new Set<string>();

        const groups = ROOM_PROMPT_VARIABLE_GROUPS.map((group) => {
            const items = group.keys
                .map((key) => variableByKey.get(key))
                .filter((item): item is IAIPromptVariable => Boolean(item));
            items.forEach((item) => assigned.add(item.variable_key));
            return { title: group.title, items };
        }).filter((group) => group.items.length > 0);

        const uncategorized = variableOrder.filter((variable) => !assigned.has(variable.variable_key));
        if (uncategorized.length > 0) {
            groups.push({ title: 'Other', items: uncategorized });
        }

        return groups;
    }, [variableOrder]);

    useEffect(() => {
        const nextDrafts: Record<string, string> = {};
        for (const variable of variableOrder) {
            nextDrafts[variable.variable_key] = buildDraftText(mergedOptions[variable.variable_key] || [AUTOGENERATE_LABEL]);
        }
        setDrafts(nextDrafts);
    }, [mergedOptions, variableOrder]);

    const handleSave = async () => {
        const nextPayload: IAIPromptDropdownOptionsByVariable = {};
        for (const variable of variableOrder) {
            const raw = drafts[variable.variable_key] || AUTOGENERATE_LABEL;
            nextPayload[variable.variable_key] = parseDraftText(raw);
        }
        const res = await onSave(nextPayload);
        if (res.success) {
            window.WFToast?.success?.('Prompt variable options saved');
        } else {
            window.WFToast?.error?.(res.error || 'Failed to save prompt variable options');
        }
    };

    return (
        <div className="space-y-4 border border-slate-200 rounded-xl p-4 bg-slate-50/60">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <label className="text-[10px] font-black uppercase tracking-widest text-slate-500">Prompt Variable Options</label>
                    <p className="text-[11px] text-slate-500 mt-1">Edit one option per line. The first option is always `{AUTOGENERATE_LABEL}`.</p>
                </div>
                <button
                    type="button"
                    onClick={() => void handleSave()}
                    disabled={isLoading}
                    className="btn btn-primary px-4 py-2 text-xs font-black uppercase tracking-widest"
                >
                    Save Options
                </button>
            </div>

            <div className="space-y-4">
                {groupedVariables.map((group) => (
                    <section key={group.title} className="rounded-xl border border-slate-200 p-3 bg-white/70">
                        <h4 className="text-[10px] font-black uppercase tracking-widest text-slate-600 mb-2">{group.title}</h4>
                        <div className="grid grid-cols-1 xl:grid-cols-2 gap-3">
                            {group.items.map((variable) => (
                                <div key={variable.variable_key} className="space-y-1">
                                    <label className="text-[10px] font-black uppercase tracking-widest text-slate-500">
                                        {getVariableLabel(variable.variable_key, variable.display_name)}
                                    </label>
                                    <textarea
                                        value={drafts[variable.variable_key] || AUTOGENERATE_LABEL}
                                        onChange={(e) => setDrafts((prev) => ({ ...prev, [variable.variable_key]: e.target.value }))}
                                        rows={5}
                                        className="w-full text-xs p-2.5 border border-slate-200 rounded-lg bg-white font-medium"
                                    />
                                </div>
                            ))}
                        </div>
                    </section>
                ))}
            </div>
        </div>
    );
};
